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
    disconnect client
"""
import sys
import ujson
from lib.db import DB
from lib.ipfw import IPFW

# parse input parameters
parameters = {'sessionid': None, 'zoneid': None, 'output_type': 'plain'}
current_param = None
for param in sys.argv[1:]:
    if len(param) > 1 and param[0] == '/' and param[1:] in parameters:
        current_param = param[1:].lower()
    elif current_param is not None:
        parameters[current_param] = param.strip()
        current_param = None

# disconnect client
response = {'terminateCause': 'UNKNOWN'}
if parameters['sessionid'] is not None and parameters['zoneid'] is not None:
    cp_db = DB()
    # remove client
    client_session_info = cp_db.del_client(parameters['zoneid'], parameters['sessionid'])
    if client_session_info is not None:
        cpIPFW = IPFW()
        cpIPFW.delete_from_table(parameters['zoneid'], client_session_info['ip_address'])
        client_session_info['terminateCause'] = 'User-Request'
        response = client_session_info

# output result as plain text or json
if parameters['output_type'] != 'json':
    for item in response:
        print '%20s %s' % (item, response[item])
else:
    print(ujson.dumps(response))
