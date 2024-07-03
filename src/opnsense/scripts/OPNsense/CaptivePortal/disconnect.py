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
    disconnect client
"""
import argparse
import ujson
from lib.db import DB
from lib.ipfw import IPFW


parser = argparse.ArgumentParser()
parser.add_argument('session', help='session id to delete', type=str)
parser.add_argument('-z', help='optional zoneid to filter on', type=str)
args = parser.parse_args()

response = {'terminateCause': 'UNKNOWN'}
client_session_info = DB().del_client(int(args.z) if str(args.z).isdigit() else None, args.session)
if client_session_info is not None:
    if client_session_info['ip_address']:
        IPFW().delete(client_session_info['zoneid'], client_session_info['ip_address'])
    client_session_info['terminateCause'] = 'User-Request'
    response = client_session_info

print(ujson.dumps(response))
