"""
    Copyright (c) 2015-2024 Ad Schellevis <ad@opnsense.org>
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
import fcntl
import ipaddress
import os
import subprocess
import ujson


class AddressParser:
    def __init__(self):
        self._addresses = {}
        self._in_network = {}

    def split_ip_port(self, addr):
        if addr not in self._addresses:
            self._addresses[addr] = {
                'port': '0'
            }
            if addr.count(':') > 1:
                # parse IPv6 address
                tmp = addr.split('[')
                self._addresses[addr]['addr'] = tmp[0]
                self._addresses[addr]['ipproto'] = 'ipv6'
                if addr.find('[') > -1:
                    self._addresses[addr]['port'] = tmp[1].split(']')[0]
            else:
                # parse IPv4 address
                tmp = addr.split(':')
                self._addresses[addr]['ipproto'] = 'ipv4'
                self._addresses[addr]['addr'] = tmp[0]
                if len(tmp) > 1:
                    self._addresses[addr]['port'] = tmp[1]

        return self._addresses[addr]

    def overlaps(self, net, addr: str):
        if net not in self._in_network:
            self._in_network[net] = {}
        if addr not in self._in_network[net]:
            self._in_network[net][addr] = net.overlaps(ipaddress.ip_network(addr))

        return self._in_network[net][addr]


def fetch_rule_labels():
    """ Generate dict with labels per rule.
        Since the output is directly related to the rules loaded by /tmp/rules.debug, we can safely cache the results
        (into /tmp/cache_rule_labels.json) until /tmp/rules.debug changes.
        :return: dict
    """
    pf_rules_file = '/tmp/rules.debug'
    fhandle = open('/tmp/cache_rule_labels.json', 'a+')
    try:
        fhandle.seek(0)
        cached_labels = ujson.loads(fhandle.read())
    except ValueError:
        cached_labels = {'labels': {}}

    pfr_mtime = os.stat(pf_rules_file).st_mtime if os.path.isfile(pf_rules_file) else 0
    if cached_labels.get('mtime', 0) != pfr_mtime or cached_labels.get('labels', None) is None:
        descriptions = dict()
        # query descriptions from active ruleset so we can search and display rule descriptions as well.
        if os.path.isfile(pf_rules_file):
            with open(pf_rules_file, "rt", encoding="utf-8") as f_in:
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
                    cached_labels['labels'][line_id] = {'rid': rid, 'descr': None}
                    if rid in descriptions:
                        cached_labels['labels'][line_id]['descr'] = descriptions[rid]

        fcntl.flock(fhandle, fcntl.LOCK_EX)
        cached_labels['mtime'] = pfr_mtime
        fhandle.seek(0)
        fhandle.truncate()
        fhandle.write(ujson.dumps(cached_labels))
        fhandle.close()

    return cached_labels['labels']


def split_filter_clauses(filter_str):
    filter_clauses = []
    filter_net_clauses = []
    for filter_clause in filter_str.split():
        try:
            addr = filter_clause.strip()
            filter_port = None
            if addr.startswith('[') and addr.count(']') == 1:
                filter_port = addr.split(']')[1].split(':')[1] if addr.split(']')[1].count(':') == 1 else None
                addr = addr.split(']')[0]
            elif addr.count(':') == 1:
                filter_port = addr.split(':')[1]
                addr = addr.split(':')[0]
            filter_network = ipaddress.ip_network(addr)
            filter_net_clauses.append([filter_network, filter_port])
        except ValueError:
            filter_clauses.append(filter_clause)
    return (filter_net_clauses, filter_clauses)



def query_states(rule_label, filter_str):
    addr_parser = AddressParser()

    result = list()
    filter_net_clauses, filter_clauses = split_filter_clauses(filter_str)

    rule_labels = fetch_rule_labels()
    lines = subprocess.run(['/sbin/pfctl', '-vvs', 'state'], capture_output=True, text=True).stdout.strip().split('\n')
    record = None
    for line in lines:
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
                    elif part in [
                        'allow-opts', 'sloppy', 'no-sync', 'psync-ack', 'no-df', 'random-id', 'reassemble-tcp'
                    ]:
                        record["flags"].append(part)
            elif parts[0] == "id:":
                # XXX: in order to kill a state, we need to pass both the id and the creator, so it seeems to make
                #      sense to uniquely identify the state by the combined number
                record["id"] = "%s/%s" % (parts[1], parts[3])
                if len(parts) > 5:
                    # gateway, route-to, dup-to, reply-to option
                    rt = parts[4].rstrip(':')
                    if rt in ['route-to', 'dup-to', 'reply-to', 'gateway']:
                        record[rt] = parts[5]
                        if len(parts) > 7 and parts[7].isdigit():
                            record['rtable'] = int(parts[7])
                    elif rt == 'rtable' and  parts[5].isdigit():
                        record['rtable'] = int(parts[5])
            if rule_label != "" and record['label'].lower().find(rule_label) == -1:
                # label
                continue
            elif parts[0] == "id:" and (filter_clauses or filter_net_clauses):
                match = False
                for filter_net in filter_net_clauses:
                    try:
                        match = False
                        for field in ['src_addr', 'dst_addr', 'nat_addr', 'gateway']:
                            port_field = "%s_port" % field[0:3]
                            if record[field] is not None and addr_parser.overlaps(filter_net[0], record[field]):
                                if filter_net[1] is None or filter_net[1] == record[port_field]:
                                    match = True
                        if not match:
                            break
                    except:
                        continue
                if not match:
                    continue

                if filter_clauses:
                    search_line = " ".join(str(item) for item in filter(None, record.values()))
                    for filter_clause in filter_clauses:
                        if search_line.find(filter_clause) == -1:
                            match = False
                            break
                    if not match:
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
                'gateway': None,
                'iface': parts[0],
                'proto': parts[1],
                'ipproto': addr_parser.split_ip_port(parts[2])['ipproto'],
                'flags': []
            }
            if parts[3].find('(') > -1:
                # NAT enabled
                nat_record = addr_parser.split_ip_port(parts[3][1:-1])
                record['nat_addr'] = nat_record['addr']
                if nat_record['port'] != '0':
                    record['nat_port'] = nat_record['port']

            if parts[-3] == '->':
                record['direction'] = 'out'
            else:
                record['direction'] = 'in'

            tmp_parts1 = addr_parser.split_ip_port(parts[2])
            tmp_parts2 = addr_parser.split_ip_port(parts[-2])
            record['dst_addr'] = tmp_parts2['addr'] if record['direction'] == 'out' else tmp_parts1['addr']
            record['dst_port'] = tmp_parts2['port'] if record['direction'] == 'out' else tmp_parts1['port']
            record['src_addr'] = tmp_parts1['addr'] if record['direction'] == 'out' else tmp_parts2['addr']
            record['src_port'] = tmp_parts1['port'] if record['direction'] == 'out' else tmp_parts2['port']

            record['state'] = parts[-1]

    return result



def query_top():
    addr_parser = AddressParser()
    result = {
        'details': [],
        'metadata': {
            'labels': fetch_rule_labels()
        }
    }

    sp = subprocess.run(
        ['/usr/local/sbin/pftop', '-w', '1000', '-b','-v', 'long','200000'],
        capture_output=True,
        text=True
    )

    for rownum, line in enumerate(sp.stdout.strip().split('\n')):
        parts = line.strip().split()
        if rownum >= 2 and len(parts) > 5:
            record = {
                'proto': parts[0],
                'dir': parts[1].lower(),
                'src_addr': addr_parser.split_ip_port(parts[2])['addr'],
                'src_port': addr_parser.split_ip_port(parts[2])['port'],
                'dst_addr': addr_parser.split_ip_port(parts[3])['addr'],
                'dst_port': addr_parser.split_ip_port(parts[3])['port'],
                'gw_addr': None,
                'gw_port': None
            }
            if parts[4].count(':') > 2 or parts[4].count('.') > 2:
                record['gw_addr'] = addr_parser.split_ip_port(parts[4])['addr']
                record['gw_port'] = addr_parser.split_ip_port(parts[4])['port']
                idx = 5
            else:
                idx = 4

            pows =  {
                'K': 1,
                'M': 2,
                'G': 3,
            }
            time_marks =  {
                'm': 60,
                'h': 3600,
                'd': 86400,
            }
            record['state'] = parts[idx]
            record['age'] = parts[idx+1]
            record['expire'] = parts[idx+2]
            record['pkts'] = int(parts[idx+3]) if parts[idx+3].isdigit() else 0
            if parts[idx+4].isdigit():
                record['bytes'] = int(parts[idx+4])
            elif parts[idx+4][:-1].isdigit() and parts[idx+4][-1] in pows:
                record['bytes'] = int(parts[idx+4][:-1])*pow(1024, pows[parts[idx+4][-1]])
            else:
                record['bytes'] = 0
            record['avg'] = int(parts[idx+5]) if parts[idx+5].isdigit() else 0
            record['rule'] = parts[idx+6]
            for timefield in ['age', 'expire']:
                if ':' in record[timefield]:
                    tmp = record[timefield].split(':')
                    record[timefield] = int(tmp[0]) * 3600 + int(tmp[1]) * 60 + int(tmp[2]) if len(tmp) > 2 else 0
                elif record[timefield].isdigit():
                    record[timefield] = int(record[timefield])
                elif record[timefield][-1] in time_marks and record[timefield][:-1].isdigit():
                    record[timefield] = int(record[timefield][:-1])*time_marks[record[timefield][-1]]
                else:
                    record[timefield] = 0


            result['details'].append(record)

    return result
