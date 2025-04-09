#!/usr/local/bin/php
<?php

/*
 * Copyright (C) 2025 Deciso B.V.
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

require_once 'config.inc';
require_once 'interfaces.inc';
require_once 'util.inc';
require_once 'system.inc';

$ifconfig_details = legacy_interfaces_details();
$current_bridgeifs = [];
if (isset($config['bridges']['bridged'])) {
    foreach ($config['bridges']['bridged'] as $bridge) {
        $current_bridgeifs[$bridge['bridgeif']] = $bridge;
    }
}

/* delete before update */
foreach (array_keys($ifconfig_details) as $ifname) {
    if (str_starts_with($ifname, 'bridge') && !isset($current_bridgeifs[$ifname])) {
        legacy_interface_destroy($ifname);
    }
}
/* update and create new */
foreach ($current_bridgeifs as $bridge) {
    _interfaces_bridge_configure($bridge, $ifconfig_details);
}
