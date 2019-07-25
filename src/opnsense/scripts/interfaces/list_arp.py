#!/usr/local/bin/python3

"""
    Copyright (c) 2016-2019 Ad Schellevis <ad@opnsense.org>
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

    --------------------------------------------------------------------------------------
    list arp table
"""
import subprocess
import os
import sys
import ujson
import netaddr

if __name__ == '__main__':
    result = []

    # import dhcp_leases (index by ip address)
    dhcp_leases = {}
    dhcp_leases_filename = '/var/dhcpd/var/db/dhcpd.leases'
    if os.path.isfile(dhcp_leases_filename):
        leases = open(dhcp_leases_filename, 'r').read()
        first_lease = leases.find('\nlease')
        if first_lease > 0:
            leases = leases[first_lease:].strip()

        for lease in leases.split('}'):
            if lease.strip().find('lease') == 0 and lease.find('{') > -1:
                dhcp_ipv4_address = lease.split('{')[0].split('lease')[1].strip()
                if lease.find('client-hostname') > -1:
                    dhcp_leases[dhcp_ipv4_address] = {'hostname': lease.split('client-hostname')[1].strip()[1:-2]}

    # parse arp output
    sp = subprocess.run(['/usr/sbin/arp', '-an'], capture_output=True, text=True)
    for line in sp.stdout.split('\n'):
        line_parts = line.split()
        if len(line_parts) > 3 and line_parts[3] != '(incomplete)':
            record = {'mac': line_parts[3],
                      'ip': line_parts[1][1:-1],
                      'intf': line_parts[5],
                      'manufacturer': '',
                      'hostname': ''
                      }
            manufacturer_mac = netaddr.EUI(record['mac'])
            try:
                record['manufacturer'] = manufacturer_mac.oui.registration().org
            except netaddr.NotRegisteredError:
                pass
            if record['ip'] in dhcp_leases:
                record['hostname'] = dhcp_leases[record['ip']]['hostname']
            result.append(record)

    # handle command line argument (type selection)
    if len(sys.argv) > 1 and sys.argv[1] == 'json':
        print(ujson.dumps(result))
    else:
        # output plain text (console)
        print('%-16s %-20s %-10s %-20s %s' % ('ip', 'mac', 'intf', 'hostname', 'manufacturer'))
        for record in result:
            print('%(ip)-16s %(mac)-20s %(intf)-10s %(hostname)-20s %(manufacturer)s' % record)
