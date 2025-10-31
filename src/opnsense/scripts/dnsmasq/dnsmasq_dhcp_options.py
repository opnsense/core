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
import csv
import json
import os
import subprocess
import argparse

option_src = {
    'dhcp': 'iana/dhcpv4-options.csv',  # https://www.iana.org/assignments/bootp-dhcp-parameters/
    'dhcp6': 'iana/dhcpv6-parameters-2.csv' # https://www.iana.org/assignments/dhcpv6-parameters/
}

parser = argparse.ArgumentParser()
parser.add_argument("mode", nargs="?", default="dhcp", choices=["dhcp", "dhcp6"])
args = parser.parse_args()

common = {}
assigned = {}
unassigned = {}

# load IANA data
with open('/usr/local/opnsense/contrib/' + option_src[args.mode], 'r') as csvfile:
    for r in csv.reader(csvfile, delimiter=',', quotechar='"'):
        if not r or not r[0]:
            continue
        r_range = [int(x) for x in r[0].split('-') if x.isdigit()]
        if not r_range or len(r) < 2:
            continue
        name = r[1].strip().lower()
        for code in range(r_range[0], (r_range[1] if len(r_range) > 1 else r_range[0]) + 1):
            key = str(code)
            if name in ['unassigned', 'removed/unassigned']:
                # Only track unassigned for DHCPv4 (256 total), not DHCPv6 (65535 total)
                if args.mode == 'dhcp':
                    unassigned[key] = f"{name} [{code}]"
            elif name not in ['pad', 'end']:
                cleaned_name = name.replace('\n', ' ')
                assigned[key] = f"{cleaned_name} [{code}]"

# read dnsmasq supported options
sp = subprocess.run(
    ['/usr/local/sbin/dnsmasq', '--help', args.mode],
    capture_output=True, text=True
)

for line in sp.stdout.splitlines():
    parts = line.split(maxsplit=1)
    if len(parts) == 2 and parts[0].isdigit():
        key = parts[0]
        val = f"{parts[1]} [{key}]"
        # Options directly supported by dnsmasq (help output)
        common[key] = val
        # Deduplicate from other groups
        assigned.pop(key, None)
        unassigned.pop(key, None)

print(json.dumps({
    "Common": common,
    "Assigned": assigned,
    "Unassigned": unassigned
}))
