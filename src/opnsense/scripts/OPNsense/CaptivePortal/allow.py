#!/usr/local/bin/python2.7

"""
    Copyright (c) 2015 Deciso B.V. - Ad Schellevis
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
import sys
import ujson
from lib.db import DB
from lib.arp import ARP
from lib.ipfw import IPFW

# parse input parameters
parameters = {'username': '', 'ip_address': None, 'zoneid': None, 'output_type':'plain'}
current_param = None
for param in sys.argv[1:]:
    if param[0] == '/':
        current_param = param[1:].lower()
    elif current_param is not None:
        if current_param in parameters:
            parameters[current_param] = param.strip()
        current_param = None

# create new session
if parameters['ip_address'] is not None and parameters['zoneid'] is not None:
    cpDB = DB()
    cpIPFW = IPFW()
    arp_entry = ARP().get_by_ipaddress(parameters['ip_address'])
    if arp_entry is not None:
        mac_address = arp_entry['mac']
    else:
        mac_address = None

    response = cpDB.add_client(zoneid=parameters['zoneid'],
                               username=parameters['username'],
                               ip_address=parameters['ip_address'],
                               mac_address=mac_address
                               )
    # check if address is not already registered before adding it to the ipfw table
    if not cpIPFW.ip_or_net_in_table(table_number=parameters['zoneid'], address=parameters['ip_address']):
        cpIPFW.add_to_table(table_number=parameters['zoneid'], address=parameters['ip_address'])
    response['state'] = 'AUTHORIZED'
else:
    response = {'state': 'UNKNOWN'}


# output result as plain text or json
if parameters['output_type'] != 'json':
    for item in response:
        print '%20s %s' % (item, response[item])
else:
    print(ujson.dumps(response))

