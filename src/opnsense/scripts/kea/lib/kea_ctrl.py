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

import ujson
import os
import socket

class KeaCtrl:
    @staticmethod
    def send_command(command, args = None, service = "dhcp4"):
        path = ""
        if service == "dhcp4":
            path = "/var/run/kea/kea4-ctrl-socket"
        elif service == "dhcp6":
            path = "/var/run/kea/kea6-ctrl-socket"

        if not os.path.exists(path):
            return {"result": 1, "text": f"kea-{service} control socket path \"{path}\" does not exist"}

        payload = {"command": command}
        if args is not None:
            payload["arguments"] = args

        try:
            with socket.socket(socket.AF_UNIX, socket.SOCK_STREAM) as sock:
                sock.settimeout(5.0)
                sock.connect(path)
                sock.sendall(ujson.dumps(payload).encode("utf-8") + b'\n')
                buffer = []
                while True:
                    chunk = sock.recv(4096)
                    if not chunk:
                        break
                    buffer.append(chunk)
                return ujson.loads(b''.join(buffer).decode('utf-8'))
        except (OSError, ConnectionError, socket.timeout) as e:
            return {"result": 1, "text": f"kea-{service} command \"{command}\" failed: {e}"}
