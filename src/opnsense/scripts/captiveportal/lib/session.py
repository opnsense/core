"""
    Copyright (c) 2026 Eugene Malakhov <eugeniymalakhov@gmail.com>
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
    Helpers for reconciling captive portal session identity.
"""


def client_mac_changed(db, arp, zoneid, db_client):
    """Return true only when two known MAC addresses differ.

    A client may be authorized before host discovery learns its MAC address.
    The first observed MAC belongs to that session and should be persisted,
    rather than treated as an identity change.
    """
    current_arp = arp.get_by_ipaddress(db_client['ipAddress'])
    if current_arp is None:
        return False

    current_mac = current_arp['mac']
    if not db_client['macAddress']:
        db.update_client_mac(zoneid, db_client['sessionId'], current_mac)
        db_client['macAddress'] = current_mac
        return False

    return current_mac != db_client['macAddress']
