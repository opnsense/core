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
import argparse
import hashlib
import subprocess
import ujson

if __name__ == '__main__':
    parser = argparse.ArgumentParser()
    parser.add_argument('id', help='record id (md5 hash)')
    cmd_args = parser.parse_args()

    result  = {'status': 'not_found'}
    sp = subprocess.run(['/sbin/setkey', '-DP'], capture_output=True, text=True)
    spec_line = None
    spds = cmd_args.id.split(',')
    deleted_entries = []
    for line in sp.stdout.split("\n"):
        if not line.startswith("\t") and len(line.split()) > 2:
            spec_line = line.strip()
        elif spec_line:
            ident = "%s %s" % (spec_line, line.strip().split()[0])
            if hashlib.md5(ident.encode()).hexdigest() in spds:
                result['status'] = 'found'
                parts = ident.split()
                item = {
                    'src_range': parts[0],
                    'dst_range': parts[1],
                    'upperspec': parts[2],
                    'direction': parts[3],
                }
                deleted_entries.append(item)
                policy = "spddelete -n %(src_range)s %(dst_range)s %(upperspec)s -P %(direction)s;" % item
                p = subprocess.run(['/sbin/setkey', '-c'], capture_output=True, text=True, input=policy)

            spec_line = None

    result['items'] = deleted_entries
    print(ujson.dumps(result))
