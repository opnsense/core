#!/usr/local/bin/php
<?php

/*
 * Copyright (C) 2024 Deciso B.V.
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

require_once("auth.inc");
require_once("config.inc");

$opts = getopt('hu:', [], $optind);
$args = array_slice($argv, $optind);
if (isset($opts['h']) || empty($opts['u'])) {
    echo "Usage: sync_user.php [-h] \n";
    echo "\t-h show this help text and exit\n";
    echo "\t-u [required] username\n";
    exit(-1);
} else {
    $username = $opts['u'];
    $a_user = &config_read_array('system', 'user');

    $localusers = [];
    exec("/usr/sbin/pw usershow -a", $data, $ret);
    if (!$ret) {
        foreach ($data as $record) {
            $line = explode(':', $record);
            // filter system managed users
            if (count($line) < 3 ||  !strncmp($line[0], '_', 1) || ($line[2] < 2000 && $line[0] != 'root') || $line[2] > 65000) {
                continue;
            }
            $localusers[$line[0]] = $line;
        }
    }

    $update_user = null;
    $userdb = [];
    foreach ($a_user as $userent) {
        $userdb[] = $userent['name'];
        if ($userent['name'] == $username) {
            $update_user = $userent;
        }
    }

    /* rename/delete situations */
    foreach ($localusers as $item) {
        if (!in_array($item[0], $userdb)) {
            mwexecf('/usr/sbin/pw userdel -n %s', [$item[0]]);
        }
    }
    /* add or update when found */
    if ($update_user) {
        local_user_set($update_user, false, $localusers[$username] ?? []);
        /* signal backend that the user has changed. (update groups) */
        mwexecf('/usr/local/sbin/pluginctl -c user_changed ' . $username);
        echo json_encode(["status" => "updated"]);
    } else {
        echo json_encode(["status" => "not_found"]);
    }
}
