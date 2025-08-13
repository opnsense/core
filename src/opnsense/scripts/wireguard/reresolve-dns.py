#!/usr/local/bin/python3

"""
    Copyright (c) 2023 Ad Schellevis <ad@opnsense.org>
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
# Python implementation to re-resolve dns entries, for reference see:
# https://github.com/WireGuard/wireguard-tools/tree/master/contrib/reresolve-dns
import glob
import os
import time
import subprocess



sp = subprocess.run(['/usr/bin/wg', 'show', 'all', 'latest-handshakes'], capture_output=True, text=True)
ts_now = time.time()
handshakes = {}
for line in sp.stdout.split('\n'):
    parts = line.split()
    if len(parts) == 3 and parts[2].isdigit():
        handshakes["%s-%s" % (parts[0], parts[1])] = ts_now - int(parts[2])


for filename in glob.glob('/usr/local/etc/wireguard/*.conf'):
    this_peer = {}
    ifname = os.path.basename(filename).split('.')[0]
    with open(filename, 'r') as fhandle:
        for line in fhandle:
            if line.startswith('[Peer]'):
                this_peer = {'ifname': ifname}
            elif line.startswith('PublicKey'):
                this_peer['PublicKey'] = line.split('=', 1)[1].strip()
            elif line.startswith('Endpoint'):
                this_peer['Endpoint'] = line.split('=', 1)[1].strip()

            if 'Endpoint' in this_peer and 'PublicKey' in this_peer:
                peer_key = "%(ifname)s-%(PublicKey)s" % this_peer
                if handshakes.get(peer_key, 999) > 135:
                    # skip if there has been a handshake recently
                    subprocess.run(
                        [
                            '/usr/bin/wg',
                            'set',
                            ifname,
                            'peer',
                            this_peer['PublicKey'],
                            'endpoint',
                            this_peer['Endpoint']
                        ],
                        capture_output=True,
                        text=True
                    )
                this_peer = {}
