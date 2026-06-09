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

import subprocess
import syslog
import ujson

from lib.kea_ctrl import KeaCtrl


if __name__ == "__main__":
    syslog.openlog("kea-dhcp6", facility=syslog.LOG_LOCAL4)
    result = {"status": "ok"}

    try:
        config = KeaCtrl.send_command("config-get", None, "dhcp6")
        subnet6 = config.get("arguments", {}).get("Dhcp6", {}).get("subnet6", [])

        for subnet in subnet6:
            if subnet.get("id") is not None and subnet.get("user-context", {}).get("dynamic_prefix") is True:
                subnet_id = int(subnet.get("id"))
                KeaCtrl.send_command("lease6-wipe", {"subnet-id": subnet_id}, "dhcp6")

    except Exception as e:
        result["status"] = "failed"
        syslog.syslog(syslog.LOG_ERR, "failed wiping dynamic prefix leases: %s" % e)

    try:
        failed = subprocess.run(["pluginctl", "-c", "kea_generate_dhcpv6"], check=False, capture_output=True).returncode != 0

    except Exception as e:
        failed = True
        syslog.syslog(syslog.LOG_ERR, "failed generating Kea configuration: %s" % e)

    if failed:
        result["status"] = "failed"

    try:
        KeaCtrl.send_command("config-reload", None, "dhcp6")

    except Exception as e:
        result["status"] = "failed"
        syslog.syslog(syslog.LOG_ERR, "failed config-reload of Kea DHCPv6 configuration: %s" % e)

    print(ujson.dumps(result))
