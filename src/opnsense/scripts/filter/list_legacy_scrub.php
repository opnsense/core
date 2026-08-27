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
 * Convert legacy scrub rules into records accepted by the MVC filter rule importer.
 * Legacy "no scrub" and scrub rules without normalization options have no direct
 * equivalent in the match based ruleset and are reported as unsupported.
 */

require_once('config.inc');
require_once('filter.inc');

function legacy_scrub_address($address, $mask)
{
    if (empty($address) || $address === 'any') {
        return 'any';
    }

    if (is_ipaddr($address) && $mask !== '' && $mask !== null) {
        return $address . '/' . $mask;
    }

    return $address;
}

function legacy_scrub_ipprotocol($rule)
{
    if (
        in_array(
            $rule['proto'] ?? '',
            [
                'icmp6',
                'ipv6-icmp',
            ]
        )
    ) {
        return 'inet6';
    }

    foreach (
        [
            $rule['src'] ?? '',
            $rule['dst'] ?? '',
        ] as $address
    ) {
        if (is_ipaddrv4($address)) {
            return 'inet';
        }
        if (is_ipaddrv6($address)) {
            return 'inet6';
        }
    }

    return 'inet46';
}

function legacy_scrub_port($port)
{
    if (empty($port) || $port === 'any') {
        return '';
    }

    $port = str_replace('-any', '-65535', (string)$port);
    return str_replace('any-', '1-', $port);
}

$result = [
    'rules' => [],
    'unsupported' => 0,
    'reassembly_review' => 0,
];
$sequence = 1;

foreach (config_read_array('filter', 'scrub', 'rule', false) as $rule) {
    $has_scrub_option = !empty($rule['no-df']) ||
        !empty($rule['random-id']) ||
        !empty($rule['max-mss']) ||
        !empty($rule['min-ttl']) ||
        !empty($rule['set-tos']);
    if (!empty($rule['noscrub']) || !$has_scrub_option) {
        $result['unsupported']++;
        continue;
    }
    if (empty($rule['disabled']) && !empty($config['system']['scrub_interface_disable'])) {
        /* A match scrub rule cannot replace the selective reassembly implicit in this legacy rule. */
        $result['reassembly_review']++;
    }

    $protocol = !empty($rule['proto']) ? strtoupper($rule['proto']) : 'any';
    if ($protocol === 'ICMP6') {
        $protocol = 'IPV6-ICMP';
    }
    $result['rules'][] = [
        '@uuid' => '',
        'enabled' => empty($rule['disabled']) ? '1' : '0',
        'statetype' => 'keep',
        'sequence' => $sequence,
        'action' => 'match',
        'quick' => '0',
        'interfacenot' => '0',
        'interface' => $rule['interface'] ?? '',
        'direction' => !empty($rule['direction']) ? $rule['direction'] : 'any',
        'ipprotocol' => legacy_scrub_ipprotocol($rule),
        'protocol' => $protocol,
        'source_net' => legacy_scrub_address(
            $rule['src'] ?? 'any',
            $rule['srcmask'] ?? ''
        ),
        'source_not' => !empty($rule['srcnot']) ? '1' : '0',
        'source_port' => legacy_scrub_port($rule['srcport'] ?? ''),
        'destination_net' => legacy_scrub_address(
            $rule['dst'] ?? 'any',
            $rule['dstmask'] ?? ''
        ),
        'destination_not' => !empty($rule['dstnot']) ? '1' : '0',
        'destination_port' => legacy_scrub_port($rule['dstport'] ?? ''),
        'nosync' => '0',
        'categories' => '',
        'description' => $rule['descr'] ?? '',
        'scrub_no_df' => !empty($rule['no-df']) ? '1' : '0',
        'scrub_random_id' => !empty($rule['random-id']) ? '1' : '0',
        'scrub_max_mss' => !empty($rule['max-mss']) ? $rule['max-mss'] : '',
        'scrub_min_ttl' => !empty($rule['min-ttl']) ? $rule['min-ttl'] : '',
        'scrub_set_tos' => $rule['set-tos'] ?? '',
    ];
    $sequence += 10;
}

echo json_encode($result);
