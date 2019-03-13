#!/usr/local/bin/python2.7

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

import os
import ujson
import tempfile
import subprocess


if __name__ == '__main__':
    results = dict()
    hex_digits = set("0123456789abcdef")
    with tempfile.NamedTemporaryFile() as output_stream:
        subprocess.call(['/sbin/pfctl', '-sr', '-v'], stdout=output_stream, stderr=open(os.devnull, 'wb'))
        output_stream.write(b'\n') # always make sure we have a final line-ending
        output_stream.seek(0)
        stats = dict()
        prev_line = ''
        for rline in output_stream:
            line = rline.decode().strip()
            if len(line) == 0 or line[0] != '[':
                if prev_line.find(' label ') > -1:
                    lbl = prev_line.split(' label ')[-1]
                    if lbl.count('"') >= 2:
                        rule_md5 = lbl.split('"')[1]
                        if len(rule_md5) == 32 and set(rule_md5).issubset(hex_digits):
                            results[rule_md5] = stats
                # reset for next rule
                prev_line = line
                stats = dict()
            elif line[0] == '['  and line.find('Evaluations') > 0:
                parts = line.strip('[ ]').replace(':', ' ').split()
                for i in range(0, len(parts)-1, 2):
                    if parts[i+1].isdigit():
                        stats[parts[i].lower()] = int(parts[i+1])

    # output
    print (ujson.dumps(results))
