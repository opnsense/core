"""
    Copyright (c) 2016-2018 Ad Schellevis <ad@opnsense.org>
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
    aggregate flow data (format in parse.py) into sqlite structured container per type/resolution.
    Implementations are collected in lib\aggregates\
"""
import os
import datetime
import sqlite3


def convert_timestamp(val):
    """ convert timestamps from string (internal sqlite type) or seconds since epoch
    """
    if val.find(b'-') > -1:
        # formatted date/time
        if val.find(b" ") > -1:
            datepart, timepart = val.split(b" ")
        else:
            datepart = val
            timepart = b"0:0:0,0"
        year, month, day = list(map(int, datepart.split(b"-")))
        timepart_full = timepart.split(b".")
        hours, minutes, seconds = list(map(int, timepart_full[0].split(b":")))
        if len(timepart_full) == 2:
            microseconds = int('{:0<6.6}'.format(timepart_full[1].decode()))
        else:
            microseconds = 0

        val = datetime.datetime(year, month, day, hours, minutes, seconds, microseconds)
    else:
        # timestamp stored as seconds since epoch, convert to utc
        val = datetime.datetime.utcfromtimestamp(float(val))

    return val


sqlite3.register_converter('timestamp', convert_timestamp)


class AggMetadata(object):
    """ store some metadata needed to keep track of parse progress
    """
    def __init__(self, database_dir='/var/netflow'):
        self._filename = '%s/metadata.sqlite' % database_dir
        # make sure the target directory exists
        target_path = os.path.dirname(self._filename)
        if not os.path.isdir(target_path):
            os.makedirs(target_path)
        # open sqlite database and cursor
        self._db_connection = sqlite3.connect(self._filename, timeout=60,
                                              detect_types=sqlite3.PARSE_DECLTYPES | sqlite3.PARSE_COLNAMES)
        self._db_cursor = self._db_connection.cursor()
        # known tables
        self._tables = list()
        # cache known tables
        self._update_known_tables()

    def __del__(self):
        """ close database on destruct
        :return: None
        """
        if self._db_connection is not None:
            self._db_connection.close()

    def _update_known_tables(self):
        """ request known tables
        """
        self._db_cursor.execute('SELECT name FROM sqlite_master')
        for record in self._db_cursor.fetchall():
            self._tables.append(record[0])

    def update_sync_time(self, timestamp):
        """ update (last) sync timestamp
        """
        if 'sync_timestamp' not in self._tables:
            self._db_cursor.execute('create table sync_timestamp(mtime timestamp)')
            self._db_cursor.execute('insert into sync_timestamp(mtime) values(0)')
            self._db_connection.commit()
            self._update_known_tables()
        # update last sync timestamp, if this date > timestamp
        self._db_cursor.execute('update sync_timestamp set mtime = :mtime where mtime < :mtime', {'mtime': timestamp})
        self._db_connection.commit()

    def last_sync(self):
        if 'sync_timestamp' not in self._tables:
            return 0.0
        else:
            self._db_cursor.execute('select max(mtime) from sync_timestamp')
            return self._db_cursor.fetchall()[0][0]
