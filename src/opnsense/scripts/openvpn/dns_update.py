#!/usr/local/bin/python3

"""
    Copyright (c) 2026 Angus McGyver
    All rights reserved.

    Redistribution and use in source and binary forms, with or without
    modification, are permitted provided that the following conditions are met:

    1. Redistributions of source code must retain the above copyright notice,
     this list of conditions and the following disclaimer.

    2. Redistributions in binary form must reproduce the above copyright
     notice, this list of conditions and the following disclaimer in the
     documentation and/or other materials provided with the distribution.

    THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
    INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
    AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
    AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
    OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
    SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
    INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
    CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
    ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
    POSSIBILITY OF SUCH DAMAGE.
"""

import sys
import os
import argparse
import socket
import struct
import hmac
import hashlib
import time
import base64
import logging
import syslog
import traceback
import binascii
from ipaddress import ip_address, IPv4Address, IPv6Address


# DNS constants
DNS_TYPE_SOA = 6
DNS_TYPE_A = 1
DNS_TYPE_AAAA = 28
DNS_TYPE_PTR = 12
DNS_TYPE_TSIG = 250

DNS_CLASS_IN = 1
DNS_CLASS_ANY = 255
DNS_CLASS_NONE = 254

DNS_OPCODE_UPDATE = 5


class TSIGSigner:
    """TSIG signature generator (RFC 2845)"""

    ALGORITHMS = {
        'hmac-md5': ('hmac-md5.sig-alg.reg.int', hashlib.md5),
        'hmac-sha1': ('hmac-sha1', hashlib.sha1),
        'hmac-sha224': ('hmac-sha224', hashlib.sha224),
        'hmac-sha256': ('hmac-sha256', hashlib.sha256),
        'hmac-sha384': ('hmac-sha384', hashlib.sha384),
        'hmac-sha512': ('hmac-sha512', hashlib.sha512),
    }

    def __init__(self, key_name, key_secret, algorithm, logger, fudge=300):
        self.key_name = key_name.lower()

        try:
            self.key_secret = base64.b64decode(key_secret)
        except binascii.Error:
            raise ValueError(f"Invalid TSIG key-secret (not base64?)")
        
        if algorithm not in self.ALGORITHMS:
            raise ValueError(f"Unsupported algorithm: {algorithm}")
        
        self.algorithm_name, self.hash_func = self.ALGORITHMS[algorithm]
        self.logger = logger
        self.fudge = fudge

    @staticmethod
    def encode_name(name):
        """Encode DNS name to wire format"""
        if not name or name == '.':
            return b'\x00'
        
        parts = name.rstrip('.').split('.')
        result = b''
        for part in parts:
            if len(part) > 63:
                raise ValueError(f"Label too long: {part}")
            result += bytes([len(part)]) + part.encode('ascii')
        return result + b'\x00'

    def _build_tsig_variables(self, time_signed):
        """Build TSIG variables for signing"""
        time_high = (time_signed >> 32) & 0xFFFF
        time_low = time_signed & 0xFFFFFFFF
        algorithm_wire = self.encode_name(self.algorithm_name)
        
        variables = b''
        variables += self.encode_name(self.key_name)
        variables += struct.pack('!HI', DNS_CLASS_ANY, 0)  # Class ANY, TTL 0
        variables += algorithm_wire
        variables += struct.pack('!HI', time_high, time_low)  # 48-bit time
        variables += struct.pack('!HHH', self.fudge, 0, 0)  # Fudge, Error, Other Len
        
        return variables, time_high, time_low, algorithm_wire

    def sign(self, message, transaction_id):
        """Generate TSIG signature for a DNS message"""
        time_signed = int(time.time())
        
        # Build TSIG variables
        tsig_vars, time_high, time_low, algo_wire = self._build_tsig_variables(time_signed)
        
        # Sign: message + TSIG variables
        data_to_sign = message + tsig_vars
        mac = hmac.new(self.key_secret, data_to_sign, self.hash_func).digest()
        
        self.logger.debug(f"TSIG: key={self.key_name}, algo={self.algorithm_name}, "
                         f"time={time_signed}, mac_len={len(mac)}")
        
        # Build TSIG RDATA
        rdata = algo_wire
        rdata += struct.pack('!HI', time_high, time_low)
        rdata += struct.pack('!H', self.fudge)
        rdata += struct.pack('!H', len(mac)) + mac
        rdata += struct.pack('!HHH', transaction_id, 0, 0)  # Original ID, Error, Other Len
        
        # Build TSIG RR
        tsig_rr = self.encode_name(self.key_name)
        tsig_rr += struct.pack('!HHIH', DNS_TYPE_TSIG, DNS_CLASS_ANY, 0, len(rdata))
        tsig_rr += rdata
        
        return tsig_rr


class DNSUpdateClient:
    """DNS Dynamic Update client with TSIG authentication"""

    TYPE_MAP = {
        'A': DNS_TYPE_A,
        'AAAA': DNS_TYPE_AAAA,
        'PTR': DNS_TYPE_PTR,
    }

    def __init__(self, server, port, key_name, key_secret, algorithm, logger):
        self.server = server
        self.port = port
        self.logger = logger
        self.tsig_signer = TSIGSigner(key_name, key_secret, algorithm, self.logger)

    @staticmethod
    def encode_name(name):
        """Encode DNS name to wire format"""
        return TSIGSigner.encode_name(name)

    def _create_header(self, txid, qdcount=1, ancount=0, nscount=0, arcount=0):
        """Create DNS UPDATE header"""
        flags = DNS_OPCODE_UPDATE << 11
        return struct.pack('!HHHHHH', txid, flags, qdcount, ancount, nscount, arcount)

    def _create_zone_section(self, zone):
        """Create zone section (ZNAME, ZTYPE=SOA, ZCLASS=IN)"""
        return self.encode_name(zone) + struct.pack('!HH', DNS_TYPE_SOA, DNS_CLASS_IN)

    def _create_update_rr(self, name, record_type, ttl, rdata, is_delete=False):
        """
        Create DNS UPDATE RR
        
        RFC 2136:
        - Delete RRset: class=ANY, TTL=0, RDLENGTH=0
        - Delete specific RR: class=NONE, TTL=0, RDATA=<data>
        - Add RR: class=IN, TTL=<value>, RDATA=<data>
        """
        rtype = self.TYPE_MAP.get(record_type.upper(), DNS_TYPE_A)
        rr = self.encode_name(name)

        if is_delete:
            if rdata:
                # Delete specific RR
                rr += struct.pack('!HHI', rtype, DNS_CLASS_NONE, 0)
                rr += struct.pack('!H', len(rdata)) + rdata
            else:
                # Delete all RRs of this type
                rr += struct.pack('!HHIH', rtype, DNS_CLASS_ANY, 0, 0)
        else:
            # Add RR
            rr += struct.pack('!HHI', rtype, DNS_CLASS_IN, ttl)
            rr += struct.pack('!H', len(rdata)) + rdata
        
        return rr

    def _encode_rdata(self, record_type, data):
        """Encode RDATA based on record type"""
        if record_type in ('A', 'AAAA') and isinstance(data, (IPv4Address, IPv6Address)):
            return data.packed
        elif record_type == 'PTR' and isinstance(data, str):
            return self.encode_name(data)
        else:
            raise ValueError(f"Unsupported RDATA for type {record_type}: {data!r}")

    def _build_update_message(self, zone, updates):
        """Build complete DNS UPDATE message"""
        zone_section = self._create_zone_section(zone)
        update_section = b''
        
        for operation, name, record_type, ttl, rdata in updates:
            self.logger.info(f"{operation.upper()}: {name} {record_type} {ttl} "
                           f"{rdata if rdata else '(all)'}")
            
            is_delete = (operation == 'delete')
            encoded_rdata = self._encode_rdata(record_type, rdata) if rdata else b''
            
            update_section += self._create_update_rr(name, record_type, ttl, 
                                                     encoded_rdata, is_delete)
        
        return zone_section, update_section, len(updates)

    def send_update(self, zone, updates):
        """Send DNS UPDATE with TSIG"""
        txid = int(time.time()) & 0xFFFF
        self.logger.info(f"DNS UPDATE: zone={zone}, txid={txid}")

        # Build message sections
        zone_section, update_section, update_count = self._build_update_message(zone, updates)

        # Create message without TSIG
        header = self._create_header(txid, nscount=update_count, arcount=0)
        msg_without_tsig = header + zone_section + update_section

        # Add TSIG
        tsig_rr = self.tsig_signer.sign(msg_without_tsig, txid)
        
        # Final message with TSIG in additional section
        header_with_tsig = self._create_header(txid, nscount=update_count, arcount=1)
        full_message = header_with_tsig + zone_section + update_section + tsig_rr

        self.logger.debug(f"Message size: {len(full_message)} bytes")

        # Send UDP request
        return self._send_udp(full_message)

    def _send_udp(self, message):
        """Send DNS message via UDP and parse response"""
        try:
            # family=0 means: accept IPv4 or IPv6, whichever is available
            infos = socket.getaddrinfo(self.server, self.port, 0, socket.SOCK_DGRAM)
            if not infos:
                self.logger.error(f"Could not resolve server {self.server}")
                return False

            # take first entry
            af, socktype, proto, canonname, sockaddr = infos[0]

            with socket.socket(af, socktype, proto) as sock:
                sock.settimeout(10)

                self.logger.debug(f"Sending to {self.server} {self.port} ({sockaddr})")
                sock.sendto(message, sockaddr)

                response, _ = sock.recvfrom(4096)
                return self._parse_response(response)

        except socket.timeout:
            self.logger.error("DNS server timeout")
            return False
        except Exception as e:
            self.logger.error(f"Update failed: {e}")
            return False

    def _parse_response(self, response):
        """Parse DNS response and check status"""
        if len(response) < 12:
            self.logger.error("Response too short")
            return False
        
        header = struct.unpack('!HHHHHH', response[:12])
        rcode = header[1] & 0xF
        
        rcode_names = {
            # RFC 1035 base response codes
            0:  'NOERROR',  1:  'FORMERR',  2:  'SERVFAIL',
            3:  'NXDOMAIN', 4:  'NOTIMP',   5:  'REFUSED',
            # RFC 2136 dynamic update codes
            6:  'YXDOMAIN', 7:  'YXRRSET',  8:  'NXRRSET',
            9:  'NOTAUTH',  10: 'NOTZONE',
            # RFC 2845 TSIG/TKEY codes
            16: 'BADSIG',   17: 'BADKEY',   18: 'BADTIME',
        }
        
        rcode_name = rcode_names.get(rcode, f'UNKNOWN CODE')
        if rcode == 0:
            self.logger.info(f"Server response: {rcode_name} ({rcode})")
        else:
            self.logger.error(f"Update failed: {rcode_name} ({rcode})")
            return False
        
        return True


def get_reverse_zone(args, ip_obj):
    """Determine reverse DNS zone from IP address"""
    if isinstance(ip_obj, IPv6Address):
        if args.reverse_zone6:
            rev_zone = args.reverse_zone6
        else:
            # For IPv6, use /64 -> remove first 64 bits -> 16 hex chars + 16 '.' -> 32 chars
            rev_zone = ip_obj.reverse_pointer[32:]
    else:
        if args.reverse_zone4:
            rev_zone = args.reverse_zone4
        else:
            # For IPv4, use /24
            octets = str(ip_obj).split('.')
            rev_zone = f"{octets[2]}.{octets[1]}.{octets[0]}.in-addr.arpa"

    return rev_zone


def update_dns_records(args, ip_obj, logger):
    """Perform DNS update (add/delete operation)"""
    fwd_name = f"{args.hostname}.{args.zone}"
    fwd_type = 'AAAA' if isinstance(ip_obj, IPv6Address) else 'A'
    rev_name = ip_obj.reverse_pointer
    rev_zone = get_reverse_zone(args, ip_obj)

    fwd_updates = []
    rev_updates = []

    # always delete old entries first
    fwd_updates.append(('delete', fwd_name, fwd_type, 0, ip_obj))
    rev_updates.append(('delete', rev_name, 'PTR', 0, None))

    if args.operation == 'add':
        logger.info(f"Register client: {fwd_name} -> {ip_obj}")
        fwd_updates.append(('add', fwd_name, fwd_type, args.ttl, ip_obj))
        rev_updates.append(('add', rev_name, 'PTR', args.ttl, fwd_name))
    else:
        logger.info(f"Unregister Client: {fwd_name} -> {ip_obj}")

    updater = DNSUpdateClient(args.server, args.port, args.key_name, args.key, 
                                  args.algorithm, logger)
    fwd_ok = updater.send_update(args.zone, fwd_updates)
    rev_ok = updater.send_update(rev_zone, rev_updates)

    return fwd_ok and rev_ok


def define_logger(args):
    """Configure logging"""
    if args.syslog:
        # configure syslog for this process too
        syslog.openlog('openvpn_dnsupdate', facility=syslog.LOG_AUTH)
        # return a callable that mimics logger interface
        class SyslogLogger:
            def __init__(self, level):
                self.level = level
            def error(self, msg):
                if self.level >= 0:
                    syslog.syslog(syslog.LOG_ERR, msg)
            def info(self, msg):
                if self.level >= 1:
                    syslog.syslog(syslog.LOG_INFO, msg)
            def warning(self, msg):
                if self.level >= 1:
                    syslog.syslog(syslog.LOG_WARNING, msg)
            def debug(self, msg):
                if self.level >= 6:
                    syslog.syslog(syslog.LOG_DEBUG, msg)

        return SyslogLogger(args.loglevel)
    else:
        if args.loglevel >= 6:
            level = logging.DEBUG
        elif args.loglevel >= 1:
            level = logging.INFO
        else:
            level = logging.ERROR

        logging.basicConfig(
            level=level,
            format='%(asctime)s - %(levelname)s - %(message)s',
            datefmt='%Y-%m-%d %H:%M:%S',
        )
        return logging.getLogger(__name__)


def define_args():
    """Get args from config and command line"""
    # Try to read values from OpenVPN instance config file first
    instance_config = {}

    missing = []
    if configfile := os.environ.get('config', None):
        try:
            with open(configfile) as f:
                for raw_line in f:
                    line = raw_line.strip()
                    if line.startswith('#dns-update-'):
                        try:
                            key, value = line[12:].split(maxsplit=1)
                            instance_config[key] = value
                        except Exception as e:
                            missing.append(f"missing value in config file for {line}")
        except OSError as e:
            missing.append(f"could not open config file {configfile}: {e}")

    # Parse arguments
    parser = argparse.ArgumentParser(
        description='Dynamic DNS Update for OpenVPN',
        formatter_class=argparse.RawDescriptionHelpFormatter,
    )

    parser.add_argument('operation',
                        choices = ['add', 'delete'],
                        help = 'Operation to perform')
    parser.add_argument('--server',
                        default = instance_config.get('server', None),
                        help = 'DNS server hostname or ip address')
    parser.add_argument('--port',
                        type = int,
                        default = int(instance_config.get('port', 53)),
                        help = 'DNS server port')
    parser.add_argument('--key-name',
                        default = instance_config.get('key-name', None),
                        help = 'TSIG key name')
    parser.add_argument('--key',
                        default = instance_config.get('key-secret', None),
                        help = 'TSIG key secret (base64)')
    parser.add_argument('--algorithm',
                        choices = ['hmac-md5', 'hmac-sha1', 'hmac-sha224',
                                'hmac-sha256', 'hmac-sha384', 'hmac-sha512'],
                        default = instance_config.get('key-algorithm', 'hmac-sha256'),
                        help = 'TSIG algorithm (default: hmac-sha256)')
    parser.add_argument('--zone',
                        default = instance_config.get('zone-forward', None),
                        help = 'Forward DNS zone')
    parser.add_argument('--reverse-zone4',
                        default = instance_config.get('zone-reverse-4', None),
                        help = 'IPv4 reverse DNS zone (auto-detected to /24 if not specified)')
    parser.add_argument('--reverse-zone6',
                        default = instance_config.get('zone-reverse-6', None),
                        help = 'IPv6 reverse DNS zone (auto-detected to /64 if not specified)')
    parser.add_argument('--field',
                        default = instance_config.get('hostname-field', 'CN'),
                        help = 'certificate field for hostname')
    parser.add_argument('--hostname',
                        help = 'Overwrite hostname from certificate (required for add/update)')
    parser.add_argument('--prefix',
                        default = instance_config.get('hostname-prefix', ''),
                        help = 'Adds this prefix to hostname')
    parser.add_argument('--suffix',
                        default = instance_config.get('hostname-suffix', ''),
                        help = 'Adds this suffix to hostname')
    parser.add_argument('--ttl',
                        type = int,
                        default = int(instance_config.get('ttl', 300)),
                        help = 'TTL for DNS records (default: 300)')
    parser.add_argument('--address4',
                        default = os.environ.get('ifconfig_pool_remote_ip', None),
                        help = 'IP address (IPv4)')
    parser.add_argument('--address6',
                        default = os.environ.get('ifconfig_pool_remote_ip6', None),
                        help = 'IP address (IPv6)')
    parser.add_argument('--loglevel',
                        type = int,
                        choices = [0,1,2,3,4,5,6,7,8,9,10,11],
                        default = int(os.environ.get('verb', 0)),
                        help = 'Set log level: >=0:ERROR >=1:INFO >=6:DEBUG')
    parser.add_argument('--syslog',
                        action = 'store_true',
                        help = 'Use syslog instead of stdout')

    args = parser.parse_args()

    # Build hostname
    if args.hostname:
        args.hostname = f"{args.prefix}{args.hostname}{args.suffix}"
    elif hostname := os.environ.get(f"X509_0_{args.field}", None):
        args.hostname = f"{args.prefix}{hostname}{args.suffix}"

    # Validate arguments
    if not args.hostname:
        parser.error(f"one of '--hostname' or envvar 'X509_0_{args.field}' is required")
    elif not args.server:
        parser.error("one of '--server' or configfile option '#dns-update-server' is required")
    elif not args.key_name:
        parser.error("one of '--key-name' or configfile option '#dns-update-key-name' is required")
    elif not args.key:
        parser.error("one of '--key' or configfile option '#dns-update-key-secret' is required")
    elif not args.zone:
        parser.error("one of '--zone' or configfile option '#dns-update-zone-forward' is required")
    elif not args.address4 and not args.address6:
        parser.error("one of '--address4', '--address6', env var 'ifconfig_pool_remote_ip' "
                     "or env var 'ifconfig_pool_remote_ip6' is required"
        )

    return args, missing


def main():
    """Main function"""
    args, missing = define_args()

    # Setup logging
    logger = define_logger(args)

    logger.debug(str(os.environ))
    logger.debug(str(args).replace(args.key,'***'))

    for miss in missing:
        logger.warning(miss)

    # Parse and validate IP address
    ip_objects = []

    if args.address4:
        try:
            ip4 = ip_address(args.address4)
            ip_objects.append(ip4)
        except ValueError as e:
            logger.error(f"Invalid IP4 address: {e}")
            return 1

    if args.address6:
        try:
            ip6 = ip_address(args.address6)
            ip_objects.append(ip6)
        except ValueError as e:
            logger.error(f"Invalid IP6 address: {e}")
            return 1

    # Perform operation
    returncode = 0
    for ip_obj in ip_objects:
        try:
            if not update_dns_records(args, ip_obj, logger):
                returncode = 1
        except Exception as e:
            logger.error(f"Fatal error: {e}")
            return 1

    return returncode


if __name__ == '__main__':
    sys.exit(main())