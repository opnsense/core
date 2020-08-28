#!/usr/local/bin/python3

"""
    Copyright (c) 2016-2019 Ad Schellevis <ad@opnsense.org>
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

import subprocess
import ujson

result = dict()
if __name__ == '__main__':
    sp = subprocess.run(['/usr/local/sbin/ipsec', 'leases'], capture_output=True, text=True)
    current_pool=None
    for line in sp.stdout.strip().split('\n'):
        if line.find('Leases in pool') > -1:
            current_pool = line.split("'")[1]
            result[current_pool] = {'items': []}
            result[current_pool]['usage'] = line.split(',')[1].split(':')[1].strip()
            result[current_pool]['online'] = line.split(',')[2].strip().split(' ')[0]
        elif current_pool is not None and line.find('line') > -1:
            lease = {'address': line.split()[0], 'status': line.split()[1], 'user': line.split("'")[1]}
            result[current_pool] ['items'].append(lease)

print(ujson.dumps(result))
