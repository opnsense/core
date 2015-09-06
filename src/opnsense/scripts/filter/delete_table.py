#!/usr/local/bin/python2.7

"""
    Copyright (c) 2015 Ad Schellevis
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
    delete items from pf table
    usage : delete_table.py [tablename] [item|ALL]
"""
import tempfile
import subprocess
import os
import sys

if __name__ == '__main__':
    result = []
    if len(sys.argv) > 2:
        # always validate if the item is in the pf table before trying to delete
        with tempfile.NamedTemporaryFile() as output_stream:
            # delete an entry from a pf table
            subprocess.call(['/sbin/pfctl', '-t', sys.argv[1], '-T', 'show'],
                            stdout=output_stream, stderr=open(os.devnull, 'wb'))
            output_stream.seek(0)
            if sys.argv[2].strip() == 'ALL':
                if len(output_stream.read().strip().split('\n')) > 0:
                    # delete all entries from a pf table
                    subprocess.call(['/sbin/pfctl', '-t', sys.argv[1], '-T', 'flush'],
                                    stdout=output_stream, stderr=open(os.devnull, 'wb'))
            else:
                for line in output_stream.read().strip().split('\n'):
                    if line.strip() == sys.argv[2].strip():
                        result = []
                        subprocess.call(['/sbin/pfctl', '-t', sys.argv[1], '-T', 'delete', line.strip()],
                                        stdout=open(os.devnull, 'wb'), stderr=open(os.devnull, 'wb'))
