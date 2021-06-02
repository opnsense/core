#!/usr/local/bin/python3

"""
    Copyright (c) 2015 Ad Schellevis <ad@opnsense.org>
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
import subprocess
import sys
import ujson
import argparse

def fetch_rule_labels():
    result = dict()
    sp = subprocess.run(['/sbin/pfctl', '-vvPsr'], capture_output=True, text=True)
    for line in sp.stdout.strip().split('\n'):
        if line.startswith('@'):
            line_id = line.split()[0][1:]
            if line.find(' label ') > -1:
                rid = ''.join(line.split(' label ')[-1:]).strip()[1:].split('"')[0]
                result[line_id] = rid
    return result

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
    # parse input arguments
    parser = argparse.ArgumentParser()
    parser.add_argument('--filter', help='filter results', default='')
    parser.add_argument('--label', help='label / rule id', default='')
    parser.add_argument('--limit', help='limit number of results', default='')
    parser.add_argument('--offset', help='offset results', default='')
    inputargs = parser.parse_args()

    rule_labels = fetch_rule_labels()
    result = {'details': [], 'total_entries': 0}
    sp = subprocess.run(['/sbin/pfctl', '-vs', 'state'], capture_output=True, text=True)
    record = None
    state_line = ''
    for line in sp.stdout.strip().split('\n'):
        parts = line.split()
        if line.startswith(" ") and len(parts) > 1 and record:
            if parts[0] == 'age':
                for part in line.split(","):
                    part = part.strip()
                    if part.startswith("rule "):
                        record["rule"] = part.split()[-1]
                        if record["rule"] in rule_labels:
                            record["label"] = rule_labels[record["rule"]]
                    elif part.startswith("age "):
                        record["age"] = part.split()[-1]
                    elif part.startswith("expires in"):
                        record["expires"] = part.split()[-1]
                    elif part.endswith("pkts"):
                        record["pkts"] = [int(s) for s in part.split()[0].split(':')]
                    elif part.endswith("bytes"):
                        record["bytes"] = [int(s) for s in part.split()[0].split(':')]
            if inputargs.label != "" and record['label'].lower().find(inputargs.label) == -1:
                # label
                continue
            elif inputargs.filter != "" and state_line.lower().find(inputargs.filter) == -1:
                # apply filter when provided
                continue
            # append to response
            result['details'].append(record)
        elif len(parts) >= 6:
            # count total number of state table entries
            result['total_entries'] += 1
            record = {
                'label': '',
                'nat_addr': None,
                'nat_port': None,
                'iface': parts[0],
                'proto': parts[1],
                'src_addr': parse_address(parts[2])['addr'],
                'src_port': parse_address(parts[2])['port'],
                'ipproto': parse_address(parts[2])['ipproto']
            }
            if parts[3].find('(') > -1:
                # NAT enabled
                record['nat_addr'] = parts[3][1:].split(':')[0]
                if parts[3].find(':') > -1:
                   record['nat_port'] = parts[3].split(':')[1][:-1]

            record['dst_addr'] = parse_address(parts[-2])['addr']
            record['dst_port'] = parse_address(parts[-2])['port']

            if parts[-3] == '->':
                record['direction'] = 'out'
            else:
                record['direction'] = 'in'

            record['state'] = parts[-1]
            state_line = line

    # apply offset and limit
    if inputargs.offset.isdigit():
        result['details'] = result['details'][int(inputargs.offset):]
    if inputargs.limit.isdigit() and len(result['details']) >= int(inputargs.limit):
        result['details'] = result['details'][:int(inputargs.limit)]

    result['total'] = len(result['details'])

    print(ujson.dumps(result))
