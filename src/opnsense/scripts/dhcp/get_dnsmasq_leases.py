#!/usr/local/bin/python3

"""
    Copyright (c) 2025 Ad Schellevis <ad@opnsense.org>
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

import ipaddress
import subprocess
import os
import ujson
import argparse

LEASES_FILE = '/var/db/dnsmasq.leases'


def get_leases(ip=None):
    ranges = {}
    leases = []
    this_interface = None
    ifconfig = subprocess.run(['/sbin/ifconfig', '-f', 'inet:cidr,inet6:cidr'], capture_output=True, text=True).stdout
    for line in ifconfig.split('\n'):
        if not line.startswith("\t") and line.find(':') > -1:
            this_interface = line.strip().split(':')[0]
        elif this_interface is not None and line.startswith("\tinet") and line.find('-->') == -1:
            ranges[ipaddress.ip_network(line.split()[1], strict=False)] = this_interface

    if os.path.isfile(LEASES_FILE):
        with open(LEASES_FILE, 'r') as leasefile:
            for line in leasefile:
                parts = line.split()
                if len(parts) > 4 and parts[0].isdigit():
                    lease = {
                        'expire': int(parts[0]),
                        'hwaddr': '',
                        'iaid': '',
                        'address': parts[2],
                        'hostname': parts[3],
                        'client_id': parts[4]
                    }

                    # MAC (IPv4) and IAID (IPv6) share the same spot
                    if ':' in parts[2]:
                        lease['iaid'] = parts[1]
                    else:
                        lease['hwaddr'] = parts[1]

                    # DUID-LL and DUID-LLT (IPv6) contain the hwaddr, extract it
                    if lease['hwaddr'] == '' and ':' in lease['client_id']:
                        parts_client_id = lease['client_id'].lower().split(":")
                        if len(parts_client_id) >= 10:
                            duid_type = parts_client_id[0:2]
                            if duid_type in [['00', '01'], ['00', '03']]:
                                lease['hwaddr'] = ":".join(parts_client_id[-6:])

                    for net in ranges:
                        if net.overlaps(ipaddress.ip_network(lease['address'])):
                            lease['if'] = ranges[net]
                            break
                    if ip is None or ip == "all" or lease['address'] == ip:
                        leases.append(lease)

    print(ujson.dumps({'records': leases}))


def delete_lease(ip):
    if not os.path.isfile(LEASES_FILE):
        return

    if ip == "all":
        # Truncate the lease file (delete all)
        with open(LEASES_FILE, 'w') as f:
            pass
    else:
        with open(LEASES_FILE, 'r') as f:
            lines = f.readlines()
        with open(LEASES_FILE, 'w') as f:
            for line in lines:
                if f' {ip} ' not in line:
                    f.write(line)


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description='dnsmasq lease tool')
    parser.add_argument(
        'command',
        nargs='?',
        default='get',
        choices=['get', 'delete'],
        help='Command to run: get (default) or delete'
    )
    parser.add_argument(
        'ip',
        nargs='?',
        help='IP to get or delete, or "all"'
    )

    args = parser.parse_args()

    if args.command == 'get':
        get_leases(args.ip)
    elif args.command == 'delete':
        if not args.ip:
            parser.error("delete requires an IP or 'all'")
        delete_lease(args.ip)

