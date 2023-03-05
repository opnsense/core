#!/usr/local/bin/python3

"""
    Copyright (c) 2016-2022 Ad Schellevis <ad@opnsense.org>
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
    list ipsec pools and leases
"""

import argparse
import subprocess
import ujson

# sample pool output from swanctl:
# pools_text = """
# defaultv4            192.168.99.0                        1 / 2 / 254
#   10.18.0.1                      offline  'user1'
#   10.18.0.2                      online   'user2'
#   10.18.0.3                      offline  'user3'
# defaultv6            fe80::20c:29ff:feaf:8196       0 / 0 / 22052457
# """

result = {'pools': {}}
if __name__ == '__main__':
    parser = argparse.ArgumentParser()
    parser.add_argument('--leases', action='store_true')
    cmd_args = parser.parse_args()
    args = ['/usr/local/sbin/swanctl', '--list-pools']
    if cmd_args.leases:
        args.append('--leases')
        result['leases'] = []
    pools_text = subprocess.run(args, capture_output=True, text=True).stdout.strip()

    current_pool=None
    for line in pools_text.split('\n'):
        parts = line.split()
        if cmd_args.leases and line.startswith('  ') and current_pool is not None and len(parts) >= 3:
            result['leases'].append({
                'pool': current_pool['name'],
                'address': parts[0],
                'online': parts[1] == 'online',
                'user': ' '.join(parts[2:])[1:-1]
            })
        elif len(parts) > 3 and parts[-1].isdigit():
            current_pool = {
                'name': parts[0],
                'net': parts[1],
                'online': int(parts[2]),
                'offline': int(parts[4]),
                'size': int(parts[6])
            }
            result['pools'][parts[0]] = current_pool

print(ujson.dumps(result))
