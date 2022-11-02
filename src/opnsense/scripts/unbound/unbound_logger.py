import sys
import selectors
import argparse
import syslog
import sqlite3
import time
import random, string
from timeit import default_timer as timer
from collections import deque
sys.path.insert(0, "/usr/local/opnsense/site-python")
from daemonize import Daemonize
from sqlite3_helper import check_and_repair

class DNSReader:
    def __init__(self):
        check_and_repair("/var/unbound/data/unbound.sqlite")

        self.con = sqlite3.connect("/var/unbound/data/unbound.sqlite")
        self.cursor = self.con.cursor()
        self.timer = 0
        self.flush_interval = 5
        self.count = 0
        self.buffer = deque()
        self.buf_max = 4000

        try:
            self.cursor.execute("CREATE TABLE IF NOT EXISTS query(time, client, family, type, domain, action, response_type)")
        except sqlite3.DatabaseError as e:
            syslog.syslog(syslog.LOG_ERR, "Unable to set up database: %s" % e)
            self.con.close()
            sys.exit(1)

    def close(self):
        self.con.close()

    def read(self, fd, mask):
        r = fd.readline()
        if r == '':
            return False

        # Start a transaction every flush_interval seconds or
        # when buf_max has been reached
        self.buffer.append(tuple(r.strip("\n").split()))
        flush = (time.time() - self.timer) > self.flush_interval
        if len(self.buffer) > self.buf_max or flush:
            if flush:
                self.timer = time.time()
            while len(self.buffer) > 0:
                self.cursor.execute("INSERT INTO query VALUES(?, ?, ?, ?, ?, ?, ?)",
                    self.buffer.popleft())
            self.con.commit()

        return True

    def delete(self):
        self.cursor.execute("DELETE FROM query;")

    def write_test(self, qbuffer):
        start = timer()
        for entry in qbuffer:
            self.cursor.execute("INSERT INTO query VALUES(?, ?, ?, ?, ?, ?, ?)",
                tuple(entry.strip("\n").split()))

        end = timer()
        t1 = (end - start)
        start = timer()
        self.con.commit()
        end = timer()
        t2 = (end - start)
        return (t1, t2)

def run_logger(target_pipe):
    fd = None
    sel = selectors.DefaultSelector()

    try:
        fd = open(target_pipe, 'r')
    except OSError:
        syslog.syslog(syslog.LOG_ERR, "Unable to open pipe. This is likely because Unbound isn't running.")
        sys.exit(1)

    r = DNSReader()
    sel.register(fd, selectors.EVENT_READ, r.read)

    while True:
        events = sel.select()
        for key, mask in events:
            callback = key.data
            if not callback(key.fileobj, mask):
                syslog.syslog(syslog.LOG_NOTICE, "Unbound closed logging pipe. Exiting")
                sel.unregister(fd)
                sel.close()
                r.close()
                sys.exit()

if __name__ == '__main__':
    parser = argparse.ArgumentParser()
    parser.add_argument('--pid', help='pid file location', default='/var/run/unbound_logger.pid')
    parser.add_argument('--pipe', help='named pipe file location', default='/var/unbound/data/dns_logger')
    parser.add_argument('--foreground', help='run (log) in foreground', default=False, action='store_true')
    parser.add_argument('--test', help='run in test mode', default=False, action='store_true')

    inputargs = parser.parse_args()

    syslog.openlog('unbound', logoption=syslog.LOG_DAEMON, facility=syslog.LOG_LOCAL4)

    if not inputargs.test:
        syslog.syslog(syslog.LOG_NOTICE, 'Daemonizing unbound logging backend.')
        cmd = lambda : run_logger(target_pipe=inputargs.pipe)
        daemon = Daemonize(app="unbound_logger", pid=inputargs.pid, action=cmd, foreground=inputargs.foreground)
        daemon.start()
    else:
        # XXX: to be removed
        r = DNSReader()
        total = 500000
        buf_size = 4000
        buf = []
        start = timer()
        for i in range(total // buf_size):
            slice = []
            for j in range(buf_size):
                # generate buf_size records
                d = ''.join(random.choice(string.ascii_lowercase) for x in range(50))
                q = "{t} 127.0.0.1 ipv4 A {domain} 1 1".format(t=time.time(), domain=d)
                slice.append(q)
            buf.append(slice)
        end = timer()
        print("generated {len} random records in {t} seconds".format(t=(end-start), len=len(buf) * buf_size))
        print("starting db test with buffer size %s and a total of %s" % (buf_size, total))
        r.delete()
        total_insert_t = 0
        total_commit_t = 0
        total_t = 0
        count = 0
        for slice in buf:
            # t1 = insert time
            # t2 = commit time
            t1, t2 = r.write_test(slice)
            total_insert_t += t1
            total_commit_t += t2
            total_t += (t1 + t2)
            count +=1

        print("Total insert time: %s" % total_insert_t)
        print("Total commit time: %s" % total_commit_t)
        print("Total transaction time: %s" % total_t)


