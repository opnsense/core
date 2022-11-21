#!/usr/local/bin/python3
import argparse
import sqlite3
import ujson
import sys
import syslog
import re
from time import time
from collections import deque
sys.path.insert(0, "/usr/local/opnsense/site-python")
from sqlite3_helper import check_and_repair

class DBWrapper:
    def __init__(self):
        syslog.openlog('unbound', logoption=syslog.LOG_DAEMON, facility=syslog.LOG_LOCAL4)
        self.db_name = "/var/unbound/data/unbound.sqlite"
        self.con = sqlite3.connect(self.db_name)
        self.cursor = self.con.cursor()

    def execute(self, query, params=None):
        try:
            if params:
                self.cursor.execute(query, params)
            else:
                self.cursor.execute(query)
        except sqlite3.DatabaseError as e:
            syslog.syslog(syslog.LOG_ERR, "Unable to execute database operation: %s." % e)

        return self.cursor.fetchall()

    def close(self):
        self.cursor.close()
        self.con.close()

def handle_counters(db, args):
    query = """
        SELECT COUNT(*) AS total,
            COUNT(case q.action when 1 then 1 else null end) AS blocked,
            COUNT(case q.response_type when 2 then 1 else null end) AS cached,
            COUNT(case q.response_type when 1 then 1 else null end) AS local
        FROM query q;
    """

    result = db.execute(query)

    t = """
        SELECT time
        FROM query
        ORDER BY qid ASC
        LIMIT 1;
    """

    start_time = db.execute(t)

    if result and start_time:
        print(ujson.dumps({
            "total": result[0][0],
            "blocked": result[0][1],
            "cached": result[0][2],
            "local": result[0][3],
            "start_time": start_time[0][0]
        }))

def handle_rolling(db, args):
    interval = int(re.sub("^(?:(?!300|60).)*$", "300", str(args.interval)))
    tp = int(re.sub("^(?:(?!24|12|1).)*$", "24", str(args.timeperiod)))

    query = """
        SELECT v.start_timestamp, v.end_timestamp, COUNT(q.qid) AS cnt,
            COUNT(case q.action when 0 then 1 else null end) AS passed,
            COUNT(case q.action when 1 then 1 else null end) AS blocked,
            COUNT(case q.action when 2 then 1 else null end) AS dropped,
            COUNT(case q.response_type when 0 then 1 else null end) AS resolved,
            COUNT(case q.response_type when 1 then 1 else null end) AS local,
            COUNT(case q.response_type when 2 then 1 else null end) AS cached
        FROM v_time_buckets_{intv}min v
        LEFT JOIN query q ON
            q.time >= v.start_timestamp AND
            q.time <= v.end_timestamp
        WHERE DATETIME(v.start_timestamp, 'unixepoch') > DATETIME('now', '-{tp} hour')
        GROUP BY
            v.end_timestamp
        ORDER BY
            v.end_timestamp;
    """.format(intv=(interval//60), tp=tp)

    data = db.execute(query)

    if data:
        result = {tup[0]: {
                    'total': tup[2],
                    'passed': tup[3],
                    'blocked': tup[4],
                    'dropped': tup[5],
                    'resolved': tup[6],
                    'local': tup[7],
                    'cached': tup[8]
                } 
            for tup in data}
        print(ujson.dumps(result))

def handle_top(db, args):
    cond_where = ""
    if args.blocked:
        cond_where = "WHERE action == 1"
    query = """
        SELECT domain, COUNT(domain) AS cnt FROM query
        {w}
        GROUP BY domain
        ORDER BY cnt DESC
        LIMIT :max;
    """.format(w=cond_where)

    result = db.execute(query, {"max": args.max})

    if result:
        print(ujson.dumps(dict(result)))

if __name__ == '__main__':
    parser = argparse.ArgumentParser()
    parser.add_argument('--counters', help='get counter values', action='store_true')
    parser.set_defaults(func=handle_counters)

    subparsers = parser.add_subparsers(dest='command', help='sub-command help')
    r_parser = subparsers.add_parser('rolling', help='get rolling aggregate of query data')
    r_parser.add_argument('--timeperiod', help='timeperiod in hours. Valid values are [24, 12, 1]', type=int, default=24)
    r_parser.add_argument('--interval', help='interval in seconds. valid values are [300, 60]', type=int, default=300)
    r_parser.set_defaults(func=handle_rolling)

    t_parser = subparsers.add_parser('top', help='get top queried domains')
    t_parser.add_argument('--max', help='limit results by max items', type=int, default=10)
    t_parser.add_argument('--blocked', help='get the top blocked domains instead', action='store_true')
    t_parser.set_defaults(func=handle_top)

    if len(sys.argv)==1:
        parser.print_help()
        sys.exit(1)

    inputargs = parser.parse_args()
    db = DBWrapper()

    inputargs.func(db, inputargs)

    db.close()
