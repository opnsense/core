#!/usr/local/bin/php
<?php

require_once('script/load_phalcon.php');
require_once('util.inc');
require_once('config.inc');
require_once('interfaces.inc');
require_once('plugins.inc');
require_once('filter.inc');

// Get the rule origin filter(s) from the command line.
// Usage examples:
//   get_internal_rules.php all
//   get_internal_rules.php internal,floating,group,internal2,automation
$originParam = 'internal';
if (isset($argv[1]) && !empty($argv[1])) {
    $originParam = trim($argv[1]);
}

$origins = [];
if (strtolower($originParam) === 'all') {
    $filterAll = true;
} else {
    $filterAll = false;
    $origins = array_map('trim', explode(',', $originParam));
}

$fw = filter_core_get_initialized_plugin_system();
filter_core_bootstrap($fw);
plugins_firewall($fw);
filter_core_rules_user($fw);

// Iterate over all firewall rules and filter based on origin.
$rules = [];
foreach ($fw->iterateFilterRules() as $rule) {
    if ($filterAll || in_array($rule->ruleOrigin(), $origins)) {
        $rules[] = $rule->getRawRule();
    }
}

// XXX: I want it pretty print cause this is a test script
echo json_encode($rules, JSON_PRETTY_PRINT);
exit(0);
