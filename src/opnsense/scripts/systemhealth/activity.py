#!/usr/local/bin/python3

"""
    Copyright (c) 2015-2019 Ad Schellevis <ad@opnsense.org>
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
    returns system activity (top)
"""
import collections
import tempfile
import subprocess
import os
import sys
import ujson

if __name__ == '__main__':
    fieldnames = None
    field_max_width = dict()
    result = {'headers': [], 'details': []}
    is_header = True
    tidpid = dict()
    for line in subprocess.run(['/usr/bin/procstat','-ath'], capture_output=True, text=True).stdout.split('\n'):
        parts = line.split(maxsplit=2)
        if len(parts) > 1:
            tidpid[parts[1]] = parts[0]
    # grab second display so that CPU time data appears
    sp = subprocess.run(['/usr/bin/top','-aHSTn','-d2','999999'], capture_output=True, text=True)
    topData = sp.stdout.strip().split('\n\n',2)[-1]
    for line in topData.split('\n'):
        # end of header, start of top detection
        if line.find('USERNAME') > -1 and line.find('COMMAND') > -1:
            is_header = False
        if is_header:
            # parse headers from top command, add to result
            if len(line.strip()) > 0:
                result['headers'].append(line)
        else:
            # parse details including fieldnames (leave original)
            if fieldnames is None:
                fieldnames = ['PID'] + line.split()
            else:
                tmp = line.split(maxsplit=10)
                record = {'C': '0'}
                for field_id in range(len(fieldnames)):
                    fieldname = fieldnames[field_id]
                    if field_id == 0:  # PID
                        record[fieldname] = tidpid[tmp[0]] if tmp[0] in tidpid else ''
                    else:
                        record[fieldname] = tmp[field_id - 1]

                    if fieldname not in field_max_width or field_max_width[fieldname] < len(record[fieldname]):
                        field_max_width[fieldname] = len(record[fieldname])
                result['details'].append(record)

    if len(sys.argv) > 1 and sys.argv[1] == 'json':
        # output as json
        print(ujson.dumps(result))
    else:
        # output plain (reconstruct data)
        for header_line in result['headers']:
            print (header_line)
        print ("\n")
        if fieldnames is not None:
            format_str = ""
            header_fields = {}
            for fieldname in fieldnames:
                format_str = '%s %%(%s)-%ds'%(format_str,fieldname, field_max_width[fieldname]+1)
                header_fields[fieldname] = fieldname

            print (format_str % header_fields)
            for detail_line in result['details']:
                print (format_str % detail_line)
