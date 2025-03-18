#!/usr/local/bin/python3

"""
    Copyright (c) 2025 Ad Schellevis <ad@opnsense.org>
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
import json
import subprocess
import sys

valid_modes = {"dhcp", "dhcp6"}
mode = sys.argv[1] if len(sys.argv) > 1 and sys.argv[1] in valid_modes else "dhcp"

result = {}

# not yet registered by name, but pratical to have
# https://www.iana.org/assignments/bootp-dhcp-parameters/bootp-dhcp-parameters.xhtml
if mode == "dhcp":
    result['114'] = 'dhcp captive-portal [114]'

sp = subprocess.run(['/usr/local/sbin/dnsmasq', '--help', mode], capture_output=True, text=True)
for line in sp.stdout.split("\n"):
    parts = line.split(maxsplit=1)
    if len(parts) == 2 and parts[0].isdigit():
        result[parts[0]] = "%s [%s]" % (parts[1], parts[0])

print(json.dumps(result))
