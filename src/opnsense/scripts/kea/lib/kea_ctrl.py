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

import os
import ujson
import json
import socket

KEA4_PATH = "/var/run/kea/kea4-ctrl-socket"
KEA6_PATH = "/var/run/kea/kea6-ctrl-socket"

class KeaCtrl:
    def __init__(self):
        self.socket4 = None
        self.socket6 = None
        self.decoder = json.JSONDecoder()

    def _connect(self, service="dhcp4"):
        if service == "dhcp4" and self.socket4 is None and os.path.exists(KEA4_PATH):
            try:
                self.socket4 = socket.socket(socket.AF_UNIX, socket.SOCK_STREAM)
                self.socket4.connect(KEA4_PATH)
                return True
            except:
                return False
        if service == "dhcp6" and self.socket6 is None and os.path.exists(KEA6_PATH):
            try:
                self.socket6 = socket.socket(socket.AF_UNIX, socket.SOCK_STREAM)
                self.socket6.connect(KEA6_PATH)
                return True
            except:
                return False

        return False
    
    def _close(self):
        if self.socket4 is not None:
            self.socket4.close()
            self.socket4 = None
        if self.socket6 is not None:
            self.socket6.close()
            self.socket6 = None

    def _json_recv(self, service="dhcp4"):
        buffer = ""
        sock = self.socket4 if service == "dhcp4" else self.socket6
        while True:
            chunk = sock.recv(4096)
            if not chunk:
                raise ConnectionError("Socket closed before full JSON was received")
            buffer += chunk.decode("utf-8")
            try:
                obj, _ = self.decoder.raw_decode(buffer)
                return obj
            except json.JSONDecodeError:
                continue
        
    def send_command(self, command, args=None, service="dhcp4"):
        result = {}
        if not self._connect(service):
            return result
        sock = self.socket4 if service == "dhcp4" else self.socket6
        cmd = {"command": command}
        if args is not None:
            cmd["arguments"] = args
        sock.settimeout(5.0)
        sock.sendall(ujson.dumps(cmd).encode() + b"\n")
        try:
            result = self._json_recv(service)
        except (ConnectionError, socket.timeout):
            pass

        self._close()
        return result
