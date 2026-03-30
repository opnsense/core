#!/usr/local/bin/python3

"""
    Copyright (c) 2026 Deciso B.V.
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
import os
import ujson

from lib.kea_ctrl import KeaCtrl

if __name__ == '__main__':
    parser = argparse.ArgumentParser()
    parser.add_argument("ip", help="IP address(es) to delete, comma separated")
    ips = [ip.strip() for ip in parser.parse_args().ip.split(',') if ip.strip()]

    results = []

    for ip in ips:
        is_v6 = ":" in ip
        cmd = "lease6-del" if is_v6 else "lease4-del"
        service = "dhcp6" if is_v6 else "dhcp4"

        result = KeaCtrl.send_command(cmd, {"ip-address": ip}, service)
        result["ip"] = ip
        results.append(result)

    print(ujson.dumps(results))
