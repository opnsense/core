#!/usr/local/bin/php
<?php

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
        if (empty($rule['action'])) {
            $rule['action'] = ucfirst(strtolower(trim($rule['action'] ?? 'pass')));
        } else {
            $rule['action'] = 'Pass';
        }
        switch ($rule['ipprotocol'] ?? '') {
            case 'inet':
                $rule['ipprotocol'] = gettext('IPv4');
                break;
            case 'inet6':
                $rule['ipprotocol'] = gettext('IPv6');
                break;
            case 'inet46':
                $rule['ipprotocol'] = gettext('IPv4+IPv6');
                break;
            default:
                $rule['ipprotocol'] = gettext('IPv4'); // Default
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
