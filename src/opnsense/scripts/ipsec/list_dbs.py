#!/usr/local/bin/python3

"""
    Copyright (c) 2022 Manuel Faux <mfaux@conf.at>
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
    show SAs using vici interface and sec. policies using /sbin/setkey
"""

import argparse
import datetime
import re
import sys
import subprocess
import socket
import ujson
import vici


def format_proto(child_sa):
    return "{0}{1}".format(
        child_sa['protocol'].decode('utf-8'),
        "-UDP" if 'encap' in child_sa and child_sa['encap'] == "yes" else ""
        )


def dump_sa(filter_str = ''):
    result = []
    filter_str = filter_str.lower()
    # attach Security Associations
    for sas in s.list_sas():
        # iterate through SAs
        for sa in sas.values():
            # iterate through each child SA
            for child_sa in sa['child-sas'].values():
                # in direction
                in_direction = {
                    'id': sa['uniqueid'],
                    'ikeversion': sa['version'],
                    'src_addr': sa['local-host'],
                    'src_port': sa['local-port'],
                    'src_id': sa['local-id'],
                    'dst_addr': sa['remote-host'],
                    'dst_port': sa['remote-port'],
                    'dst_id': sa['remote-id'],
                    'dir': "in",
                    'proto': format_proto(child_sa),
                    'spi': child_sa['spi-in'],
                    'encr-alg': child_sa['encr-alg'] if 'encr-alg' in child_sa else "",
                    'integ-alg': child_sa['integ-alg'] if 'integ-alg' in child_sa else "",
                    'dh-group': child_sa['dh-group'] if 'dh-group' in child_sa else "",
                    'bytes': child_sa['bytes-in'],
                    'pkts': child_sa['packets-in']
                }
                # Apply filter
                if filter_str == '' or len([i for i in in_direction.values() if i.lower().find(filter_str)]) > 0:
                    result.append(in_direction)

                # out direction
                out_direction = {
                    'id': sa['uniqueid'],
                    'ikeversion': sa['version'],
                    'src_addr': sa['remote-host'],
                    'src_port': sa['remote-port'],
                    'src_id': sa['remote-id'],
                    'dst_addr': sa['local-host'],
                    'dst_port': sa['local-port'],
                    'dst_id': sa['local-id'],
                    'dir': "out",
                    'proto': format_proto(child_sa),
                    'spi': child_sa['spi-out'],
                    'encr-alg': child_sa['encr-alg'] if 'encr-alg' in child_sa else "",
                    'integ-alg': child_sa['integ-alg'] if 'integ-alg' in child_sa else "",
                    'dh-group': child_sa['dh-group'] if 'dh-group' in child_sa else "",
                    'bytes': child_sa['bytes-out'],
                    'pkts': child_sa['packets-out']
                }
                # Apply filter
                if filter_str == '' or len([i for i in out_direction.values() if i.lower().find(filter_str)]) > 0:
                    result.append(out_direction)
    return result


def dump_sp(filter_str = ''):
    result = []
    filter_str = filter_str.lower()
    process = subprocess.run(["/sbin/setkey", "-DP"], capture_output=True, text=True)
    if process.returncode == 0:
        # Define regexes
        selector_re = re.compile(r"(?P<src_addr>[a-f\d.:]+/?\d*)\[(?P<src_port>[^\]]*)\]\s+(?P<dst_addr>[a-f\d.:]+/?\d*)\[(?P<dst_port>[^\]]*)\]\s+(?P<proto>\S+)")
        policy_re = re.compile(r"(?P<dir>any|in|out).+?(?P<policy>\w+)\s*\n\s+(?P<proto>esp|ah|epcomp|tcp)/(?P<mode>\w+)/(?P<addr_a>[a-f\d.:]*)-?(?P<addr_b>[a-f\d.:]*)/(?P<level>\w+)")
        lifetime_re = re.compile(r"created:\s+(?P<created>.+?)\s+lastused:\s+(?P<lastused>.+)\s+lifetime:\s+(?P<lifetime>\d+).*validtime:\s+(?P<validtime>\d+)")
        scope_re = re.compile(r"spid=(?P<spid>\d+).*scope=(?P<scope>\w+)")

        # Fetch each SP block
        for sp in re.finditer(r"([a-f\d:]+[.:].*(?:\n\s.*)+)", process.stdout):
            selector = selector_re.search(sp[1])
            policy = policy_re.search(sp[1])
            lifetime = lifetime_re.search(sp[1])
            scope = scope_re.search(sp[1])

            if not selector or not scope:
                continue

            if lifetime:
                created = datetime.datetime.strptime(lifetime['created'], "%b %d %H:%M:%S %Y")
                lastused = datetime.datetime.strptime(lifetime['lastused'], "%b %d %H:%M:%S %Y")

            row = {
                'src_addr': selector['src_addr'],
                'src_port': selector['src_port'],
                'dst_addr': selector['dst_addr'],
                'dst_port': selector['dst_port'],
                'dir': policy['dir'] if policy else '',
                'proto': policy['proto'].upper() if policy else '',
                'mode': policy['mode'] if policy else '',
                'endpoints': [policy['addr_a'], policy['addr_b']] if policy else '',
                'created': datetime.datetime.timestamp(created) if lifetime else '',
                'lastused': datetime.datetime.timestamp(lastused) if lifetime else '',
                'id': scope['spid'],
                'scope': scope['scope']
            }
            # Apply filter
            if filter_str == '' or len([i for i in row.values() if filter_str in str(i).lower()]) > 0:
                result.append(row)

    return result


if __name__ == '__main__':
    # parse input arguments
    parser = argparse.ArgumentParser()
    parser.add_argument('db', choices=['sa', 'sp'])
    parser.add_argument('--filter', help='filter results', default='')
    parser.add_argument('--limit', help='limit number of results', default='')
    parser.add_argument('--offset', help='offset results', default='')
    parser.add_argument('--sort_by', help='sort by (field asc|desc)', default='')
    inputargs = parser.parse_args()

    try:
        s = vici.Session()
    except socket.error:
        # cannot connect to session, strongswan not running?
        print ('ipsec not active')
        sys.exit(0)

    # Retrieve results
    result = dict()
    if inputargs.db == 'sa':
        result['rows'] = dump_sa(inputargs.filter)
    elif inputargs.db == 'sp':
        result['rows'] = dump_sp(inputargs.filter)

    # Sort results
    if inputargs.sort_by.strip() != '':
        sort_key = inputargs.sort_by.split()[0]
        sort_desc = inputargs.sort_by.split()[-1] == 'desc'
        result['rows'] = sorted(
            result['rows'],
            key=lambda k: str(k[sort_key]).lower() if sort_key in k else '',
            reverse=sort_desc
        )

    result['total_entries'] = len(result['rows'])
    # apply offset and limit
    if inputargs.offset.isdigit():
        result['rows'] = result['rows'][int(inputargs.offset):]
    if inputargs.limit.isdigit() and len(result['rows']) >= int(inputargs.limit):
        result['rows'] = result['rows'][:int(inputargs.limit)]

    result['total'] = len(result['rows'])

    print(ujson.dumps(result, reject_bytes=False, indent=2))
