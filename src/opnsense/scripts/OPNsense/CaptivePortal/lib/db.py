"""
    Copyright (c) 2015 Ad Schellevis
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
    database_filename = '/tmp/captiveportal.sqlite'

    def __init__(self):
        """ construct new database connection
        :return:
        """
        self._connection = sqlite3.connect(self.database_filename)
        self.create()

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
        cur.execute('SELECT count(*) FROM sqlite_master')
        if cur.fetchall()[0][0] == 0:
            # empty database, initialize database
            init_script_filename = '%s/../sql/init.sql' % os.path.dirname(os.path.abspath(__file__))
            cur.executescript(open(init_script_filename, 'rb').read())
        cur.close()

    def add_client(self, zoneid, authenticated_via, username, ip_address, mac_address):
        """ add a new client to the captive portal administration
        :param zoneid: cp zone number
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
        response['sessionId'] = base64.b64encode(os.urandom(16))  # generate a new random session id

        cur = self._connection.cursor()
        # set cp_client as deleted in case there's already a user logged-in at this ip address.
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

    def del_client(self, zoneid, sessionid):
        """ mark (administrative) client for removal
        :param zoneid: zone id
        :param sessionid: session id
        :return: client info before removal or None if client not found
        """
        cur = self._connection.cursor()
        cur.execute(""" SELECT  *
                        FROM    cp_clients
                        WHERE   sessionid = :sessionid
                        AND     zoneid = :zoneid
                        AND     deleted = 0
                    """, {'zoneid': zoneid, 'sessionid': sessionid})
        data = cur.fetchall()
        if len(data) > 0:
            session_info = dict()
            for fields in cur.description:
                session_info[fields[0]] = data[0][len(session_info)]
            # remove client
            cur.execute("UPDATE cp_clients SET deleted = 1 WHERE sessionid = :sessionid AND zoneid = :zoneid",
                        {'zoneid': zoneid, 'sessionid': sessionid})
            self._connection.commit()

            return session_info
        else:
            return None

    def list_clients(self, zoneid):
        """ return list of (administrative) connected clients and usage statistics
        :param zoneid: zone id
        :return: list of clients
        """
        result = list()
        fieldnames = list()
        cur = self._connection.cursor()
        # rename fields for API
        cur.execute(""" SELECT  cc.zoneid
                        ,       cc.sessionid   sessionId
                        ,       cc.authenticated_via authenticated_via
                        ,       cc.username    userName
                        ,       cc.created     startTime
                        ,       cc.ip_address  ipAddress
                        ,       cc.mac_address macAddress
                        ,       CASE WHEN si.packets_in IS NULL THEN 0 ELSE si.packets_in END packets_in
                        ,       CASE WHEN si.packets_out IS NULL THEN 0 ELSE si.packets_out END packets_out
                        ,       CASE WHEN si.bytes_in IS NULL THEN 0 ELSE si.bytes_in END bytes_in
                        ,       CASE WHEN si.bytes_out IS NULL THEN 0 ELSE si.bytes_out END bytes_out
                        ,       CASE WHEN si.last_accessed IS NULL THEN 0 ELSE si.last_accessed END last_accessed
                        FROM    cp_clients cc
                        LEFT JOIN session_info si ON si.zoneid = cc.zoneid AND si.sessionid = cc.sessionid
                        WHERE   cc.zoneid = :zoneid
                        AND     cc.deleted = 0
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

    def update_accounting_info(self, details):
        """ update internal accounting database with given ipfw info (not per zone)
        :param details: ipfw accounting details
        """
        if type(details) == dict:
            # query registered data
            sql = """ select    cc.ip_address, cc.zoneid, cc.sessionid
                      ,         si.rowid si_rowid, si.prev_packets_in, si.prev_bytes_in
                      ,         si.prev_packets_out, si.prev_bytes_out, si.last_accessed
                      from      cp_clients cc
                      left join session_info si on si.zoneid = cc.zoneid and si.sessionid = cc.sessionid
                      order by  cc.ip_address, cc.deleted
                  """
            cur = self._connection.cursor()
            cur2 = self._connection.cursor()
            cur.execute(sql)
            prev_record = {'ip_address': None}
            for row in cur.fetchall():
                # map fieldnumbers to names
                record = {}
                for fieldId in range(len(row)):
                    record[cur.description[fieldId][0]] = row[fieldId]
                # search unique hosts from dataset, both disabled and enabled.
                if prev_record['ip_address'] != record['ip_address'] and record['ip_address'] in details:
                    if record['si_rowid'] is None:
                        # new session, add info object
                        sql_new = """ insert into session_info(zoneid, sessionid, prev_packets_in, prev_bytes_in,
                                                               prev_packets_out, prev_bytes_out,
                                                               packets_in, packets_out, bytes_in, bytes_out,
                                                               last_accessed)
                                      values (:zoneid, :sessionid, :packets_in, :bytes_in, :packets_out, :bytes_out,
                                              :packets_in, :packets_out, :bytes_in, :bytes_out, :last_accessed)
                        """
                        record['packets_in'] = details[record['ip_address']]['in_pkts']
                        record['bytes_in'] = details[record['ip_address']]['in_bytes']
                        record['packets_out'] = details[record['ip_address']]['out_pkts']
                        record['bytes_out'] = details[record['ip_address']]['out_bytes']
                        record['last_accessed'] = details[record['ip_address']]['last_accessed']
                        cur2.execute(sql_new, record)
                    else:
                        # update session
                        sql_update = """ update session_info
                                         set    last_accessed = :last_accessed
                                         ,      prev_packets_in = :prev_packets_in
                                         ,      prev_packets_out = :prev_packets_out
                                         ,      prev_bytes_in = :prev_bytes_in
                                         ,      prev_bytes_out = :prev_bytes_out
                                         ,      packets_in = packets_in + :packets_in
                                         ,      packets_out = packets_out + :packets_out
                                         ,      bytes_in = bytes_in + :bytes_in
                                         ,      bytes_out = bytes_out + :bytes_out
                                         where  rowid = :si_rowid
                        """
                        # add usage to session
                        record['last_accessed'] = details[record['ip_address']]['last_accessed']
                        if record['prev_packets_in'] <= details[record['ip_address']]['in_pkts'] and \
                           record['prev_packets_out'] <= details[record['ip_address']]['out_pkts']:
                            # ipfw data is still valid, add difference to use
                            record['packets_in'] = (
                                details[record['ip_address']]['in_pkts'] - record['prev_packets_in'])
                            record['packets_out'] = (
                                details[record['ip_address']]['out_pkts'] - record['prev_packets_out'])
                            record['bytes_in'] = (details[record['ip_address']]['in_bytes'] - record['prev_bytes_in'])
                            record['bytes_out'] = (
                                details[record['ip_address']]['out_bytes'] - record['prev_bytes_out'])
                        else:
                            # the data has been reset (reloading rules), add current packet count
                            record['packets_in'] = details[record['ip_address']]['in_pkts']
                            record['packets_out'] = details[record['ip_address']]['out_pkts']
                            record['bytes_in'] = details[record['ip_address']]['in_bytes']
                            record['bytes_out'] = details[record['ip_address']]['out_bytes']

                        record['prev_packets_in'] = details[record['ip_address']]['in_pkts']
                        record['prev_packets_out'] = details[record['ip_address']]['out_pkts']
                        record['prev_bytes_in'] = details[record['ip_address']]['in_bytes']
                        record['prev_bytes_out'] = details[record['ip_address']]['out_bytes']
                        cur2.execute(sql_update, record)

                prev_record = record
            self._connection.commit()
