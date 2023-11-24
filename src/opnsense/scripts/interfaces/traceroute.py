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
parser.add_argument('--domain', help='domain name or ip to trace', default='')
parser.add_argument('--ipproto', help='ip protocol version [inet,inet6]', default='inet')
parser.add_argument('--source_address', help='source address to use', default=None)
parser.add_argument('--timeout', help='timeout in seconds', type=int, default=20)
parser.add_argument('--probes', help='number of probes', type=int, default=1)
parser.add_argument('--protocol', help='protocol to use [icmp, udp]', default='udp')
inputargs = parser.parse_args()



result = {'rows': []}
if inputargs.ipproto == 'inet6':
    cmd = ['/usr/sbin/traceroute6']
else:
    cmd = ['/usr/sbin/traceroute']

cmd = cmd + ['-a', '-w', '2', '-q', '%d' % inputargs.probes]
if inputargs.source_address:
    cmd = cmd + ['-s', inputargs.source_address]
if inputargs.protocol.lower() == 'icmp':
    cmd.append('-I')

cmd.append(inputargs.domain)

proc = subprocess.Popen(cmd, stdin=subprocess.PIPE, stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True)
try:
    outs, errs = proc.communicate(timeout=inputargs.timeout)
except subprocess.TimeoutExpired:
    result['error'] = 'timeout reached'
    proc.kill()
    outs, errs = proc.communicate()

if errs:
    result['notice'] = errs.strip()


for line in outs.strip().split('\n'):
    parts = line.split()
    if len(parts) < 3:
        continue
    record = {'address': ''}
    if parts[0].startswith('['):
        parts = [result['rows'][-1]['ttl'] if len(result['rows']) > 0 else ''] + parts

    record['ttl'] = parts[0]
    record['AS'] = parts[1][1:-1]
    record['host'] = parts[2]
    if parts[3].startswith('('):
        record['address'] = parts[3][1:-1]
        record['probes'] = ' '.join(parts[4:])
    else:
        record['probes'] = ' '.join(parts[3:])
    result['rows'].append(record)

print(json.dumps(result))
