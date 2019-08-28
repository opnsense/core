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
    delete items from pf table
    usage : delete_table.py [tablename] [item|ALL]
"""
import tempfile
import subprocess
import os
import sys

if __name__ == '__main__':
    if len(sys.argv) > 2:
        # always validate if the item is in the pf table before trying to delete
        sp = subprocess.run(['/sbin/pfctl', '-t', sys.argv[1], '-T', 'show'], capture_output=True, text=True)
        if sys.argv[2].strip() == 'ALL':
            if len(sp.stdout.strip().split('\n')) > 0:
                # delete all entries from a pf table
                subprocess.run(['/sbin/pfctl', '-t', sys.argv[1], '-T', 'flush'], capture_output=True)
        else:
            for line in sp.stdout.strip().split('\n'):
                if line.strip() == sys.argv[2].strip():
                    subprocess.run(['/sbin/pfctl', '-t', sys.argv[1], '-T', 'delete', line.strip()],
                                    capture_output=True)
