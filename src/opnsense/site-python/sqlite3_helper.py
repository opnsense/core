"""
    Copyright (c) 2016 Ad Schellevis
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
    SQLite3 support functions
"""
import datetime
import glob
import sqlite3
import syslog
import os

def check_and_repair(filename_mask):
    """ check and repair sqlite databases
    :param filename_mask: filenames (glob pattern)
    :return: None
    """
    for filename in glob.glob(filename_mask):
        try:
            conn = sqlite3.connect(filename, detect_types=sqlite3.PARSE_DECLTYPES|sqlite3.PARSE_COLNAMES)
            cur = conn.cursor()
            cur.execute("SELECT name FROM sqlite_master where type = 'table'")
        except sqlite3.DatabaseError:
            # unrecoverable, doesn't look like a database, rename to .bck
            filename_tmp = '%s.%s.bck'%(filename, datetime.datetime.now().strftime("%Y%m%d%H%M%S"))
            syslog.syslog(syslog.LOG_ERR, "sqlite3 %s doesn't look like a database, renamed to %s " % (filename,
                                                                                                       filename_tmp))
            cur = None
            os.rename(filename, filename_tmp)

        # try to vacuum all tables, triggers a "database disk image is malformed" when corrupted
        # force a repair when corrupted, using a dump / import
        if cur is not None:
            try:
                for table in cur.fetchall():
                    cur.execute('vacuum %s' % table[0])
            except sqlite3.DatabaseError, e:
                if e.message.find('malformed') > -1:
                    syslog.syslog(syslog.LOG_ERR, "sqlite3 repair %s" % filename)
                    filename_tmp = '%s.fix'%filename
                    if os.path.exists(filename_tmp):
                        os.remove(filename_tmp)
                    os.system('echo ".dump" | /usr/local/bin/sqlite3 %s | /usr/local/bin/sqlite3 %s' % (filename,
                                                                                                        filename_tmp))
                    if os.path.exists(filename_tmp):
                        os.remove(filename)
                        os.rename(filename_tmp, filename)
