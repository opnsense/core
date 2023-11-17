#!/usr/local/bin/php
<?php

/*
 * Copyright (C) 2023 Deciso B.V.
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

require_once('config.inc');
require_once('util.inc');
require_once('interfaces.inc');

use OPNsense\Core\Config;
use OPNsense\Routing\Gateways;

$opts = getopt("i:d:h", [], $optind);
$args = array_slice($argv, $optind);

if (isset($opts['h'])) {
    echo "usage: ifinfo.php [-i <interface>|-d <0|1>]\n";
    exit(0);
}

$int = $opts['i'] ?? null;
$detailed = $opts['d'] ?? null;

$cfg = Config::getInstance()->object();
$gateways = new Gateways();
$ifconfig = legacy_interfaces_details($int);

/* Combine interfaces details with config */
foreach ($cfg->interfaces->children() as $key => $node) {
    if (!empty((string)$node->if) && !empty($ifconfig[(string)$node->if])) {
        $props = [];
        foreach ($node->children() as $property) {
            $props[$property->getName()] = (string)$property;
        }
        $ifconfig[(string)$node->if]['config'] = $props;
        $ifconfig[(string)$node->if]['config']['identifier'] = $key;
    }
}


if (!empty($detailed)) {
    $stats = legacy_interface_stats();
    foreach ($ifconfig as $if => $config) {
        if (array_key_exists($if, $stats)) {
            $ifconfig[$if]['statistics'] = $stats[$if];
        }
    }

    /* Include primary address 4/6 as well */
    foreach ($ifconfig as $if => $config) {
        if (empty($config['config'])) {
            continue;
        }

        $ifconfig[$if]['ipaddr'] = '';
        if (!empty($config['ipv4'])) {
            list ($primary4,, $bits4) = interfaces_primary_address($config['config']['identifier'], $ifconfig);
            if (!empty($primary4)) {
                $ifconfig[$if]['ipaddr'] = $primary4;
                $ifconfig[$if]['subnet'] = $bits4;
            } else {
                $ifconfig[$if]['ipaddr'] = $config['ipv4'][0]['ipaddr'];
                $ifconfig[$if]['subnet'] = $config['ipv4'][0]['subnetbits'];
            }
        }

        $ifconfig[$if]['ipaddrv6'] = '';
        if (!empty($config['ipv6'])) {
            list ($primary6,, $bits6) = interfaces_primary_address6($config['config']['identifier'], $ifconfig);
            if (!empty($primary6)) {
                $ifconfig[$if]['ipaddrv6'] = $primary6;
                $ifconfig[$if]['subnetv6'] = $bits6;
            }
            foreach ($config['ipv6'] as $ipv6addr) {
                if (!empty($ipv6addr['link-local'])) {
                    $ifconfig[$if]['linklocal'] = $ipv6addr['ipaddr'];
                } elseif (empty($ifinfo['ipaddrv6'])) {
                    $ifconfig[$if]['ipaddrv6'] = $ipv6addr['ipaddr'];
                    $ifconfig[$if]['subnetv6'] = $ipv6addr['subnetbits'];
                }
            }
        }
    }
}

echo json_encode($ifconfig, JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT) . PHP_EOL;
