#!/usr/local/bin/python3

"""
    Copyright (c) 2017-2022 Ad Schellevis <ad@opnsense.org>
    Copyright (C) 2017 Fabian Franz
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
import sys
import re
import tempfile
import subprocess
import argparse
import json
import shutil
import syslog

def unbound_control_reader(action):
    sp = subprocess.run(['/usr/local/sbin/unbound-control', '-c', '/var/unbound/unbound.conf', action],
                        capture_output=True, text=True)
    for line in sp.stdout.strip().split("\n"):
        yield line

# parse arguments
parser = argparse.ArgumentParser()
parser.add_argument('-c', '--cache', help='Dump cache', action="store_true", default=False)
parser.add_argument('-i', '--infra', help='Dump infrastructure cache', action="store_true", default=False)
parser.add_argument('-s', '--stats', help='Dump stats', action="store_true", default=False)
parser.add_argument('-l', '--list-local-zones', help='List local Zones', action="store_true", default=False)
parser.add_argument('-I', '--list-insecure', help='List Domain-Insecure Zones', action="store_true", default=False)
parser.add_argument('-d', '--list-local-data', help='List local data', action="store_true", default=False)
args = parser.parse_args()

#
try:
    os.kill(int(open("/var/run/unbound.pid").read().strip()), 0)
except:
    # unbound not active
    sys.exit(1)

output = None
if args.cache:
    output = list()
    for line in unbound_control_reader('dump_cache'):
        parts = re.split('^(\S+)\s+(?:([\d]*)\s+)?(IN)\s+(\S+)\s+(.*)$', line)
        if line.find('IN') > -1 and not line.startswith('msg') and len(parts) > 5:
            output.append({'host': parts[1], 'ttl': parts[2], 'type': parts[3], 'rrtype': parts[4], 'value': parts[5]})
elif args.infra:
    output = list()
    for line in unbound_control_reader('dump_infra'):
        parts = line.split()
        if len(parts) > 2:
            record = {'ip': parts.pop(0), 'host': parts.pop(0)}
            while len(parts) > 0:
                key = parts.pop(0)
                if key == 'lame':
                    record['lame'] = True
                    continue
                record[key] = parts.pop(0)
            output.append(record)
elif args.stats:
    output = dict()
    for line in unbound_control_reader('stats_noreset'):
        full_key, value = line.split('=')
        keys = full_key.split('.')
        if keys[0] == 'histogram':
            if 'histogram' not in output:
                output['histogram'] = list()
            output['histogram'].append({
                'from': (int(keys[1]), int(keys[2])),
                'to': (int(keys[4]), int(keys[5])),
                'value': value.strip()
            })
        else:
            ptr = output
            while len(keys) > 0 :
                key = keys.pop(0)
                if len(keys) == 0:
                    ptr[key] = value.strip()
                elif key not in ptr:
                    ptr[key] = dict()
                elif type(ptr[key]) != dict:
                    ptr[key] = {'__value__': ptr[key]}
                ptr = ptr[key]

elif args.list_local_zones:
    output = list()
    for line in unbound_control_reader('list_local_zones'):
        parts = line.split()
        if len(parts) >= 2:
            output.append({'zone': parts[0], 'type': parts[1]})
elif args.list_insecure:
    output = list()
    for line in unbound_control_reader('list_insecure'):
        output.append(line)
elif args.list_local_data:
    output = list()
    for line in unbound_control_reader('list_local_data'):
        parts = line.split()
        if len(parts) >= 5:
            output.append({'name': parts[0], 'ttl': parts[1], 'type': parts[2], 'rrtype': parts[3], 'value': parts[4]})
else:
    parser.print_help()
    sys.exit(1)

# flush output
print (json.dumps(output))
