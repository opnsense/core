"""
    Copyright (c) 2015-2025 Ad Schellevis <ad@opnsense.org>
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
from lib.arp import ARP


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

        cur.close()

    def sessions_per_address(self, zoneid, ip_address=None, mac_address=None):
        """ fetch session(s) per (mac) address
        :param zoneid: cp zone number
        :param ip_address: ip address
        :return: active status (boolean)
        """
        cur = self._connection.cursor()
        request = {
            'zoneid': zoneid,
            'ip_address': ip_address,
            'mac_address': mac_address
        }
        cur.execute("""select   cc.sessionid         sessionId
                        ,       cc.authenticated_via authenticated_via
                        , cc.ip_address
                       from     cp_clients cc
                       where    cc.deleted = 0
                       and      cc.zoneid = :zoneid
                       and   (
                                cc.ip_address = :ip_address
                                or
                                cc.mac_address = :mac_address
                             )""", request)

        result = []
        for row in cur.fetchall():
            result.append({'sessionId': row[0], 'authenticated_via': row[1]})
        return result

    def add_client(self, zoneid, authenticated_via, username, ip_address, mac_address):
        """ add a new client to the captive portal administration
        :param zoneid: cp zone number
        :param authenticated_via: name/id of the authenticator or ---ip--- / ---mac--- for authentication by address
        :param username: username, maybe empty
        :param ip_address: ip address (to unlock)
        :param mac_address: physical address of this ip
        :return: dictionary with session info
        """
        response = dict()
        response['zoneid'] = zoneid
        response['authenticated_via'] = authenticated_via
        response['userName'] = username
        response['ipAddress'] = ip_address
        response['macAddress'] = mac_address
        response['startTime'] = time.time()  # record creation = sign-in time
        response['sessionId'] = base64.b64encode(os.urandom(16)).decode()  # generate a new random session id

        cur = self._connection.cursor()
        # set cp_client as deleted in case there's already a user logged-in at this ip address.
        if ip_address is not None and ip_address != '':
            cur.execute("""UPDATE cp_clients
                           SET    deleted = 1
                           WHERE  zoneid = :zoneid
                           AND    ip_address = :ipAddress
                        """, response)

        # add new session
        cur.execute("""INSERT INTO cp_clients(zoneid, authenticated_via, sessionid, username,  ip_address, mac_address, created)
                       VALUES (:zoneid, :authenticated_via, :sessionId, :userName, :ipAddress, :macAddress, :startTime)
                    """, response)

        self._connection.commit()
        return response

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

        # Create ARP instance once to load tables (expensive operation done once)
        arp_helper = ARP()

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

                # Enrich record with all IP addresses (IPv4 and IPv6) via MAC lookup
                mac_address = record.get('macAddress')
                ipv4_addresses = []
                ipv6_addresses = []

                if mac_address and mac_address != '':
                    try:
                        # Get all addresses for this MAC
                        all_addresses = arp_helper.get_all_addresses_by_mac(mac_address)
                        # Separate into IPv4 and IPv6
                        if all_addresses:
                            for addr in all_addresses:
                                if addr and ':' in addr:
                                    ipv6_addresses.append(addr)
                                elif addr:
                                    ipv4_addresses.append(addr)
                    except Exception:
                        # If MAC lookup fails, fallback to classifying stored IP
                        pass

                # If no addresses found via MAC lookup, classify stored IP address
                if len(ipv4_addresses) == 0 and len(ipv6_addresses) == 0:
                    stored_ip = record.get('ipAddress', '')
                    if stored_ip:
                        if ':' in stored_ip:
                            ipv6_addresses.append(stored_ip)
                        else:
                            ipv4_addresses.append(stored_ip)

                # Always add enriched fields to record (ensure they're lists, not None)
                record['ipv4Addresses'] = ipv4_addresses if ipv4_addresses else []
                record['ipv6Addresses'] = ipv6_addresses if ipv6_addresses else []

                result.append(record)
        return result

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
        """ update internal accounting database with given pf info (not per zone)
        Aggregates stats across all IP addresses for each session (dual-stack support)
        :param details: pf accounting details (keyed by IP address)
        """
        if type(details) == dict:
            # Create ARP instance to map IPs to MACs for aggregation
            arp_helper = ARP()
            
            # query registered data with MAC addresses
            sql = """ select    cc.ip_address, cc.zoneid, cc.sessionid, cc.mac_address
                      ,         si.rowid si_rowid, si.last_accessed
                      from      cp_clients cc
                      left join session_info si on si.zoneid = cc.zoneid and si.sessionid = cc.sessionid
                      where     cc.deleted = 0
                      order by  cc.zoneid, cc.sessionid, cc.ip_address
                  """
            cur = self._connection.cursor()
            cur2 = self._connection.cursor()
            cur.execute(sql)
            
            # Group sessions and aggregate stats across all IPs per session
            sessions = {}
            for row in cur.fetchall():
                # map fieldnumbers to names
                record = {}
                for fieldId in range(len(row)):
                    record[cur.description[fieldId][0]] = row[fieldId]
                
                zoneid = record['zoneid']
                sessionid = record['sessionid']
                session_key = (zoneid, sessionid)
                
                if session_key not in sessions:
                    sessions[session_key] = {
                        'zoneid': zoneid,
                        'sessionid': sessionid,
                        'si_rowid': record['si_rowid'],
                        'mac_address': record['mac_address'],
                        'ip_addresses': [],
                        'aggregated': {
                            'packets_in': 0,
                            'packets_out': 0,
                            'bytes_in': 0,
                            'bytes_out': 0,
                            'last_accessed': 0
                        }
                    }
                
                # Collect all IPs for this session
                primary_ip = record['ip_address']
                sessions[session_key]['ip_addresses'].append(primary_ip)
                
                # Get all IPs for this MAC to aggregate stats
                if record['mac_address']:
                    try:
                        all_ips = arp_helper.get_all_addresses_by_mac(record['mac_address'])
                        sessions[session_key]['ip_addresses'].extend(all_ips)
                    except Exception:
                        pass
                
                # Remove duplicates
                sessions[session_key]['ip_addresses'] = list(set(sessions[session_key]['ip_addresses']))
            
            # Aggregate stats across all IPs for each session
            for session_key, session_data in sessions.items():
                aggregated = session_data['aggregated']
                
                for ip in session_data['ip_addresses']:
                    if ip in details:
                        ip_stats = details[ip]
                        aggregated['packets_in'] += ip_stats.get('in_pkts', 0)
                        aggregated['packets_out'] += ip_stats.get('out_pkts', 0)
                        aggregated['bytes_in'] += ip_stats.get('in_bytes', 0)
                        aggregated['bytes_out'] += ip_stats.get('out_bytes', 0)
                        # Use most recent last_accessed across all IPs
                        ip_last_accessed = ip_stats.get('last_accessed', 0) or 0
                        if ip_last_accessed > aggregated['last_accessed']:
                            aggregated['last_accessed'] = ip_last_accessed
                
                # Only update if we have stats
                if aggregated['packets_in'] > 0 or aggregated['packets_out'] > 0 or \
                   aggregated['bytes_in'] > 0 or aggregated['bytes_out'] > 0:
                    if session_data['si_rowid'] is None:
                        # new session, add info object
                        sql_new = """ insert into session_info(zoneid, sessionid, packets_in,
                                                               packets_out, bytes_in, bytes_out, last_accessed)
                                      values (:zoneid, :sessionid, :packets_in,
                                              :packets_out, :bytes_in, :bytes_out, :last_accessed)
                        """
                        params = {
                            'zoneid': session_data['zoneid'],
                            'sessionid': session_data['sessionid'],
                            'packets_in': aggregated['packets_in'],
                            'packets_out': aggregated['packets_out'],
                            'bytes_in': aggregated['bytes_in'],
                            'bytes_out': aggregated['bytes_out'],
                            'last_accessed': aggregated['last_accessed'] if aggregated['last_accessed'] > 0 else None
                        }
                        cur2.execute(sql_new, params)
                    else:
                        # update session
                        sql_update = """ update session_info
                                         set    last_accessed = :last_accessed
                                         ,      packets_in = packets_in + :packets_in
                                         ,      packets_out = packets_out + :packets_out
                                         ,      bytes_in = bytes_in + :bytes_in
                                         ,      bytes_out = bytes_out + :bytes_out
                                         where  rowid = :si_rowid
                        """
                        params = {
                            'si_rowid': session_data['si_rowid'],
                            'packets_in': aggregated['packets_in'],
                            'packets_out': aggregated['packets_out'],
                            'bytes_in': aggregated['bytes_in'],
                            'bytes_out': aggregated['bytes_out'],
                            'last_accessed': aggregated['last_accessed'] if aggregated['last_accessed'] > 0 else None
                        }
                        cur2.execute(sql_update, params)
            
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
