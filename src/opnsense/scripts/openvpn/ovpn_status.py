#!/usr/local/bin/python3

"""
    Copyright (c) 2023 Ad Schellevis <ad@opnsense.org>
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

import argparse
import glob
import socket
import re
import os
import ujson
socket.setdefaulttimeout(5)


def ovpn_cmd(filename, cmd):
    try:
        sock = socket.socket(socket.AF_UNIX, socket.SOCK_STREAM)
        sock.connect(filename)
    except socket.error:
        return None

    sock.send(('%s\n'%cmd).encode())
    buffer = ''
    while True:
        try:
            buffer += sock.recv(65536).decode()
        except socket.timeout:
            break
        eob =  buffer[-20:]
        if eob.find('END') > -1 or eob.find('ERROR') > -1:
            break
    sock.close()
    return buffer


def ovpn_status(filename):
    response = {}
    buffer = ovpn_cmd(filename, 'status 3')
    if buffer is None:
        return {'status': 'failed'}

    header_def = []
    client_fieldnames = {'read_bytes': 'bytes_received', 'write_bytes': 'bytes_sent'}
    target_struct = None
    for line in buffer.split('\n'):
        if line.startswith('TCP/UDP'):
            line = line.split(',')
            fieldname = line[0][8:].replace(' ', '_')
            if fieldname in client_fieldnames:
                fieldname = client_fieldnames[fieldname]
            response[fieldname] = line[1].strip()
            continue
        line = line.split('\t')
        if line[0] == 'HEADER':
            header_def = []
            for item in line[1:]:
                header_def.append(re.sub('[\ \(\)]', '_', item.lower().strip()))
            target_struct = header_def.pop(0).lower() if len(header_def) > 0 else None
            response['status'] = 'ok'
        elif target_struct is not None and line[0] in ['CLIENT_LIST', 'ROUTING_TABLE']:
            line = line[1:]
            record = {}
            for i in range(len(header_def)):
                record[header_def[i]] = line[i].strip()
            if target_struct not in response:
                response[target_struct] = []
            response[target_struct].append(record)

    return response


def ovpn_state(filename):
    response = {'status': 'failed'}
    buffer = ovpn_cmd(filename, 'state')
    if buffer is None:
        return response

    for line in buffer.split('\n'):
        tmp = line.split(',')
        if len(tmp) > 2 and tmp[0].isdigit():
            response['timestamp'] = int(tmp[0])
            response['status'] = tmp[1].lower()
            response['virtual_address'] = tmp[3] if len(tmp) > 3 else ""
            response['real_address'] = tmp[4] if len(tmp) > 4 else ""

    return response


def main(params):
    response = {}
    for filename in glob.glob('/var/etc/openvpn/*.sock'):
        bname = os.path.basename(filename)[:-5]
        this_id = bname[9:] if bname.startswith('inst') else bname[6:]
        if bname.startswith('instance-'):
            this_status = ovpn_status(filename)
            role = 'client' if 'bytes_received' in this_status else 'server'
            if role not in response:
                response[role] = {}
            if role == 'server' and 'server' in params.options:
                response['server'][this_id] = this_status
                if 'status' not in response['server'][this_id]:
                    # p2p mode, no client_list or routing_table
                    response['server'][this_id].update(ovpn_state(filename))
            elif role == 'client' and  'client' in params.options:
                response['client'][this_id] = ovpn_state(filename)
                if response['client'][this_id]['status'] != 'failed':
                    response['client'][this_id].update(this_status)
        elif bname.startswith('server') and 'server' in params.options:
            if 'server' not in response:
                response['server'] = {}
            response['server'][this_id] = ovpn_status(filename)
            if 'status' not in response['server'][this_id]:
                # p2p mode, no client_list or routing_table
                response['server'][this_id].update(ovpn_state(filename))
        elif bname.startswith('client') and 'client' in params.options:
            if 'client' not in response:
                response['client'] = {}
            response['client'][this_id] = ovpn_state(filename)
            if response['client'][this_id]['status'] != 'failed':
                response['client'][this_id].update(ovpn_status(filename))

    return response


if __name__ == '__main__':
    parser = argparse.ArgumentParser()
    parser.add_argument(
        '--options',
        help='request status from client,server (comma separated)',
        type=lambda x: x.split(','),
        default='server'
    )
    print(ujson.dumps(main(parser.parse_args())))
