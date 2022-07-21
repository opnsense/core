#!/usr/local/bin/python3

"""
    Copyright (c) 2022 Ad Schellevis <ad@opnsense.org>
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
import ipaddress
import json
import subprocess

parser = argparse.ArgumentParser()
parser.add_argument('domain', help='domain name to query')
parser.add_argument('--types', help='list of types to query (when querying hostnames)', default='A,AAAA,MX,TXT')
parser.add_argument('--server', help='server to query', default=None)
inputargs = parser.parse_args()

is_ipaddr = True
try:
    ipaddress.ip_address(inputargs.domain)
except ValueError:
    is_ipaddr = False

result = {}
if is_ipaddr:
    cmd = ['/usr/bin/host', '-v', inputargs.domain]
    if inputargs.server:
        cmd.append(inputargs.server)
    sp = subprocess.run(cmd, capture_output=True, text=True)
    if sp.stderr:
        result['error_message'] = sp.stderr.strip()
    else:
        result['PTR'] = {'answers': [], 'query_time': None, 'server': None}
        for line in sp.stdout.split('\n'):
            if line.find('IN') > -1 and not line.startswith(';'):
                result['PTR']['answers'].append(line)
            elif line.find('Received') == 0:
                result['PTR']['server'] = line.split('from')[-1].split('#')[0].strip()
                result['PTR']['query_time'] = line.split(' in ')[-1]
else:
    for qtype in inputargs.types.split(','):
        cmd = ['/usr/bin/drill']
        cmd.append(inputargs.domain)
        if inputargs.server:
            cmd.append('@%s' % inputargs.server)
        cmd.append(qtype)
        sp = subprocess.run(cmd, capture_output=True, text=True)
        response_section = None
        if sp.stderr:
            result['error_message'] = sp.stderr.strip()
            break

        qtype_results = {'answers': [], 'query_time': None, 'server': None}
        for line in sp.stdout.split('\n'):
            if line.startswith(';;') and line.endswith('SECTION:'):
                response_section = line.split()[1]
            elif not line.startswith(';;') and line.find('IN') > -1:
                if response_section == 'ANSWER':
                    qtype_results['answers'].append(line)
            elif response_section == 'ADDITIONAL' and line.find('Query time') > -1:
                qtype_results['query_time'] = line.split(':')[1].strip()
            elif response_section == 'ADDITIONAL' and line.find('SERVER') > -1:
                qtype_results['server'] = line.split(':', 1)[1].strip()
        if qtype_results['answers']:
            result[qtype] = qtype_results

print(json.dumps(result))
