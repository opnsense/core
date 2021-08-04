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
    returns the stats for pf table load and errors (optional as a json container)
    usage : pfrecords.py [optional|json]
"""
import collections
import subprocess
import sys
import os
import ujson

if __name__ == '__main__':
    result = {'status': 'ok'}
    sp = subprocess.run(['/sbin/pfctl', '-vvsT'], capture_output=True, text=True)
    tables_count = 0
    for line in sp.stdout.strip().split('\n'):
        if "Addresses:" in line:
            table_count = int("".join(filter(str.isdigit, line)))
            tables_count += table_count
    result['overall_count'] = tables_count

    sp = subprocess.run(['/sbin/pfctl', '-sm'], capture_output=True, text=True)
    for line in sp.stdout.strip().split('\n'):
        if "table-entries" in line:
            entries_limit = int("".join(filter(str.isdigit, line)))
    result['table_entries'] = entries_limit
    result['errors'] = len(open('/var/db/aliastables/alias_errs.log').readlines(  ));
    if os.stat('/var/db/aliastables/alias_errs.log').st_size != 0:
        extra_count = 0
        err_file = open('/var/db/aliastables/alias_errs.log', 'r')
        for err_line in err_file:
            extra_count += int(err_line.split(',')[2])
        result['overall_count'] += extra_count 

    # handle command line argument (type selection)
    if len(sys.argv) > 1 and sys.argv[1] == 'json':
        print(ujson.dumps(result))
    else:
        # output plain
        print (result['overall_count'], entries_limit, result['errors'])
