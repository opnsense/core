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
        eob =  buffer[-200:]
        if eob.find('END') > -1 or eob.find('ERROR') > -1 or eob.find('SUCCESS') > -1:
            break
    sock.close()
    return buffer


if __name__ == '__main__':
    parser = argparse.ArgumentParser()
    parser.add_argument('server_id', help='server/client id (where to find socket)', type=str)
    parser.add_argument('session_id', help='session id (address+port) or common name')
    args = parser.parse_args()
    socket_name = None
    for filename in glob.glob("/var/etc/openvpn/*.sock"):
        basename = os.path.basename(filename)
        if basename in [
            'client%s.sock'%args.server_id, 'server%s.sock'%args.server_id, 'instance-%s.sock'%args.server_id
        ]:
            socket_name = filename
            break
    if socket_name:
        res = ovpn_cmd(socket_name, 'kill %s\n' % args.session_id)
        if res.find('SUCCESS:') >= 0:
            clients = 0
            for tmp in res.strip().split('\n')[-1].split():
                if tmp.isdigit():
                    clients = int(tmp)
            print(ujson.encode({'status': 'killed', 'clients': clients}))
        else:
            print(ujson.encode({'status': 'not_found'}))
    else:
        print(ujson.encode({'status': 'server_not_found'}))
