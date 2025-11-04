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
require_once('script/load_phalcon.php');
require_once('util.inc');
require_once('config.inc');
require_once('interfaces.inc');
require_once('plugins.inc');
require_once('filter.inc');


$fw = filter_core_get_initialized_plugin_system();
filter_core_bootstrap($fw);
/* fetch all firewall plugins, except pf_firewall as this registers our mvc rules */
foreach (plugins_scan() as $name => $path) {
    try {
        include_once $path;
    } catch (\Error $e) {
        error_log($e);
    }
    $func = sprintf('%s_firewall', $name);
    if ($func != 'pf_firewall' && function_exists($func)) {
        $func($fw);
    }
}
filter_core_rules_user($fw);

$mapping = [
    'type' => 'action',
    'reply-to' => 'replyto',
    'descr' => 'description',
    'from' => 'source_net',
    'from_port' => 'source_port',
    'to' => 'destination_net',
    'to_port' => 'destination_port',
    '#ref' => 'ref',
    'label' => 'uuid'
];

$rules = [];
$sequence = 1;
foreach ($fw->iterateFilterRules() as $prio => $item) {
    $rule = $item->getRawRule();
    if (empty($rule['disabled'])) {
        $rule['enabled'] = '1';
        $rule['direction'] = $rule['direction'] ?? 'in';
        foreach ($mapping as $src => $dst) {
            $rule[$dst] = $rule[$src] ?? '';
            if (isset($rule[$src])) {
                unset($rule[$src]);
            }
        }

        /* normalize <virusprod> or (opt1) for frontend grid formatters */
        foreach (['source_net', 'destination_net', 'source_port', 'destination_port'] as $field) {
            if (empty($rule[$field])) {
                continue;
            }

            $val = $rule[$field];
            $scope = null;

            // pf syntax detection
            if ($val[0] === '(') {
                $scope = 'network';
                $val = trim($val, '()');
            } elseif ($val[0] === '<') {
                $scope = 'address';
                $val = trim($val, '<>');
            }

            // special case
            if ($val === 'self') {
                $rule[$field] = gettext('This Firewall');
                continue;
            }

            // try to map to interface description
            if (in_array($field, ['source_net', 'destination_net'], true)) {
                foreach (($config['interfaces'] ?? []) as $ifkey => $ifdata) {
                    if (($ifdata['if'] ?? '') === $val) {
                        $descr = $ifdata['descr'] ?: strtoupper($ifkey);
                        $suffix = $scope === 'network' ? gettext('net') :
                                ($scope === 'address' ? gettext('address') : '');
                        $rule[$field] = "$descr $suffix";
                        continue 2;
                    }
                }
            }

            // fallback for alias, literal or port
            $rule[$field] = $val;
        }

        $rule['action'] = $rule['action'] ?? 'pass';
        $rule['ipprotocol'] = $rule['ipprotocol'] ?? 'inet';
        if (!empty($rule['from_not'])) {
            unset($rule['from_not']);
            $rule['source_not'] = true;
        }
        if (!empty($rule['to_not'])) {
            unset($rule['to_not']);
            $rule['destination_not'] = true;
        }

        /**
         * Evaluation order consists of a priority group and a sequence within the set,
         * prefixed with 1 as these are located after mvc rules.
         **/
        $rule['sort_order'] = sprintf("%06d.1%06d", $prio, $sequence++);
        $rule['legacy'] = true;
        $rules[] = $rule;
    }
}
echo json_encode($rules, JSON_PRETTY_PRINT);
