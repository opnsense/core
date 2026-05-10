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

if (is_array($config['interfaces'])) {
    if (is_file('/tmp/.interfaces.todo')) {
        $todos = (new \OPNsense\Core\FileObject('/tmp/.interfaces.todo', 'r'))->readJson() ?? [];
    } else {
        $todos = [];
    }

    $to_configure = [];
    foreach ($config['interfaces'] as $id => $ifcfg) {
        if (!isset($todos[$id])) {
            continue;
        }
        $pending_act = $todos[$id]['pending_action'] ?? '';
        if (in_array($pending_act, ['delete', 'relink'])) {
            interface_reset($id);
            if ($pending_act == 'relink') {
                $to_configure[] = $id;
            }
        }
    }

    foreach ($to_configure as $ifname) {
        $config['interfaces'][$ifname]['if'] = $todos[$ifname]['pending_if'];
        if (isset($config['interfaces'][$ifname]['wireless'])) {
            interface_sync_wireless_clones($config['interfaces'][$ifname], false);
        }
        /* Reload all for the interface. */
        interface_configure(false, $ifname, true);
    }
}