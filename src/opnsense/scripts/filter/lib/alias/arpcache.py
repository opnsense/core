"""
    Copyright (c) 2020-2025 Ad Schellevis <ad@opnsense.org>
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
import fcntl
import time
import subprocess
import ujson
from .base import BaseContentParser


class ArpCache(BaseContentParser):
    """ Static arp cache which lives for the duration of a script
    """
    _cache_filename = "/tmp/alias_filter_arp.cache"
    _address_ttl = 43200
    _data = dict()
    _now = time.time()

    @classmethod
    def _update(cls):
        cls._cache_handle = open(cls._cache_filename, 'a+')
        try:
            fcntl.flock(cls._cache_handle, fcntl.LOCK_EX | fcntl.LOCK_NB)
        except IOError:
            # other process is already creating the cache, wait, let the other process do it's work and return.
            fcntl.flock(cls._cache_handle, fcntl.LOCK_EX)
            fcntl.flock(cls._cache_handle, fcntl.LOCK_UN)

        cls._cache_handle.seek(0)
        try:
            cls._data = ujson.loads(cls._cache_handle.read())
        except ValueError:
            cls._data = dict()

        cls._data.update(cls.current_cache())
        for macaddr in list(cls._data):
            if cls._now - cls._data[macaddr]['last_seen'] > cls._address_ttl:
                del cls._data[macaddr]

        # save
        cls._cache_handle.seek(0)
        cls._cache_handle.truncate()
        cls._cache_handle.write(ujson.dumps(cls._data))
        fcntl.flock(cls._cache_handle, fcntl.LOCK_UN)

    @classmethod
    def current_cache(cls):
        """ fetch current arp+ndp items
        """
        current_macs = dict()
        # collect hosts, use hostdiscovery daemon when enabled, otherwise use arp+ndp
        sp = subprocess.run(
            ['/usr/local/opnsense/scripts/interfaces/list_hosts.py', '-n'],
            capture_output=True,
            text=True
        )
        try:
            hosts = ujson.loads(sp.stdout)
        except ValueError:
            hosts = {}

        for item in hosts.get('rows', []):
            if item[1] not in current_macs:
                current_macs[item[1]] = {'items': [], 'last_seen': cls._now}
            current_macs[item[1]]['items'].append(item[2])

        return current_macs

    def __init__(self, **kwargs):
        super().__init__(**kwargs)
        self._now = time.time()
        if len(self._data) == 0:
            self._update()

    def iter_addresses(self, pattern):
        for macaddr in self._data:
            if macaddr.startswith(pattern.lower()):
                for item in self._data[macaddr]['items']:
                    yield item
