#!/usr/local/bin/php
<?php

/*
 * Copyright (C) 2024 Franco Fichtner <franco@opnsense.org>
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
require_once 'util.inc';
require_once 'system.inc';
require_once 'interfaces.inc';
require_once 'filter.inc';
require_once 'auth.inc';

if (empty($argv[1]) || empty($argv[2])) {
    exit(1);
}

$interface = convert_real_interface_to_friendly_interface_name($argv[1]);
$family = $argv[2];

if (empty($interface) || ($family != 4 && $family != 6)) {
    exit(1);
}

if (!interface_ppps_bound($interface, $family)) {
    exit(1);
}

switch (isset($config['system']['ipv6allow']) ? ($config['interfaces'][$interface]['ipaddrv6'] ?? 'none') : 'none') {
    case 'dhcp6':
    case 'slaac':
        interface_dhcpv6_prepare($interface, $config['interfaces'][$interface]);
        interface_dhcpv6_configure($interface, $config['interfaces'][$interface]);
        /* signal this succeeded to avoid triggering a newwanip event right away */
        exit(0);
    default:
        interface_static6_configure($interface, $config['interfaces'][$interface]);
        system_routing_configure(false, $interface, true, 'inet6');
}

exit(1);
