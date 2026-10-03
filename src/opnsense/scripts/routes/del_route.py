#!/usr/local/bin/python3

"""
    Copyright (c) 2019 Ad Schellevis <ad@opnsense.org>
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
    manually delete a static route, when found in the routing table (by number or name)
"""
import subprocess
import sys
import ujson
import argparse
import ipaddress


if __name__ == '__main__':
    # parse input arguments
    parser = argparse.ArgumentParser()
    parser.add_argument('--family', help='IP address family', required=True)
    parser.add_argument('--destination', help='Route destination to remove', required=True)
    parser.add_argument('--gateway', help='Match gateway or none for host route')
    parser.add_argument('--names', help='Resolve names')
    inputargs = parser.parse_args()

    inet = '-6' if inputargs.family == 'ipv6' else '-4'
    flags = '-rW'

    if inputargs.names:
       flags += 'n'

    sp = subprocess.run(['/usr/bin/netstat', inet, flags], capture_output=True, text=True)
    for line in sp.stdout.split("\n"):
        parts = line.split()
        if len(parts) <= 2:
            continue
        if parts[0] != inputargs.destination:
            continue;

        if not inputargs.gateway:
            subprocess.run(['/sbin/route', 'delete', '-host', destination], capture_output=True)
        elif parts[1] == inputargs.gateway:
            # route entry found, try to delete
            try:
                ipaddress.ip_address(inputargs.gateway)
                # gateway is an ip address (v4/v6)
                subprocess.run(['/sbin/route', 'delete', inet, inputargs.destination, inputargs.gateway, capture_output=True)
            except ValueError:
                subprocess.run(['/sbin/route', 'delete', inet, inputargs.destination], capture_output=True)
        else:
            continue

        # found
        sys.exit(0)

    # not found
    sys.exit(1)
