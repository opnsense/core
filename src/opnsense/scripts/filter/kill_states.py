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
import argparse
import ujson
import subprocess
from lib.states import query_states


if __name__ == '__main__':
    result = dict()
    # parse input arguments
    parser = argparse.ArgumentParser()
    parser.add_argument('--filter', help='filter results', default='')
    parser.add_argument('--label', help='label / rule id', default='')
    inputargs = parser.parse_args()

    # collect all unique state id's
    commands = dict()
    for record in query_states(rule_label=inputargs.label, filter_str=inputargs.filter):
        commands[record['id']] = "/sbin/pfctl -k id -k %s" % record['id']

    # drop list of states in chunks
    chunk_size = 500
    commands  = list(commands.values())
    for chunk in [commands[i:i + chunk_size] for i in range(0, len(commands), chunk_size)]:
        sp = subprocess.run([";\n".join(chunk)], capture_output=True, text=True, shell=True)
    print(ujson.dumps({'dropped_states': len(commands)}))
