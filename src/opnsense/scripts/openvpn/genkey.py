#!/usr/local/bin/python3

"""
    Copyright (c) 2026 Ad Schellevis <ad@opnsense.org>
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
import base64
import subprocess
import tempfile


if __name__ == '__main__':
    parser = argparse.ArgumentParser()
    parser.add_argument('type', help='type', type=str)
    parser.add_argument('--server_key', help='server key, base64 encoded', type=str)
    args = parser.parse_args()
    with tempfile.NamedTemporaryFile(mode='w') as fh:
        cmd = [
            '/usr/local/sbin/openvpn',
            '--genkey',
            args.type
        ]
        if args.type == 'tls-crypt-v2-client':
            fh.write(base64.b64decode(args.server_key).decode())
            fh.flush()
            cmd.append('--tls-crypt-v2')
            cmd.append(fh.name)

        sp = subprocess.run(cmd, capture_output=True, text=True)
        print(sp.stdout.strip())
