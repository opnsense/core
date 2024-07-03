#!/usr/local/bin/python3

"""
    Copyright (c) 2024 Deciso B.V.
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

    stream log file
"""

import ujson
import argparse
from collections import deque
from log_matcher import LogMatcher

if __name__ == '__main__':
    parser = argparse.ArgumentParser()
    parser.add_argument('--filter', help='filter results', default='')
    parser.add_argument('--offset', help='include last N matching lines', default='')
    parser.add_argument('--filename', help='log file name (excluding .log extension)', default='')
    parser.add_argument('--module', help='module', default='core')
    parser.add_argument('--severity', help='comma separated list of severities', default='')
    inputargs = parser.parse_args()

    result = deque()
    if inputargs.filename == "":
        exit

    offset = int(inputargs.offset) if inputargs.offset.isdigit() else 0

    log_matcher = LogMatcher(inputargs.filter, inputargs.filename, inputargs.module, inputargs.severity)
    if offset > 0:
        for record in log_matcher.match_records():
            if len(result) >= offset:
                break
            result.appendleft(f"event: message\ndata:{ujson.dumps(record)}\n\n")

    for record in result:
        print(record, flush=True)

    for record in log_matcher.live_match_records():
        if record is None:
            # keepalive
            print("event: keepalive\ndata:\n\n", flush=True)
        else:
            print(f"event: log\ndata:{ujson.dumps(record)}\n\n", flush=True)
