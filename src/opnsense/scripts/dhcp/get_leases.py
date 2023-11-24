#!/usr/local/bin/python3

"""
    Copyright (c) 2017-2019 Ad Schellevis <ad@opnsense.org>
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

    --------------------------------------------------------------------------------------
    list dhcp leases
"""
import sys
sys.path.insert(0, "/usr/local/opnsense/site-python")
import watchers.dhcpd
import time
import argparse
import ujson

parser = argparse.ArgumentParser()
parser.add_argument('--inactive', help='include inactive leases', default='0', type=str)
args = parser.parse_args()

last_leases = dict()
result = list()
dhcpdleases = watchers.dhcpd.DHCPDLease()
for lease in dhcpdleases.watch():
    # only the last entries for a given IP are relevant
    last_leases[lease['address']] = lease

for lease in last_leases.values():
    if ('ends' in lease and lease['ends'] is not None and lease['ends'] > time.time()) or args.inactive == '1':
        result.append(lease)

print (ujson.dumps(result))
