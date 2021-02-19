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
sys.path.insert(0, "/usr/local/opnsense/site-python")
import watchers.dhcpd

if __name__ == '__main__':
    # index mac database (shipped with netaddr)
    macdb = dict()
    with open("%s/eui/oui.txt" % os.path.dirname(netaddr.__file__)) as fh_macdb:
        for line in fh_macdb:
            if line[11:].startswith('(hex)'):
                macprefix = line[0:8].replace('-', ':').lower()
                macdb[macprefix] = line[18:].strip()

    result = []

    # import dhcp_leases (index by ip address)
    dhcp_leases = {}
    dhcpdleases = watchers.dhcpd.DHCPDLease()
    for lease in dhcpdleases.watch():
        if 'client-hostname' in lease and 'address' in lease:
            dhcp_leases[lease['address']]  = {'hostname': lease['client-hostname']}

    # parse arp output
    sp = subprocess.run(['/usr/sbin/arp', '-an', '--libxo','json'], capture_output=True, text=True)
    libxo_out = ujson.loads(sp.stdout)
    arp_cache = libxo_out['arp']['arp-cache'] if 'arp' in libxo_out and 'arp-cache' in libxo_out['arp'] else []
    for src_record in arp_cache:
        if 'incomplete' in src_record and src_record['incomplete'] is True:
            continue
        record = {
            'mac': src_record['mac-address'],
            'ip': src_record['ip-address'],
            'intf': src_record['interface'],
            'expired': src_record['expired'] if 'expired' in src_record else False,
            'expires': src_record['expires'] if 'expires' in src_record else -1,
            'permanent': src_record['permanent'] if 'permanent' in src_record else False,
            'type': src_record['type'],
            'manufacturer': '',
            'hostname': ''
        }
        if record['mac'][0:8] in macdb:
            record['manufacturer'] = macdb[record['mac'][0:8]]
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
