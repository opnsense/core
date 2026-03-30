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

import json
import os
import socket
import syslog

KEA4_PATH = "/var/run/kea/kea4-ctrl-socket"
KEA6_PATH = "/var/run/kea/kea6-ctrl-socket"

class KeaCtrl:
    @staticmethod
    def _socket_path(service):
        if service == "dhcp4":
            return KEA4_PATH
        if service == "dhcp6":
            return KEA6_PATH
        return ""

    @staticmethod
    def _json_recv(sock):
        # kea source code doesn't indicate command responses are properly newline-terminated
        # so assume we need a full valid JSON structure here.
        decoder = json.JSONDecoder()
        buffer = ""

        while True:
            chunk = sock.recv(4096)
            if not chunk:
                raise ConnectionError("Socket closed before full JSON was received")

            buffer += chunk.decode("utf-8")

            try:
                obj, _ = decoder.raw_decode(buffer)
                return obj
            except json.JSONDecodeError:
                continue

    @staticmethod
    def send_command(command, args = None, service = "dhcp4", timeout = 5.0):
        path = KeaCtrl._socket_path(service)
        syslog.openlog("kea-%s" % service, facility=syslog.LOG_LOCAL4)
        if not os.path.exists(path):
            syslog.syslog(syslog.LOG_WARNING, f"kea_ctrl.py: kea-{service} control socket path \"{path}\" does not exist")
            return {"result": 1}

        payload  = {"command": command}
        if args is not None:
            payload["arguments"] = args

        try:
            with socket.socket(socket.AF_UNIX, socket.SOCK_STREAM) as sock:
                sock.settimeout(timeout)
                sock.connect(path)
                sock.sendall(json.dumps(payload).encode("utf-8") + b"\n")
                response = KeaCtrl._json_recv(sock)
                return response
        except (OSError, ConnectionError, socket.timeout) as e:
            syslog.syslog(syslog.LOG_ERR, f"kea_ctrl.py: kea-{service} command \"{command}\" failed: {e}")
            return {"result": 1}
