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
    list connected clients for a captive portal zone
"""
import sys
import ujson
from lib.db import DB

# parse input parameters
parameters = {'zoneid': None, 'output_type': 'plain'}
current_param = None
for param in sys.argv[1:]:
    if len(param) > 1 and param[0] == '/':
        current_param = param[1:].lower()
    elif current_param is not None:
        if current_param in parameters:
            parameters[current_param] = param.strip()
        current_param = None

if parameters['zoneid'] is not None:
    cpDB = DB()
    response = cpDB.list_clients(parameters['zoneid'])
else:
    response = []

# output result as plain text or json
if parameters['output_type'] != 'json':
    heading = {'sessionId': 'sessionid',
               'userName': 'username',
               'ipAddress': 'ip_address',
               'macAddress': 'mac_address',
               'total_bytes': 'total_bytes'
               }
    print '%(sessionId)-30s %(userName)-20s %(ipAddress)-20s %(macAddress)-20s %(total_bytes)-20s' % heading
    for item in response:
        item['total_bytes'] = (item['bytes_out'] + item['bytes_in'])
        print '%(sessionId)-30s %(userName)-20s %(ipAddress)-20s %(macAddress)-20s %(total_bytes)-20s' % item
else:
    print(ujson.dumps(response))
