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
    list ndp table
"""
import subprocess
import os
import sys
import ujson
import netaddr

if __name__ == '__main__':
    # index mac database (shipped with netaddr)
    macdb = dict()
    with open("%s/eui/oui.txt" % os.path.dirname(netaddr.__file__)) as fh_macdb:
        for line in fh_macdb:
            if line[11:].startswith('(hex)'):
                macprefix = line[0:8].replace('-', ':').lower()
                macdb[macprefix] = line[18:].strip()

    result = []
    # parse ndp output
    sp = subprocess.run(['/usr/sbin/ndp', '-an'], capture_output=True, text=True)
    for line in sp.stdout.split('\n')[1:]:
        line_parts = line.split()
        if len(line_parts) > 3 and line_parts[1] != '(incomplete)':
            record = {'mac': line_parts[1],
                      'ip': line_parts[0],
                      'intf': line_parts[2],
                      'manufacturer': ''
                      }
            if record['mac'][0:8] in macdb:
                record['manufacturer'] = macdb[record['mac'][0:8]]
            result.append(record)

    # handle command line argument (type selection)
    if len(sys.argv) > 1 and sys.argv[1] == 'json':
        print(ujson.dumps(result))
    else:
        # output plain text (console)
        print ('%-40s %-20s %-10s %s' % ('ip', 'mac', 'intf', 'manufacturer'))
        for record in result:
            print ('%(ip)-40s %(mac)-20s %(intf)-10s %(manufacturer)s' % record)
