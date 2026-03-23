#!/usr/local/bin/python3

"""
    Copyright (c) 2026 Deciso B.V.
    Copyright (c) 2023-2025 Ad Schellevis <ad@opnsense.org>
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
import argparse
import os
import csv
import ujson
import socket


def build_ranges(proto):
    ranges = {}
    this_interface = None
    addr_prefix = "\tinet6" if proto == 'inet6' else "\tinet "

    ifconfig = subprocess.run(['/sbin/ifconfig', '-f', 'inet:cidr,inet6:cidr'], capture_output=True, text=True).stdout

    for line in ifconfig.split('\n'):
        if not line.startswith("\t") and ':' in line:
            this_interface = line.strip().split(':')[0]
        elif this_interface is not None and line.startswith(addr_prefix) and '-->' not in line:
            ranges[ipaddress.ip_network(line.split()[1], strict=False)] = this_interface

    return ranges


def send_command(socket_path, payload):
    sock = socket.socket(socket.AF_UNIX, socket.SOCK_STREAM)
    sock.connect(socket_path)
    sock.sendall(ujson.dumps(payload).encode() + b"\n")

    data = b""
    while True:
        chunk = sock.recv(4096)
        if not chunk:
            break
        data += chunk
        if data.strip().endswith(b'}'):
            break

    sock.close()
    return ujson.loads(data.decode())


if __name__ == '__main__':
    parser = argparse.ArgumentParser()
    parser.add_argument('--proto', help='protocol to fetch (inet, inet6)', default='inet', choices=['inet', 'inet6'])
    inputargs = parser.parse_args()

    filename = '/var/db/kea/kea-leases4.csv' if inputargs.proto == 'inet' else '/var/db/kea/kea-leases6.csv'
    socket_path = '/var/run/kea/kea4-ctrl-socket' if inputargs.proto == 'inet' else '/var/run/kea/kea6-ctrl-socket'
    lease_cmd = 'lease4-get-all' if inputargs.proto == 'inet' else 'lease6-get-all'
    config_key = 'subnet4' if inputargs.proto == 'inet' else 'subnet6'
    dhcp_key = 'Dhcp4' if inputargs.proto == 'inet' else 'Dhcp6'

    # Figure out how to group leases by interface
    ranges = build_ranges(inputargs.proto)

    # The in-memory database is the most accurate, only use the CSV files as fallback if the socket is not available
    # If leases are deleted in the in-memory database this does not immediately reflect on the CSV files
    if os.path.exists(socket_path):
        config = send_command(socket_path, {"command": "config-get"})

        subnets = [
            subnet['id']
            for subnet in config.get('arguments', {}).get(dhcp_key, {}).get(config_key, [])
            if 'id' in subnet
        ]

        leases = send_command(socket_path, {"command": lease_cmd, "arguments": {"subnets": subnets}})

        records = []
        for lease in leases.get('arguments', {}).get('leases', []):
            address = lease.get("ip-address")
            lease_if = None

            if address:
                for net, ifname in ranges.items():
                    if net.overlaps(ipaddress.ip_network(address)):
                        lease_if = ifname
                        break

            # Normalize as keys are different than in the CSV files
            records.append({
                "address": address,
                "hwaddr": lease.get("hw-address", ""),
                "valid_lifetime": lease.get("valid-lft", 0),
                "expire": lease.get("cltt", 0) + lease.get("valid-lft", 0),
                "hostname": lease.get("hostname", ""),
                "if": lease_if,
                "if_descr": "",
                "is_reserved": ""
            })

        if records:
            print(ujson.dumps({"records": records}))
            exit(0)

    # CSV fallback if control socket is unavailable or returns no records
    # Lease processing after cleanup according to KEA
    # https://github.com/isc-projects/kea/blob/ef1f878f5272d/src/lib/dhcpsrv/memfile_lease_mgr.h#L1039-L1051
    if os.path.isfile('%s.completed' % filename):
        filenames = ['%s.completed' % filename, filename]
    else:
        filenames = ['%s.2' % filename, '%s.1' % filename, filename]

    leases = {}
    for filename in filenames:
        if not os.path.isfile(filename):
            continue
        with open(filename, 'r', encoding='utf-8',  errors='ignore') as csvfile:
            header = None
            for idx, record in enumerate(csv.reader(csvfile, delimiter=',', quotechar='"')):
                rec_key = ','.join(record[:2])
                if idx == 0:
                    header = record
                elif header:
                    named_record = {'if': None}
                    for findx, field in enumerate(record):
                        if findx < len(header):
                            named_record[header[findx]] = field
                    if rec_key in leases:
                        named_record['if'] = leases[rec_key]['if']
                    else:
                        # prevent additional range check when lease was already found in an earlier set.
                        for net in ranges:
                            if net.overlaps(ipaddress.ip_network(named_record['address'])):
                                named_record['if'] = ranges[net]
                    leases[rec_key] = named_record

    print (ujson.dumps({'records': list(leases.values())}))
