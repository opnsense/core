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
    list known mac prefixes
"""

import os.path
import sys
import ujson
import importlib.util

if __name__ == '__main__':
    result=dict()
    oui_registry_file = "%s/eui/oui.txt" % os.path.dirname(importlib.util.find_spec('netaddr').origin)
    if os.path.isfile(oui_registry_file):
        for line in open(oui_registry_file, 'rb'):
            line = line.decode()
            if line.find('(base 16)') > -1:
                parts=line.split('(base 16)')
                if len(parts) >= 2:
                    result[parts[0].strip()] = parts[1].strip()

    if len(sys.argv) > 1 and sys.argv[1] == 'json':
        # output json
        print(ujson.dumps(result))
    else:
        # output plain text (console)
        print ('%-6s %s' % ('prefix', 'manufacturer'))
        for mac_prefix in result:
            print ('%s %s' % (mac_prefix, result[mac_prefix]))
