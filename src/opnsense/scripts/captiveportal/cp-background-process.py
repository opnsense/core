#!/usr/local/bin/python3

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
        # Note: We use PF accounting, not IPFW

    def _remove_client(self, zoneid, ip):
        PF.remove_from_table(zoneid, ip)
        # Note: We use PF accounting, not IPFW

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
            registered_addresses = list(PF.list_table(zoneid))
            expected_clients = self.db.list_clients(zoneid)
            concurrent_users = self.db.find_concurrent_user_sessions(zoneid)

            # handle connected clients, timeouts, address changes, etc.
            for db_client in expected_clients:
                # fetch ip address (or network) from database
                cpnet = db_client['ipAddress'].strip()

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

                    # if mac address changes, drop session. it's not the same client
                    current_arp = self.arp.get_by_ipaddress(cpnet)
                    if current_arp is not None and current_arp['mac'] != db_client['macAddress']:
                        drop_session_reason = "mac address changed for session %s" % db_client['sessionId']
                        delete_reason = "Admin-Reset"

                    # session accounting
                    if db_client['acc_session_timeout'] is not None \
                            and type(db_client['acc_session_timeout']) in (int, float) \
                            and time.time() - float(db_client['startTime']) > db_client['acc_session_timeout']:
                            drop_session_reason = "accounting limit reached for session %s" % db_client['sessionId']
                            delete_reason = "Session-Timeout"
                elif db_client['authenticated_via'] == '---mac---':
                    # MAC-based authentication: ensure prioritized addresses (IPv4 + IPv6) are in PF table
                    # Enhanced to support dual-stack and IPv6 address rotation (privacy extensions, SLAAC changes, DHCPv6 renewals)
                    # Note: We can only discover addresses that appear in NDP (have been used for neighbor discovery)
                    # New addresses will be discovered as they appear in NDP and added automatically
                    all_addresses = self.arp.get_all_addresses_by_mac(db_client['macAddress'])

                    if all_addresses:
                        # Get the primary address (prefer IPv4, then first IPv6)
                        current_ip = None
                        for addr in all_addresses:
                            if ':' not in addr:  # IPv4
                                current_ip = addr
                                break
                        if current_ip is None:
                            current_ip = all_addresses[0]  # Use first IPv6 if no IPv4

                        # Update database if primary address changed
                        if current_ip != db_client['ipAddress']:
                            old_ip = db_client['ipAddress']
                            if old_ip != '':
                                syslog.syslog(syslog.LOG_INFO,
                                    "MAC-based session %s: primary IP changed from %s to %s (zone %s)" %
                                    (db_client['sessionId'], old_ip, current_ip, zoneid))
                            self.db.update_client_ip(zoneid, db_client['sessionId'], current_ip)

                        # Add all addresses to PF table (dual-stack support)
                        for addr in all_addresses:
                            if addr not in registered_addresses:
                                self._add_client(zoneid, addr)
                                syslog.syslog(syslog.LOG_INFO,
                                    "MAC-based session %s: added address %s to PF table (zone %s)" %
                                    (db_client['sessionId'], addr, zoneid))
                    elif db_client['ipAddress'] != '':
                        # Handle expired addresses: stored IP no longer exists in ARP/NDP
                        stored_ip = db_client['ipAddress']
                        stored_entry = self.arp.get_by_ipaddress(stored_ip)
                        if stored_entry is None:
                            # IP expired and MAC has no addresses - remove from PF table
                            self._remove_client(zoneid, stored_ip)
                            syslog.syslog(syslog.LOG_INFO,
                                "MAC-based session %s: expired IP %s removed from PF table (zone %s)" %
                                (db_client['sessionId'], stored_ip, zoneid))

                # check session, if it should be active, validate its properties
                if drop_session_reason is None:
                    # registered client, but not active or missing accounting according to pf (after reboot)
                    if cpnet not in registered_addresses:
                        self._add_client(zoneid, cpnet)

                    # Dual-stack awareness: check for additional prioritized addresses (IPv4/IPv6) not in PF table
                    # This ensures dual-stack clients have all their addresses allowed (that appear in NDP)
                    # Note: We can only discover addresses that appear in NDP (have been used for neighbor discovery)
                    if db_client['macAddress'] and db_client['macAddress'] != '':
                        all_addresses = self.arp.get_all_addresses_by_mac(db_client['macAddress'])
                        for addr in all_addresses:
                            if addr not in registered_addresses:
                                self._add_client(zoneid, addr)
                                syslog.syslog(syslog.LOG_INFO,
                                    "Session %s: added missing dual-stack address %s to PF table (zone %s)" %
                                    (db_client['sessionId'], addr, zoneid))
                else:
                    # remove session
                    syslog.syslog(syslog.LOG_NOTICE, drop_session_reason)
                    self._remove_client(zoneid, cpnet)
                    self.db.del_client(zoneid, db_client['sessionId'], delete_reason)

            # if there are addresses/networks in the underlying pf table which are not in our administration,
            # remove them from pf.
            for registered_address in registered_addresses:
                address_active = False
                for db_client in expected_clients:
                    if registered_address == db_client['ipAddress']:
                        address_active = True
                        break
                if not address_active:
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
