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
    def _update_rules(zoneid, address, delete=False):
        current = PF.list_table(zoneid)
        rules = ""
        for entry in current:
            if delete and entry == address:
                continue
            rules += f'match from {entry} to any label "{entry}-in"\n'
            rules += f'match from any to {entry} label "{entry}-out"\n'

        if not delete:
            rules += f'match from {address} to any label "{address}-in"\n'
            rules += f'match from any to {address} label "{address}-out"\n'

        with tempfile.NamedTemporaryFile(mode="w", delete=True) as tmp_file:
            tmp_file.write(rules)
            tmp_file.flush()

            subprocess.run(
                ['/sbin/pfctl', '-a', f'captiveportal/zone_{zoneid}', '-f', tmp_file.name],
                text=True,
                capture_output=True
            )

    @staticmethod
    def add_to_table(zoneid, address):
        subprocess.run(['/sbin/pfctl', '-t', f'__captiveportal_zone_{zoneid}', '-T', 'add', address])
        PF._update_rules(zoneid, address, delete=False)

    @staticmethod
    def remove_from_table(zoneid, address):
        subprocess.run(['/sbin/pfctl', '-t', f'__captiveportal_zone_{zoneid}', '-T', 'del', address])
        # kill associated states
        subprocess.run(['/sbin/pfctl', '-k', f'{address}'])
        PF._update_rules(zoneid, address, delete=True)

    @staticmethod
    def list_accounting_info(zoneid):
        sp = subprocess.run(['/sbin/pfctl', '-a', f'captiveportal/zone_{zoneid}', '-sr', '-v'], capture_output=True, text=True)
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
                        results.setdefault(ip, {'in_pkts': 0, 'in_bytes': 0, 'out_pkts': 0, 'out_bytes': 0})
                        results[ip][f'{stats_key}_pkts'] += stats.get('packets', 0)
                        results[ip][f'{stats_key}_bytes'] += stats.get('bytes', 0)
                prev_line = line
            elif line[0] == '['  and line.find('Evaluations') > 0:
                parts = line.strip('[ ]').replace(':', ' ').split()
                stats.update({parts[i].lower(): int(parts[i+1]) for i in range(0, len(parts)-1, 2) if parts[i+1].isdigit()})

        return results
