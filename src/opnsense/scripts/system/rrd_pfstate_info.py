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

if __name__ == '__main__':
    result = {'pfrate': 0.0, 'pfstates': 0, 'pfnat': 0, 'srcip': 0, 'dstip': 0}
    for row in subprocess.run(['/sbin/pfctl', '-si'], capture_output=True, text=True).stdout.split('\n'):
        parts = row.split()
        if len(parts) < 2:
            continue
        elif parts[0] in ['inserts', 'removals']:
            result['pfrate'] += float(parts[-1].split('/')[0])

    src_ips = set()
    dst_ips = set()
    for row in subprocess.run(['/sbin/pfctl', '-ss'], capture_output=True, text=True).stdout.split('\n'):
        parts = row.split()
        result['pfstates'] += 1
        if row.find('(') > -1 and row.find(')') > -1:
            result['pfnat'] += 1
        if row.find('->') > -1:
            src_ips.add(parts[2].split(':')[0])
        elif row.find('<-') > -1:
            dst_ips.add(parts[2].split(':')[0])
    result['srcip'] = len(src_ips)
    result['dstip'] = len(dst_ips)
    print('%(pfrate)0.1f:%(pfstates)d:%(pfnat)d:%(srcip)d:%(dstip)d' % result,end='')
