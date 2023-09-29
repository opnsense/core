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
import subprocess
import ujson


interfaces = {}
for line in subprocess.run(['/sbin/ifconfig'], capture_output=True, text=True).stdout.split("\n"):
    if not line.startswith('\t') and line.find('<') > -1:
        ifname = line.split(':')[0]
        interfaces[ifname] = 'up' if 'UP' in line.split('<')[1].split('>')[0].split(',') else 'down'

sp = subprocess.run(['/usr/bin/wg', 'show', 'all', 'dump'], capture_output=True, text=True)
result = {'records': []}
if sp.returncode == 0:
    for line in sp.stdout.split("\n"):
        record = {}
        parts = line.split("\t")
        # parse fields as explained in 'man wg'
        record['if'] = parts[0] if len(parts) else None
        if len(parts) == 5:
            # intentially skip private key, should not expose it
            record['type'] = 'interface'
            record['public-key'] = parts[2]
            record['listen-port'] = parts[3]
            record['fwmark'] = parts[4]
            # convenience, copy listen-port to endpoint
            record['endpoint'] = parts[3]
            record['status'] = interfaces.get(record['if'], 'down')
        elif len(parts) == 9:
            record['type'] = 'peer'
            record['public-key'] = parts[1]
            # intentially skip preshared-key, should not expose it
            record['endpoint'] = parts[3]
            record['allowed-ips'] = parts[4]
            record['latest-handshake'] = int(parts[5]) if parts[5].isdigit() else 0
            record['transfer-rx'] = int(parts[6]) if parts[6].isdigit() else 0
            record['transfer-tx'] = int(parts[7]) if parts[7].isdigit() else 0
            record['persistent-keepalive'] = parts[8]
        else:
            continue
        result['records'].append(record)
    result['status'] = 'ok'
else:
    result['status'] = 'failed'

print(ujson.dumps(result))
