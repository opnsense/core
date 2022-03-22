#!/usr/local/bin/php
<?php

require_once 'config.inc';
require_once 'system.inc';
require_once 'util.inc';

use OPNsense\Core\Config;

$config = Config::getInstance()->object();

$result = array();

/* get dynamic nameservers */
foreach (get_nameservers() as $nameserver) {
    $result["dynamic"][] = $nameserver;
}

/* get manually entered nameservers */
foreach ($config->system->children() as $key => $node) {
    if ($key == "dnsserver") {
        $result["static"][] = (string)$node;
    }
}

echo json_encode($result) . PHP_EOL;
