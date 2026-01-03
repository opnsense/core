"""
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
import csv
import os

DB_FILE = '/usr/local/opnsense/contrib/ieee/oui.csv'

class OUI:
    _db = None
    def __init__(self):
        # init database with csv file when not populated yet
        if OUI._db is None:
            OUI._db = {}
            with open(DB_FILE, newline='') as f_oui:
                for idx, record in enumerate(csv.reader(f_oui, delimiter=',')):
                    if idx > 0 and len(record) > 2:
                        OUI._db[record[1]] = record[2]

    def get_vendor(self, mac, default=''):
        key = mac.replace(':', '').replace('-', '')[:6].upper()
        if key in OUI._db:
            return OUI._db[key]
        return default

    def get_db(self):
        return OUI._db

    @property
    def st_time(self):
        if os.path.isfile(DB_FILE):
            return os.stat(DB_FILE).st_mtime
