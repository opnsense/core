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

$opts = getopt('hg:', [], $optind);
$args = array_slice($argv, $optind);
if (isset($opts['h']) || empty($opts['g'])) {
    echo "Usage: sync_group.php [-h] \n";
    echo "\t-h show this help text and exit\n";
    echo "\t-g [required] groupname\n";
    exit(-1);
} else {
    $groupname = $opts['g'];
    $a_group = &config_read_array('system', 'group');

    $localgroups = [];
    exec("/usr/sbin/pw groupshow -a", $data, $ret);
    if (!$ret) {
        foreach ($data as $record) {
            $line = explode(':', $record);
            // filter system managed users and groups
            if (count($line) < 3 || !strncmp($line[0], '_', 1) || $line[2] < 2000 || $line[2] > 65000) {
                continue;
            }
            $localgroups[$line[0]] = $line;
        }
    }

    $update_group = null;
    $groupdb = [];
    foreach ($a_group as $groupent) {
        $groupdb[] = $groupent['name'];
        if ($groupent['name'] == $groupname) {
            $update_group = $groupent;
        }
    }

    /* rename/delete situations */
    foreach ($localgroups as $item) {
        if (!in_array($item[0], $groupdb)) {
            mwexecf('/usr/sbin/pw groupdel -n %s', [$item[0]]);
        }
    }

    /* add or update when found */
    if ($update_group) {
        local_group_set($update_group);
        echo json_encode(["status" => "updated"]);
    } else {
        echo json_encode(["status" => "not_found"]);
    }
}
