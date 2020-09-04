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
from netaddr import IPNetwork, IPAddress, AddrFormatError


def iftop(interface, target):
    try:
        sp = subprocess.run(
            ['/usr/local/sbin/iftop', '-nNb', '-i', interface, '-s', '2', '-t'],
            capture_output=True, text=True, timeout=10
        )
        target[interface] = sp.stdout
    except subprocess.TimeoutExpired:
        target[interface] = None

def local_addresses():
    result = []
    sp = subprocess.run(['/sbin/ifconfig'], capture_output=True, text=True)
    for line in sp.stdout.split('\n'):
        if line.find('\tinet') > -1:
            try:
                ip = IPAddress(line.split()[1].split('%')[0])
            except AddrFormatError:
                ip = None

            if ip and not ip.is_loopback() and not ip.is_link_local():
                result.append(ip)
    return result

if __name__ == '__main__':
    result = dict()
    parser = argparse.ArgumentParser()
    parser.add_argument('--interfaces', help='interface(s) to sample')
    cmd_args = parser.parse_args()
    all_local_addresses = local_addresses()
    interfaces = cmd_args.interfaces.split(',')
    iftop_data = dict()
    with ThreadPoolExecutor(max_workers=len(interfaces)) as executor:
        for intf in interfaces:
            executor.submit(iftop, intf.strip(), iftop_data)

    for intf in iftop_data:
        result[intf] = {'in' : [], 'out': []}
        if iftop_data[intf] is None:
            result[intf]['status'] = 'timeout'
            continue
        else:
            result[intf]['status'] = 'ok'

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
                item = {'address': parts[0], 'rate': parts[2], 'rate_bits': rate_bits, 'tags': []}
                # attach tags (type of address)
                try:
                    ip = IPAddress(parts[0])
                    if ip in all_local_addresses:
                        item['tags'].append('local')
                    if ip.is_private():
                        item['tags'].append('private')
                except subprocess.TimeoutExpired:
                    pass

                if parts[1] == '=>':
                    result[intf]['out'].append(item)
                else:
                    result[intf]['in'].append(item)

        for sort_key in ['in', 'out']:
            result[intf][sort_key] = sorted(result[intf][sort_key], key=lambda x: x['rate_bits'], reverse=True)

    print(ujson.dumps(result))
