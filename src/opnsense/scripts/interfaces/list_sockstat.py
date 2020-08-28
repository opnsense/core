#!/usr/local/bin/python3

"""
    Copyright (c) 2020 Ad Schellevis <ad@opnsense.org>
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
    dump sockstat
"""
import subprocess
import os
import sys
import ujson
import netaddr

if __name__ == '__main__':
    netstat = dict()
    for line in subprocess.run(['/usr/bin/netstat', '-anL'], capture_output=True, text=True).stdout.split('\n')[2:]:
        parts = line.split()
        if len(parts) >= 3:
            tmp = parts[1].split('/')
            key = parts[2].replace('.', ':') if parts[0] != 'unix' else parts[2]
            netstat[key] = {'qlen': tmp[0], 'incqlen': tmp[1], 'maxqlen': tmp[2]}

    result = []
    for line in subprocess.run(['/usr/bin/sockstat'], capture_output=True, text=True).stdout.split('\n')[1:]:
        parts = line.split()
        if len(parts) >= 6:
            record = {
                'user': parts[0],
                'command': parts[1],
                'pid': parts[2],
                'fd': parts[3],
                'proto': parts[4],
                'local': "",
                'remote': ""
            }
            if parts[5] == '->':
                record['local'] = parts[6]
            else:
                record['local'] = parts[5]
                if len(parts) > 6:
                    record['remote'] = parts[6]

            if record['local'] in netstat:
                record['listen-queue-sizes'] = netstat[record['local']]
            result.append(record)
    print(ujson.dumps(result))
