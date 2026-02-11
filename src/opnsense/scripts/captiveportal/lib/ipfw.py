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
import os
import subprocess
import ipaddress

class IPFW(object):
    @staticmethod
    def _is_ipv6(address):
        """Check if address is IPv6
        :param address: IP address string
        :return: True if IPv6, False if IPv4
        """
        try:
            ipaddress.IPv6Address(address)
            return True
        except (ValueError, AttributeError):
            return False

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
    def add_accounting(address):
        """ add ip address for accounting
        :param address: ip address
        :return: added or known rule number
        """
        # search for unused rule number
        acc_info = IPFW.list_accounting_info()
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
                proto = 'ip6' if IPFW._is_ipv6(address) else 'ip'
                subprocess.run(['/sbin/ipfw', 'add', str(new_rule_id), 'count', proto, 'from', address, 'to', 'any'],
                               capture_output=True)
                subprocess.run(['/sbin/ipfw', 'add', str(new_rule_id), 'count', proto, 'from', 'any', 'to', address],
                               capture_output=True)

                return new_rule_id
        else:
            return acc_info[address]['rule']

    @staticmethod
    def del_accounting(address):
        """ remove ip address from accounting rules
        :param address: ip address
        :return: None
        """
        acc_info = IPFW.list_accounting_info()
        if address in acc_info:
            subprocess.run(['/sbin/ipfw', 'delete', str(acc_info[address]['rule'])], capture_output=True)
