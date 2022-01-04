#!/usr/local/bin/python3

"""
    Copyright (c) 2022 Manuel Faux <mfaux@conf.at>
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
"""

import argparse
import sys
import subprocess
import ujson

result = dict()
if __name__ == '__main__':
    # parse input parameter
    parser = argparse.ArgumentParser()
    parser.add_argument('--type', help='key type (rsa, ecdsa, ed25519, ed448)',
        choices=['rsa', 'ecdsa', 'ed25519', 'ed448'], default='rsa')
    parser.add_argument('--size', help='key size (only for rsa and ecdsa)',
        type=int, default=0)
    inputargs = parser.parse_args()

    keytype = inputargs.type
    keysize = inputargs.size
    if keytype == 'rsa':
        if keysize < 512 or keysize > 65536:
            print('Invalid keysize passed')
            sys.exit(1)
    elif keytype == 'ecdsa':
        if keysize != 256 and keysize != 384 and keysize != 521:
            print('Invalid keysize passed')
            sys.exit(1)

    # Generate private key
    spprv = subprocess.run(['/usr/local/sbin/ipsec', 'pki',
        '--gen', '--type', keytype,
        '--size', str(keysize),
        '--outform', 'pem'], capture_output=True, text=True)
    result['privkey'] = spprv.stdout.strip()

    # Extract public key
    sppub = subprocess.run(['/usr/local/sbin/ipsec', 'pki', '--pub', '--outform', 'pem'],
        capture_output=True, text=True, input=result['privkey'])
    result['pubkey'] = sppub.stdout.strip()

    if spprv.returncode != 0 or sppub.returncode != 0:
        sys.exit(1)

print(ujson.dumps(result))
