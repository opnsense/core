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

"""
import os
import tempfile
import subprocess


class IPFW(object):
    def __init__(self):
        pass

    @staticmethod
    def list_table(table_number):
        """ list ipfw table
        :param table_number: ipfw table number
        :return: list
        """
        devnull = open(os.devnull, 'w')
        result = list()
        with tempfile.NamedTemporaryFile() as output_stream:
            subprocess.check_call(['/sbin/ipfw', 'table', table_number, 'list'],
                                  stdout=output_stream,
                                  stderr=devnull)
            output_stream.seek(0)
            for line in output_stream.read().split('\n'):
                result.append(line.split(' ')[0])
            return result

    def ip_or_net_in_table(self, table_number, address):
        """ check if address or net is in this zone's table
        :param table_number: ipfw table number to query
        :param address: ip address or net
        :return: boolean
        """
        ipfw_tbl = self.list_table(table_number)
        if address.find('.') > -1 and address.find('/') == -1:
            # address given, search for /32 net in ipfw rules
            if '%s/32' % address.strip() in ipfw_tbl:
                return True
        elif address.strip() in ipfw_tbl:
            return True

        return False

    @staticmethod
    def add_to_table(table_number, address):
        """ add new entry to ipfw table
        :param table_number: ipfw table number
        :param address: ip address or net to add to table
        :return:
        """
        devnull = open(os.devnull, 'w')
        subprocess.call(['/sbin/ipfw', 'table', table_number, 'add', address], stdout=devnull, stderr=devnull)

    @staticmethod
    def delete_from_table(table_number, address):
        """ remove entry from ipfw table
        :param table_number: ipfw table number
        :param address: ip address or net to add to table
        :return:
        """
        devnull = open(os.devnull, 'w')
        subprocess.call(['/sbin/ipfw', 'table', table_number, 'delete', address], stdout=devnull, stderr=devnull)

    @staticmethod
    def list_accounting_info():
        """ list accounting info per ip addres, addresses can't overlap in zone's so we just output all we know here
        instead of trying to map addresses back to zones.
        :return: list accounting info per ip address
        """
        devnull = open(os.devnull, 'w')
        result = dict()
        with tempfile.NamedTemporaryFile() as output_stream:
            subprocess.check_call(['/sbin/ipfw', '-aT', 'list'],
                                  stdout=output_stream,
                                  stderr=devnull)
            output_stream.seek(0)
            for line in output_stream.read().split('\n'):
                parts = line.split()
                if len(parts) > 5:
                    if 30001 <= int(parts[0]) <= 50000 and parts[4] == 'count':
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

    def add_accounting(self, address):
        """ add ip address for accounting
        :param address: ip address
        :return: None
        """
        # search for unused rule number
        acc_info = self.list_accounting_info()
        if address not in acc_info:
            rule_ids = list()
            for ip_address in acc_info:
                if acc_info[ip_address]['rule'] not in rule_ids:
                    rule_ids.append(acc_info[ip_address]['rule'])

            new_rule_id = -1
            for ruleId in range(30001, 50000):
                if ruleId not in rule_ids:
                    new_rule_id = ruleId
                    break

            # add accounting rule
            if new_rule_id != -1:
                devnull = open(os.devnull, 'w')
                subprocess.call(['/sbin/ipfw', 'add', str(new_rule_id), 'count', 'ip', 'from', address, 'to', 'any'],
                                stdout=devnull, stderr=devnull)
                subprocess.call(['/sbin/ipfw', 'add', str(new_rule_id), 'count', 'ip', 'from', 'any', 'to', address],
                                stdout=devnull, stderr=devnull)

    def del_accounting(self, address):
        """ remove ip address from accounting rules
        :param address: ip address
        :return: None
        """
        acc_info = self.list_accounting_info()
        if address in acc_info:
            devnull = open(os.devnull, 'w')
            subprocess.call(['/sbin/ipfw', 'delete', str(acc_info[address]['rule'])],
                            stdout=devnull, stderr=devnull)
