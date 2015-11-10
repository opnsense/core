#!/usr/local/bin/python2.7

"""
    Copyright (c) 2015 Ad Schellevis
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

import ujson
import vici
s = vici.Session()

result = dict()
# parse connections
for conns in s.list_conns():
    for connection_id in conns:
        result[connection_id] = dict()
        result[connection_id]['version'] = conns[connection_id]['version']
        result[connection_id]['local_addrs'] = ','.join(conns[connection_id]['local_addrs'])
        result[connection_id]['local-id'] = ''
        result[connection_id]['local-class'] = []
        result[connection_id]['remote-id'] = ''
        result[connection_id]['remote-class'] = []
        result[connection_id]['children'] = conns[connection_id]['children']
        result[connection_id]['sas'] = []

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

        result[connection_id]['local-class'] = '+'.join(result[connection_id]['local-class'])
        result[connection_id]['remote-class'] = '+'.join(result[connection_id]['remote-class'])

# attach Security Associations
for sas in s.list_sas():
    for sa in sas:
        if sa in result:
            result[sa]['sas'].append(sas[sa])

print(ujson.dumps(result))
