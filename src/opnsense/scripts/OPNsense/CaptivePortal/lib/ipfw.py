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

    def list_table(self, table_number):
        """ list ipfw table
        :param table_number: ipfw table number
        :return: list
        """
        DEVNULL = open(os.devnull, 'w')
        result = list()
        with tempfile.NamedTemporaryFile() as output_stream:
            subprocess.check_call(['/sbin/ipfw','table', table_number, 'list'],
                                  stdout=output_stream,
                                  stderr=DEVNULL)
            output_stream.seek(0)
            for line in output_stream.read().split('\n'):
                result.append(line.split(' ')[0])
            return result

    def ip_or_net_in_table(self, table_number,  address):
        """ check if address or net is in this zone's table
        :param table_number: ipfw table number to query
        :param address: ip address or net
        :return: boolean
        """
        ipfw_tbl = self.list_table(table_number)
        if address.find('.') > -1 and address.find('/') == -1:
            # address given, search for /32 net in ipfw rules
            if '%s/32'%address.strip() in ipfw_tbl:
                return True
        elif address.strip() in ipfw_tbl:
            return True

        return False

    def add_to_table(self, table_number, address):
        """ add new entry to ipfw table
        :param table_number: ipfw table number
        :param address: ip address or net to add to table
        :return:
        """
        DEVNULL = open(os.devnull, 'w')
        subprocess.call(['/sbin/ipfw', 'table', table_number, 'add', address], stdout=DEVNULL, stderr=DEVNULL)


    def delete_from_table(self, table_number, address):
        """ remove entry from ipfw table
        :param table_number: ipfw table number
        :param address: ip address or net to add to table
        :return:
        """
        DEVNULL = open(os.devnull, 'w')
        subprocess.call(['/sbin/ipfw', 'table', table_number, 'delete', address], stdout=DEVNULL, stderr=DEVNULL)
