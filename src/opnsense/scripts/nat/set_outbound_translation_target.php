#!/usr/local/bin/php
<?php

/*
 * Copyright (c) 2020 Josef 'veloc1ty' Stautner
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

# Parameter 1: Interface name or alias
# Parameter 2: New translation ip

if ( ! isset($argv[1]) || ! isset($argv[2]) ) {
   echo("2 parameters needed: interface name and new translation target address\n");
   exit(1);
} else if ( ! is_ipaddr($argv[2]) ) {
   echo("parameter 2 needs to be a valid ip address\n");
   exit(1);
}

$interface = $argv[1];
$newAddress = $argv[2];

$rules = &config_read_array('nat', 'outbound', 'rule');
$interfaces = config_read_array('interfaces');

foreach ( $rules as $i => &$ruleconfig ) {
	if ( $ruleconfig['interface'] == $interface || getUserProvidedInterfaceName($ruleconfig['interface'], $interfaces) == $interface ) {
        if ( $ruleconfig['targetip'] != $newAddress ) {
            $ruleconfig['target'] = 'other-subnet';
            $ruleconfig['targetip'] = $newAddress;

            write_config();
            configdp_run('filter reload');
        }

	    echo("OK\n");
		exit(0);
	}
}

echo("Interface with that name not found\n");
exit(1);

function getUserProvidedInterfaceName($interface, $interfaces) {
    if ( array_key_exists($interface, $interfaces) &&
        array_key_exists('descr', $interfaces[$interface]) &&
        strlen($interfaces[$interface]['descr']) > 0 ) {
        return $interfaces[$interface]['descr'];
    } else {
        return '';
    }
}
