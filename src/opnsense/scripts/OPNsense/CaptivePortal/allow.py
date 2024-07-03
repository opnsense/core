#!/usr/local/bin/python3

"""
    Copyright (c) 2015-2024 Ad Schellevis <ad@opnsense.org>
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
    allow user/host to captive portal
"""
import argparse
import sys
import ujson
from lib.db import DB
from lib.arp import ARP
from lib.ipfw import IPFW

parser = argparse.ArgumentParser()
parser.add_argument('-username', help='username', type=str, required=True)
parser.add_argument('-zoneid', help='zone number to allow this user in', type=str, required=True)
parser.add_argument('-authenticated_via', help='authentication source', type=str)
parser.add_argument('-ip_address', help='source ip address', type=str)
args = parser.parse_args()

arp_entry = ARP().get_by_ipaddress(args.ip_address)
response = DB().add_client(
    zoneid=args.zoneid,
    authenticated_via=args.authenticated_via,
    username=args.username,
    ip_address=args.ip_address,
    mac_address=arp_entry['mac'] if arp_entry is not None else None
)
IPFW().add_to_table(table_number=args.zoneid, address=args.ip_address)
response['clientState'] = 'AUTHORIZED'
print(ujson.dumps(response))
