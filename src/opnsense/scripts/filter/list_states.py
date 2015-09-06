#!/usr/local/bin/python2.7

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

    --------------------------------------------------------------------------------------
    list pf states
"""
import tempfile
import subprocess
import os
import sys
import ujson


def parse_address(addr):
    parse_result = {'port': '0'}
    if addr.count(':') > 1:
        # parse IPv6 address
        parse_result['addr'] = addr.split('[')[0]
        parse_result['ipproto'] = 'ipv6'
        if addr.find('[') > -1:
            parse_result['port'] = addr.split('[')[1].split(']')[0]
    else:
        # parse IPv4 address
        parse_result['ipproto'] = 'ipv4'
        parse_result['addr'] = addr.split(':')[0]
        if addr.find(':') > -1:
            parse_result['port'] = addr.split(':')[1]

    return parse_result

if __name__ == '__main__':
    result = {'details': []}
    with tempfile.NamedTemporaryFile() as output_stream:
        subprocess.call(['/sbin/pfctl', '-s', 'state'], stdout=output_stream, stderr=open(os.devnull, 'wb'))
        output_stream.seek(0)
        data = output_stream.read().strip()
        if data.count('\n') > 2:
            for line in data.split('\n'):
                parts = line.split()
                if len(parts) >= 6:
                    record = dict
                    record['nat_addr'] = None
                    record['nat_port'] = None
                    record['iface'] = parts[0]
                    record['proto'] = parts[1]
                    record['src_addr'] = parse_address(parts[2])['addr']
                    record['src_port'] = parse_address(parts[2])['port']
                    record['ipproto'] = parse_address(parts[2])['ipproto']

                    if parts[3].find('(') > -1:
                        # NAT enabled
                        record['nat_addr'] = parts[3][1:].split(':')[0]
                        record['nat_port'] = parts[3].split(':')[1][:-1]

                    record['dst_addr'] = parse_address(parts[-2])['addr']
                    record['dst_port'] = parse_address(parts[-2])['port']

                    if parts[-3] == '->':
                        record['direction'] = 'out'
                    else:
                        record['direction'] = 'in'

                    record['state'] = parts[-1]

                    result['details'].append(record)

    result['total'] = len(result['details'])

    # handle command line argument (type selection)
    if len(sys.argv) > 1 and sys.argv[1] == 'json':
        print(ujson.dumps(result))
    else:
        # output plain
        print ('------------------------- STATES -------------------------')
        for state in result['details']:
            if state['ipproto'] == 'ipv4':
                if state['nat_addr'] is not None:
                    print ('%(iface)s %(proto)s %(src_addr)s:%(src_port)s \
  (%(nat_addr)s:%(nat_port)s) %(direction)s %(dst_addr)s:%(dst_port)s %(state)s' % state)
                else:
                    print ('%(iface)s %(proto)s %(src_addr)s:%(src_port)s \
  %(direction)s %(dst_addr)s:%(dst_port)s %(state)s' % state)
            else:
                print ('%(iface)s %(proto)s %(src_addr)s[%(src_port)s] \
  %(direction)s %(dst_addr)s[%(dst_port)s] %(state)s' % state)
