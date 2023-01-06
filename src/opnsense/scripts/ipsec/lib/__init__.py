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
import subprocess


def list_spds(req_id=None, automatic=True):
    result = []
    setkey_text = subprocess.run(['/sbin/setkey', '-PD'], capture_output=True, text=True).stdout.strip()
    this_record = None
    line_num = 0
    for line in setkey_text.split('\n'):
        if not line.startswith('\t') and len(line) > 10:
            parts = line.replace('[', ' ').replace(']', ' ').split()
            this_record = {
                'src': parts[0],
                'dst': parts[2],
                'automatic': False,
                'reqid': None
            }
            line_num = 0
        elif this_record is None:
            continue
        elif line_num == 1:
            this_record['direction'] = line.split()[0]
        elif line.startswith('\tlifetime:') or line.find('ifname=') > 0:
            this_record['automatic'] = True
        elif line.find('/unique:') > 0:
            this_record['reqid'] = line.split('/unique:')[1].split()[0]
        elif line.startswith('\trefcnt'):
            if (automatic or not this_record['automatic']) and (req_id is None or req_id == this_record['reqid']):
                result.append(this_record)
        line_num += 1

    return result
