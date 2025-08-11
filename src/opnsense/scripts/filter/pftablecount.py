#!/usr/local/bin/python3

"""
    Copyright (c) 2021 Deciso B.V.
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
    returns pf table allocated and reserved size
"""
import subprocess
import os
import ujson
from datetime import datetime


if __name__ == '__main__':
    result = {
        'status': 'ok',
        'size': 0,
        'used': 0,
        'details': {}
    }
    tables_count = 0
    table_name = None
    for line in subprocess.run(['/sbin/pfctl', '-vvsT'], capture_output=True, text=True).stdout.strip().split('\n'):
        parts = line.strip().split()
        digits = [int(x) for x in parts if x.isdigit()]
        if "-" in parts[0]:
            table_name = line.split()[1].strip()
            result['details'][table_name] = {}
        elif table_name is None and len(digits) == 2:
            continue
        elif parts[0] == 'Evaluations:':
            result['details'][table_name]['eval_nomatch'] = digits[0]
            result['details'][table_name]['eval_match'] = digits[1]
        elif parts[0] == 'In/Block:':
            result['details'][table_name]['in_block_p'] = digits[0]
            result['details'][table_name]['in_block_b'] = digits[1]
        elif parts[0] == 'In/Pass:':
            result['details'][table_name]['in_pass_p'] = digits[0]
            result['details'][table_name]['in_pass_b'] = digits[1]
        elif parts[0] == 'Out/Block:':
            result['details'][table_name]['out_block_p'] = digits[0]
            result['details'][table_name]['out_block_b'] = digits[1]
        elif parts[0] == 'Out/Pass:':
            result['details'][table_name]['out_pass_p'] = digits[0]
            result['details'][table_name]['out_pass_b'] = digits[1]
        elif parts[0] ==  'Addresses:':
            table_size = digits[0]
            table_updated = None
            filename = "/var/db/aliastables/%s.txt" % table_name
            if os.path.isfile(filename):
                tmp = open(filename).read()
                planned_size  = tmp.count('\n') + 1 if len(tmp) > 0 else 0
                # if planned size doesn't fit the table, make sure we report intented size
                # used size can be divert a bit if pfctl optimizes as well.
                table_size = max(planned_size, table_size)
                table_updated = datetime.fromtimestamp(os.path.getmtime(filename)).isoformat()

            result['details'][table_name]['count'] = table_size
            result['details'][table_name]['updated'] = table_updated
            result['used'] += table_size

    for line in subprocess.run(['/sbin/pfctl', '-sm'], capture_output=True, text=True).stdout.strip().split('\n'):
        if "table-entries" in line:
            result['size'] = int("".join(filter(str.isdigit, line)))

    print(ujson.dumps(result))
