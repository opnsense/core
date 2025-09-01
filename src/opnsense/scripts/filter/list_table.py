#!/usr/local/bin/python3

"""
    Copyright (c) 2015 Ad Schellevis <ad@opnsense.org>
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
    returns the contents of a pf table
    usage : list_table.py [tablename]
"""
import subprocess
import sys
import ujson

if __name__ == '__main__':
    result = dict()
    if len(sys.argv) > 1:
        sp = subprocess.Popen(
            ['/sbin/pfctl', '-t', sys.argv[1], '-vT', 'show'],
            stdout=subprocess.PIPE,
            stderr=subprocess.DEVNULL,
            text=True
        )
        this_entry=None
        labels = {}
        while (line := sp.stdout.readline()):
            if line.startswith(' '):
                this_entry = line.strip()
                result[this_entry] = {'ip': this_entry}
            elif this_entry and 'Packets:' in line and 'Packets: 0 ' not in line:
                parts = line.split()
                if this_entry and len(parts) > 6 and parts[3].isdigit() and parts[5].isdigit():
                    topic = parts[0].lower().replace('/', '_').replace(':', '')
                    result[this_entry]['%s_p' % topic] = int(parts[3])
                    result[this_entry]['%s_b' % topic] = int(parts[5])

    print(ujson.dumps({'items': list(result.values())}))
