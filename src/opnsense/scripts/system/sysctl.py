#!/usr/local/bin/python3

"""
    Copyright (c) 2021-2022 Franco Fichtner <franco@opnsense.org>
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
    return current sysctl(8) information
"""

import argparse
import os
import subprocess
import sys
import ujson

# type mapping: r => read-only, t => boot-time, w => runtime

if __name__ == '__main__':
    parser = argparse.ArgumentParser()
    parser.add_argument('--gather', help='gather syctl info', action='store_true')
    parser.add_argument('--values', help='comma-separated list of sysctl values to fetch')
    inputargs = parser.parse_args()

    _cache_filename = "/tmp/sysctl_map.cache"

    if inputargs.values:
        result = {}
        params = inputargs.values.split(',')
        sp = subprocess.run(['/sbin/sysctl', '-i'] + params, capture_output=True, text=True)
        for line in sp.stdout.split("\n"):
            # include original oid in output so caller can match back
            parts = line.strip().split(":")
            if len(parts) > 1:
                result[parts[0]] = parts[1].strip()
        output = ujson.dumps(result)
    elif inputargs.gather:
        if os.path.exists(_cache_filename):
            f = open(_cache_filename, "r")
            print(f.read())
            sys.exit(0)

        result = {}
        sp = subprocess.run(['/sbin/sysctl', '-a'], capture_output=True, text=True)
        for line in sp.stdout.split("\n"):
            parts = line.strip().split(": ", 1)
            if len(parts) > 1:
                item = {'name': parts[0], 'value': parts[1], 'type': 'r', 'description': ''}
                result[parts[0]] = item
        sp = subprocess.run(['/sbin/sysctl', '-ad'], capture_output=True, text=True)
        for line in sp.stdout.split("\n"):
            parts = line.strip().split(": ", 1)
            if len(parts) > 1:
                if parts[0] in result:
                    result[parts[0]].update({'description': parts[1]})
                else:
                    # sysctl entries exist that seem to have no value?
                    item = {'name': parts[0], 'value': '', 'type': 'r', 'description': parts[1]}
                    result[parts[0]] = item
        sp = subprocess.run(['/sbin/sysctl', '-aNT'], capture_output=True, text=True)
        for line in sp.stdout.split("\n"):
            part = line.strip()
            if part in result:
                result[part].update({'type': 't'})
        sp = subprocess.run(['/sbin/sysctl', '-aNW'], capture_output=True, text=True)
        for line in sp.stdout.split("\n"):
            part = line.strip()
            if part in result:
                result[part].update({'type': 'w'})

        output = ujson.dumps(result)

        f = open(_cache_filename, "w")
        f.write(output)
        f.close()

    print (output)
