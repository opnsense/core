#!/usr/local/bin/python3

"""
    Copyright (c) 2022 Ad Schellevis <ad@opnsense.org>
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
import hashlib
import subprocess
import ujson

if __name__ == '__main__':
    result = {'records': []}
    sp = subprocess.run(['/sbin/setkey', '-DP'], capture_output=True, text=True)
    line_no = 0
    spd_rec = None
    spec_line = ""
    for line in sp.stdout.split("\n"):
        line_no += 1
        parts = line.split()
        if not line.startswith("\t") and len(parts) > 2:
            line_no = 0
            spec_line = line.strip()
            spd_rec = {
                'src': parts[0].split('[')[0],
                'dst': parts[1].split('[')[0]
            }
            if parts[0].find('[') > 0:
                spd_rec['src_port'] = parts[0].split('[')[1].split(']')[0]
            if parts[1].find('[') > 0:
                spd_rec['dst_port'] = parts[1].split('[')[1].split(']')[0]
            spd_rec['upperspec'] = parts[2]
        elif spd_rec and line_no == 1 and len(parts) >= 2:
            spd_rec['dir'] = parts[0]
            spd_rec['type'] = parts[1]
            # the spd record (used for spddelete) identities itself by the spec {first row} + direction
            ident = "%s %s" % (spec_line, parts[0])
            spd_rec['id'] = hashlib.md5(ident.encode()).hexdigest()
        elif spd_rec and line_no == 2 and line.count('/') >= 3:
            parts = line.strip().split('/')
            spd_rec['proto'] = parts[0]
            spd_rec['mode'] = parts[1]
            spd_rec['src-dst'] = parts[2].split('-')
            spd_rec['level'] = parts[3].split('#')[0].split(':')[0]
            if parts[3].find(':') > -1:
                spd_rec['reqid'] = parts[3].split(':')[-1]
        elif spd_rec and line.find('=') > 0:
            for attr in parts:
                if attr.find('=') > -1:
                    tmp = attr.split('=')
                    spd_rec[tmp[0]] = tmp[1]
            if line.startswith('\trefcnt'):
                result['records'].append(spd_rec)

    # make sure all records are formatted equally in terms of available fields
    all_keys = set()
    for record in result['records']:
        all_keys = all_keys.union(record.keys())
    for record in result['records']:
        for key in all_keys:
            if key not in record:
                record[key] = None

    print(ujson.dumps(result))
