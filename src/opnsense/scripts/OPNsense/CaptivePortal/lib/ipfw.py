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

"""
import os
import subprocess


class IPFW(object):
    def __init__(self):
        pass

    @staticmethod
    def list_table(table_number):
        """ list ipfw table
        :param table_number: ipfw table number
        :return: dict (key value address + rule_number)
        """
        devnull = open(os.devnull, 'w')
        result = dict()
        sp = subprocess.run(['/sbin/ipfw', 'table', str(table_number), 'list'], capture_output=True, text=True)
        for line in sp.stdout.split('\n'):
            if line.split(' ')[0].strip() != "":
                parts = line.split()
                address = parts[0]
                rulenum = parts[1] if len(parts) > 1 else None
                # process / 32 nets as single addresses to align better with the rule syntax
                # and local administration.
                if address.split('/')[-1] == '32':
                    # single IPv4 address
                    result[address.split('/')[0]] = rulenum
                elif not line.startswith('-'):
                    # network
                    result[address] = rulenum

        return result

    def ip_or_net_in_table(self, table_number, address):
        """ check if address or net is in this zone's table
        :param table_number: ipfw table number to query
        :param address: ip address or net
        :return: boolean
        """
        ipfw_tbl = self.list_table(table_number)
        if address.strip() in ipfw_tbl:
            return True

        return False

    def add_to_table(self, table_number, address):
        """ add/update entry to ipfw table
        :param table_number: ipfw table number
        :param address: ip address or net to add to table
        :return:
        """
        ipfw_tbl = self.list_table(table_number)
        rule_number = str(self._add_accounting(address))
        if address not in ipfw_tbl:
            subprocess.run(['/sbin/ipfw', 'table', str(table_number), 'add', address, rule_number], capture_output=True)
        elif str(ipfw_tbl[address]) != str(table_number):
            # update table when accounting rule mismatches table entry
            subprocess.run(['/sbin/ipfw', 'table', str(table_number), 'del', address], capture_output=True)
            subprocess.run(['/sbin/ipfw', 'table', str(table_number), 'add', address, rule_number], capture_output=True)

    @staticmethod
    def delete_from_table(table_number, address):
        """ remove entry from ipfw table
        :param table_number: ipfw table number
        :param address: ip address or net to add to table
        :return:
        """
        subprocess.run(['/sbin/ipfw', 'table', str(table_number), 'delete', address], capture_output=True)

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
                if parts[7] != 'any':
                    ip_address = parts[7]
                else:
                    ip_address = parts[9]

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

    def _add_accounting(self, address):
        """ add ip address for accounting
        :param address: ip address
        :return: added or known rule number
        """
        # search for unused rule number
        acc_info = self.list_accounting_info()
        if address not in acc_info:
            rule_ids = list()
            for ip_address in acc_info:
                if acc_info[ip_address]['rule'] not in rule_ids:
                    rule_ids.append(acc_info[ip_address]['rule'])

            new_rule_id = -1
            for ruleId in range(30000, 50000):
                if ruleId not in rule_ids:
                    new_rule_id = ruleId
                    break

            # add accounting rule
            if new_rule_id != -1:
                subprocess.run(['/sbin/ipfw', 'add', str(new_rule_id), 'count', 'ip', 'from', address, 'to', 'any'],
                               capture_output=True)
                subprocess.run(['/sbin/ipfw', 'add', str(new_rule_id), 'count', 'ip', 'from', 'any', 'to', address],
                               capture_output=True)

                # end of accounting block lives at rule number 50000
                subprocess.run(
                    ['/sbin/ipfw', 'add', str(new_rule_id), 'skipto', '60000', 'ip', 'from', 'any', 'to', 'any'],
                    capture_output=True
                )

                return new_rule_id
        else:
            return acc_info[address]['rule']

    def _del_accounting(self, address):
        """ remove ip address from accounting rules
        :param address: ip address
        :return: None
        """
        acc_info = self.list_accounting_info()
        if address in acc_info:
            subprocess.run(['/sbin/ipfw', 'delete', str(acc_info[address]['rule'])], capture_output=True)

    def delete(self, table_number, address):
        """ remove entry from both ipfw table and accounting rules
        :param table_number: ipfw table number
        :param address: ip address or net to add to table
        :return:
        """
        self.delete_from_table(table_number, address)
        self._del_accounting(address)
