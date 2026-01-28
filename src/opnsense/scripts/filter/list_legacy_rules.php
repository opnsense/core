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
 * simple wrapper to convert legacy rules into usable data for our MVC implementation
 */

require_once('script/load_phalcon.php');
require_once('config.inc');
require_once('filter.inc');

use OPNsense\Firewall\Alias;

$result = [];
if (!empty($config['filter']['rule'])) {
    $icmp6types = [
        'unreach' => '1',
        'toobig' => '2',
        'timex' => '3',
        'paramprob' => '4',
        'echoreq' => '128',
        'echorep' => '129',
        'listqry' => '130',
        'listenrep' => '131',
        'listendone' => '132',
        'routersol' => '133',
        'routeradv' => '134',
        'neighbrsol' => '135',
        'neighbradv' => '136',
        'redir' => '137',
        'routrrenum' => '138',
        'niqry' => '139',
        'nirep' => '140',
        'mtraceresp' => '200',
        'mtrace' => '201'
    ];
    /* sort and make sure uuid's exist */
    filter_rules_sort();
    $sequence = 1;
    foreach ($config['filter']['rule'] as $rule) {
        $target_rule = [
            '@uuid' => $rule['@attributes']['uuid'],
            'enabled' => empty($rule['disabled']) ? '1' : '0',
            'statetype' => !empty($rule['statetype']) ? explode(' ', $rule['statetype'])[0] : 'keep',
            'state-policy' => $rule['state-policy'] ?? '',
            'sequence' => $sequence,
            'action' => $rule['type'] ?? 'pass',
            'quick' => !empty($rule['quick']) ? '1' : '0',
            'interfacenot' => !empty($rule['interfacenot']) ? '1' : '0',
            'interface' => $rule['interface'] ?? '',
            'direction' => !empty($rule['direction']) ? $rule['direction'] : 'in',
            'ipprotocol' => $rule['ipprotocol'] ?? 'inet',
            'protocol' => !empty($rule['protocol']) ? strtoupper($rule['protocol']) : 'any',
            'icmptype' => $rule['icmptype'] ?? '',
            'icmp6type' => '',
            'gateway' => $rule['gateway'] ?? '',
            'replyto' => $rule['reply-to'] ?? '',
            'disablereplyto' => !empty($rule['disablereplyto']) ? '1' : '0',
            'log' => !empty($rule['log']) ? '1' : '0',
            'allowopts' => !empty($rule['allowopts']) ? '1' : '0',
            'nosync' => !empty($rule['nosync']) ? '1' : '0',
            'nopfsync' => !empty($rule['nopfsync']) ? '1' : '0',
            'statetimeout' => $rule['statetimeout'] ?? '',
            'max-src-nodes' => $rule['max-src-nodes'] ?? '',
            'max-src-states' => $rule['max-src-states'] ?? '',
            'max-src-conn' => $rule['max-src-conn'] ?? '',
            'max' => $rule['max'] ?? '',
            'max-src-conn-rate' => $rule['max-src-conn-rate'] ?? '',
            'max-src-conn-rates' => $rule['max-src-conn-rates'] ?? '',
            'overload' => $rule['overload'] ?? '',
            'adaptivestart' => $rule['adaptivestart'] ?? '',
            'adaptiveend' => $rule['adaptiveend'] ?? '',
            'prio' => $rule['prio'] ?? '',
            'set-prio' => $rule['set-prio'] ?? '',
            'set-prio-low' => $rule['set-prio-low'] ?? '',
            'tag' => $rule['tag'] ?? '',
            'tagged' => $rule['tagged'] ?? '',
            'tcpflags1' => $rule['tcpflags1'] ?? '',
            'tcpflags2' => $rule['tcpflags2'] ?? '',
            'categories' => $rule['category'] ?? '',
            'sched' => $rule['sched'] ?? '',
            'tos' => $rule['tos'] ?? '',
            'shaper1' => $rule['shaper1'] ?? '',
            'shaper2' => $rule['shaper2'] ?? '',
            'description' => $rule['descr'] ?? '',
        ];
        if (!isset($rule['quick'])) {
            $target_rule['quick'] = !empty($rule['floating']) ? '0' : '1';
        }
        foreach (['source', 'destination'] as $field) {
            if (!empty($rule[$field])) {
                $target_rule[$field . '_not'] = isset($rule[$field]['not']) ? "1" : "0";
                if (isset($rule[$field]['any'])) {
                    $target_rule[$field . '_net'] = 'any';
                } elseif (!empty($rule[$field]['network'])) {
                    $target_rule[$field . '_net'] = $rule[$field]['network'];
                } else {
                    $target_rule[$field . '_net'] = $rule[$field]['address'] ?? '';
                }
                $target_rule[$field . '_port'] = $rule[$field]['port'] ?? '';
                $target_rule[$field . '_port'] = str_replace('-any', '-65535', $target_rule[$field . '_port']);
                $target_rule[$field . '_port'] = str_replace('any-', '1-', $target_rule[$field . '_port']);
            }
        }
        if (!empty($rule['icmp6-type'])) {
            $items = [];
            foreach (explode(',', $rule['icmp6-type']) as $item) {
                if (isset($icmp6types[$item])) {
                    $items[] = $icmp6types[$item];
                }
            }
            $target_rule['icmp6type'] = implode(',', $items);
        }


        $result[] = $target_rule;
        $sequence += 10;
    }
}
echo json_encode($result);
