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
import ujson

from lib.kea_ctrl import KeaCtrl

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

if __name__ == '__main__':
    parser = argparse.ArgumentParser()
    parser.add_argument('--proto', help='protocol to fetch (inet, inet6)', default='inet', choices=['inet', 'inet6'])
    inputargs = parser.parse_args()

    lease_cmd = 'lease4-get-all' if inputargs.proto == 'inet' else 'lease6-get-all'
    service = 'dhcp4' if inputargs.proto == 'inet' else 'dhcp6'
    config_key = 'subnet4' if inputargs.proto == 'inet' else 'subnet6'
    dhcp_key = 'Dhcp4' if inputargs.proto == 'inet' else 'Dhcp6'

    # Figure out how to group leases by interface
    ranges = build_ranges(inputargs.proto)

    config = KeaCtrl.send_command("config-get", None, service)

    subnets = [
        subnet['id']
        for subnet in config.get('arguments', {}).get(dhcp_key, {}).get(config_key, [])
        if 'id' in subnet
    ]

    leases = KeaCtrl.send_command(lease_cmd, {"subnets": subnets}, service)

    records = []
    for lease in leases.get("arguments", {}).get("leases", []):
        address = lease.get("ip-address")
        lease_if = None

        if address:
            for net, ifname in ranges.items():
                if net.overlaps(ipaddress.ip_network(address)):
                    lease_if = ifname
                    break

        records.append({
            "address": address,
            "prefix_len": lease.get("prefix-len", 128),
            "type": lease.get("type", ""),
            "hwaddr": lease.get("hw-address", ""),
            "duid": lease.get("duid", ""),
            "iaid": lease.get("iaid", ""),
            "valid_lifetime": lease.get("valid-lft", 0),
            "expire": lease.get("cltt", 0) + lease.get("valid-lft", 0),
            "hostname": lease.get("hostname", ""),
            "if": lease_if,
            "if_descr": "",
            "is_reserved": ""
        })

    if records:
        print(ujson.dumps({"records": records}))
