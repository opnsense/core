#!/usr/local/bin/python3

"""
    Copyright (c) 2017-2019 Ad Schellevis <ad@opnsense.org>
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
    list pf byte/packet counter
"""
import tempfile
import subprocess
import os
import sys
import ujson

if __name__ == '__main__':
    result = dict()
    sp = subprocess.run(['/sbin/pfctl', '-vvsI'], capture_output=True, text=True)
    intf = None
    for line in sp.stdout.strip().split('\n'):
        if line.find('[') == -1  and line[0] not in (' ', '\t'):
            intf = line.strip()
            result[intf] = {'inbytespass': 0, 'outbytespass': 0, 'inpktspass': 0, 'outpktspass': 0,
                            'inbytesblock': 0, 'outbytesblock': 0, 'inpktsblock': 0, 'outpktsblock': 0,
                            'inpkts':0, 'inbytes': 0, 'outpkts': 0, 'outbytes': 0}
        if intf is  not None and line.find('[') > -1:
            packets = int(line.split(' Packets:')[-1].strip().split()[0])
            bytes = int(line.split(' Bytes:')[-1].strip().split()[0])
            if line.find('In4/Pass:') > -1 or line.find('In6/Pass:') > -1:
                result[intf]['inpktspass'] += packets
                result[intf]['inbytespass'] += bytes
                result[intf]['inpkts'] += packets
                result[intf]['inbytes'] += bytes
            elif line.find('In4/Block:') > -1 or line.find('In6/Block:') > -1:
                result[intf]['inbytesblock'] += packets
                result[intf]['inpktsblock'] += bytes
                result[intf]['inpkts'] += packets
                result[intf]['inbytes'] += bytes
            elif line.find('Out4/Pass:') > -1 or line.find('Out6/Pass:') > -1:
                result[intf]['outpktspass'] += packets
                result[intf]['outbytespass'] += bytes
                result[intf]['outpkts'] += packets
                result[intf]['outbytes'] += bytes
            elif line.find('Out4/Block:') > -1 or line.find('Out6/Block:') > -1:
                result[intf]['outpktsblock'] += packets
                result[intf]['outbytesblock'] += bytes
                result[intf]['outpkts'] += packets
                result[intf]['outbytes'] += bytes

    # handle command line argument (type selection)
    if len(sys.argv) > 1 and sys.argv[1] == 'json':
        print(ujson.dumps(result))
    else:
        # output plain
        print('------------------------- COUNTERS -------------------------')
        for intf in result:
            print('[%s] %s' % (intf, result[intf]))
