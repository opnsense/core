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
require_once("interfaces.inc");
require_once("config.inc");
require_once("util.inc");

$client_ip = isset($argv[1]) ? $argv[1] : '';
$system_mac = '';

if (!empty($client_ip)) {
    $macs = OPNsense\Core\Shell::shell_safe('/usr/sbin/arp -an | grep %s | awk \'{ print $4 }\'', [$client_ip], true);
    $system_mac = !empty($macs[0]) ? $macs[0] : '';
}

// fall back to the MAC of a primary interface on this system
if (empty($system_mac)) {
    $primary_if = get_primary_interface_from_list();
    $system_mac = get_interface_mac(get_real_interface($primary_if));
}

$result = [];

// LLT
$llt = '';
$ts = time() - 946684800;
$hts = dechex($ts);
$timestamp = sprintf("%s", $hts);
$timestamp_array = str_split($timestamp, 2);
$timestamp = implode(":", $timestamp_array);
$type = "\x00\x01\x00\x01";
for ($count = 0; $count < strlen($type);) {
    $llt .= bin2hex($type[$count]);
    $count++;
    if ($count < strlen($type)) {
        $llt .= ':';
    }
}
$result['llt'] = strtoupper($llt . ':' . $timestamp . ':' . $system_mac);

// LL - NO TIMESTAMP: Just 00:03:00:01: + Link layer address in canonical form, so says RFC.
$ll = '';
$type = "\x00\x03\x00\x01";
for ($count = 0; $count < strlen($type);) {
    $ll .= bin2hex($type[$count]);
    $count++;
    if ($count < strlen($type)) {
        $ll .= ':';
    }
}
$result['ll'] = strtoupper($ll . ':' . $system_mac);

// UUID
$uuid = '';
$type = "\x00\x00\x00\x04" . random_bytes(16);
for ($count = 0; $count < strlen($type);) {
    $uuid .= bin2hex($type[$count]);
    $count++;
    if ($count < strlen($type)) {
        $uuid .= ':';
    }
}
$result['uuid'] = strtoupper($uuid);

// EN - Using OPNsense PEN
$en = '';
$type = "\x00\x02\x00\x00\xD2\x6D" . random_bytes(8);
for ($count = 0; $count < strlen($type);) {
    $en .= bin2hex($type[$count]);
    $count++;
    if ($count < strlen($type)) {
        $en .= ':';
    }
}
$result['en'] = strtoupper($en);

$result['current'] = dhcp6c_duid_read();
$result['default'] = 'XX:XX:XX:XX:XX:XX:XX:XX:XX:XX:XX:XX:XX:XX';

echo json_encode($result);
