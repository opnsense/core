#!/usr/local/bin/php
<?php

/*
 * Copyright (C) 2026 Deciso B.V.
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

require_once("interfaces.inc");
require_once("system.inc");
require_once("config.inc");
require_once("util.inc");

$filename = '/tmp/.interfaces.todo';

$todos = (new \OPNsense\Core\FileObject($filename, 'r'))->readJson();
if (empty($todos)) {
    exit(0);
}

$interfaces = &config_read_array('interfaces');
$uuids = [];

foreach ($todos as $uuid => $work) {
    if (in_array($work['pending_action'], ['delete', 'relink'])) {
        interface_reset($uuid);
        if ($work['pending_action'] == 'delete') {
            continue;
        }
    } else {
        /* suspend only when we are changing the enabled status */
        interface_reset($uuid, false, !empty($interfaces[$uuid]['enable']) && !empty($work['pending']['enable']));
    }

    $uuids[] = $uuid;
}

if (!count($uuids)) {
    exit(0);
}

foreach ($todos as $uuid => $work) {
    if ($work['pending_action'] == 'delete') {
        unset($interfaces[$uuid]);
    } else {
        $interfaces[$uuid] = $work['pending'];
    }
}

/* XXX at this point interfaces_merge_pending() should start working */

foreach ($uuids as $uuid) {
    interface_configure(true, $uuid, true, false, true);
}

system_routing_configure(true, $uuids);

plugins_configure('ipsec', true, [$uuids]);
plugins_configure('dhcp', true);
plugins_configure('dns', true, [$uuids]);
plugins_configure('updateip', true, [$uuids]);

interfaces_pfsync_configure();
interface_proxyarp_configure();
