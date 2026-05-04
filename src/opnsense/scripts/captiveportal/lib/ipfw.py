"""
    Copyright (c) 2025-2026 Deciso B.V.
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

"""
import subprocess

class IPFW(object):
    @staticmethod
    def list_table(table_number):
        """ list ipfw table
        :param table_number: ipfw table number
        :return: dict (key value address + rule_number)
        """
        result = dict()
        sp = subprocess.run(['/sbin/ipfw', 'table', str(table_number), 'list'], capture_output=True, text=True)
        for line in sp.stdout.split('\n'):
            if line.split(' ')[0].strip() != "":
                parts = line.split()
                address = parts[0]
                rulenum = parts[1] if len(parts) > 1 else None
                # process /32 and /128 nets as single addresses to align better with the rule syntax
                # and local administration.
                prefix = address.split('/')[-1]
                if prefix == '32' or prefix == '128':
                    # single IP address
                    result[address.split('/')[0]] = rulenum
                elif not line.startswith('-'):
                    # network
                    result[address] = rulenum

        return result

    @staticmethod
    def list_accounting_info():
        """ list accounting info per ip address, addresses can't overlap in zone's so we just output all we know here
        instead of trying to map addresses back to zones.
        :return: list accounting info per ip address
        """
        result = dict()
        sp = subprocess.run(['/sbin/ipfw', '-aT', 'list'], capture_output=True, text=True)
        for line in sp.stdout.split('\n'):
            parts = line.split()
            if len(parts) > 5 and 30000 <= int(parts[0]) <= 50000 and parts[4] == 'count':
                line_pkts = int(parts[1])
                line_bytes = int(parts[2])
                last_accessed = int(parts[3])
                ip_address = parts[7] if parts[7] != 'any' else parts[9]

                if ip_address not in result:
                    result[ip_address] = {'rule': int(parts[0]),
                                          'last_accessed': 0,
                                          'in_pkts': 0,
                                          'in_bytes': 0,
                                          'out_pkts': 0,
                                          'out_bytes': 0
                                          }
                result[ip_address]['last_accessed'] = max(result[ip_address]['last_accessed'],
                                                          last_accessed)
                if parts[7] != 'any':
                    # count input
                    result[ip_address]['in_pkts'] = line_pkts
                    result[ip_address]['in_bytes'] = line_bytes
                else:
                    # count output
                    result[ip_address]['out_pkts'] = line_pkts
                    result[ip_address]['out_bytes'] = line_bytes

        return result

    @staticmethod
    def add_accounting(table_number, addresses):
        """ add ip addresses for accounting
        this function assumes all addresses passed belong to the same client and will
        therefore assign the same rule number to these addresses.
        :param address: ip address
        :return: added or known rule number
        """
        ipfw_tbl = IPFW.list_table(table_number)
        acc_info = IPFW.list_accounting_info()

        a_present = set(addresses) & acc_info.keys()
        a_missing = set(addresses) - acc_info.keys()
        present_rules = sorted([(acc_info[address]['rule'], address) for address in a_present])
        first = present_rules[0] if present_rules else None
        rule_number = None

        if first is not None:
            # Re-use this rule number, check if the rest use this rule number.
            # If not, delete those and add to the missing set to sync
            rule_number = first[0]
            rest = present_rules[1:]
            for r_rulenr, addr in rest:
                if r_rulenr != rule_number:
                    IPFW.del_accounting(table_number, addr)
                    a_missing.add(addr)
        else:
            # find unused rule number
            rule_ids = list()
            for ip_address in acc_info:
                if acc_info[ip_address]['rule'] not in rule_ids:
                    rule_ids.append(acc_info[ip_address]['rule'])
            new_rule_id = -1
            for ruleId in range(30000, 50000):
                if ruleId not in rule_ids:
                    new_rule_id = ruleId
                    break
            if new_rule_id != -1:
                rule_number = new_rule_id
        
        if rule_number is not None:
            for address in a_missing:
                subprocess.run(['/sbin/ipfw', 'add', str(rule_number), 'count', 'ip', 'from', address, 'to', 'any'],
                            capture_output=True)
                subprocess.run(['/sbin/ipfw', 'add', str(rule_number), 'count', 'ip', 'from', 'any', 'to', address],
                            capture_output=True)

                if address not in ipfw_tbl:
                    subprocess.run(['/sbin/ipfw', 'table', str(table_number), 'add', address, str(rule_number)], capture_output=True)
                elif str(ipfw_tbl[address] != str(rule_number)):
                    # update table when accounting rule mismatches table entry
                    subprocess.run(['/sbin/ipfw', 'table', str(table_number), 'del', address], capture_output=True)
                    subprocess.run(['/sbin/ipfw', 'table', str(table_number), 'add', address, str(rule_number)], capture_output=True)
            if len(a_missing) > 0:
                # end of accounting block lives at rule number 50000
                subprocess.run(['/sbin/ipfw', 'add', str(rule_number), 'skipto', '60000', 'ip', 'from', 'any', 'to', 'any'], capture_output=True)

    @staticmethod
    def del_accounting(table_number, address):
        """ remove ip address from accounting rules
        :param address: ip address
        :return: None
        """
        acc_info = IPFW.list_accounting_info()

        if address in acc_info:
            subprocess.run(['/sbin/ipfw', 'delete', str(acc_info[address]['rule'])], capture_output=True)
        
        # no-op if address not in table
        subprocess.run(['/sbin/ipfw', 'table', str(table_number), 'del', address], capture_output=True)
