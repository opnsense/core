#!/usr/local/bin/python3

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

    --------------------------------------------------------------------------------------
    update captive portal statistics
"""
import copy
import sys
import time
import syslog
import traceback
import subprocess
import sqlite3
sys.path.insert(0, "/usr/local/opnsense/site-python")
from lib import Config
from lib.db import DB
from lib.arp import ARP
from lib.pf import PF
from lib.ipfw import IPFW
from lib.daemonize import Daemonize
from sqlite3_helper import check_and_repair


class CPBackgroundProcess(object):
    """ background process helper class
    """
    def __init__(self):
        # open syslog and notice startup
        syslog.openlog('captiveportal', facility=syslog.LOG_LOCAL4)
        syslog.syslog(syslog.LOG_NOTICE, 'starting captiveportal background process')
        # handles to pf, arp, the config and the internal administration
        self.arp = ARP()
        self.cnf = Config()
        self.db = DB()
        self._conf_zone_info = self.cnf.get_zones()

    def list_zone_ids(self):
        """ return zone numbers
        """
        return self._conf_zone_info.keys()

    def _add_client(self, zoneid, ip):
        PF.add_to_table(zoneid, ip)
        IPFW.add_accounting(ip)

    def _remove_client(self, zoneid, ip):
        PF.remove_from_table(zoneid, ip)
        IPFW.del_accounting(ip)

    def initialize_fixed(self):
        """ initialize fixed ip / hosts per zone
        """
        cpzones = self._conf_zone_info
        for zoneid in cpzones:
            for conf_section in ['allowedaddresses', 'allowedmacaddresses']:
                for address in (a.lower() for a in cpzones[zoneid][conf_section]):
                    if conf_section.find('mac') == -1:
                        sessions = self.db.sessions_per_address(zoneid, ip_address=address)
                        ip_address = address
                        mac_address = None
                    else:
                        sessions = self.db.sessions_per_address(zoneid, mac_address=address)
                        ip_address = None
                        mac_address = address
                    sessions_deleted = 0
                    for session in sessions:
                        if session['authenticated_via'] not in ('---ip---', '---mac---'):
                            sessions_deleted += 1
                            self.db.del_client(zoneid, session['sessionId'], 'NAS-Request')
                    if sessions_deleted == len(sessions) or len(sessions) == 0:
                        # when there's no session active, add a new one
                        # (only administrative, the sync process will add it if necessary)
                        if ip_address is not None:
                            self.db.add_client(zoneid, "---ip---", "", ip_address, "")
                        else:
                            self.db.add_client(zoneid, "---mac---", "", "", mac_address)

            # cleanup removed static sessions
            for dbclient in self.db.list_clients(zoneid):
                if dbclient['authenticated_via'] == '---ip---' \
                        and dbclient['ipAddress'] not in cpzones[zoneid]['allowedaddresses']:
                        self._remove_client(zoneid, dbclient['ipAddress'])
                        self.db.del_client(zoneid, dbclient['sessionId'], 'NAS-Request')
                elif dbclient['authenticated_via'] == '---mac---' \
                        and dbclient['macAddress'] not in (a.lower() for a in cpzones[zoneid]['allowedmacaddresses']):
                        if dbclient['ipAddress'] != '':
                            self._remove_client(zoneid, dbclient['ipAddress'])
                        self.db.del_client(zoneid, dbclient['sessionId'], 'NAS-Request')

    def sync_zone(self, zoneid, registered_addr_accounting):
        """ Synchronize captiveportal zone.
            Handles timeouts and administrative changes to this zones sessions
        """
        if zoneid in self._conf_zone_info:
            # fetch data for this zone
            cpzone_info = self._conf_zone_info[zoneid]
            registered_addresses_pf = set(PF.list_table(zoneid))
            registered_addresses_ipfw = set(registered_addr_accounting.keys())
            expected_clients = self.db.list_clients(zoneid)
            concurrent_users = self.db.find_concurrent_user_sessions(zoneid)
            allow_roaming = bool(int(cpzone_info['roaming']))

            # handle connected clients, timeouts, address changes, etc.
            for db_client in expected_clients:
                # fetch ip address (or network) from database
                session_ips = self.db.list_session_ips(zoneid, db_client['sessionId'])

                # there are different reasons why a session should be removed, check for all reasons and
                # use the same method for the actual removal
                drop_session_reason = None
                # XXX delete_reason is currently mapped to RADIUS keywords, as is this
                # is the only authenticator handling accounting
                delete_reason = None

                # session cleanups, only for users not for static hosts/ranges.
                if db_client['authenticated_via'] not in ('---ip---', '---mac---'):
                    # check if hardtimeout is set and overrun for this session
                    if 'hardtimeout' in cpzone_info and str(cpzone_info['hardtimeout']).isdigit():
                        # hardtimeout should be set and we should have collected some session data from the client
                        if int(cpzone_info['hardtimeout']) > 0 and float(db_client['startTime']) > 0:
                            if (time.time() - float(db_client['startTime'])) / 60 > int(cpzone_info['hardtimeout']):
                                drop_session_reason = "session %s hit hardtimeout" % db_client['sessionId']
                                delete_reason = "Session-Timeout"

                    # check if idletimeout is set and overrun for this session
                    if 'idletimeout' in cpzone_info and str(cpzone_info['idletimeout']).isdigit():
                        # idletimeout should be set and we should have collected some session data from the client
                        if int(cpzone_info['idletimeout']) > 0 and float(db_client['last_accessed']) > 0:
                            if (time.time() - float(db_client['last_accessed'])) / 60 > int(cpzone_info['idletimeout']):
                                drop_session_reason = "session %s hit idletimeout" % db_client['sessionId']
                                delete_reason = "Idle-Timeout"

                    # cleanup concurrent users
                    if 'concurrentlogins' in cpzone_info and int(cpzone_info['concurrentlogins']) == 0:
                        if db_client['sessionId'] in concurrent_users:
                            drop_session_reason = "remove concurrent session %s" % db_client['sessionId']
                            delete_reason = "User-Request"

                    # if mac address changes, drop session. it's not the same client. Use the "primary IP" to determine this
                    current_arp = self.arp.get_by_ipaddress(db_client['ipAddress'])
                    if current_arp is not None and current_arp['mac'] != db_client['macAddress']:
                        drop_session_reason = "mac address changed for session %s" % db_client['sessionId']
                        delete_reason = "Admin-Reset"

                    # session accounting
                    if db_client['acc_session_timeout'] is not None \
                            and type(db_client['acc_session_timeout']) in (int, float) \
                            and time.time() - float(db_client['startTime']) > db_client['acc_session_timeout']:
                            drop_session_reason = "accounting limit reached for session %s" % db_client['sessionId']
                            delete_reason = "Session-Timeout"

                if drop_session_reason is not None:
                    # remove session
                    syslog.syslog(syslog.LOG_NOTICE, drop_session_reason)
                    for ip in session_ips:
                        self._remove_client(zoneid, ip)
                    self.db.del_client(zoneid, db_client['sessionId'], delete_reason)
                    continue

                # if primary IP changed, update db accordingly. Not relevant for static IP-authenticated clients
                if db_client['authenticated_via'] != '---ip---':
                    current_ips = self.arp.get_all_addresses_by_mac(db_client['macAddress'])
                    if len(current_ips) > 0 and db_client['ipAddress'] != current_ips[0]:
                        if db_client['ipAddress'] != '':
                            # remove old ip
                            self._remove_client(zoneid, db_client['ipAddress'])
                        self.db.update_client_ip(zoneid, db_client['sessionId'], current_ips[0])
                        self._add_client(zoneid, current_ips[0])
                        db_client['ipAddress'] = current_ips[0]

                # session should be active, validate its properties
                if allow_roaming:
                    # this will add the "primary" IP as well, but both list_session_ips and update_roaming_ips will return a deduplicated set
                    session_ips = self.db.update_roaming_ips(zoneid, db_client['sessionId'], self.arp.get_all_addresses_by_mac(db_client['macAddress']))
                else:
                    # may have been updated if primary IP changed
                    session_ips = set(db_client['ipAddress'])

                to_add = (session_ips - registered_addresses_pf) | (session_ips - registered_addresses_ipfw)
                if session_ips and to_add:
                    for ip in to_add:
                        self._add_client(zoneid, ip)

            # remove any address from pf/ipfw that isn't expected
            expected_addresses = set()
            # need to query again as clients may have been updated
            for db_client in self.db.list_clients(zoneid):
                expected_addresses.update(self.db.list_session_ips(zoneid, db_client['sessionId']))

            for registered_address in (registered_addresses_pf | registered_addresses_ipfw):
                if registered_address not in expected_addresses:
                    self._remove_client(zoneid, registered_address)

def main():
    """ Background process loop, runs as backend daemon for all zones. only one should be active at all times.
        The main job of this procedure is to sync the administration with the actual situation in pf.
    """
    # perform integrity check and repair database if needed
    check_and_repair('/var/captiveportal/captiveportal.sqlite')

    last_cleanup_timestamp = 0
    bgprocess = CPBackgroundProcess()
    bgprocess.initialize_fixed()

    while True:
        try:
            # open database
            bgprocess.db.open()

            # cleanup old settings, every 5 minutes
            if time.time() - last_cleanup_timestamp > 300:
                bgprocess.db.cleanup_sessions()
                last_cleanup_timestamp = time.time()

            # reload cached arp table contents
            bgprocess.arp.reload()

            accounting_info = IPFW.list_accounting_info()

            # process sessions per zone
            for zoneid in bgprocess.list_zone_ids():
                bgprocess.sync_zone(zoneid, accounting_info)

            # update accounting info, for all zones
            bgprocess.db.update_accounting_info(accounting_info)

            # close the database handle while waiting for the next poll
            bgprocess.db.close()

            # process accounting messages (uses php script, for reuse of Auth classes)
            try:
                subprocess.run(
                    ['/usr/local/opnsense/scripts/captiveportal/process_accounting_messages.php'],
                    capture_output=True
                )
            except OSError:
                # if accounting script crashes don't exit background process
                pass

            # sleep
            time.sleep(5)
        except KeyboardInterrupt:
            break
        except SystemExit:
            break
        except sqlite3.DatabaseError:
            # try to repair a broken sqlite database if it appears to be broken after using a table
            syslog.syslog(syslog.LOG_ERR, "Forcefully repair database (%s)" % traceback.format_exc().replace("\n", " "))
            check_and_repair('/var/captiveportal/captiveportal.sqlite', force_repair=True)
            time.sleep(60)
        except:
            syslog.syslog(syslog.LOG_ERR, traceback.format_exc())
            print(traceback.format_exc())
            break

# startup
if len(sys.argv) > 1 and sys.argv[1].strip().lower() == 'run':
    main()
else:
    daemon = Daemonize(app=__file__.split('/')[-1].split('.py')[0], pid='/var/run/captiveportal.db.pid', action=main)
    daemon.start()
