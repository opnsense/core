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

import argparse
import ujson
import sys
import re
import os
import pandas
import numpy as np
from time import time
from operator import itemgetter
sys.path.insert(0, "/usr/local/opnsense/site-python")
from duckdb_helper import DbConnection

def percent(val, total):
    if val == 0 or total == 0:
        return 0
    return '{:.2f}'.format(round(((val / total) * 100), 2))

def handle_rolling(args):
    # sanitize input
    interval = int(re.sub("^(?:(?!600|300|60).)*$", "600", str(args.interval)))
    tp = int(re.sub("^(?:(?!24|12|1).)*$", "24", str(args.timeperiod)))
    data = pandas.DataFrame()

    result = {}

    if args.clients:
        query = """
            WITH grouped AS (
                SELECT v.start_timestamp s, v.end_timestamp e, c.client cl, COUNT(c.client) cnt_cl
                FROM v_time_series_{intv}min v
                LEFT JOIN query c ON
                    c.time >= v.start_timestamp AND
                    c.time <= v.end_timestamp
                WHERE to_timestamp(v.start_timestamp) > (to_timestamp(epoch(now())) - INTERVAL {tp} HOUR)
                GROUP BY
                    v.end_timestamp, v.start_timestamp, c.client
                ORDER BY
                    v.end_timestamp
            )
            SELECT
                s as start_timestamp,
                e as end_timestamp,
                GROUP_CONCAT(cl) as clients,
                GROUP_CONCAT(COALESCE(resolved.hostname, '')) as hostnames,
                GROUP_CONCAT(cnt_cl) as client_totals
            FROM grouped
            LEFT JOIN client resolved ON cl = resolved.ipaddr
            GROUP BY s, e
            ORDER BY e
        """.format(intv=interval//60, tp=tp)
    else:
        query = """
            SELECT v.start_timestamp, v.end_timestamp, COUNT(q.time) AS total,
                COUNT(case q.action when 0 then 1 else null end) AS passed,
                COUNT(case q.action when 1 then 1 else null end) AS blocked,
                COUNT(case q.action when 2 then 1 else null end) AS dropped,
                COUNT(case q.source when 0 then 1 else null end) AS resolved,
                COUNT(case q.source when 2 then 1 else null end) AS local,
                COUNT(case q.source when 3 then 1 else null end) AS cached
            FROM v_time_series_{intv}min v
            LEFT JOIN query q ON
                q.time >= v.start_timestamp AND
                q.time <= v.end_timestamp
            WHERE to_timestamp(v.start_timestamp) > (to_timestamp(epoch(now())) - INTERVAL {tp} HOUR)
            GROUP BY v.end_timestamp, v.start_timestamp
            ORDER BY v.end_timestamp
        """.format(intv=(interval//60), tp=tp)

    with DbConnection('/var/unbound/data/unbound.duckdb', read_only=True) as db:
        if db.connection is not None and db.table_exists('query'):
            data = db.connection.execute(query).fetchdf().astype('object')

    if not data.empty:
        if args.clients:
            # a group_concat without any client returns NaN in a Dataframe, replace it with an empty string
            data = data.replace(np.nan, '', regex=True)
            data = list(data.itertuples(index=False, name=None))
            result = {}
            for row in data:
                interval = {row[0]: {}}
                if row[2]:
                    tmp = []
                    hosts = row[3].split(',')
                    counts = row[4].split(',')
                    for idx, client in enumerate(row[2].split(',')):
                        tmp.append((client, int(counts[idx]), hosts[idx]))
                    # sort the list by most active client
                    tmp.sort(key=itemgetter(1), reverse=True)
                    # limit by 10
                    tmp = tmp[:10]
                    interval[row[0]] |= {t[0]: {'count': t[1], 'hostname': t[2]} for t in tmp}
                result |= interval
        else:
            result = data.set_index('start_timestamp').apply(lambda x: {
                'total': x.total,
                'passed': x.passed,
                'blocked': x.blocked,
                'dropped': x.dropped,
                'resolved': x.resolved,
                'local': x.local,
                'cached': x.cached
            }, axis=1).to_dict()

    print(ujson.dumps(result))

def handle_top(args):
    total = resolved = blocked = local = passed = blocklist_size = 0
    r_top = r_top_blocked = r_total = r_start_time = pandas.DataFrame()
    top = top_blocked = {}
    start_time = int(time())

    bl_path = '/var/unbound/data/dnsbl.size'
    if os.path.isfile(bl_path) and os.path.getsize(bl_path) > 0:
        with open(bl_path, 'r') as f:
            blocklist_size = int(f.readline())

    with DbConnection('/var/unbound/data/unbound.duckdb', read_only=True) as db:
        if db.connection is not None and db.table_exists('query'):
            # all queries are stored in a DataFrame() and its resulting set
            # cast to native python types (`int` instead of `numpy.int64`)
            # in order to properly convert it to json format
            r_top = db.connection.execute("""
                SELECT domain, COUNT(domain) as cnt
                FROM query
                WHERE action == 0
                GROUP BY domain
                ORDER BY cnt DESC
                LIMIT ?
            """, [args.max]).fetchdf().astype('object')

            r_top_blocked = db.connection.execute("""
                SELECT domain, COUNT(domain) as cnt, blocklist
                FROM query
                WHERE action == 1
                GROUP BY domain, blocklist
                ORDER BY cnt DESC
                LIMIT ?
            """, [args.max]).fetchdf().astype('object')

            r_total = db.connection.execute("""
                SELECT COUNT(*) AS total,
                    COUNT(case q.action when 1 then 1 else null end) AS blocked,
                    COUNT(case q.source when 2 then 1 else null end) AS local,
                    COUNT(case q.action when 0 then 1 else null end) AS passed,
                    COUNT(case q.source when 0 then 1 else null end) as resolved
                FROM query q
            """).fetchdf().astype('object')

            r_start_time = db.connection.execute("""
                SELECT time
                FROM query
                ORDER BY time ASC
                LIMIT 1
            """).fetchdf().astype('object')

    if not r_total.empty:
        total = r_total.total.iloc[0]
        resolved = r_total.resolved.iloc[0]
        blocked = r_total.blocked.iloc[0]
        local = r_total.local.iloc[0]
        passed = r_total.passed.iloc[0]
    if not r_start_time.empty:
        start_time = r_start_time.time.iloc[0]
    if not r_top.empty:
        top = r_top.set_index('domain').apply(lambda x: {
            "total": x.cnt,
            "pcnt": percent(x.cnt, passed)
        }, axis=1).to_dict()
    if not r_top_blocked.empty:
        top_blocked = r_top_blocked.set_index('domain').apply(lambda x: {
            "total": x.cnt,
            "pcnt": percent(x.cnt, blocked),
            "blocklist": x.blocklist
        }, axis=1).to_dict()

    print(ujson.dumps({
        "total": total,
        "blocklist_size": blocklist_size,
        "passed": passed,
        "resolved": {"total": resolved, "pcnt": percent(resolved, total)},
        "blocked": {"total": blocked, "pcnt": percent(blocked, total)},
        "local": {"total": local, "pcnt": percent(local, total)},
        "start_time": start_time,
        "top": top,
        "top_blocked": top_blocked
    }))

def handle_details(args):
    result = []
    details = pandas.DataFrame()

    with DbConnection('/var/unbound/data/unbound.duckdb', read_only=True) as db:
        if db.connection is not None and db.table_exists('query') and db.table_exists('client'):
            if args.client and args.start and args.end:
                details = db.connection.execute("""
                    SELECT * FROM query q
                    LEFT JOIN client resolved on q.client = resolved.ipaddr
                    WHERE q.client = ? AND q.time > ? AND q.time < ?
                    ORDER BY time DESC
                    LIMIT ?
                """, [args.client, args.start, args.end, args.limit]).fetchdf().astype({'uuid': str})
            else:
                details = db.connection.execute("""
                    SELECT * FROM query
                    LEFT JOIN client resolved on client = resolved.ipaddr
                    ORDER BY time DESC
                    LIMIT ?
                """, [args.limit]).fetchdf().astype({'uuid': str})


    if not details.empty:
        # use a resolved hostname if possible
        details['client'] = np.where(details['hostname'].isnull(), details['client'], details['hostname'])
        details['blocklist'] = details['blocklist'].replace(np.nan, None)
        details = details.drop(['hostname', 'ipaddr'], axis=1)
        # map the integer types to a sensible description
        details['action'] = details['action'].map({0: 'Pass', 1: 'Block', 2: 'Drop'})
        details['source'] = details['source'].map({
            0: 'Recursion', 1: 'Local', 2: 'Local-data', 3: 'Cache'
        })
        details['rcode'] =  details['rcode'].map({
            0: 'NOERROR', 1: 'FORMERR', 2: 'SERVFAIL', 3: 'NXDOMAIN', 4: 'NOTIMPL',
            5: 'REFUSED', 6: 'YXDOMAIN', 7: 'YXRRSET', 8: 'NXRRSET', 9: 'NOTAUTH',
            10: 'NOTZONE'
        })
        details['dnssec_status'] = details['dnssec_status'].map({
            0: 'Unchecked', 1: 'Bogus', 2: 'Indeterminate', 3: 'Insecure', 5: 'Secure'
        })
        result = details.to_dict('records')

    print(ujson.dumps(result))

if __name__ == '__main__':
    parser = argparse.ArgumentParser()
    subparsers = parser.add_subparsers(dest='command', help='sub-command help')
    r_parser = subparsers.add_parser('rolling', help='get rolling aggregate of query data')
    r_parser.add_argument('--timeperiod', help='timeperiod in hours. Valid values are [24, 12, 1]', type=int, default=24)
    r_parser.add_argument('--interval', help='interval in seconds. valid values are [600, 300, 60]', type=int, default=300)
    r_parser.add_argument('--clients', help='get top 10 client activity instead', action='store_true')
    r_parser.set_defaults(func=handle_rolling)

    t_parser = subparsers.add_parser('totals', help='get top queried domains and total counters')
    t_parser.add_argument('--max', help='limit top queried domains by max items', type=int, default=10)
    t_parser.set_defaults(func=handle_top)

    d_parser = subparsers.add_parser('details', help='get detailed query information')
    d_parser.add_argument('--limit', help='limit results', type=int, default=500)
    d_parser.add_argument('--client', help='limit result to client')
    d_parser.add_argument('--start', type=int, help='start unix epoch')
    d_parser.add_argument('--end', type=int, help='end unix epoch')
    d_parser.set_defaults(func=handle_details)

    if len(sys.argv)==1:
        parser.print_help()
        sys.exit(1)

    inputargs = parser.parse_args()

    inputargs.func(inputargs)
