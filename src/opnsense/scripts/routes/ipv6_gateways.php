#!/usr/local/bin/php
<?php

/*
 * Returns static IPv6 routes with gateway names in TSV format.
 * It is used for approximate IPv6 translation of dynamic addresses in traffic view (traffic_top.py).
 */
require_once 'config.inc';
require_once 'util.inc';

$allRoutes = get_staticroutes();
$allSubnets = [];
foreach ($allRoutes as $route) {
    if (str_contains($route['network'], ':') && is_subnet($route['network'])) {
        $allSubnets[$route['network']] = $route['gateway'];
    }
}

foreach ($allSubnets as $network => $gateway) {
    echo $network . "\t" . $gateway . "\n";
}
