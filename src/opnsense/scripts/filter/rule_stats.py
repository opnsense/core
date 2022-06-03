#!/usr/local/bin/python3

"""
    Copyright (c) 2019 Ad Schellevis <ad@opnsense.org>
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

import fcntl
import os
import time
import ujson
import sys
import subprocess

# rate limit pfctl calls, request every X seconds
RATE_LIMIT_S = 60

if __name__ == '__main__':
    results = dict()
    cache_filename = '/tmp/cache_filter_rulestats.json'
    fstat = os.stat(cache_filename) if os.path.isfile(cache_filename) else None
    fhandle = open(cache_filename, 'a+')
    try:
        fhandle.seek(0)
        results = ujson.loads(fhandle.read())
    except ValueError:
        results = dict()
    if fstat is None or (time.time() - fstat.st_mtime) > RATE_LIMIT_S or len(results) == 0:
        if len(results) == 0:
            # lock blocking, nothing to return yet
            fcntl.flock(fhandle, fcntl.LOCK_EX)
        else:
            try:
                fcntl.flock(fhandle, fcntl.LOCK_EX | fcntl.LOCK_NB)
            except IOError:
                # already locked, return previous content
                print (ujson.dumps(results))
                sys.exit(0)

        results = dict()
        hex_digits = set("0123456789abcdef")
        sp = subprocess.run(['/sbin/pfctl', '-sr', '-v'], capture_output=True, text=True)
        stats = dict()
        prev_line = ''
        for rline in sp.stdout.split('\n') + []:
            line = rline.strip()
            if len(line) == 0 or line[0] != '[':
                if prev_line.find(' label ') > -1:
                    lbl = prev_line.split(' label ')[-1]
                    if lbl.count('"') >= 2:
                        rule_md5 = lbl.split('"')[1]
                        if len(rule_md5) == 32 and set(rule_md5).issubset(hex_digits):
                            if rule_md5 in results:
                                # aggregate raw pf rules (a single rule in out ruleset could be expanded)
                                for key in stats:
                                    if key in results[rule_md5]:
                                        if key == 'pf_rules':
                                            results[rule_md5][key] += 1
                                        else:
                                            results[rule_md5][key] += stats[key]
                                    else:
                                        results[rule_md5][key] = stats[key]
                            else:
                                results[rule_md5] = stats
                # reset for next rule
                prev_line = line
                stats = {'pf_rules': 1}
            elif line[0] == '['  and line.find('Evaluations') > 0:
                parts = line.strip('[ ]').replace(':', ' ').split()
                for i in range(0, len(parts)-1, 2):
                    if parts[i+1].isdigit():
                        stats[parts[i].lower()] = int(parts[i+1])
        output = ujson.dumps(results)
        fhandle.seek(0)
        fhandle.truncate()
        fhandle.write(output)
        fhandle.close()
        print(output)
    else:
        # output
        print (ujson.dumps(results))
