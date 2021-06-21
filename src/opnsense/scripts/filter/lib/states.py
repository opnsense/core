"""
    Copyright (c) 2015-2021 Ad Schellevis <ad@opnsense.org>
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
import ipaddress
import os
import subprocess
import sys


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


def fetch_rule_labels():
    result = dict()
    descriptions = dict()
    # query descriptions from active ruleset so we can search and display rule descriptions as well.
    if os.path.isfile('/tmp/rules.debug'):
        with open('/tmp/rules.debug', "rt", encoding="utf-8") as f_in:
            for line in f_in:
                lbl = line.split(' label ')[-1] if line.find(' label ') > -1 else ""
                rule_label = lbl.split('"')[1] if lbl.count('"') >= 2 else None
                descriptions[rule_label] = ''.join(lbl.split('"')[2:]).strip().strip('# : ')

    sp = subprocess.run(['/sbin/pfctl', '-vvPsr'], capture_output=True, text=True)
    for line in sp.stdout.strip().split('\n'):
        if line.startswith('@'):
            line_id = line.split()[0][1:]
            if line.find(' label ') > -1:
                rid = ''.join(line.split(' label ')[-1:]).strip()[1:].split('"')[0]
                result[line_id] = {'rid': rid, 'descr': None}
                if rid in descriptions:
                    result[line_id]['descr'] = descriptions[rid]

    return result


def query_states(rule_label, filter_str):
    result = list()
    try:
        filter_network = ipaddress.ip_network(filter_str.strip())
    except ValueError:
        filter_network = None

    rule_labels = fetch_rule_labels()
    sp = subprocess.run(['/sbin/pfctl', '-vvs', 'state'], capture_output=True, text=True)
    record = None
    for line in sp.stdout.strip().split('\n'):
        parts = line.split()
        if line.startswith(" ") and len(parts) > 1 and record:
            if parts[0] == 'age':
                for part in line.split(","):
                    part = part.strip()
                    if part.startswith("rule "):
                        record["rule"] = part.split()[-1]
                        if record["rule"] in rule_labels:
                            record["label"] = rule_labels[record["rule"]]["rid"]
                            record["descr"] = rule_labels[record["rule"]]["descr"]
                    elif part.startswith("age "):
                        record["age"] = part.split()[-1]
                    elif part.startswith("expires in"):
                        record["expires"] = part.split()[-1]
                    elif part.endswith("pkts"):
                        record["pkts"] = [int(s) for s in part.split()[0].split(':')]
                    elif part.endswith("bytes"):
                        record["bytes"] = [int(s) for s in part.split()[0].split(':')]
            elif parts[0] == "id:":
                # XXX: in order to kill a state, we need to pass both the id and the creator, so it seeems to make
                #      sense to uniquely identify the state by the combined number
                record["id"] = "%s/%s" % (parts[1], parts[3])
            search_line = " ".join(str(item) for item in filter(None, record.values()))
            if rule_label != "" and record['label'].lower().find(rule_label) == -1:
                # label
                continue
            elif filter_network is not None:
                try:
                    match = False
                    for field in ['src_addr', 'dst_addr', 'nat_addr']:
                        addr = ipaddress.ip_network(record[field])
                        if field is not None and ipaddress.ip_network(filter_network).overlaps(addr):
                            match = True
                            break
                    if not match:
                        continue
                except:
                    continue
            elif filter_str != "" and search_line.lower().find(filter_str.lower()) == -1:
                # apply filter when provided
                continue

            if parts[0] == "id:":
                # append to response
                result.append(record)
        elif len(parts) >= 6:
            record = {
                'label': '',
                'descr': '',
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

    return result
