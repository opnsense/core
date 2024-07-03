#!/usr/local/bin/python3

"""
    Copyright (c) 2023 Ad Schellevis <ad@opnsense.org>
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


if __name__ == '__main__':
    parser = argparse.ArgumentParser()
    parser.add_argument('--proto', help='protocol to fetch (inet, inet6)', default='inet', choices=['inet'])
    inputargs = parser.parse_args()
    if inputargs.proto == 'inet':
        filename = '/var/db/kea/kea-leases4.csv'

    ranges = {}
    this_interface = None
    ifconfig = subprocess.run(['/sbin/ifconfig', '-f', 'inet:cidr,inet6:cidr'], capture_output=True, text=True).stdout
    for line in ifconfig.split('\n'):
        if not line.startswith("\t") and line.find(':') > -1:
            this_interface = line.strip().split(':')[0]
        elif this_interface is not None and line.startswith("\tinet") and line.find('-->') == -1:
            ranges[ipaddress.ip_network(line.split()[1], strict=False)] = this_interface

    result = {'records': []}
    header = None
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
        with open(filename, 'r') as csvfile:
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
