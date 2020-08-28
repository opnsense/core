"""
    Copyright (c) 2016 Ad Schellevis <ad@opnsense.org>
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

def check_and_repair(filename_mask, force_repair=False):
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

        # try to perform an integrity_check, triggers a "database disk image is malformed" when corrupted
        # force a repair when corrupted, using a dump / import
        if cur is not None:
            try:
                if force_repair:
                    raise sqlite3.DatabaseError("Requested forced repair")
                cur.execute('pragma integrity_check')
                cur.execute('analyze')
            except sqlite3.DatabaseError as e:
                if str(e).find('malformed') > -1 or force_repair:
                    syslog.syslog(syslog.LOG_ERR, "sqlite3 repair %s" % filename)
                    filename_tmp = '%s.fix'%filename
                    filename_sql = '%s.sql'%filename
                    filename_sql_clean = '%s.clean.sql'%filename
                    for tmp_filename in [filename_tmp, filename_sql, filename_sql_clean]:
                        if os.path.exists(tmp_filename):
                            os.remove(tmp_filename)
                    # export the usable parts from the file to an sql file
                    os.system('echo ".dump" | /usr/local/bin/sqlite3 %s > %s ' % (filename, filename_sql))
                    # remove transaction and error blocks
                    with open(filename_sql, 'r') as f_in:
                        with open(filename_sql_clean, 'w') as f_out:
                            for line in f_in:
                                if line.strip().split(';')[0] not in ('BEGIN TRANSACTION', 'ROLLBACK'):
                                    f_out.write(line)
                    # create a new sqlite3 database
                    os.system('/usr/local/bin/sqlite3 %s < %s ' % (filename_tmp, filename_sql_clean))
                    # cleanup / move new database into place
                    if os.path.exists(filename_tmp):
                        for tmp_filename in [filename, filename_sql, filename_sql_clean]:
                            os.remove(tmp_filename)
                        os.rename(filename_tmp, filename)
                    syslog.syslog(syslog.LOG_ERR, "sqlite3 repair %s [done]" % filename)
