"""
    Copyright (c) 2020 Ad Schellevis <ad@opnsense.org>
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
import ujson
import re
import datetime


def parse_flow(flow_line):
    tmp = flow_line.split()
    if flow_line.find(':') > 0 and len(tmp) > 8:
        # IPv6 layout
        return {
            'BKT':tmp[0],
            'Prot':tmp[1],
            'flowid':tmp[2],
            'Source':tmp[3],
            'Destination':tmp[4],
            'pkt':int(tmp[5]) if tmp[5].isdigit() else 0,
            'bytes':int(tmp[6]) if tmp[6].isdigit() else 0,
            'drop_pkt':int(tmp[7]) if tmp[7].isdigit() else 0,
            'drop_bytes':int(tmp[8]) if tmp[8].isdigit() else 0,
        }
    elif len(tmp) > 7:
        return {
            'BKT':tmp[0],
            'Prot':tmp[1],
            'Source':tmp[2],
            'Destination':tmp[3],
            'pkt':int(tmp[4]) if tmp[4].isdigit() else 0,
            'bytes':int(tmp[5]) if tmp[5].isdigit() else 0,
            'drop_pkt':int(tmp[6]) if tmp[6].isdigit() else 0,
            'drop_bytes':int(tmp[7]) if tmp[7].isdigit() else 0
        }

def parse_flowset_params(line):
    return re.match(
        r"q(?P<flow_set_nr>[0-9]*)(?P<queue_size>.*) (?P<flows>[0-9]*) flows"
        " \((?P<buckets>[0-9]*) buckets\) sched (?P<sched_nr>[0-9]*)"
        " weight (?P<weight>[0-9]*)"
        " lmax (?P<lmax>[0-9]*)"
        " pri (?P<pri>[0-9]*)"
        "(?P<queue_params>.*)",
        line
    )


def trim_dict(payload):
    for key in payload:
        if type(payload[key]) == str:
            payload[key] = payload[key].strip()
        elif type(payload[key]) == dict:
            trim_dict(payload[key])
    return payload

def parse_ipfw_pipes():
    result = dict()
    pipetxt = subprocess.run(['/sbin/ipfw', 'pipe', 'show'], capture_output=True, text=True).stdout.strip()
    current_pipe = None
    current_pipe_header = False
    for line in ("%s\n000000X" % pipetxt).split('\n'):
        if len(line) == 0:
            continue
        if line[0].isdigit():
            if current_pipe:
                result[current_pipe['pipe']] = current_pipe
            current_pipe_header = False
            if line.find('burst') > -1:
                current_pipe = {
                    'pipe': line[0:5],
                    'bw': line[7:line.find('ms') - 5].strip(),
                    'delay': line[line.find('ms') - 5: line.find('ms')].strip(),
                    'burst': line[line.find('burst ')+6:].strip(),
                    'flows': []
                }
        elif line[0] == 'q' and current_pipe is not None:
            m = parse_flowset_params(line)
            if m:
                current_pipe['flowset'] = m.groupdict()
        elif line.find("RED") > -1 and current_pipe is not None:
            current_pipe['flowset']['queue_params'] = line.strip()
        elif line.startswith(' sched'):
            #
            m = re.match(
                r" sched (?P<sched_nr>[0-9]*) type (?P<sched_type>.*) flags (?P<sched_flags>0x[0-9a-fA-F]*)"
                " (?P<sched_buckets>[0-9]*) buckets (?P<sched_active>[0-9]*) active",
                line
            )
            if m and current_pipe:
                current_pipe['scheduler'] = m.groupdict()
        elif line.find('__Source') > 0:
            current_pipe_header = True
        elif current_pipe_header:
            flow_stats = parse_flow(line)
            if flow_stats:
                current_pipe['flows'].append(flow_stats)

    return trim_dict(result)

def parse_ipfw_queues():
    result = dict()
    queuetxt = subprocess.run(['/sbin/ipfw', 'queue', 'show'], capture_output=True, text=True).stdout.strip()
    current_queue = None
    current_queue_header = False
    for line in ("%s\nq000000X" % queuetxt).split('\n'):
        if len(line) == 0:
            continue
        if line[0] == 'q':
            m = parse_flowset_params(line)
            if current_queue:
                result[current_queue['flow_set_nr']] = current_queue
            if m:
                current_queue = m.groupdict()
                current_queue['flows'] = list()
        elif line.find('__Source') > 0:
            current_queue_header = True
        else:
            flow_stats = parse_flow(line)
            if flow_stats and current_queue:
                current_queue['flows'].append(flow_stats)

    return trim_dict(result)

def parse_ipfw_scheds():
    result = dict()
    schedtxt = subprocess.run(['/sbin/ipfw', 'sched', 'show'], capture_output=True, text=True).stdout.strip()
    current_sched = None
    for line in ("%s\n000000X" % schedtxt).split('\n'):
        if len(line) == 0:
            continue
        if line[0].isdigit():
            if current_sched:
                result[current_sched['pipe']] = current_sched
            if line.find('burst') > 0:
                current_sched = {
                    'pipe': line[0:5]
                }
        elif line.startswith(' sched'):
            m = re.match(
                r" sched (?P<sched_nr>[0-9]*) type (?P<sched_type>.*) flags (?P<sched_flags>0x[0-9a-fA-F]*)"
                " (?P<sched_buckets>[0-9]*) buckets (?P<sched_active>[0-9]*) active",
                line
            )
            if m and current_sched:
                current_sched.update(m.groupdict())
        elif line.find('Children flowsets') > 0:
            current_sched['children'] = line[22:].split()

    return trim_dict(result)


def parse_ipfw_rules():
    result = {'queues': list(), 'pipes': list()}
    ruletxt = subprocess.run(['/sbin/ipfw', '-aT', 'list'], capture_output=True, text=True).stdout.strip()
    for line in ruletxt.split('\n'):
        parts = line.split()
        if len(parts) > 5 and parts[4] in ['queue', 'pipe']:
            rule = {
                'rule': parts[0],
                'pkts': int(parts[1]) if parts[1].isdigit() else 0,
                'bytes': int(parts[2]) if parts[2].isdigit() else 0,
                'accessed': datetime.datetime.fromtimestamp(int(parts[3])).isoformat() if parts[3].isdigit() else '',
                'accessed_epoch': int(parts[3]) if parts[3].isdigit() else 0,
                'attached_to': parts[5],
                'rule_uuid': None
            }
            if line.find('//') > -1:
                rule_uuid = line[line.find('//')+3:].strip().split()[0]
                if rule_uuid.count('-') == 4:
                    rule['rule_uuid'] = rule_uuid
            result["%ss" % parts[4]].append(rule)

    return result
