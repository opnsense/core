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
import argparse
import json
import subprocess

parser = argparse.ArgumentParser()
parser.add_argument('--hostname', help='domain name or ip to probe', default='')
parser.add_argument('--port', help='port to connect to', default='')
parser.add_argument('--ipproto', help='ip protocol version [inet,inet6]', default='inet')
parser.add_argument('--source_address', help='source address to use', default=None)
parser.add_argument('--source_port', help='source port to use', default=None)
parser.add_argument('--timeout', help='timeout in seconds', type=int, default=10)
parser.add_argument('--showtext', help='show output from remote host', default='0')
inputargs = parser.parse_args()

if inputargs.ipproto == 'inet6':
    cmd = ['/usr/bin/nc', '-6', '-v']
else:
    cmd = ['/usr/bin/nc', '-4', '-v']

cmd = cmd + ['-w', '%d' % inputargs.timeout]
if inputargs.source_address:
    cmd = cmd + ['-s', inputargs.source_address]

if inputargs.source_port:
    cmd = cmd + ['-p', inputargs.source_port]

if inputargs.showtext != '1':
    cmd.append('-z')

cmd = cmd + [inputargs.hostname, inputargs.port]

result = {}
sp = subprocess.run(cmd, capture_output=True, text=True)
if sp.stderr:
    result['message'] = sp.stderr.strip()

result['payload'] = sp.stdout if sp.stdout else ''

print(json.dumps(result))
