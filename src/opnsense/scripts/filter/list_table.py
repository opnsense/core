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
    returns the contents of a pf table (optional as a json container)
    usage : list_table.py [tablename] [optional|json]
"""
import subprocess
import sys
import ujson

if __name__ == '__main__':
    result = dict()
    if len(sys.argv) > 1:
        sp = subprocess.run(['/sbin/pfctl', '-t', sys.argv[1], '-vT', 'show'], capture_output=True, text=True)
        prev_entry=''
        statistics=dict()
        for line in sp.stdout.split('\n') + []:
            if not line.startswith('\t'):
                this_entry = line.strip()
                if len(prev_entry) > 0:
                    result[prev_entry] = statistics
                prev_entry = this_entry
                statistics=dict()
            else:
                parts = line.split()
                if len(parts) > 6:
                    if parts[3].isdigit() and parts[5].isdigit():
                        pkts=int(parts[3])
                        bytes=int(parts[5])
                        topic = parts[0].lower().replace('/', '_').replace(':', '')
                        if pkts >= 0:
                            statistics['%s_p' % topic] = pkts
                            statistics['%s_b' % topic] = bytes

    # handle command line argument (type selection)
    if len(sys.argv) > 2 and sys.argv[2] == 'json':
        print(ujson.dumps(result))
    else:
        # output plain, simple no statistics
        for table in result:
            print (table)
