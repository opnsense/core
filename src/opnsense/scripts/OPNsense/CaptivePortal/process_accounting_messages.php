#!/usr/local/bin/php
<?php

/*
 * Copyright (C) 2015 Deciso B.V.
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * 1. Redistributions of source code must retain the above copyright notice,
 *    this list of conditions and the following disclaimer.
 *
 * 2. Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in the
 *    documentation and/or other materials provided with the distribution.
 *
 * THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
 * INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 * AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 * AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 * OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 * INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 */

require_once('script/load_phalcon.php');

use OPNsense\Auth\AuthenticationFactory;

// open database
$database_filename = '/var/captiveportal/captiveportal.sqlite';
$db = new SQLite3($database_filename);
$db->busyTimeout(30000);

// query all sessions with client restrictions
$result = $db->query('
    select      c.zoneid
    ,           c.sessionid
    ,           c.username
    ,           c.ip_address
    ,           c.authenticated_via
    ,           c.deleted
    ,           c.created
    ,           si.bytes_in
    ,           si.bytes_out
    ,           accs.state
    from        cp_clients c
    inner join  session_restrictions sr on sr.zoneid = c.zoneid and sr.sessionid = c.sessionid
    left join   session_info si on c.zoneid = si.zoneid and c.sessionid = si.sessionid
    left join   accounting_state accs on accs.zoneid = c.zoneid and accs.sessionid = c.sessionid
    order by    c.authenticated_via
    ');

// process all sessions
if ($result !== false) {
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $authFactory = new OPNsense\Auth\AuthenticationFactory();
        $authenticator = $authFactory->get($row['authenticated_via']);
        if ($authenticator != null) {
            if ($row['state'] == null) {
                // new accounting state, send start event (if applicable)
                $stmt = $db->prepare('insert into accounting_state(zoneid, sessionid, state)
                                      values (:zoneid, :sessionid, \'RUNNING\')');
                $stmt->bindParam(':zoneid', $row['zoneid']);
                $stmt->bindParam(':sessionid', $row['sessionid']);
                $stmt->execute();
                if (method_exists($authenticator, 'startAccounting')) {
                    // send start accounting event
                    $authenticator->startAccounting($row['username'], $row['sessionid']);
                }
            } elseif ($row['deleted'] == 1 && $row['state'] != 'STOPPED') {
                // stop accounting, send stop event (if applicable)
                $stmt = $db->prepare('update accounting_state
                                      set state = \'STOPPED\'
                                      where zoneid = :zoneid
                                      and   sessionid = :sessionid');
                $stmt->bindParam(':zoneid', $row['zoneid']);
                $stmt->bindParam(':sessionid', $row['sessionid']);
                $stmt->execute();
                if (method_exists($authenticator, 'stopAccounting')) {
                    $time_spend = time() - $row['created'];
                    $authenticator->stopAccounting($row['username'], $row['sessionid'], $time_spend, $row['bytes_in'], $row['bytes_out'], $row['ip_address']);
                }
            } elseif ($row['state'] != 'STOPPED') {
                // send interim updates (if applicable)
                if (method_exists($authenticator, 'updateAccounting')) {
                    // send interim update event
                    $time_spend = time() - $row['created'];
                    $authenticator->updateAccounting($row['username'], $row['sessionid'], $time_spend, $row['bytes_in'], $row['bytes_out'], $row['ip_address']);
                }
            }
        }
    }
}

$db->close();
