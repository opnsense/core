#!/usr/local/bin/python3

"""
    Copyright (c) 2020 Ad Schellevis <ad@opnsense.org>
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
import decimal
import subprocess
import os
import sys
import ujson
import netaddr
from concurrent.futures import ThreadPoolExecutor


def iftop(interface, target):
    try:
        sp = subprocess.run(
            ['/usr/local/sbin/iftop', '-nNb', '-i', interface, '-s', '2', '-t'],
            capture_output=True, text=True, timeout=10
        )
        target[interface] = sp.stdout
    except subprocess.TimeoutExpired:
        target[interface] = ""


if __name__ == '__main__':
    result = dict()
    parser = argparse.ArgumentParser()
    parser.add_argument('--interfaces', help='interface(s) to sample', default='lo0')
    cmd_args = parser.parse_args()
    interfaces = cmd_args.interfaces.split(',')
    iftop_data = dict()
    with ThreadPoolExecutor(max_workers=len(interfaces)) as executor:
        for intf in interfaces:
            executor.submit(iftop, intf.strip(), iftop_data)

    for intf in iftop_data:
        result[intf] = {'in' : [], 'out': []}
        for line in iftop_data[intf].split('\n'):
            if line.find('=>') > -1 or line.find('<=') > -1:
                parts = line.split()
                if parts[0].find('.') == -1 and parts[0].find(':') == -1:
                    parts.pop(0)
                rate_bits = 0
                if parts[2].endswith('Kb'):
                    rate_bits = decimal.Decimal(parts[2][:-2]) * 1000
                elif parts[2].endswith('Mb'):
                    rate_bits = decimal.Decimal(parts[2][:-2]) * 1000000
                elif parts[2].endswith('Gb'):
                    rate_bits = decimal.Decimal(parts[2][:-2]) * 1000000000
                elif parts[2].endswith('b') and parts[2][:-1].isdigit():
                    rate_bits = decimal.Decimal(parts[2][:-1])
                item = {'address': parts[0], 'rate': parts[2], 'rate_bits': rate_bits}
                if parts[1] == '=>':
                    result[intf]['out'].append(item)
                else:
                    result[intf]['in'].append(item)

    print(ujson.dumps(result))
