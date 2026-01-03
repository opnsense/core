#!/usr/local/bin/python3

"""
    Copyright (c) 2025 Deciso B.V.
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
    Synchronize firewall heads ordering. This script should only be called after
    ipfw has been enabled.
"""

import subprocess
import sys

if __name__ == '__main__':
    try:
        val = subprocess.run(['/sbin/sysctl', 'net.inet.ip.fw.enable'], capture_output=True, text=True, check=True)
        enabled = val.stdout.strip().split(':')[1].strip()
        if enabled != '1':
            # ipfw not enabled, this script shouldn't have been called
            sys.exit()
    except subprocess.CalledProcessError as e:
        # module not loaded or different generic error
        sys.exit()

    sp = subprocess.run(['/sbin/pfilctl', 'heads'], capture_output=True, text=True)

    ipv6_hooks, ipv4_hooks = {'in': [], 'out': []}, {'in': [], 'out': []}
    section = None

    for line in sp.stdout.splitlines():
        stripped = line.strip()
        parts = stripped.split()

        if "inet6" in stripped:
            section = "ipv6"
            continue
        elif "inet" in stripped:
            section = "ipv4"
            continue

        if section and parts and parts[0] in ("In", "Out"):
            dir = parts[0].lower()
            fw = parts[1].split(':')[0]
            if section == "ipv6":
                ipv6_hooks[dir].append(fw)
            elif section == "ipv4":
                ipv4_hooks[dir].append(fw)
        else:
            section = None

    cmds = []

    for ver, hooks in (("4", ipv4_hooks), ("6", ipv6_hooks)):
        fam    = "ipfw:default" + ("" if ver == "4" else "6")
        proto  = "inet" + ("" if ver == "4" else "6")
        for dir_flag, opt in (("in", "-i"), ("out", "-o")):
            lst = hooks[dir_flag]
            has = "ipfw" in lst
            # if absent, we need to append
            if not has:
                cmds.append(["link", f"-a{dir_flag[0]}", fam, proto])
            else:
                # if exactly two entries and ipfw is at the wrong spot, re‐order
                idx = 0 if dir_flag == "in"  else 1
                if len(lst) == 2 and lst[idx] == "ipfw":
                    # first unlink, then link
                    cmds.append(["unlink", opt, fam, proto])
                    cmds.append(["link",   f"-a{dir_flag[0]}", fam, proto])

    for args in cmds:
        full = ["/sbin/pfilctl"] + args
        try:
            subprocess.run(full, capture_output=True, text=True, check=True)
        except subprocess.CalledProcessError as e:
            # XXX logging, but where?
            pass
