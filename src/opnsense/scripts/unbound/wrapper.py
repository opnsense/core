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
import ipaddress

def unbound_control_reader(action):
    sp = subprocess.run(['/usr/local/sbin/unbound-control', '-c', '/var/unbound/unbound.conf', action],
                        capture_output=True, text=True)
    for line in sp.stdout.strip().split("\n"):
        yield line

def unbound_control_do(action, bulk_input = None):
    p = subprocess.Popen(['/usr/local/sbin/unbound-control', '-c', '/var/unbound/unbound.conf', action],
                         stdin=subprocess.PIPE, stdout=subprocess.PIPE, stderr=subprocess.STDOUT, text=True)

    if bulk_input:
        for input in bulk_input:
            p.stdin.write("%s\n" % input)

    result = p.communicate()[0]

    # return code is only available after communicate()
    if (p.returncode != 0):
        syslog.syslog(syslog.LOG_ERR, 'unbound-control returned: %s' % result)
        sys.exit(1)

    syslog.syslog(syslog.LOG_NOTICE, 'unbound-control returned: %s' % result)

def diff_cache(new, cache, parse_func):
    files = {'new': new, 'cache': cache}
    contents = {}
    for filetype in files:
        if os.path.exists(files[filetype]):
            with open(files[filetype], 'r') as current_f:
                # we pass a filepointer so the parser generator can read multiple lines in a single iteration
                contents[filetype] = set(parse_func(current_f))

    additions = contents['new'] - contents['cache']
    removals = contents['cache'] - contents['new']
    shutil.copyfile(files['new'], files['cache'])
    return {'removals': removals, 'additions': additions}

# parse arguments
parser = argparse.ArgumentParser()
parser.add_argument('-o', '--overrides', help="Update DNS overrides", action="store_true", default=False)
parser.add_argument('-b', '--dnsbl', help='Update DNS blocklists', action="store_true", default=False)
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
syslog.openlog('unbound', logoption=syslog.LOG_DAEMON, facility=syslog.LOG_LOCAL4)
if args.overrides:
    def parse_hosts(filepointer):
        for entry in filepointer:
            command = entry.split(':', 1)[0]
            if command in ['local-data', 'local-data-ptr', 'local-zone']:
                parsed = " ".join([word.strip(' "\'\t\r\n') for word in entry[len(command) + 1:].strip().split(' ')])
                if command == 'local-data-ptr':
                    # unbound-control does not handle formatting PTR records, so do it here (a.b.c.d --> d.c.b.a.in-addr.arpa)
                    parsed = " ".join([ipaddress.ip_address(parsed.split(' ', 1)[0]).reverse_pointer, 'PTR', parsed.split(' ', 1)[1]])
                yield " ".join([command, parsed])

    def parse_domains(filepointer):
        dnqlh_done = False
        for entry in filepointer:
            if entry.strip().startswith('forward-zone:'):
                name = next(filepointer).strip()[5:].strip(' "\'\t\r\n')
                forward_addr = next(filepointer).strip()[13:].strip(' "\'\t\r\n')
                yield " ".join([name, forward_addr])
            elif entry.strip().startswith('server:') and not dnqlh_done:
                unbound_control_do('set_option do-not-query-localhost: no')
                dnqlh_done = True

    hosts = diff_cache('/var/unbound/host_entries.conf', '/tmp/unbound_hostoverrides.cache', parse_hosts)
    domains = diff_cache('/usr/local/etc/unbound.opnsense.d/domainoverrides.conf', '/tmp/unbound_domainoverrides.cache', parse_domains)

    for op in hosts.keys():
        local_zones = []
        local_datas = []
        if hosts[op]:
            for val in hosts[op]:
                split = val.split(' ')
                local_zones.append(" ".join(split[1:])) if split[0] == 'local-zone' else local_datas.append(" ".join(split[1:]))

            # first do removals to maintain a clean state
            if op == 'removals':
                if local_zones:
                    removals = {line.split(' ')[0].strip() for line in local_zones}
                    unbound_control_do('local_zones_remove', removals)
                if local_datas:
                    removals = {line.split(' ')[0].strip() for line in local_datas}
                    unbound_control_do('local_datas_remove', removals)

            if op == 'additions':
                # add local zones first, otherwise the redirect for the domain in the linked local_data
                # does not take effect.
                if local_zones:
                    unbound_control_do('local_zones', local_zones)
                if local_datas:
                    unbound_control_do('local_datas', local_datas)

    # unbound-control does not do bulk insertion/removal of forwards
    if domains['removals']:
        for val in domains['removals']:
            unbound_control_do(" ".join(['forward_remove', val.split(' ')[0]]))

    if domains['additions']:
        for val in domains['additions']:
            unbound_control_do(" ".join(['forward_add', val]))

    output = "OK"
elif args.dnsbl:
    def parse_dnsbl(filepointer):
        for line in filepointer:
            if line.startswith('local-data:'):
                yield line[11:].strip(' "\'\t\r\n')

    diff = diff_cache('/usr/local/etc/unbound.opnsense.d/dnsbl.conf', '/tmp/unbound_dnsbl.cache', parse_dnsbl)

    additions = diff['additions']
    removals = diff['removals']
    if removals:
        # RR removals only accept domain names, so strip it again (xxx.xx 0.0.0.0 --> xxx.xx)
        removals = {line.split(' ')[0].strip() for line in removals}
        unbound_control_do('local_datas_remove', removals)
    if additions:
        unbound_control_do('local_datas', additions)

    output = {'additions': len(additions), 'removals': len(removals)}

    syslog.syslog(syslog.LOG_NOTICE, 'got %d RR additions and %d RR removals' % (output['additions'], output['removals']))
elif args.cache:
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
