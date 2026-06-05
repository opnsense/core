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

/**
 * simple wrapper to convert legacy source NAT rules into usable data for our MVC implementation
 */

require_once('config.inc');

function normalize_port($port)
{
    $port = (string)$port;
    $port = str_replace('-any', '-65535', $port);
    $port = str_replace('any-', '1-', $port);
    return $port;
}

function legacy_address_to_network($data)
{
    if (!is_array($data)) {
        return 'any';
    } elseif (isset($data['any'])) {
        return 'any';
    } elseif (!empty($data['network'])) {
        return $data['network'];
    } elseif (!empty($data['address'])) {
        return $data['address'];
    }

    return 'any';
}

function legacy_target_to_network($rule)
{
    $interface = !empty($rule['interface']) ? $rule['interface'] : 'wan';
    $target = (string)($rule['target'] ?? '');

    /*
     * Empty target in the legacy outbound NAT rule means interface address.
     * In the MVC model we store this explicitly as <interface>ip.
     */
    if ($target === '') {
        return $interface . 'ip';
    }

    /*
     * "other-subnet" stores the actual address in targetip and optional mask
     * in targetip_subnet.
     */
    if ($target === 'other-subnet') {
        $targetip = (string)($rule['targetip'] ?? '');
        $subnet = (string)($rule['targetip_subnet'] ?? '');

        if ($targetip === '') {
            return $interface . 'ip';
        }

        if ($subnet !== '' && $subnet !== '0' && $subnet !== '32' && $subnet !== '128') {
            return $targetip . '/' . $subnet;
        }

        return $targetip;
    }

    return $target;
}

$nat_rules = config_read_array('nat', 'outbound', 'rule', false);
$result = [];

if (count($nat_rules)) {
    $sequence = 1;

    foreach ($nat_rules as $rule) {
        $source = $rule['source'] ?? [];
        $destination = $rule['destination'] ?? [];

        $target_rule = [
            '@uuid' => '',
            'enabled' => empty($rule['disabled']) ? '1' : '0',
            'nonat' => !empty($rule['nonat']) ? '1' : '0',
            'nosync' => !empty($rule['nosync']) ? '1' : '0',
            'sequence' => $sequence,
            'interface' => $rule['interface'] ?? 'lan',
            'ipprotocol' => $rule['ipprotocol'] ?? 'inet',
            'protocol' => !empty($rule['protocol']) ? strtoupper($rule['protocol']) : 'any',
            'source_net' => legacy_address_to_network($source),
            'source_not' => isset($source['not']) ? '1' : '0',
            'source_port' => normalize_port($rule['sourceport'] ?? ''),
            'destination_net' => legacy_address_to_network($destination),
            'destination_not' => isset($destination['not']) ? '1' : '0',
            'destination_port' => normalize_port($rule['dstport'] ?? ''),
            'target' => legacy_target_to_network($rule),
            'target_port' => normalize_port($rule['natport'] ?? ''),
            'staticnatport' => !empty($rule['staticnatport']) ? '1' : '0',
            'log' => !empty($rule['log']) ? '1' : '0',
            'categories' => $rule['category'] ?? '',
            'tag' => $rule['tag'] ?? '',
            'tagged' => $rule['tagged'] ?? '',
            'description' => $rule['descr'] ?? '',
        ];

        $result[] = $target_rule;
        $sequence += 10;
    }
}

echo json_encode($result);
