#!/usr/local/bin/python2.7

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
    with tempfile.NamedTemporaryFile() as output_stream:
        subprocess.call(['/usr/bin/top','-aHSn','999999'], stdout=output_stream, stderr=open(os.devnull, 'wb'))
        output_stream.seek(0)
        header = True
        for line in output_stream.read().strip().split('\n'):
            line_strip = line.strip()
            if len(line_strip) == 0:
                continue

            tmp = line_strip.split()
            if len(tmp) == 0:
                continue

            if header:
                # parse headers from top command, add to result
                result['headers'].append(line_strip)
                if tmp[0] == "Swap:":
                    header = False
                continue

            # parse details including fieldnames (leave original)
            if fieldnames is None:
                fieldnames = tmp
                continue

            record = dict()
            for field_id in range(len(fieldnames)):
                fieldname = fieldnames[field_id]
                if field_id == len(fieldnames)-1:
                    record[fieldname] = ' '.join(tmp[field_id:])
                else:
                    record[fieldname] = tmp[field_id]

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
