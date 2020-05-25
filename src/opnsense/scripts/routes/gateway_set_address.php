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

# Parameter 1: Gateway name
# Parameter 2: New gateway address

if ( ! isset($argv[1]) || ! isset($argv[2]) ) {
  echo("2 parameters needed: gateway name and new gateway address\n");
  exit(1);
} else if ( ! is_ipaddr($argv[2]) ) {
  echo("parameter 2 needs to be a valid ip address\n");
  exit(1);
}

$wantedGateway = $argv[1];
$newAddress = $argv[2];

$gateways = &config_read_array('gateways', 'gateway_item');

foreach ( $gateways as $i => &$gwconfig ) {
  if ( $gwconfig['name'] == $wantedGateway ) {
      if ( $gwconfig['gateway'] != $newAddress ) {
        $gwconfig['gateway'] = $newAddress;
        write_config();
        configdp_run('filter reload');
      }

      echo("OK\n");
      exit(0);
   }
}

echo("Gateway with that name not found\n");
exit(1);
