#!/usr/bin/env python3
"""
    Copyright (c) 2024 Deciso B.V.
    Copyright (c) 2024 Sheridan Computers Limited
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
    ------------------------------------------------------------------------------------------
    Simple wrapper around bectl shell command
"""
import argparse
import datetime
import json
import subprocess
import sys
import time
import uuid


if __name__ == '__main__':
    parser = argparse.ArgumentParser()
    parser.add_argument(
        'action',
        help='action to perform, see bectl for details',
        choices=['is_supported', 'activate', 'create', 'clone', 'destroy', 'list', 'rename']
    )
    parser.add_argument('--beName', help='name of boot environment', type=str)
    parser.add_argument('--from-source', help='boot environment to clone', type=str)
    inputargs = parser.parse_args()

    cmd = []
    error_msg = None
    if subprocess.run(['df', '-Tt', 'zfs', '/'], capture_output=True).returncode != 0:
        error_msg = 'Unsupported root filesystem'
    elif inputargs.action == 'is_supported':
        print(json.dumps({"status": "OK", "message": "File system is ZFS"}))
    elif inputargs.action == 'list':
        cmd = ['bectl', 'list', '-H']
    elif inputargs.action == 'activate' and inputargs.beName:
        cmd = ['bectl', 'activate', inputargs.beName]
    elif inputargs.action == 'create':
        name = inputargs.beName if inputargs.beName else "{date:%Y%m%d%H%M%S}".format(date=datetime.datetime.now())
        cmd = ['bectl', 'create', name]
    elif inputargs.action == 'clone' and inputargs.from_source:
        name = inputargs.beName if inputargs.beName else "{date:%Y%m%d%H%M%S}".format(date=datetime.datetime.now())
        cmd = ['bectl', 'create', '-e', inputargs.from_source, name]
    elif inputargs.action == 'destroy' and inputargs.beName:
        cmd = ['bectl', 'destroy', inputargs.beName]
    elif inputargs.action == 'rename' and inputargs.beName and inputargs.from_source:
        cmd = ['bectl', 'rename', inputargs.from_source, inputargs.beName]
    else:
        print(json.dumps({"status": "failed", "result": "Incomplete argument list"}))
        sys.exit(-1)

    if error_msg:
        print(json.dumps({"status": "failed", "result": error_msg}))
    elif len(cmd) > 0:
        sp = subprocess.run(cmd, capture_output=True, text=True)
        if sp.returncode != 0:
            print(json.dumps({"status": "failed", "result": sp.stderr.strip()}))
        elif inputargs.action == 'list':
            result = []
            for line in sp.stdout.split("\n"):
                parts = line.split("\t")
                if len(parts) >= 5:
                    result.append({
                        "uuid": str(uuid.uuid3(uuid.NAMESPACE_DNS, parts[0])),
                        "name": parts[0],
                        "active": parts[1],
                        "mountpoint": parts[2],
                        "size": parts[3],
                        "created_str": parts[4],
                        "created": time.mktime(datetime.datetime.strptime(parts[4], "%Y-%m-%d %H:%M").timetuple())
                    })
            print(json.dumps(result))
        else:
            print(json.dumps({
                "status": "ok",
                "result": 'bootenvironment executed %s successfully' % inputargs.action
            }))
