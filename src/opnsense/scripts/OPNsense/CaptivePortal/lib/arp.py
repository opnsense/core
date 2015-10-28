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
import tempfile
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
        with tempfile.NamedTemporaryFile() as output_stream:
            subprocess.check_call(['/usr/sbin/arp', '-an'], stdout=output_stream, stderr=subprocess.STDOUT)
            output_stream.seek(0)
            for line in output_stream.read().split('\n'):
                if line.find('(') > -1 and line.find(')') > -1:
                    if line.find('expires in') > -1:
                        expires = line.split('expires in')[1:][0].strip().split(' ')[0]
                        if expires.isdigit():
                            expires = int(expires)
                        else:
                            expires = -1
                    else:
                        expires = -1
                    address = line.split(')')[0].split('(')[-1]
                    mac = line.split('at')[-1].split('on')[0].strip()
                    physical_intf = line.split('on')[-1].strip().split(' ')[0]
                    if address in self._arp_table:
                        self._arp_table[address]['intf'].append(physical_intf)
                    else:
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
