"""
    Copyright (c) 2023 Ad Schellevis <ad@opnsense.org>
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

"""

import subprocess

class PF:

    @staticmethod
    def list_tables():
        current_table = None
        current_info = {}
        for line in subprocess.run(['/sbin/pfctl', '-vvsT'], capture_output=True, text=True).stdout.strip().split('\n'):
            parts = line.strip().split()
            if len(parts) < 2:
                continue
            elif line.startswith("\t"):
                if parts[0] == 'Addresses:' and parts[1].isdigit():
                    current_info['addresses'] = int(parts[1])
            else:
                if current_table is not None:
                    yield current_table, current_info
                current_table  = parts[1]
                current_info = {}

        if current_table is not None:
            yield current_table, current_info

    @staticmethod
    def list_table(table_name):
        pfctl_cmd = ['/sbin/pfctl', '-t', table_name, '-T', 'show']
        for line in subprocess.run(pfctl_cmd, capture_output=True, text=True).stdout.split('\n'):
            # split('\n') on an empty string will return an empty string, we need to make sure to suppress these
            tmp = line.strip()
            if len(tmp) > 0:
                yield tmp

    @staticmethod
    def flush_network(table_name, ifname):
        subprocess.run(['/sbin/pfctl', '-t', table_name, '-T', 'replace', '%s:network' % ifname], capture_output=True)

    @staticmethod
    def flush(table_name):
        subprocess.run(['/sbin/pfctl', '-t', table_name, '-T', 'flush'], capture_output=True)

    @staticmethod
    def replace(table_name, filename):
        sp = subprocess.run(
            ['/sbin/pfctl', '-t', table_name, '-T', 'replace', '-f', filename],
            capture_output=True,
            text=True
        )
        return sp.stderr.strip()

    @staticmethod
    def remove(table_name):
        subprocess.run(['/sbin/pfctl', '-t', table_name, '-T', 'kill'], capture_output=True)
