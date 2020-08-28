"""
    Copyright (c) 2020 Ad Schellevis <ad@opnsense.org>
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


class ArpCache:
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

        current_cache = cls.current_cache()
        cls._data.update(current_cache)
        used_addresses = set()
        for macaddr in cls._data:
            if cls._now  == cls._data[macaddr]['last_seen']:
                for address in cls._data[macaddr]['items']:
                    used_addresses.add(address)
        for macaddr in list(cls._data):
            if cls._now - cls._data[macaddr]['last_seen'] > cls._address_ttl:
                # expired
                del cls._data[macaddr]
            elif cls._now != cls._data[macaddr]['last_seen']:
                # reused within expiry
                for ix in reversed(range(len(cls._data[macaddr]['items']))):
                    if cls._data[macaddr]['items'][ix] in used_addresses:
                        del cls._data[macaddr]['items'][ix]
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
        sp = subprocess.run(['/usr/sbin/ndp', '-an'], capture_output=True, text=True)
        for line in sp.stdout.split('\n')[1:]:
            line_parts = line.split()
            if len(line_parts) > 3 and line_parts[1] != '(incomplete)':
                if line_parts[1] not in current_macs:
                    current_macs[line_parts[1]] = {'items': []}
                current_macs[line_parts[1]]['last_seen'] = cls._now
                current_macs[line_parts[1]]['items'].append(line_parts[0].split('%')[0])

        sp = subprocess.run(['/usr/sbin/arp', '-an', '--libxo','json'], capture_output=True, text=True)
        libxo_out = ujson.loads(sp.stdout)
        if 'arp' in libxo_out and 'arp-cache' in libxo_out['arp']:
            for rec in libxo_out['arp']['arp-cache']:
                if 'incomplete' in rec and rec['incomplete'] is True:
                    continue
                if  rec['mac-address'] not in current_macs:
                    current_macs[rec['mac-address']] = {'items': []}
                current_macs[rec['mac-address']]['last_seen'] = cls._now
                current_macs[rec['mac-address']]['items'].append(rec['ip-address'])

        return current_macs

    def __init__(self):
        self._now = time.time()
        if len(self._data) == 0:
            self._update()

    def iter_addresses(self, pattern):
        for macaddr in self._data:
            if macaddr.startswith(pattern.lower()):
                for item in self._data[macaddr]['items']:
                    yield item
