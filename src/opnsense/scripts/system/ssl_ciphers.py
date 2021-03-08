#!/usr/local/bin/python3

"""
    Copyright (c) 2016-2020 Ad Schellevis <ad@opnsense.org>
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
    return all available ciphers
"""
import subprocess
import os
import sys
import ujson
import csv
import argparse

if __name__ == '__main__':
    parser = argparse.ArgumentParser()
    parser.add_argument('--format', help='format',choices=['full', 'key_value'], default='full')
    inputargs = parser.parse_args()
    # source https://www.iana.org/assignments/tls-parameters/tls-parameters-4.csv
    rfc5246_file = '%s/rfc5246_cipher_suites.csv' % os.path.dirname(os.path.realpath(__file__))
    rfc5246 = dict()
    if os.path.isfile(rfc5246_file):
        with open(rfc5246_file, 'r') as csvfile:
            for row in csv.reader(csvfile, delimiter=',', quotechar='"'):
                rfc5246[row[0]] = {'description': row[1]}

    result = {}
    sp = subprocess.run(['/usr/local/bin/openssl', 'ciphers', '-V'], capture_output=True, text=True)
    for line in sp.stdout.split("\n"):
        parts = line.strip().split()
        if len(parts) > 1:
            cipher_id = parts[0]
            cipher_key = parts[2]
            item = {'version': parts[3], 'id': cipher_id, 'description': ''}
            for part in parts[4:]:
                item[part.split('=')[0]] = part.split('=')[-1]
            if cipher_id in rfc5246:
                item['description'] = rfc5246[cipher_id]['description']
            result[cipher_key] = item
    if inputargs.format == 'key_value':
        tmp = dict()
        for key in result:
            tmp[key] = result[key]['description'] if result[key]['description'] else key
        print (ujson.dumps(tmp))
    else:
        print (ujson.dumps(result))
