"""
    Copyright (c) 2015-2019 Ad Schellevis <ad@opnsense.org>
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
import subprocess
import ujson
from datetime import datetime

class ARP(object):
    def __init__(self):
        """ construct new arp helper
        :return: None
        """
        self._table = {}
        self.reload()

    def reload(self):
        """ reload / parse arp and ndp tables
        """
        self._table.clear()

        # fetch addresses, no IPv6 if hostwatch disabled
        out = ujson.loads(subprocess.run(
            ['/usr/local/opnsense/scripts/interfaces/list_hosts.py', '--ndp', '--last-seen-window', '86400', '-v'],
            capture_output=True,
            text=True
        ).stdout)

        source = out.get("source")
        rows = out.get("rows", [])

        if source == "discovery":
            rows_iter = sorted(
                rows,
                key=lambda row: datetime.strptime(row[5], "%Y-%m-%d %H:%M:%S"),
                reverse=True
            )
        else:
            rows_iter = rows

        for row in rows_iter:
            ip = row[2]

            entry = {
                "intf": row[0],
                "mac": row[1],
            }

            if source == "discovery":
                entry["first_seen"] = datetime.strptime(row[4], "%Y-%m-%d %H:%M:%S")
                entry["last_seen"]  = datetime.strptime(row[5], "%Y-%m-%d %H:%M:%S")

            self._table[ip] = entry

    def get_by_ipaddress(self, address):
        return self._table.get(address, None)

    def get_all_addresses_by_mac(self, mac_address):
        return [
            ip for ip, data in self._table.items()
            if data['mac'] == mac_address
        ]
