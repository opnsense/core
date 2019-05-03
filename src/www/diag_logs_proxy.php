<?php

$type = 'cache';
if (isset($_GET['type']) && ($_GET['type'] === 'access' || $_GET['type'] === 'store')) {
    $type = $_GET['type'];
}

$logfile = "/var/log/squid/{$type}.log";
$logclog = false;

$logsplit = 2;
$log_datetime_format = 'readable';

if ($type === 'access' || $type === 'store') {
    $logsplit = 1;
    $log_datetime_format = 'unix';
}

$logpills = array();
$logpills[] = array(gettext('Cache'), true, '/diag_logs_proxy.php?type=cache');
$logpills[] = array(gettext('Access'), false, '/diag_logs_proxy.php?type=access');
$logpills[] = array(gettext('Store'), false, '/diag_logs_proxy.php?type=store');

$service_hook = 'squid';

require_once 'diag_logs_template.inc';
