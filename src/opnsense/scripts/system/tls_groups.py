#!/usr/local/bin/python3

"""
    Copyright (c) 2024 Ad Schellevis <ad@opnsense.org>
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
    return all available dhgroups / curves
"""
import os
import subprocess
import ujson

if __name__ == '__main__':
    result = {}
    # dhgroup params can't be queried from openssl.
    for item in ['ffdhe2048', 'ffdhe3072', 'ffdhe4096', 'ffdhe6144', 'ffdhe8192', 'X25519', 'X448']:
        result[item] = item
    # use opnsense.cnf template to avoid generic config constraints limiting options
    ossl_env = os.environ.copy()
    ossl_env['OPENSSL_CONF'] = '/usr/local/etc/ssl/opnsense.cnf'
    sp = subprocess.run(
        ['/usr/local/bin/openssl', 'ecparam', '-list_curves'],
        capture_output=True,
        text=True,
        env=ossl_env
    )
    for line in sp.stdout.split("\n"):
        if line.startswith('  '):
            tmp = line.strip().split(':')[0].strip()
            result[tmp] = tmp
    print (ujson.dumps(result))
