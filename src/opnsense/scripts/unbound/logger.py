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

import sys
import selectors
import argparse
import syslog
import time
import datetime
import pandas
from collections import deque
sys.path.insert(0, "/usr/local/opnsense/site-python")
from daemonize import Daemonize
from duckdb_helper import DbConnection

class DNSReader:
    def __init__(self, flush_interval):
        self.timer = 0
        self.flush_interval = flush_interval
        self.count = 0
        self.buffer = deque()

        with DbConnection('/var/unbound/data/unbound.duckdb', read_only=False) as db:
            db.connection.execute("""
                CREATE TABLE IF NOT EXISTS query (
                    time INTEGER,
                    client TEXT,
                    family TEXT,
                    type TEXT,
                    domain TEXT,
                    action INTEGER,
                    response_type INTEGER,
                    blocklist TEXT
                )
            """)

            for size in [300, 60]:
                db.connection.execute(
                    """
                        CREATE OR REPLACE VIEW v_time_series_{min}min AS (
                            SELECT
                                epoch(to_timestamp(epoch(now()) - epoch(now()) % {intv}) -
                                    (i.generate_series * INTERVAL {min} MINUTE)) as start_timestamp,
                                epoch(to_timestamp(epoch(now()) - epoch(now()) % {intv}) -
                                    ((i.generate_series - 1) * INTERVAL {min} MINUTE)) as end_timestamp
                            FROM
                                generate_series(0, {lim}) as i
                        )
                    """.format(intv=size, min=(size//60), lim=((60//(size//60))*24))
                )

            db.connection.execute("CREATE INDEX IF NOT EXISTS idx_query ON query (time)")

    def read(self, fd, mask):
        r = fd.readline()
        if r == '':
            return False

        self.buffer.append(tuple(r.strip("\n").split()))

        # Start a transaction every flush_interval seconds. With regular inserts
        # we would also need to limit the amount of queries we buffer before inserting them,
        # but appending a DataFrame of N size is always faster than splitting N into chunks and
        # individually appending them in separate DataFrames.
        if (time.time() - self.timer) > self.flush_interval:
            # A note on the usage of DbConnection:
            # The pipe fifo can fill up while blocking for the lock, since during this time the
            # read() callback cannot run to empty it. The dnsbl module side catches this with
            # a BlockingIOError, forcing it to re-buffer the query, making the process
            # "eventually consistent". Realistically this condition should never occur.
            with DbConnection('/var/unbound/data/unbound.duckdb', read_only=False) as db:
                self.timer = time.time()
                # truncate the database to the last 7 days
                t = datetime.date.today() - datetime.timedelta(days=7)
                epoch = int(datetime.datetime(year=t.year, month=t.month, day=t.day).timestamp())
                db.connection.execute("""
                    DELETE
                    FROM query
                    WHERE to_timestamp(time) < to_timestamp(?)
                """, [epoch])
                if len(self.buffer) > 0:
                    # construct a dataframe from the current buffer and empty it. This is orders of magniture
                    # faster than transactional inserts, and doesn't block even under high load.
                    db.connection.append('query', pandas.DataFrame(list(self.buffer)))
                    self.buffer.clear()

        return True

def run_logger(target_pipe, flush_interval):
    fd = None
    sel = selectors.DefaultSelector()

    # Create the DNSReader now to ensure the database has been set up prior to queries coming in.
    r = DNSReader(flush_interval)

    try:
        # open() will block until a query has been pushed down the fifo
        fd = open(target_pipe, 'r')
    except OSError:
        syslog.syslog(syslog.LOG_ERR, "Unable to open pipe. This is likely because Unbound isn't running.")
        sys.exit(1)

    sel.register(fd, selectors.EVENT_READ, r.read)

    while True:
        events = sel.select()
        for key, mask in events:
            callback = key.data
            if not callback(key.fileobj, mask):
                syslog.syslog(syslog.LOG_NOTICE, "Unbound closed logging pipe. Exiting")
                sel.unregister(fd)
                sel.close()
                sys.exit()

if __name__ == '__main__':
    parser = argparse.ArgumentParser()
    parser.add_argument('--pid', help='pid file location', default='/var/run/unbound_logger.pid')
    parser.add_argument('--pipe', help='named pipe file location', default='/var/unbound/data/dns_logger')
    parser.add_argument('--foreground', help='run (log) in foreground', default=False, action='store_true')
    parser.add_argument('--flush_interval', help='interval to flush to db', default=10)

    inputargs = parser.parse_args()

    syslog.openlog('unbound', logoption=syslog.LOG_DAEMON, facility=syslog.LOG_LOCAL4)

    syslog.syslog(syslog.LOG_NOTICE, 'Daemonizing unbound logging backend.')
    cmd = lambda : run_logger(target_pipe=inputargs.pipe, flush_interval=inputargs.flush_interval)
    daemon = Daemonize(app="unbound_logger", pid=inputargs.pid, action=cmd, foreground=inputargs.foreground)
    daemon.start()
