#!/usr/local/bin/python3

"""
    Copyright (c) 2018-2019 Deciso B.V.
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
    check which aliases match the given IP
"""
import tempfile
import subprocess
import os
import sys
import ujson
from netaddr import IPNetwork, IPAddress, AddrFormatError

if __name__ == '__main__':
    # IP should have been passed as a command line argument
    if len(sys.argv) >= 1:

        try:
            ip = IPAddress(sys.argv[1])
            result = {'status': 'ok', 'matches': []}
            tables = []

            # Fetch tables
            sp = subprocess.run(['/sbin/pfctl', '-sT'], capture_output=True, text=True)
            for line in sp.stdout.strip().split('\n'):
                tables.append(line.strip())

            # Test given address against tables
            for table in tables:
                sp = subprocess.run(['/sbin/pfctl', '-t', table, '-Ttest', sys.argv[1]], capture_output=True, text=True)
                if sp.stderr.strip().find("1/1") == 0:
                   result['matches'].append(table)
            print(ujson.dumps(result))

        except AddrFormatError:
            print(ujson.dumps({'status': 'Invalid IP specified!'}))

    else:
        print(ujson.dumps({'status': 'IP parameter not specified!'}))
