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
    list device interrupts stats
"""
import subprocess
import sys
import ujson

if __name__ == '__main__':
    result = dict()
    sp = subprocess.run(['/usr/bin/vmstat', '-i'], capture_output=True, text=True)
    intf = None
    interrupts = dict()
    interrupt_map = dict()
    for line in sp.stdout.split("\n"):
        if line.find(':') > -1:
            intrp = line.split(':')[0].strip()
            parts = ':'.join(line.split(':')[1:]).split()
            interrupts[intrp] = {'devices': [], 'total': None, 'rate': None}
            for part in parts:
                if not part.isdigit():
                    interrupts[intrp]['devices'].append(part)
                    devnm = part.split(':')[0]
                    if devnm not in interrupt_map:
                        interrupt_map[devnm] = list()
                    interrupt_map[devnm].append(intrp)
                elif interrupts[intrp]['total'] is None:
                    interrupts[intrp]['total'] = int(part)
                else:
                    interrupts[intrp]['rate'] = int(part)
        result['interrupts'] = interrupts # interrupts as reported by vmstat
        result['interrupt_map'] = interrupt_map # link device to interrupt

    # handle command line argument (type selection)
    if len(sys.argv) > 1 and sys.argv[1] == 'json':
        print(ujson.dumps(result))
    else:
        # output plain
        if 'interrupts' in result:
            for intr in result['interrupts']:
                print ('%-10s [%-20s] %-10d %d' % (intr,
                                          ','.join(result['interrupts'][intr]['devices']),
                                          result['interrupts'][intr]['total'],
                                          result['interrupts'][intr]['rate']
                                          ))
