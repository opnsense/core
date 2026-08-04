#!/usr/local/bin/python3

"""
    Copyright (c) 2026 SilentandUnknown <SilentansUnknown@proton.me>
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

"""Send a one-shot DHCPRELEASE (RFC 2131 4.4.4) for an interface's current lease.

/sbin/dhclient in FreeBSD base has no -r; this reproduces just the RELEASE.
Fire-and-forget: no reply is defined for RELEASE, so we never block on the server.
"""
import errno
import os
import random
import re
import socket
import struct
import subprocess
import sys

LEASE_DB = '/var/db/dhclient.leases.%s'
DHCLIENT_CONF = '/var/etc/dhclient.%s.conf'
BOOTP_MIN_LEN = 300     # sbin/dhclient/dhcp.h -- real dhclient pads every message to this
# bind()/sendto() failures that mean "the address is already gone" rather
# than a real problem -- expected after an expired lease or a downed link,
# not worth logging as an error now that the caller no longer mutes us.
BENIGN_ERRNOS = {errno.EADDRNOTAVAIL, errno.ENETUNREACH, errno.ENETDOWN, errno.EHOSTUNREACH}


def last_lease(path):
    try:
        with open(path, 'r') as f:
            blob = f.read()
    except OSError:
        return None
    best = None
    for m in re.finditer(r'lease\s*\{(.*?)\n\}', blob, re.S):
        body = m.group(1)
        ip = re.search(r'\bfixed-address\s+([\d.]+)\s*;', body)
        sid = re.search(r'\boption\s+dhcp-server-identifier\s+([\d.]+)\s*;', body)
        if ip and sid:
            best = (ip.group(1), sid.group(1))
    return best


def hwaddr(dev):
    out = subprocess.run(['/sbin/ifconfig', dev], capture_output=True, text=True).stdout
    m = re.search(r'\bether\s+([0-9a-f:]{17})', out)
    return bytes.fromhex(m.group(1).replace(':', '')) if m else None


def client_id(dev, mac):
    """Option 61 payload, matching whatever the original DHCPREQUEST sent.

    If the release doesn't send the same identifier the request used, the
    server's lease binding won't match and the release silently fails to
    take effect.  dhclient.conf can carry an explicit identifier in either
    of the two forms dhclient's own parser accepts for a byte-string
    option, and interfaces.inc can generate both:

      send dhcp-client-identifier "name";        <- 'dhcphostname' is set
      send dhcp-client-identifier 01:aa:bb:...;  <- 'adv_dhcp_send_options'

    The quoted form is a raw string with no RFC 2132 type byte; the hex
    form is the literal option payload, type byte included if the user
    wrote one.  Anything else is left to the default MAC-derived type-1
    form, which is what dhclient itself sends when told nothing.
    """
    try:
        with open(DHCLIENT_CONF % dev, 'r') as f:
            conf = f.read()
    except OSError:
        conf = ''
    # anchored to the start of a line so a commented-out statement in a
    # user-supplied override config can't be mistaken for a live one
    m = re.search(r'^\s*send\s+dhcp-client-identifier\s+([^;\n]+);', conf, re.M)
    if m:
        val = m.group(1).strip()
        if len(val) >= 2 and val.startswith('"') and val.endswith('"'):
            return val[1:-1].encode('latin-1', errors='replace')
        if re.match(r'^[0-9A-Fa-f]{1,2}(:[0-9A-Fa-f]{1,2})*$', val):
            return bytes(int(b, 16) for b in val.split(':'))
    return b'\x01' + mac


def main():
    if len(sys.argv) != 2:
        return 2
    dev = sys.argv[1]
    if not re.match(r'^[0-9A-Za-z_.]+$', dev):
        return 2
    lease = last_lease(LEASE_DB % dev)
    mac = hwaddr(dev)
    if not lease or not mac:
        # Nothing to give back (no usable lease, or a non-ethernet device).
        # Not an error: the caller no longer mutes us, so don't log noise.
        return 0
    ciaddr, server = lease
    cid = client_id(dev, mac)

    pkt = struct.pack('!4B I 2H 4s 4s 4s 4s 16s 64s 128s 4s',
                      1, 1, 6, 0, random.getrandbits(32), 0, 0,
                      socket.inet_aton(ciaddr), b'\0' * 4, b'\0' * 4, b'\0' * 4,
                      mac.ljust(16, b'\0'), b'', b'', bytes([99, 130, 83, 99]))
    pkt += bytes([53, 1, 7])                                   # DHCPRELEASE
    pkt += bytes([54, 4]) + socket.inet_aton(server)           # server identifier
    pkt += bytes([61, len(cid)]) + cid                         # client identifier
    pkt += bytes([255])                                        # end of options
    # dhclient(8) pads every BOOTP message out to BOOTP_MIN_LEN; trailing
    # zeroes are PAD options. Some servers/relays drop short messages, so
    # match base dhclient's real on-wire size exactly.
    if len(pkt) < BOOTP_MIN_LEN:
        pkt += b'\0' * (BOOTP_MIN_LEN - len(pkt))

    s = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
    s.setsockopt(socket.SOL_SOCKET, socket.SO_REUSEADDR, 1)
    if hasattr(socket, 'SO_REUSEPORT'):
        s.setsockopt(socket.SOL_SOCKET, socket.SO_REUSEPORT, 1)
    try:
        s.bind((ciaddr, 68))
        s.sendto(pkt, (server, 67))
    except OSError as exc:
        s.close()
        if exc.errno in BENIGN_ERRNOS:
            # The address/link is already gone (expired lease, downed WAN,
            # etc.) -- there's nothing left to release, not a real failure.
            return 0
        sys.stderr.write('dhclient-release: %s: %s\n' % (dev, exc))
        return 1
    s.close()
    # RFC 2131: the lease is void once released; drop it so a restart cannot REBOOT it.
    try:
        os.unlink(LEASE_DB % dev)
    except OSError:
        pass
    return 0


if __name__ == '__main__':
    sys.exit(main())
