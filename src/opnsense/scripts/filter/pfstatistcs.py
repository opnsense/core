#!/usr/local/bin/python3

"""
    Copyright (c) 2021 Ad Schellevis <ad@opnsense.org>
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
import datetime
import sys
import subprocess
import ujson


def pfctl_info():
    result = dict()
    heading_line = None
    info_sections = ['state-table', 'source-tracking-table', 'counters', 'limit-counters']
    for line in subprocess.run(['/sbin/pfctl', '-vvsinfo'], capture_output=True, text=True).stdout.split("\n"):
        if len(line) > 0:
            if line[1] != ' ':
                heading_line = line[:30].strip().lower().replace(' ', '-')
            elif line.startswith('Status'):
                result['uptime'] = line[7:30].strip()
            elif heading_line in info_sections:
                if heading_line not in result:
                    result[heading_line] = {}
                parts = line[30:].strip().split()
                prop = line[:30].strip().lower().replace(' ', '-')
                result[heading_line][prop] = {
                    'total': int(parts[-2]) if len(parts) > 1 else int(parts[-1])
                }
                if len(parts) > 1:
                    result[heading_line][prop]['rate'] = float(parts[-1][:-2])
    return result

def pfctl_memory():
    result = dict()
    for line in subprocess.run(['/sbin/pfctl', '-vvsmemory'], capture_output=True, text=True).stdout.split("\n"):
        parts = line.split()
        if len(parts) > 1 and parts[-1].isdigit():
            result[parts[0]] = int(parts[-1])

    return result

def pfctl_timeouts():
    result = dict()
    for line in subprocess.run(['/sbin/pfctl', '-vvstimeouts'], capture_output=True, text=True).stdout.split("\n"):
        parts = line.split(maxsplit=1)
        if len(parts) > 1:
            result[parts[0]] = parts[1]

    return result

def pfctl_interfaces():
    result = dict()
    heading_line = None
    for line in subprocess.run(['/sbin/pfctl', '-vvsInterfaces'], capture_output=True, text=True).stdout.split("\n"):
        if len(line) > 0 and line[0] not in ["\t", " "]:
            heading_line = line.strip()
            result[heading_line] = dict()
        elif heading_line is not None and len(line) > 10:
            line = line.strip()
            topic, line = line.split(maxsplit=1)
            topic = topic.rstrip(':').lower().replace('/', '_')
            if topic == 'cleared':
                result[heading_line][topic] = datetime.datetime.strptime(
                    line.split(maxsplit=1)[-1], "%b %d %H:%M:%S %Y"
                ).isoformat()
            elif topic == 'references':
                result[heading_line][topic] = int(line)
            elif line.find('Packets') > -1 and line.find('Bytes') > -1:
                parts = line.split()
                result[heading_line]["%s_packets" % topic] = int(parts[4])
                result[heading_line]["%s_bytes" % topic] = int(parts[4])

    return result

def pfctl_rules():
    result = dict()
    headings = {
        "rules": "filter rules",
        "nat": "nat rules"
    }
    for key in headings:
        result[headings[key]] = dict()
        rule = None
        for line in subprocess.run(['/sbin/pfctl', '-vvs' + key], capture_output=True, text=True).stdout.split("\n"):
            sline = line.strip()
            if len(line) > 0 and line[0] not in ["\t", " "]:
                rule = sline
                result[headings[key]][rule] = dict()
            elif rule is not None and sline.startswith('[') and sline.endswith(']'):
                items = sline[1:].strip().lower().split(':')
                for idx, item in enumerate(items[1:],1):
                    opt = 'state_creations' if items[idx-1].find('creations') >  -1 else items[idx-1].split()[-1]
                    val = " ".join(item.split()[:-1]).replace('state', '')
                    result[headings[key]][rule][opt] = int(val) if val.isdigit() else val

    return result

def main():
    sections = {
        'info': pfctl_info,
        'memory': pfctl_memory,
        'timeouts': pfctl_timeouts,
        'interfaces': pfctl_interfaces,
        'rules': pfctl_rules
    }
    result = dict()
    for section in sections:
        if (len(sys.argv) > 1 and sys.argv[1] == section) or (len(sys.argv) == 1):
            result[section] = sections[section]()

    return result

print(ujson.dumps(main()))
