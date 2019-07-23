#!/usr/local/bin/python3

"""
    Copyright (c) 2016-2019 Ad Schellevis <ad@opnsense.org>
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
    returns the system routing table
"""
import subprocess
import sys
import ujson

if __name__ == '__main__':
    result = []
    fieldnames=[]
    if '-n' in sys.argv:
        resolv = 'n'
    else:
        resolv = ''
    sp = subprocess.run(['/usr/bin/netstat', '-rW' + resolv], capture_output=True, text=True)
    current_proto = ""
    for line in sp.stdout.split("\n"):
        fields = line.split()
        if len(fields) == 0:
            continue
        elif len(fields) == 1 and fields[0] == 'Internet:':
            current_proto = 'ipv4'
        elif len(fields) == 1 and  fields[0] == 'Internet6:':
            current_proto = 'ipv6'
        elif len(fields) > 2 and fields[0] == 'Destination' and fields[1] == 'Gateway':
            fieldnames = list(map(lambda x : x.lower(), fields))
        elif len(fields) > 2:
            record = {'proto': current_proto}
            for fieldid in range(len(fields)):
                if len(fieldnames) > fieldid:
                    record[fieldnames[fieldid]] = fields[fieldid]
            # space out missing fields
            for fieldname in fieldnames:
                if fieldname not in record:
                    record[fieldname] = ""
            result.append(record)

    # handle command line argument (type selection)
    if len(sys.argv) > 1 and 'json' in sys.argv:
        print(ujson.dumps(result))
    else:
        # output plain
        print ('\t\t'.join(fieldnames))
        frmt = "%(proto)s\t"
        for fieldname in fieldnames:
            frmt = frmt + "%("+fieldname+")s\t"
        for record in result:
            print (frmt%record)
