#!/usr/local/bin/python3

"""
    Copyright (c) 2015-2019 Ad Schellevis <ad@opnsense.org>
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
    list ipsec status, using vici interface
"""

import sys
import socket
import ujson
import vici
try:
    s = vici.Session()
except socket.error:
    # cannot connect to session, strongswan not running?
    print ('ipsec not active')
    sys.exit(0)


def parse_sa(in_conn):
    result = {'local-addrs': '', 'remote-addrs': '', 'children': '', 'local-id': '', 'remote-id': ''}
    result['version'] = in_conn['version']
    if 'local_addrs' in in_conn:
        result['local-addrs'] = b','.join(in_conn['local_addrs'])
    elif 'local-host' in in_conn:
        result['local-addrs'] = in_conn['local-host']
    if 'remote_addrs'  in in_conn:
        result['remote-addrs'] =  b','.join(in_conn['remote_addrs'])
    elif 'remote-host' in in_conn:
        result['remote-addrs'] = in_conn['remote-host']
    if 'children' in in_conn:
        result['children'] = in_conn['children']

    result['sas'] = []
    return result

result = dict()
# parse connections
for conns in s.list_conns():
    for connection_id in conns:
        result[connection_id] = parse_sa(conns[connection_id])
        result[connection_id]['routed'] = True
        result[connection_id]['local-class'] = []
        result[connection_id]['remote-class'] = []
        # parse local-% and remote-% keys
        for connKey in conns[connection_id].keys():
            if connKey.find('local-') == 0:
                if 'id' in conns[connection_id][connKey]:
                    result[connection_id]['local-id'] = conns[connection_id][connKey]['id']
                result[connection_id]['local-class'].append(conns[connection_id][connKey]['class'])
            elif connKey.find('remote-') == 0:
                if 'id' in conns[connection_id][connKey]:
                    result[connection_id]['remote-id'] = conns[connection_id][connKey]['id']
                result[connection_id]['remote-class'].append(conns[connection_id][connKey]['class'])
        result[connection_id]['local-class'] = b'+'.join(result[connection_id]['local-class'])
        result[connection_id]['remote-class'] = b'+'.join(result[connection_id]['remote-class'])

# attach Security Associations
for sas in s.list_sas():
    for sa in sas:
        if sa not in result:
            result[sa] = parse_sa(sas[sa])
            result[sa]['routed'] = False
        result[sa]['sas'].append(sas[sa])

print (ujson.dumps(result, reject_bytes=False))
