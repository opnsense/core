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
import subprocess


class ARP(object):
    def __init__(self):
        """ construct new arp helper
        :return: None
        """
        self._arp_table = dict()
        self._fetch_arp_table()

    def reload(self):
        """ reload / parse arp table
        """
        self._fetch_arp_table()

    def _fetch_arp_table(self):
        """ parse system arp table and store result in this object
        :return: None
        """
        # parse arp table
        self._arp_table = dict()
        sp = subprocess.run(['/usr/sbin/arp', '-an'], capture_output=True, text=True)
        for line in sp.stdout.split("\n"):
            line_parts = line.split()

            if len(line_parts) < 6 or line_parts[2] != 'at' or line_parts[4] != 'on':
                continue
            elif len(line_parts[1]) < 2 or line_parts[1][0] != '(' or line_parts[1][-1] != ')':
                continue

            address = line_parts[1][1:-1]
            physical_intf = line_parts[5]
            mac = line_parts[3]
            expires = -1

            for index in range(len(line_parts) - 3):
                if line_parts[index] == 'expires' and line_parts[index + 1] == 'in':
                    if line_parts[index + 2].isdigit():
                        expires = int(line_parts[index + 2])

            if address in self._arp_table:
                self._arp_table[address]['intf'].append(physical_intf)
            elif mac.find('incomplete') == -1:
                self._arp_table[address] = {'mac': mac, 'intf': [physical_intf], 'expires': expires}

    def list_items(self):
        """ return parsed arp list
        :return: dict
        """
        return self._arp_table

    def get_by_ipaddress(self, address):
        """ search arp entry by ip address
        :param address: ip address
        :return: dict or None (if not found)
        """
        if address in self._arp_table:
            return self._arp_table[address]
        else:
            return None

    def get_address_by_mac(self, address):
        """ search arp entry by mac address, most recent arp entry
        :param address: ip address
        :return: dict or None (if not found)
        """
        result = None
        for item in self._arp_table:
            if self._arp_table[item]['mac'] == address:
                if result is None:
                    result = item
                elif self._arp_table[result]['expires'] < self._arp_table[item]['expires']:
                    result = item
        return result
