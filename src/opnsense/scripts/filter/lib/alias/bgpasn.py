"""
    Copyright (c) 2022 Ad Schellevis <ad@opnsense.org>
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
import csv
import fcntl
import time
import os
import syslog
import gzip
import requests
from .base import BaseContentParser


class BGPASN(BaseContentParser):
    _asn_source = 'https://rulesets.opnsense.org/alias/asn.gz'      # source for ASN administration
    _asn_filename = '/usr/local/share/bgp/asn.csv'                  # local copy
    _asn_ttl =  (86400 - 90)                                        # validity in seconds of the local copy
    _asn_fhandle = None                                             # file handle to local copy
    _asn_db = {}                                                    # cache

    @classmethod
    def _update(cls):
        do_update = True
        if os.path.isfile(cls._asn_filename):
            fstat = os.stat(cls._asn_filename)
            if (time.time() - fstat.st_mtime) < cls._asn_ttl and fstat.st_size > 1024:
                do_update = False
        if do_update:
            if not os.path.exists(os.path.dirname(cls._asn_filename)):
                os.makedirs(os.path.dirname(cls._asn_filename))
            cls._asn_fhandle = open(cls._asn_filename, 'a+')
            try:
                fcntl.flock(cls._asn_fhandle, fcntl.LOCK_EX | fcntl.LOCK_NB)
            except IOError:
                # other process is already creating the cache, wait, let the other process do it's work and return.
                fcntl.flock(cls._asn_fhandle, fcntl.LOCK_EX)
                fcntl.flock(cls._asn_fhandle, fcntl.LOCK_UN)
                return

            req = requests.get(url=cls._asn_source, stream=True, timeout=20)
            if req.status_code == 200:
                gf = gzip.GzipFile(mode='r', fileobj=req.raw)
                cls._asn_fhandle.seek(0)
                cls._asn_fhandle.truncate()
                count = 0
                for line in gf:
                    parts = line.decode().strip().split()
                    if len(parts) == 2:
                        cls._asn_fhandle.write("%s,%s\n" % tuple(parts))
                        count += 1
                fcntl.flock(cls._asn_fhandle, fcntl.LOCK_UN)
                syslog.syslog(syslog.LOG_NOTICE, 'dowloaded ASN list (%d entries)' % count)
            else:
                syslog.syslog(
                    syslog.LOG_ERR,
                    'error fetching BGP ASN url %s [http_code:%s]' % (cls._asn_source, req.status_code)
                )
                raise IOError('error fetching BGP ASN url %s' % cls._asn_source)
        else:
            cls._asn_fhandle = open(cls._asn_filename, 'rt')

    def __init__(self, proto='IPv4', **kwargs):
        super().__init__(**kwargs)
        self.proto = proto.split(',')
        if self._asn_fhandle is None:
            # update local asn list if needed, return a file pointer to a local csv file for reading
            self._update()

    def iter_addresses(self, asn):
        if len(self._asn_db) == 0:
            self._asn_fhandle.seek(0)
            for row in csv.reader(self._asn_fhandle, delimiter=',', quotechar='"'):
                if len(row) == 2:
                    if row[1] not in self._asn_db:
                        self._asn_db[row[1]] = []
                    self._asn_db[row[1]].append(row[0])

        if asn in self._asn_db:
            for address in self._asn_db[asn]:
                if 'IPv4' in self.proto and address.find(':') == -1:
                    yield address
                elif 'IPv6' in self.proto and address.find(':') > -1:
                    yield address
