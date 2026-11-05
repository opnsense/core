#!/usr/local/bin/python3

"""
    Copyright (c) 2015-2025 Ad Schellevis <ad@opnsense.org>
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
    allow user/host to captive portal
"""
import argparse
import sys
import syslog
import time
import ujson
from lib.db import DB
from lib.arp import ARP
from lib.pf import PF, PFError
from lib.ipfw import IPFW, IPFWError


def find_client_mac(arp, ip_address, retry_delays=(0, 0.1, 0.3, 0.7), sleep=time.sleep):
    """Allow host discovery a short bounded window to learn the client MAC."""
    for retry_delay in retry_delays:
        if retry_delay:
            sleep(retry_delay)
            arp.reload()
        arp_entry = arp.get_by_ipaddress(ip_address)
        if arp_entry is not None:
            return arp_entry['mac']
    return None


def authorize_client(zoneid, username, authenticated_via, ip_address, db=None, arp=None):
    """Create a session only when its PF authorization can be applied."""
    db = db if db is not None else DB()
    arp = arp if arp is not None else ARP()
    response = db.add_client(
        zoneid=zoneid,
        authenticated_via=authenticated_via,
        username=username,
        ip_address=ip_address,
        mac_address=find_client_mac(arp, ip_address)
    )

    try:
        PF.add_to_table(zoneid=zoneid, address=ip_address)
    except PFError as error:
        db.del_client(zoneid, response['sessionId'], 'Admin-Reset')
        syslog.syslog(syslog.LOG_ERR, "unable to add captive portal client to PF: %s" % error)
        return {'clientState': 'UNKNOWN', 'zoneid': zoneid, 'ipAddress': ip_address}

    try:
        IPFW.add_accounting(zoneid, {ip_address})
    except IPFWError as error:
        # PF controls access. Keep the authorized session and let the background
        # process retry the accounting setup during its next reconciliation.
        syslog.syslog(syslog.LOG_ERR, "unable to add captive portal accounting: %s" % error)

    response['clientState'] = 'AUTHORIZED'
    return response


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('--username', help='username', type=str, required=True)
    parser.add_argument('--zoneid', help='zone number to allow this user in', type=str, required=True)
    parser.add_argument('--authenticated_via', help='authentication source', type=str)
    parser.add_argument('--ip_address', help='source ip address', type=str)
    args = parser.parse_args()

    print(ujson.dumps(authorize_client(
        zoneid=args.zoneid,
        username=args.username,
        authenticated_via=args.authenticated_via,
        ip_address=args.ip_address
    )))
    return 0


if __name__ == '__main__':
    sys.exit(main())
