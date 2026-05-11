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

def get_reservation_keys(record, proto):
    keys = []
    subnet_id = record.get('subnet-id')

    if subnet_id is not None:
        # Reservation returns hw-address and duid like this: "01:48:90"...
        hwaddr = record.get('hw-address', '').lower()
        duid = record.get('duid', '').lower()
        # ...but client_id like this: "014890"
        client_id = record.get('client-id', '').replace(':', '').lower()

        if hwaddr:
            keys.append((subnet_id, 'hwaddr', hwaddr))
        if proto == 'inet6' and duid:
            keys.append((subnet_id, 'duid', duid))
        if proto == 'inet' and client_id:
            keys.append((subnet_id, 'client_id', client_id))

    return keys

def build_reserved_matches(config, leases, proto, dhcp_key, config_key):
    """
    Kea does not expose whether a lease came from a reservation, so we infer it
    by comparing client identity within the same subnet-id. The subnet-id check
    matters because the same MAC, DUID or client-id may have reservations on
    one subnet, but due to client roaming currently exist on a different subnet as lease.
    They should not be marked as reserved when they are not in the expected subnet-id scope.
    """
    wanted = set()
    matches = {}

    for lease in leases:
        wanted.update(get_reservation_keys(lease, proto))

    if wanted:
        for subnet in config.get('arguments', {}).get(dhcp_key, {}).get(config_key, []):
            subnet_id = subnet.get('id')
            if subnet_id is None:
                continue

            for reservation in subnet.get('reservations', []):
                hwaddr = reservation.get('hw-address', '').lower()
                duid = reservation.get('duid', '').lower()
                client_id = reservation.get('client-id', '').replace(':', '').lower()

                if hwaddr and (subnet_id, 'hwaddr', hwaddr) in wanted:
                    matches[(subnet_id, 'hwaddr', hwaddr)] = 'hwaddr'
                if proto == 'inet6' and duid and (subnet_id, 'duid', duid) in wanted:
                    matches[(subnet_id, 'duid', duid)] = 'duid'
                if proto == 'inet' and client_id and (subnet_id, 'client_id', client_id) in wanted:
                    matches[(subnet_id, 'client_id', client_id)] = 'client_id'

    return matches

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
    leases = leases.get("arguments", {}).get("leases", [])
    reserved_matches = build_reserved_matches(config, leases, inputargs.proto, dhcp_key, config_key)

    records = []
    for lease in leases:
        address = lease.get("ip-address")
        lease_if = None
        is_reserved = []

        if address:
            for net, ifname in ranges.items():
                if net.overlaps(ipaddress.ip_network(address)):
                    lease_if = ifname
                    break

        for key in get_reservation_keys(lease, inputargs.proto):
            if key in reserved_matches:
                is_reserved.append(reserved_matches[key])

        records.append({
            "address": address,
            "prefix_len": lease.get("prefix-len", 128),
            "type": lease.get("type", ""),
            "hwaddr": lease.get("hw-address", ""),
            "duid": lease.get("duid", ""),
            "client_id": lease.get("client-id", ""),
            "iaid": lease.get("iaid", ""),
            "valid_lifetime": lease.get("valid-lft", 0),
            "expire": lease.get("cltt", 0) + lease.get("valid-lft", 0),
            "hostname": lease.get("hostname", ""),
            "state": lease.get("state", 0),
            "if": lease_if,
            "if_descr": "",
            "is_reserved": is_reserved
        })

    if records:
        print(ujson.dumps({"records": records}))
