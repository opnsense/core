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
    streams cpu usage
"""

import subprocess
import select
import argparse
import ujson
import re

if __name__ == '__main__':
    parser = argparse.ArgumentParser()
    parser.add_argument('--interval', help='poll interval', default='1')
    inputargs = parser.parse_args()

    process = subprocess.Popen(
        ['iostat', '-w', inputargs.interval, 'cpu'],
        stdout=subprocess.PIPE, stderr=subprocess.PIPE,
        bufsize=0
    )
    read_fds = [process.stdout]

    while True:
        readable, _, _ = select.select(read_fds, [], [])

        for fd in readable:
            data = fd.readline()

            if data:
                output = data.decode().strip()
                if output.startswith("tty") or output.startswith("tin"):
                    continue

                formatted = re.sub(r'\s+', ' ', output).split(" ")[2:]
                formatted = [int(x) for x in formatted]
                result = {
                    'total': sum(formatted) - formatted[4],
                    'user': formatted[0],
                    'nice': formatted[1],
                    'sys': formatted[2],
                    'intr': formatted[3],
                    'idle': formatted[4]
                }
                print(f"event: message\ndata: {ujson.dumps(result)}\n\n", flush=True)
            else:
                read_fds.remove(fd)

        if process.poll() is not None:
            break

    process.stdout.close()
    process.stderr.close()
