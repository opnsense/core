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
import time
import uuid
from subprocess import Popen, run, PIPE


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
    if run(['df', '-Tt', 'zfs', '/'], capture_output=True).returncode != 0:
        print(json.dumps({"status": "failed", "result": "Unsupported root filesystem"}))
    elif inputargs.action == 'is_supported':
        print(json.dumps({"status": "OK", "message": "File system is ZFS"}))
    elif inputargs.action == 'list':
        result = []
        for line in run(['bectl', 'list', '-H'], capture_output=True, text=True).stdout.split("\n"):
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
    elif inputargs.action == 'activate' and inputargs.beName:
        if run(['bectl', 'activate', inputargs.beName], capture_output=True).returncode == 0:
            print(json.dumps({"status": "ok", "result": 'Boot environment activated'}))
        else:
            print(json.dumps({"status": "failed", "result": "An error ocurred whilst activating the boot environment"}))
    elif inputargs.action == 'create':
        name = inputargs.beName if inputargs.beName else "BE-{date:%Y%m%d%H%M%S}".format(date=datetime.datetime.now())
        if run(['bectl', 'create', name], capture_output=True).returncode == 0:
            print(json.dumps({"status": "ok", "result": "Boot environment created"}))
        else:
            print(json.dumps({"status": "failed", "result": "Error: Could not create boot environment"}))
    elif inputargs.action == 'clone' and inputargs.from_source:
        name = inputargs.beName if inputargs.beName else "BE-{date:%Y%m%d%H%M%S}".format(date=datetime.datetime.now())
        if run(['bectl', 'create', '-e', inputargs.from_source, name], capture_output=True).returncode == 0:
            print(json.dumps({"status": "ok", "result": "Boot environment created"}))
        else:
            print(json.dumps({"status": "failed", "result": "Error: Could not create boot environment"}))
    elif inputargs.action == 'destroy' and inputargs.beName:
        if run(['bectl', 'destroy', inputargs.beName], capture_output=True).returncode == 0:
            print(json.dumps({"status": "ok", "result": 'Boot environment destroyed'}))
        else:
            print(json.dumps({"status": "failed", "result": "An error ocurred whilst destroying the boot environment"}))
    elif inputargs.action == 'rename' and inputargs.beName and inputargs.from_source:
        if run(['bectl', 'rename', inputargs.from_source, inputargs.beName], capture_output=True).returncode == 0:
            print(json.dumps({"status": "ok", "result": "Boot environment renamed"}))
        else:
            print(json.dumps({"status": "failed", "result": "Error: Could not rename boot environment"}))
    else:
        print(json.dumps({"status": "failed", "result": "Incomplete argument list"}))
