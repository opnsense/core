#!/usr/local/bin/python3

"""
    Copyright (c) 2022 Deciso B.V.
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

import os
import duckdb
import fcntl
import glob
import shutil


class StorageVersionException(Exception):
    pass


class DbConnection:
    """
    ContextManager wrapper for a DuckDb connection. Use this to synchronize
    access to a DuckDb instance in cases where there is both a writer and
    one or more readers. Since this isn't natively supported by DuckDb,
    the assumption here is that both the reader and the writer can afford
    to block for the duration of an arbitrary operation of its counterpart.
    For example, when a reader has a connection, a writer blocks until the readers drops
    the connection and vice versa. The use of this ContextManager therefore forces
    a connection to only last for the duration of its operation.
    """

    def __init__(self, path, read_only=True):
        self._path = path
        self._read_only = read_only
        self._fd = None
        self.connection = None

    def __enter__(self):
        """
        :return duckdb.DuckDBPyConnection or None if the database doesn't exist
        while in read_only mode
        """
        while self.connection is None:
            try:
                self.connection = duckdb.connect(database=self._path, read_only=self._read_only)

                # Doing any call to now()/get_current_timestamp() etc. in DuckDb will result
                # in a timestamp adjusted for the current time zone. Since we want to store and query
                # UTC at all times also set the database time zone to UTC. This is scoped within the connection.
                self.connection.execute("SET TimeZone='UTC'")
            except duckdb.IOException as e:
                if str(e).find('database file with version number') > -1:
                    # XXX: this is extremely wacky, apparently we are not able to read the current storage version
                    #      via python so we can only watch for an exception... which is the same one for all types
                    raise StorageVersionException(str(e))

                # write-only, but no truncating, so use os-level open
                self._fd = os.open(self._path, os.O_WRONLY)
                # Try to obtain an exclusive lock and block when unable to.
                # Since we aren't able to hold both an flock() and connect()
                # at the same time, we wrap the connection in a try-except
                # and retry if another connect() was done in between
                fcntl.flock(self._fd, fcntl.LOCK_EX)
                fcntl.flock(self._fd, fcntl.LOCK_UN)
            except duckdb.CatalogException:
                # Tried to open a non-existing database in read_only mode.
                return None

        return self

    def __exit__(self, ex_type, ex_value, traceback):
        if self.connection is not None:
            self.connection.close()
        if self._fd is not None:
            os.close(self._fd)

    def table_exists(self, table_name):
        """
        :return True if table exists, else False

        Note: not used with prepared statements, duckdb doesn't support it in this manner.
        """
        if self.connection is None:
            return False

        try:
            self.connection.execute("DESCRIBE %s" % table_name)
        except duckdb.CatalogException:
            return False

        return True



def restore_database(path, target):
    """
    :param path: backup source
    :param target: duckdb target database
    :return: bool success (false when locked)
    """
    lock_fn = "%s/schema.sql" % path.rstrip('/')
    if os.path.isfile(lock_fn):
        with open(lock_fn, 'a+') as lockh:
            try:
                fcntl.flock(lockh, fcntl.LOCK_EX | fcntl.LOCK_NB)
            except IOError:
                # locked
                return False
            if os.path.isfile(target):
                os.remove(target)
            with DbConnection(target, read_only=False) as db:
                db.connection.execute("IMPORT DATABASE '%s';" % path)
    else:
        # import schema not found, raise exception to inform the caller there is no backup
        raise FileNotFoundError(lock_fn)

    return True


def export_database(source, target, owner_uid='root', owner_gid='wheel'):
    """
    :param source: source database
    :param target: target export directory
    :param owner_uid: owner (user)
    :param owner_gid: owner (group)
    """
    with DbConnection(source, read_only=True) as db:
        if db is not None and db.connection is not None:
            os.makedirs(target, mode=0o750, exist_ok=True)
            shutil.chown(target, 'unbound', 'unbound')
            db.connection.execute("EXPORT DATABASE '%s';" % target)
            for filename in glob.glob('%s/*'% target):
                shutil.chown(filename, owner_uid, owner_gid)
            return True

    return False
