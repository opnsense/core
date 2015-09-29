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

    def add_client(self, zoneid, username, ip_address, mac_address):
        """ add a new client to the captive portal administration
        :param zoneid: cp zone number
        :param username: username, maybe empty
        :param ip_address: ip address (to unlock)
        :param mac_address: physical address of this ip
        :return: dictionary with session info
        """
        response = dict()
        response['zoneid'] = zoneid
        response['userName'] = username
        response['ipAddress'] = ip_address
        response['macAddress'] = mac_address
        response['startTime'] = time.time()  # record creation = sign-in time
        response['sessionId'] = base64.b64encode(os.urandom(16))  # generate a new random session id

        cur = self._connection.cursor()
        # update cp_clients in case there's already a user logged-in at this ip address.
        # places an implicit lock on this client.
        cur.execute("""update cp_clients
                       set    created = :startTime
                       ,      username = :userName
                       ,      mac_address = :macAddress
                       where  zoneid = :zoneid
                       and    ip_address = :ipAddress
                    """, response)

        # normal operation, new user at this ip, add to host
        if cur.rowcount == 0:
            cur.execute("""insert into cp_clients(zoneid, sessionid, username,  ip_address, mac_address, created)
                           values (:zoneid, :sessionId, :userName, :ipAddress, :macAddress, :startTime)
                        """, response)

        self._connection.commit()
        return response

    def list_clients(self, zoneid):
        """ return list of (administrative) connected clients
        :param zoneid: zone id
        :return: list of clients
        """
        result = list()
        fieldnames = list()
        cur = self._connection.cursor()
        # rename fields for API
        cur.execute(""" select  zoneid
                        ,       sessionid   sessionId
                        ,       username    userName
                        ,       created     startTime
                        ,       ip_address  ipAddress
                        ,       mac_address macAddress
                        from    cp_clients
                        where   zoneid = :zoneid
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
