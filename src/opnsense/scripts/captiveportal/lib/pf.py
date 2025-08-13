"""
    Copyright (c) 2025 Deciso B.V.
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
import tempfile
import time

class PF(object):
    def __init__(self):
        pass

    @staticmethod
    def list_table(zoneid):
        pfctl_cmd = ['/sbin/pfctl', '-t', f'__captiveportal_zone_{zoneid}', '-T', 'show']
        for line in subprocess.run(pfctl_cmd, capture_output=True, text=True).stdout.split('\n'):
            # split('\n') on an empty string will return an empty string, we need to make sure to suppress these
            tmp = line.strip()
            if len(tmp) > 0:
                yield tmp

    @staticmethod
    def add_to_table(zoneid, address):
        subprocess.run(['/sbin/pfctl', '-t', f'__captiveportal_zone_{zoneid}', '-T', 'add', address], capture_output=True)

    @staticmethod
    def remove_from_table(zoneid, address):
        subprocess.run(['/sbin/pfctl', '-t', f'__captiveportal_zone_{zoneid}', '-T', 'del', address], capture_output=True)
        # kill associated states to and from this host
        subprocess.run(['/sbin/pfctl', '-k', f'{address}'], capture_output=True)
        subprocess.run(['/sbin/pfctl', '-k', '0.0.0.0/0', '-k', f'{address}'], capture_output=True)

    @staticmethod
    def sync_accounting(zoneid):
        rules = ''
        for entry in PF.list_table(zoneid):
            rules += f'ether pass in quick proto {{ 0x0800 }} l3 from {entry} to any label "{entry}-in"\n'
            rules += f'ether pass out quick proto {{ 0x0800 }} l3 from any to {entry} label "{entry}-out"\n'

        with tempfile.NamedTemporaryFile(mode="w", delete=True) as tmp_file:
            tmp_file.write(rules)
            tmp_file.flush()

            subprocess.run(
                ['/sbin/pfctl', '-a', f'captiveportal_zone_{zoneid}', '-f', tmp_file.name],
                text=True,
                capture_output=True
            )

    @staticmethod
    def list_accounting_info(zoneid):
        sp = subprocess.run(['/sbin/pfctl', '-a', f'captiveportal_zone_{zoneid}', '-vvse'], capture_output=True, text=True)
        results = {}
        stats = {}
        prev_line = ''

        for line in sp.stdout.split('\n'):
            line = line.strip()
            if not line or line[0] != '[':
                if prev_line.find(' label ') > -1:
                    lbl = prev_line.split(' label ')[-1]
                    if '"' in lbl and lbl.count('"') >= 2:
                        ip, out_flag = lbl.split('"')[1].split('-')
                        out = (out_flag == 'out')
                        stats_key = ('out' if out else 'in')
                        results.setdefault(ip, {'in_pkts': 0, 'in_bytes': 0, 'out_pkts': 0, 'out_bytes': 0, 'in_last_accessed': 0, 'out_last_accessed': 0})
                        results[ip][f'{stats_key}_pkts'] += stats.get('packets', 0)
                        results[ip][f'{stats_key}_bytes'] += stats.get('bytes', 0)
                        results[ip][f'{stats_key}_last_accessed'] = stats.get('last_accessed', 0)
                prev_line = line
            elif line[0] == '['  and line.find('Evaluations') > 0:
                parts = line.strip('[ ]').replace(':', ' ').split()
                stats.update({parts[i].lower(): int(parts[i+1]) for i in range(0, len(parts)-1, 2) if parts[i+1].isdigit()})
            elif line[0] == '[' and line.find('Last Active Time') > 0:
                date_str = line.strip('[ ]').split('Time:')[1].strip()
                stats.update({'last_accessed': 0 if date_str == 'N/A' else int(time.mktime(time.strptime(date_str, "%a %b %d %H:%M:%S %Y")))})

        return results
