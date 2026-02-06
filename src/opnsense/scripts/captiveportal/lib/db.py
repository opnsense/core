"""
    Copyright (c) 2015-2019 Ad Schellevis <ad@opnsense.org>
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
import base64
import time
import sqlite3


class DB(object):
    database_filename = '/var/captiveportal/captiveportal.sqlite'

    def __init__(self):
        """ construct new database connection, open and make sure the sqlite file exists
        :return:
        """
        self._connection = None
        self.open()
        self.create()

    def __del__(self):
        """ destruct, close database handle
        """
        self.close()

    def close(self):
        """ close database
        """
        self._connection.close()

    def open(self):
        """ open database
        """
        db_path = os.path.dirname(self.database_filename)
        if not os.path.isdir(db_path):
            os.mkdir(db_path)
        self._connection = sqlite3.connect(self.database_filename)

    def create(self, force_recreate=False):
        """ create/initialize new database
        :param force_recreate: if database already exists, remove old one first
        :return: None
        """
        if force_recreate:
            if os.path.isfile(self.database_filename):
                os.remove(self.database_filename)
            self._connection = sqlite3.connect(self.database_filename)

        cur = self._connection.cursor()
        cur.execute("SELECT count(*) FROM sqlite_master where tbl_name = 'cp_clients'")
        if cur.fetchall()[0][0] == 0:
            # empty database, initialize database
            init_script_filename = '%s/../sql/init.sql' % os.path.dirname(os.path.abspath(__file__))
            cur.executescript(open(init_script_filename, 'r').read())

        # migration: add "delete_reason" column to cp_clients
        cur.execute("PRAGMA table_info(cp_clients)")
        if not any([row[1] == 'delete_reason' for row in cur.fetchall()]):
            cur.execute("ALTER TABLE cp_clients ADD COLUMN delete_reason VARCHAR")
            self._connection.commit()

        # migration: ensure prev_* columns are initialized
        cur.execute("SELECT count(*) FROM sqlite_master WHERE tbl_name = 'session_info'")
        if cur.fetchall()[0][0] > 0:
            cur.execute("PRAGMA table_info(session_info)")
            # columns exist (from init.sql) but some rows may have NULLs
            cur.execute("""
                UPDATE session_info
                SET prev_packets_in  = COALESCE(prev_packets_in,  packets_in),
                    prev_bytes_in    = COALESCE(prev_bytes_in,    bytes_in),
                    prev_packets_out = COALESCE(prev_packets_out, packets_out),
                    prev_bytes_out   = COALESCE(prev_bytes_out,   bytes_out)
                WHERE prev_packets_in  IS NULL
                    OR prev_bytes_in    IS NULL
                    OR prev_packets_out IS NULL
                    OR prev_bytes_out   IS NULL
            """)
            self._connection.commit()

        # migration: introduce cp_client_ips table (multiple IPs per (zoneid, sessionid)) for IPv6 support
        cur.execute("""
            SELECT count(*)
            FROM sqlite_master
            WHERE type='table' AND name='cp_client_ips'
        """)
        if cur.fetchall()[0][0] == 0:
            # create the new table
            cur.execute("""
                CREATE TABLE cp_client_ips (
                      zoneid     INT NOT NULL
                    , sessionid  VARCHAR NOT NULL
                    , ip_address VARCHAR NOT NULL
                    , PRIMARY KEY (zoneid, sessionid, ip_address)
                    , FOREIGN KEY (zoneid, sessionid)
                        REFERENCES cp_clients(zoneid, sessionid)
                        ON DELETE CASCADE
                )
            """)

            # indexes used when allow_virtual_ips is turned on for a zone
            cur.execute("CREATE INDEX IF NOT EXISTS cp_client_ips_ip   ON cp_client_ips(ip_address)")
            cur.execute("CREATE INDEX IF NOT EXISTS cp_client_ips_zone ON cp_client_ips(zoneid)")

            self._connection.commit()
        else:
            # Table exists: ensure indexes are present (idempotent)
            cur.execute("CREATE INDEX IF NOT EXISTS cp_client_ips_ip   ON cp_client_ips(ip_address)")
            cur.execute("CREATE INDEX IF NOT EXISTS cp_client_ips_zone ON cp_client_ips(zoneid)")
            self._connection.commit()

        cur.close()

    def sessions_per_address(self, zoneid, ip_address=None, mac_address=None):
        """ fetch session(s) per (ip/mac) address
        Primary IP is stored in cp_clients.ip_address; virtual IPs in cp_client_ips.
        """
        # Nothing to match on
        if (ip_address is None or str(ip_address).strip() == '') and (mac_address is None or str(mac_address).strip() == ''):
            return []

        cur = self._connection.cursor()
        request = {
            'zoneid': zoneid,
            'ip_address': ip_address,
            'mac_address': mac_address
        }

        clauses = []
        if ip_address is not None and str(ip_address).strip() != '':
            # Match either primary IP or any virtual IP
            clauses.append("(cc.ip_address = :ip_address OR ci.ip_address = :ip_address)")
        if mac_address is not None and str(mac_address).strip() != '':
            clauses.append("cc.mac_address = :mac_address")

        where_or = " OR ".join(clauses)

        cur.execute(f"""
            SELECT DISTINCT
                cc.sessionid         AS sessionId,
                cc.authenticated_via AS authenticated_via
            FROM cp_clients cc
            LEFT JOIN cp_client_ips ci
                ON ci.zoneid = cc.zoneid
                AND ci.sessionid = cc.sessionid
            WHERE cc.deleted = 0
            AND cc.zoneid = :zoneid
            AND ({where_or})
        """, request)

        return [{'sessionId': sessionId, 'authenticated_via': authenticated_via}
                for sessionId, authenticated_via in cur.fetchall()]

    def add_client(self, zoneid, authenticated_via, username, ip_address, mac_address):
        response = {
            'zoneid': zoneid,
            'authenticated_via': authenticated_via,
            'userName': username,
            'ipAddress': ip_address,
            'macAddress': mac_address,
            'startTime': time.time(),
            'sessionId': base64.b64encode(os.urandom(16)).decode()
        }

        cur = self._connection.cursor()
        try:
            cur.execute("BEGIN")

            # set cp_client as deleted in case there's already a user logged-in at this ip address
            # (match both primary IP and virtual IPs)
            if ip_address is not None and str(ip_address).strip() != '':
                cur.execute("""
                    UPDATE cp_clients
                    SET    deleted = 1
                    WHERE  zoneid = :zoneid
                    AND  deleted = 0
                    AND (
                            ip_address = :ipAddress
                            OR sessionid IN (
                                SELECT sessionid
                                FROM   cp_client_ips
                                WHERE  zoneid = :zoneid
                                AND  ip_address = :ipAddress
                            )
                    )
                """, response)

            # add new session (primary IP lives here)
            cur.execute("""
                INSERT INTO cp_clients(zoneid, authenticated_via, sessionid, username, ip_address, mac_address, created)
                VALUES (:zoneid, :authenticated_via, :sessionId, :userName, :ipAddress, :macAddress, :startTime)
            """, response)

            self._connection.commit()
            return response
        except Exception:
            self._connection.rollback()
            raise
        finally:
            cur.close()

    def update_client_ip(self, zoneid, sessionid, ip_address):
        """ change client ip address
        """
        if type(sessionid) == bytes:
            sessionid = sessionid.decode()
        cur = self._connection.cursor()
        cur.execute("""update cp_clients
                       set    ip_address = :ip_address
                       where  deleted = 0
                       and    zoneid = :zoneid
                       and    sessionid = :sessionid
                    """, {'zoneid': zoneid, 'sessionid': sessionid, 'ip_address': ip_address})
        self._connection.commit()

    def add_virtual_ip(self, zoneid, sessionid, ip_address):
        """Add a virtual IP address to a session.
        """
        if isinstance(sessionid, bytes):
            sessionid = sessionid.decode()

        if ip_address is None or str(ip_address).strip() == '':
            return

        cur = self._connection.cursor()
        try:
            cur.execute("""
                INSERT OR IGNORE INTO cp_client_ips(zoneid, sessionid, ip_address)
                VALUES (:zoneid, :sessionid, :ip_address)
            """, {
                'zoneid': zoneid,
                'sessionid': sessionid,
                'ip_address': ip_address
            })
            self._connection.commit()
        finally:
            cur.close()

    def del_client(self, zoneid, sessionid, reason=None):
        """ mark (administrative) client for removal
        :param zoneid: zone id
        :param sessionid: session id
        :return: client info before removal or None if client not found
        """
        if type(sessionid) == bytes:
            sessionid = sessionid.decode()
        cur = self._connection.cursor()
        cur.execute(""" select  *
                        from    cp_clients
                        where   sessionid = :sessionid
                        and     (zoneid = :zoneid or :zoneid is null)
                        and     deleted = 0
                    """, {'zoneid': zoneid, 'sessionid': sessionid})
        data = cur.fetchall()
        if len(data) > 0:
            session_info = dict()
            for fields in cur.description:
                session_info[fields[0]] = data[0][len(session_info)]
            # remove client
            cur.execute(
                "update cp_clients set deleted = 1, delete_reason = :delete_reason where sessionid = :sessionid",
                {'delete_reason': reason, 'sessionid': sessionid}
            )
            self._connection.commit()

            return session_info
        else:
            return None

    def list_clients(self, zoneid=None):
        """ return list of (administrative) connected clients and usage statistics
        :param zoneid: zone id
        :return: list of clients
        """
        result = list()
        fieldnames = list()
        cur = self._connection.cursor()
        # rename fields for API
        cur.execute(""" select  cc.zoneid
                        ,       cc.sessionid   sessionId
                        ,       cc.authenticated_via authenticated_via
                        ,       cc.username    userName
                        ,       cc.created     startTime
                        ,       cc.ip_address  ipAddress
                        ,       cc.mac_address macAddress
                        ,       case when si.packets_in is null then 0 else si.packets_in end packets_in
                        ,       case when si.packets_out is null then 0 else si.packets_out end packets_out
                        ,       case when si.bytes_in is null then 0 else si.bytes_in end bytes_in
                        ,       case when si.bytes_out is null then 0 else si.bytes_out end bytes_out
                        ,       case when si.last_accessed is null or si.last_accessed = 0
                                        then cc.created
                                        else si.last_accessed
                                end last_accessed
                        ,       sr.session_timeout acc_session_timeout
                        from    cp_clients cc
                        left join session_info si on si.zoneid = cc.zoneid and si.sessionid = cc.sessionid
                        left join session_restrictions sr on sr.zoneid = cc.zoneid and sr.sessionid = cc.sessionid
                        where   (cc.zoneid = :zoneid or :zoneid is null)
                        and     cc.deleted = 0
                        order by case when cc.username is not null then cc.username else cc.ip_address end
                        ,        cc.created desc
                        """, {'zoneid': zoneid})

        while True:
            # fetch field names
            if len(fieldnames) == 0:
                for fields in cur.description:
                    fieldnames.append(fields[0])

            row = cur.fetchone()
            if row is None:
                break
            else:
                record = dict()
                for idx in range(len(row)):
                    record[fieldnames[idx]] = row[idx]
                result.append(record)
        return result
    
    def list_session_ips(self, zoneid, sessionid, include_deleted=False):
        """
        Return primary + virtual IPs for a session.
        - Primary IP is cp_clients.ip_address
        - virtual IPs are cp_client_ips.ip_address
        Returns a de-duplicated list[str] (order not guaranteed).
        """
        if isinstance(sessionid, bytes):
            sessionid = sessionid.decode()

        cur = self._connection.cursor()
        try:
            params = {'zoneid': zoneid, 'sessionid': sessionid}
            where_deleted = "" if include_deleted else "AND cc.deleted = 0"

            cur.execute(f"""
                SELECT cc.ip_address
                FROM cp_clients cc
                WHERE cc.zoneid = :zoneid
                AND cc.sessionid = :sessionid
                {where_deleted}
            """, params)
            row = cur.fetchone()
            ips = set()
            if row and row[0] and str(row[0]).strip():
                ips.add(str(row[0]).strip())

            cur.execute("""
                SELECT ci.ip_address
                FROM cp_client_ips ci
                WHERE ci.zoneid = :zoneid
                AND ci.sessionid = :sessionid
                AND ci.ip_address IS NOT NULL
                AND TRIM(ci.ip_address) <> ''
            """, params)
            for (ip,) in cur.fetchall():
                if ip and str(ip).strip():
                    ips.add(str(ip).strip())

            return list(ips)
        finally:
            cur.close()

    def find_concurrent_user_sessions(self, zoneid):
        """ query zone database for concurrent user sessions
        :param zoneid: zone id
        :return: dictionary containing duplicate sessions
        """
        result = dict()
        cur = self._connection.cursor()
        # rename fields for API
        cur.execute(""" select   cc.sessionid   sessionId
                        ,        cc.username    userName
                        from     cp_clients cc
                        where   cc.zoneid = :zoneid
                        and     cc.deleted = 0
                        and     cc.username is not null
                        and     cc.username <> ''
                        order by case when cc.username is not null then cc.username else cc.ip_address end
                        ,        cc.created desc
                        """, {'zoneid': zoneid})

        prev_user = None
        while True:
            row = cur.fetchone()
            if row is None:
                break
            elif prev_user is not None and prev_user == row[1]:
                result[row[0]] = row[1]
            prev_user = row[1]
        return result

    def update_accounting_info(self, details):
        """ update internal accounting database with given ipfw info (not per zone)
        :param details: ipfw accounting details dict keyed by ip:
                        details[ip] = {'in_pkts','out_pkts','in_bytes','out_bytes','last_accessed'}
        """
        if type(details) != dict:
            return

        cur = self._connection.cursor()
        cur2 = self._connection.cursor()

        # 1) Load sessions + existing session_info
        cur.execute("""
            SELECT  cc.zoneid,
                    cc.sessionid,
                    cc.ip_address AS primary_ip,
                    cc.created    AS created,
                    si.rowid      AS si_rowid,
                    COALESCE(si.prev_packets_in, 0)  AS prev_packets_in,
                    COALESCE(si.prev_bytes_in, 0)    AS prev_bytes_in,
                    COALESCE(si.prev_packets_out, 0) AS prev_packets_out,
                    COALESCE(si.prev_bytes_out, 0)   AS prev_bytes_out,
                    COALESCE(si.last_accessed, 0)    AS last_accessed
            FROM cp_clients cc
            LEFT JOIN session_info si
                ON si.zoneid = cc.zoneid AND si.sessionid = cc.sessionid
        """)

        sessions = {}  # (zoneid, sessionid) -> record
        for row in cur.fetchall():
            rec = {
                'zoneid': row[0],
                'sessionid': row[1],
                'primary_ip': row[2],
                'created': row[3],
                'si_rowid': row[4],
                'prev_packets_in': row[5],
                'prev_bytes_in': row[6],
                'prev_packets_out': row[7],
                'prev_bytes_out': row[8],
                'last_accessed': row[9],
                'ips': set()
            }
            sessions[(rec['zoneid'], rec['sessionid'])] = rec

        # 2) Add virtual IPs
        cur.execute("""
            SELECT zoneid, sessionid, ip_address
            FROM cp_client_ips
            WHERE ip_address IS NOT NULL AND TRIM(ip_address) <> ''
        """)
        for zoneid, sessionid, ip in cur.fetchall():
            key = (zoneid, sessionid)
            if key in sessions:
                sessions[key]['ips'].add(ip)

        # 3) Ensure primary IP is included for each session
        for rec in sessions.values():
            pip = rec.get('primary_ip')
            if pip is not None and str(pip).strip() != '':
                rec['ips'].add(pip)

        sql_new = """
            INSERT INTO session_info(
                zoneid, sessionid,
                prev_packets_in, prev_bytes_in,
                prev_packets_out, prev_bytes_out,
                packets_in, packets_out,
                bytes_in, bytes_out,
                last_accessed
            )
            VALUES (
                :zoneid, :sessionid,
                :prev_packets_in, :prev_bytes_in,
                :prev_packets_out, :prev_bytes_out,
                :packets_in, :packets_out,
                :bytes_in, :bytes_out,
                :last_accessed
            )
        """

        sql_update = """
            UPDATE session_info
            SET    last_accessed    = :last_accessed,
                prev_packets_in  = :prev_packets_in,
                prev_packets_out = :prev_packets_out,
                prev_bytes_in    = :prev_bytes_in,
                prev_bytes_out   = :prev_bytes_out,
                packets_in       = packets_in + :packets_in,
                packets_out      = packets_out + :packets_out,
                bytes_in         = bytes_in + :bytes_in,
                bytes_out        = bytes_out + :bytes_out
            WHERE  rowid = :si_rowid
        """

        # 4) Update accounting per session by summing over all of its IPs
        for rec in sessions.values():
            cur_pkts_in = 0
            cur_pkts_out = 0
            cur_bytes_in = 0
            cur_bytes_out = 0
            cur_last_accessed = 0

            any_hit = False
            for ip in rec['ips']:
                d = details.get(ip)
                if not d:
                    continue
                any_hit = True
                cur_pkts_in += int(d.get('in_pkts', 0))
                cur_pkts_out += int(d.get('out_pkts', 0))
                cur_bytes_in += int(d.get('in_bytes', 0))
                cur_bytes_out += int(d.get('out_bytes', 0))
                cur_last_accessed = max(cur_last_accessed, int(d.get('last_accessed', 0)))

            if not any_hit:
                continue

            last_accessed = cur_last_accessed if cur_last_accessed else int(rec['created'] or 0)

            if rec['si_rowid'] is None:
                payload = {
                    'zoneid': rec['zoneid'],
                    'sessionid': rec['sessionid'],
                    'prev_packets_in': cur_pkts_in,
                    'prev_bytes_in': cur_bytes_in,
                    'prev_packets_out': cur_pkts_out,
                    'prev_bytes_out': cur_bytes_out,
                    'packets_in': cur_pkts_in,
                    'packets_out': cur_pkts_out,
                    'bytes_in': cur_bytes_in,
                    'bytes_out': cur_bytes_out,
                    'last_accessed': last_accessed
                }
                cur2.execute(sql_new, payload)
            else:
                prev_pi = int(rec['prev_packets_in'])
                prev_po = int(rec['prev_packets_out'])
                prev_bi = int(rec['prev_bytes_in'])
                prev_bo = int(rec['prev_bytes_out'])

                # If totals decreased, treat as reset and add full totals
                if (cur_pkts_in >= prev_pi and cur_pkts_out >= prev_po and
                    cur_bytes_in >= prev_bi and cur_bytes_out >= prev_bo):
                    add_pi = cur_pkts_in - prev_pi
                    add_po = cur_pkts_out - prev_po
                    add_bi = cur_bytes_in - prev_bi
                    add_bo = cur_bytes_out - prev_bo
                else:
                    add_pi = cur_pkts_in
                    add_po = cur_pkts_out
                    add_bi = cur_bytes_in
                    add_bo = cur_bytes_out

                payload = {
                    'si_rowid': rec['si_rowid'],
                    'last_accessed': last_accessed,
                    'packets_in': add_pi,
                    'packets_out': add_po,
                    'bytes_in': add_bi,
                    'bytes_out': add_bo,
                    'prev_packets_in': cur_pkts_in,
                    'prev_packets_out': cur_pkts_out,
                    'prev_bytes_in': cur_bytes_in,
                    'prev_bytes_out': cur_bytes_out
                }
                cur2.execute(sql_update, payload)

        self._connection.commit()

    def update_session_restrictions(self, zoneid, sessionid, session_timeout):
        """ upsert session restrictions
        :param zoneid: zone id
        :param sessionid: session id
        :param session_timeout: timeout in seconds
        :return: string "add"/"update" to signal the performed action to the client
        """
        cur = self._connection.cursor()
        qry_params = {'zoneid': zoneid, 'sessionid': sessionid, 'session_timeout': session_timeout}
        sql_update = """update session_restrictions
                        set session_timeout = :session_timeout
                        where zoneid = :zoneid and sessionid = :sessionid"""

        cur.execute(sql_update, qry_params)
        if cur.rowcount == 0:
            sql_insert = """insert into session_restrictions(zoneid, sessionid, session_timeout)
                            values (:zoneid, :sessionid, :session_timeout)"""
            cur.execute(sql_insert, qry_params)
            self._connection.commit()
            return 'add'
        else:
            self._connection.commit()
            return 'update'

    def cleanup_sessions(self):
        """ cleanup removed sessions, but wait for accounting to finish when busy
        """
        cur = self._connection.cursor()
        cur.execute(""" delete
                        from cp_clients
                        where cp_clients.deleted = 1
                        and not exists (
                            select  1
                            from    accounting_state
                            where   accounting_state.zoneid = cp_clients.zoneid
                            and     accounting_state.sessionid = cp_clients.sessionid
                            and     accounting_state.state <> 'STOPPED'
                        )
                        """)
        cur.execute(""" delete
                        from    accounting_state
                        where   not exists (
                            select  1
                            from    cp_clients
                            where   cp_clients.zoneid = accounting_state.zoneid
                            and     cp_clients.sessionid = accounting_state.sessionid
                        )
                    """)
        cur.execute(""" delete
                        from    session_info
                        where   not exists (
                            select  1
                            from    cp_clients
                            where   session_info.zoneid = cp_clients.zoneid
                            and     session_info.sessionid = cp_clients.sessionid
                        )
                    """)

        cur.execute(""" delete
                        from    session_restrictions
                        where   not exists (
                            select  1
                            from    cp_clients
                            where   session_restrictions.zoneid = cp_clients.zoneid
                            and     session_restrictions.sessionid = cp_clients.sessionid
                        )
                    """)

        self._connection.commit()
