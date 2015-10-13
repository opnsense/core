#!/usr/local/bin/python2.7

"""
    Copyright (c) 2015 Deciso B.V. - Ad Schellevis
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
import sys
import ujson
import time
import syslog
import traceback
from lib import Config
from lib.db import DB
from lib.arp import ARP
from lib.ipfw import IPFW
from lib.daemonize import Daemonize

def main():
    syslog.openlog('captiveportal', logoption=syslog.LOG_DAEMON)
    syslog.syslog(syslog.LOG_NOTICE, 'starting captiveportal background process')
    # handle to ipfw, arp and the config
    ipfw = IPFW()
    arp = ARP()
    cnf = Config()
    while True:
        try:
            # construct objects
            db = DB()

            # update accounting info
            db.update_accounting_info(ipfw.list_accounting_info())

            # process sessions per zone
            arp.reload()
            cpzones = cnf.get_zones()
            for zoneid in cpzones:
                registered_addresses = ipfw.list_table(zoneid)
                registered_add_accounting = ipfw.list_accounting_info()
                expected_clients = db.list_clients(zoneid)
                concurrent_users = db.find_concurrent_user_sessions(zoneid)

                # handle connected clients, timeouts, address changes, etc.
                for db_client in expected_clients:
                    # fetch ip address (or network) from database
                    cpnet = db_client['ipAddress'].strip()

                    # there are different reasons why a session should be removed, check for all reasons and
                    # use the same method for the actual removal
                    drop_session_reason = None

                    # session cleanups, only for users not for static hosts/ranges.
                    if db_client['userName'] != '':
                        # check if hardtimeout is set and overrun for this session
                        if 'hardtimeout' in cpzones[zoneid] and str(cpzones[zoneid]['hardtimeout']).isdigit():
                            # hardtimeout should be set and we should have collected some session data from the client
                            if int(cpzones[zoneid]['hardtimeout']) > 0  and float(db_client['startTime']) > 0:
                                if (time.time() - float(db_client['startTime'])) / 60 > int(cpzones[zoneid]['hardtimeout']):
                                    drop_session_reason = "session %s hit hardtimeout" % db_client['sessionId']

                        # check if idletimeout is set and overrun for this session
                        if 'idletimeout' in cpzones[zoneid] and str(cpzones[zoneid]['idletimeout']).isdigit():
                            # idletimeout should be set and we should have collected some session data from the client
                            if int(cpzones[zoneid]['idletimeout']) > 0 and float(db_client['last_accessed']) > 0:
                                if (time.time() - float(db_client['last_accessed'])) / 60 > int(cpzones[zoneid]['idletimeout']):
                                    drop_session_reason = "session %s hit idletimeout" % db_client['sessionId']

                        # cleanup concurrent users
                        if 'concurrentlogins' in cpzones[zoneid] and int(cpzones[zoneid]['concurrentlogins']) == 0:
                            if db_client['sessionId'] in concurrent_users:
                                drop_session_reason = "remove concurrent session %s" % db_client['sessionId']

                        # if mac address changes, drop session. it's not the same client
                        current_arp = arp.get_by_ipaddress(db_client['ipAddress'])
                        if current_arp is not None and current_arp['mac'] != db_client['macAddress']:
                            drop_session_reason = "mac address changed for session %s" % db_client['sessionId']

                    # check session, if it should be active, validate its properties
                    if drop_session_reason is None:
                        # registered client, but not active according to ipfw (after reboot)
                        if cpnet not in registered_addresses:
                            ipfw.add_to_table(zoneid, cpnet)

                        # is accounting rule still available? need to reapply after reload / reboot
                        if cpnet not in registered_add_accounting and db_client['ipAddress'] not in registered_add_accounting:
                            ipfw.add_accounting(cpnet)
                    else:
                        # remove session
                        syslog.syslog(syslog.LOG_NOTICE, drop_session_reason)
                        db.del_client(zoneid, db_client['sessionId'])
                        ipfw.delete_from_table(zoneid, cpnet)
                        ipfw.del_accounting(cpnet)

            # cleanup, destruct
            del db

            # sleep
            time.sleep(5)
        except KeyboardInterrupt:
            break
        except SystemExit:
            break
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
