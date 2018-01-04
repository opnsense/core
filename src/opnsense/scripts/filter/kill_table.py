#!/usr/local/bin/python2.7

"""
    Copyright (c) 2016 Ad Schellevis <ad@opnsense.org>
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
    drop an existing pf alias table
"""
import tempfile
import subprocess
import os
import sys
import ujson

if __name__ == '__main__':
    with tempfile.NamedTemporaryFile() as output_stream:
        subprocess.call(['/sbin/pfctl', '-sT'], stdout=output_stream, stderr=open(os.devnull, 'wb'))
        output_stream.seek(0)
        tables = list()
        for line in output_stream.read().strip().split('\n'):
            tables.append(line.strip())
        # only try to remove alias if it exists
        if len(sys.argv) > 1 and sys.argv[1] in tables:
            # cleanup related alias file
            for suffix in  ['txt', 'md5.txt', 'self.txt']:
                if os.path.isfile('/var/db/aliastables/%s.%s' % (sys.argv[1], suffix)):
                    os.remove('/var/db/aliastables/%s.%s' % (sys.argv[1], suffix))
            subprocess.call(['/sbin/pfctl', '-t', sys.argv[1], '-T', 'kill'], stdout=open(os.devnull, 'wb'), stderr=open(os.devnull, 'wb'))
            # all good, exit 0
            sys.exit(0)

# not found (or other issue)
sys.exit(-1)
