#!/usr/local/bin/python3

"""
    Copyright (c) 2026 Deciso B.V.
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
import ipaddress
import time
import ujson
import subprocess
import syslog

from lib.kea_ctrl import KeaCtrl


def yield_lease_records(service='dhcp6', limit=256, poll_interval=10):
    """
    Poll KEA lease pages and yield records from domain socket
    https://kea.readthedocs.io/en/latest/api.html#lease6-get-page
    Since amount of leases can be large, using get-page is recommended.
    """
    while True:
        from_addr = "start"
        while True:
            response = KeaCtrl.send_command('lease6-get-page', {"from": from_addr, "limit": limit}, service)
            leases = response.get("arguments", {}).get("leases", [])
            if not leases:
                break

            for lease in leases:
                if lease.get("type") == "IA_PD":
                    yield {
                        "address": lease.get("ip-address", ""),
                        "prefix_len": lease.get("prefix-len", 128),
                        "hwaddr": lease.get("hw-address", ""),
                        "state": lease.get("state", 0)
                    }

            last_addr = leases[-1].get("ip-address")
            if len(leases) < limit or not last_addr:
                break
            from_addr = last_addr

        time.sleep(poll_interval)


class Hostwatch:
    """
    Use hostwatch service to gather link-local IPv6 addresses.
    The endpoint falls back to NDP when hostwatch service is not running.
    """
    def __init__(self):
        self._def_local_db = {}

    def reload(self):
        self._def_local_db.clear()

        out = subprocess.run(
            ['/usr/local/opnsense/scripts/interfaces/list_hosts.py', '--proto', 'inet6', '--ndp'],
            capture_output=True,
            text=True
        ).stdout

        for row in ujson.loads(out).get("rows", []):
            # [ifname, mac, ip]
            if ipaddress.ip_address(row[2]).is_link_local:
                # link local requires scope ID here, otherwise route add will fail
                self._def_local_db[row[1]] = f"{row[2]}%{row[0]}"

    def get(self, mac):
        if mac not in self._def_local_db:
            self.reload()

        if mac in self._def_local_db:
            return self._def_local_db[mac]


if __name__ == '__main__':
    prefixes = {}
    syslog.openlog('kea-dhcp6', facility=syslog.LOG_LOCAL4)
    syslog.syslog(syslog.LOG_NOTICE, "startup kea prefix watcher")
    hostwatch = Hostwatch()
    for record in yield_lease_records():
        # IA_PD: guaranteed via "type = IA_PD"
        prefix = "%(address)s/%(prefix_len)d" %  record
        if (prefix not in prefixes or prefixes[prefix].get('hwaddr') != record.get('hwaddr')) \
                and record.get('state', 0) == 0:
            prefixes[prefix] = record
            ll_addr = hostwatch.get(record.get('hwaddr'))
            if not ll_addr:
                syslog.syslog(
                    syslog.LOG_WARNING,
                    "no LLA found for %s, skipping route %s" % (record.get('hwaddr'), prefix)
                )
                continue
            # lazy drop
            subprocess.run(['/sbin/route', 'delete', '-inet6', prefix], capture_output=True)
            # https://kea.readthedocs.io/en/latest/arm/hooks.html#the-lease4-get-by-lease6-get-by-commands
            # state names (default (or assigned) (0), declined (1), expired-reclaimed (2), released (3), and registered (4))
            if record.get('state', 0) == 0:
                # only add when still valid
                if subprocess.run(['/sbin/route', 'add', '-inet6', prefix, ll_addr], capture_output=True).returncode:
                    syslog.syslog(syslog.LOG_ERR, "failed adding route %s -> %s" % (prefix, ll_addr))
                else:
                    syslog.syslog(syslog.LOG_NOTICE, "add route %s -> %s" % (prefix, ll_addr))
