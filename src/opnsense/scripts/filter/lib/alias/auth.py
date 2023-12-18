"""
    Copyright (c) 2023 Ad Schellevis <ad@opnsense.org>
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
from .base import BaseContentParser


class AuthGroup(BaseContentParser):
    _auth_db = None

    @classmethod
    def _update(cls):
        cls._auth_db = {}
        try:
            params = ['/usr/local/opnsense/scripts/auth/list_group_members.php']
            group_members = ujson.loads(subprocess.run(params, capture_output=True, text=True).stdout.strip())
        except (ValueError, FileNotFoundError):
            syslog.syslog(syslog.LOG_ERR, 'error fetching group members (%s)' % " ".join(params))
            return
        try:
            params = ['/usr/local/opnsense/scripts/openvpn/ovpn_status.py', '--options', 'server']
            ovpn_status = ujson.loads(subprocess.run(params, capture_output=True,text=True).stdout.strip())
        except (ValueError, FileNotFoundError):
            syslog.syslog(syslog.LOG_ERR, 'error fetching openvpn clients (%s)' % " ".join(params))
            return

        users = {}
        for server in ovpn_status.get('server', []):
            if server:
                for client in ovpn_status['server'][server].get('client_list', []):
                    if type(client) is dict and 'common_name' in client:
                        if client['common_name'] not in users:
                            users[client['common_name']] = []
                        if client.get('virtual_address', '') != '':
                            users[client['common_name']].append(client.get('virtual_address'))
                        if client.get('virtual_ipv6_address', '') != '':
                            users[client['common_name']].append(client.get('virtual_ipv6_address'))
        for grp in group_members:
            cls._auth_db[grp] = []
            for user in group_members[grp].get('members', []):
                for address in users.get(user, []):
                    cls._auth_db[grp].append(address)

    def iter_addresses(self, group):
        if self._auth_db is None:
            self._update()
        if group in self._auth_db:
            for address in self._auth_db[group]:
                yield address
