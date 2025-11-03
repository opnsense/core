#!/usr/local/bin/python3

"""
    Copyright (c) 2025 Ad Schellevis <ad@opnsense.org>
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
import argparse
import os
import subprocess
import ujson
import sqlite3


def is_hostwatch_enabled(rc_file):
    if os.path.exists(rc_file):
        return open(rc_file,'r').read().find('hostwatch_enable="YES"') > -1
    return False


if __name__ == '__main__':
    parser = argparse.ArgumentParser(
        description='Dump known host entries',
        epilog='Outputs a list of entries containing [ifname, ether, ip_address <,vendor>]'
    )
    parser.add_argument('-d', '--discover', help='Disable host discovery data', action="store_false", default=True)
    parser.add_argument('-p', '--proto', nargs='+', default=['inet', 'inet6'], choices=['inet', 'inet6'])
    parser.add_argument(
        '-v', '--verbose', help='Verbose output (including vendors)', action="store_true", default=False
    )
    parser.add_argument('--rc_file', help='hostwatch rc(8) config filename', default='/etc/rc.conf.d/hostwatch')
    parser.add_argument('--db_file', help='hostwatch sqlite3 database', default='/var/db/hostwatch/hosts.db')
    inputargs = parser.parse_args()

    result = {'source': None, 'rows': []}
    if inputargs.discover and is_hostwatch_enabled(inputargs.rc_file):
        # use host discovery data, query readonly
        result['source'] = 'discovery'
        con = sqlite3.connect("file:%s?mode=ro" % inputargs.db_file, uri=True)
        con.row_factory = sqlite3.Row
        for row in con.execute(
            'select * from v_hosts where protocol in (?, ?)', (inputargs.proto[0], inputargs.proto[-1])
        ):
            record = [row['interface_name'], row['ether_address'], row['ip_address']]
            if inputargs.verbose:
                record.append(row['organization_name'])
            result['rows'].append(record)
    else:
        result['source'] = 'arp-ndp'
        # use arp/ndp
        if inputargs.verbose:
            from lib import OUI
        if 'inet' in inputargs.proto:
            sp = subprocess.run(['/usr/sbin/arp', '-an', '--libxo', 'json'], capture_output=True, text=True)
            libxo_out = ujson.loads(sp.stdout)
            arp_cache = libxo_out['arp']['arp-cache'] if 'arp' in libxo_out and 'arp-cache' in libxo_out['arp'] else []
            for row in arp_cache:
                if 'mac-address' in row:
                    record = [row['interface'], row['mac-address'], row['ip-address']]
                    if inputargs.verbose:
                        record.append(OUI().get_vendor(row['mac-address'], ''))
                    result['rows'].append(record)
        if 'inet6' in inputargs.proto:
            sp = subprocess.run(['/usr/sbin/ndp', '-an'], capture_output=True, text=True)
            for line in sp.stdout.split('\n')[1:]:
                line_parts = line.split()
                if len(line_parts) > 3 and line_parts[1] != '(incomplete)':
                    record = [line_parts[2], line_parts[1], line_parts[0].split('%', 1)[0]]
                    if inputargs.verbose:
                        record.append(OUI().get_vendor(line_parts[1], ''))
                    result['rows'].append(record)
    print(ujson.dumps(result))
