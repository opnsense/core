#!/usr/local/bin/python3

"""
    Copyright (c) 2022 Manuel Faux <mfaux@conf.at>
    Copyright (c) 2015-2019 Ad Schellevis <ad@opnsense.org>
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
import xml.etree.ElementTree as ElementTree


def format_proto(child_sa):
    return "{0}{1}".format(
        child_sa['protocol'].decode('utf-8'),
        "-UDP" if 'encap' in child_sa and child_sa['encap'] == "yes" else ""
    )


def parse_connection(in_conn):
    result = {'local-addrs': '', 'remote-addrs': '', 'local-id': '', 'remote-id': '', 'sas': 0}
    result['version'] = in_conn['version']
    if 'local_addrs' in in_conn:
        result['local-addrs'] = b','.join(in_conn['local_addrs'])
    elif 'local-host' in in_conn:
        result['local-addrs'] = in_conn['local-host']
    if 'remote_addrs'  in in_conn:
        result['remote-addrs'] = b','.join(in_conn['remote_addrs'])
    elif 'remote-host' in in_conn:
        result['remote-addrs'] = in_conn['remote-host']

    return result


def is_filtered(elements, filter_str):
    """Check if current row is excluded by filter

    :elements: list with current elements
    :filter_str: text filter
    """
    return filter_str != '' and len([i for i in elements if filter_str in str(i).lower()]) == 0


def get_conn_description(connection_id):
    """Parse config.xml for connection description

    :connection_id: IPsec connection ID (e.g. con3)
    """
    global ipsec_config
    # Load config only once
    if ipsec_config is None:
        config_root = ElementTree.parse("/conf/config.xml")
        ipsec_config = config_root.find('ipsec').findall('phase1')

    # Search phase1 and return description
    if ipsec_config is not None:
        for config in ipsec_config:
            if "con{0}".format(config.find('ikeid').text) == connection_id:
                if (descr := config.find('descr')) is not None:
                    return descr.text
                else:
                    return ""


def get_sa_for_connection(connection, filter_str = ''):
    """Retrieve SAs for a specific connection

    :connection_id: IPsec connection ID (e.g. con3)
    :filter_str: text filter
    """
    result = []
    filter_str = filter_str.lower()
    # attach Security Associations
    for sas in s.list_sas({'ike': connection}):
        # iterate through SAs
        for connection_id in sas:
            sa = sas[connection_id]

            # iterate through each child SA
            for child_sa in sa['child-sas'].values():
                row = child_sa
                # Apply filter
                if not is_filtered(row.values(), filter_str):
                    result.append(row)
    return result


def dump_conns(filter_str = ''):
    """Retrieve all connections

    :filter_str: text filter
    """
    result = {}
    parsed = []
    filter_str = filter_str.lower()
    # parse connections
    for conns in s.list_conns():
        for connection_id in conns:
            result[connection_id] = parse_connection(conns[connection_id])
            result[connection_id]['id'] = connection_id
            result[connection_id]['name'] = get_conn_description(connection_id)
            result[connection_id]['routed'] = True
            result[connection_id]['local-class'] = []
            result[connection_id]['remote-class'] = []
            # parse local-% and remote-% keys
            for connKey in conns[connection_id].keys():
                if connKey.find('local-') == 0:
                    if 'id' in conns[connection_id][connKey]:
                        result[connection_id]['local-id'] = conns[connection_id][connKey]['id']
                    result[connection_id]['local-class'].append(conns[connection_id][connKey]['class'])
                elif connKey.find('remote-') == 0:
                    if 'id' in conns[connection_id][connKey]:
                        result[connection_id]['remote-id'] = conns[connection_id][connKey]['id']
                    result[connection_id]['remote-class'].append(conns[connection_id][connKey]['class'])
            result[connection_id]['local-class'] = b'+'.join(result[connection_id]['local-class'])
            result[connection_id]['remote-class'] = b'+'.join(result[connection_id]['remote-class'])

            # Apply filter
            if is_filtered(result[connection_id].values(), filter_str):
                del result[connection_id]
            parsed.append(connection_id)

    # attach Security Associations
    for sas in s.list_sas():
        for sa in sas:
            if sa not in parsed:
                result[connection_id] = parse_connection(sas[sa])
                result[connection_id]['id'] = sa
                result[connection_id]['name'] = get_conn_description(sa)
                result[connection_id]['routed'] = False

                if is_filtered(result[connection_id].values(), filter_str):
                    del result[connection_id]
                parsed.append(sa)

            if sa in result:
                result[sa]['sas'] = len(sas[sa]['child-sas'])

    return list(result.values())


def dump_sa(filter_str = ''):
    """Retrieve all SAs

    :filter_str: text filter
    """
    result = []
    filter_str = filter_str.lower()
    # attach Security Associations
    for sas in s.list_sas():
        # iterate through SAs
        for connection_id in sas:
            sa = sas[connection_id]

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
                if not is_filtered(in_direction.values(), filter_str):
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
                if not is_filtered(out_direction.values(), filter_str):
                    result.append(out_direction)
    return result


def dump_sp(filter_str = ''):
    """Retrieve all installed SPs

    :filter_str: text filter
    """
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
            if not is_filtered(row.values(), filter_str):
                result.append(row)

    return result


if __name__ == '__main__':
    # parse input arguments
    parser = argparse.ArgumentParser()
    parser.add_argument('db', choices=['sa', 'sp', 'conn'])
    parser.add_argument('--filter', help='filter results by string', default='')
    parser.add_argument('--limit', help='limit number of results', type=int, default=-1)
    parser.add_argument('--offset', help='offset results', type=int, default=-1)
    parser.add_argument('--sort_by', help='sort by (field asc|desc)', default='')
    parser.add_argument('--connection', help='filter results by connection', default='')
    inputargs = parser.parse_args()

    try:
        s = vici.Session()
    except socket.error:
        # cannot connect to session, strongswan not running?
        print ('ipsec not active')
        sys.exit(0)

    ipsec_config = None

    # Retrieve results
    result = dict()
    if inputargs.db == 'sa':
        if inputargs.connection == '':
            result['rows'] = dump_sa(inputargs.filter)
        else:
            result['rows'] = get_sa_for_connection(inputargs.connection, inputargs.filter)
    elif inputargs.db == 'sp':
        result['rows'] = dump_sp(inputargs.filter)
    elif inputargs.db == 'conn':
        result['rows'] = dump_conns(inputargs.filter)

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
    if inputargs.offset != -1:
        result['rows'] = result['rows'][int(inputargs.offset):]
    if inputargs.limit != -1 and len(result['rows']) >= inputargs.limit:
        result['rows'] = result['rows'][:inputargs.limit]

    result['total'] = len(result['rows'])

    print(ujson.dumps(result, reject_bytes=False, indent=2))
