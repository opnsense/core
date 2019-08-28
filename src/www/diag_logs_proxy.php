<?php

$type = 'cache';
if (isset($_GET['type']) && ($_GET['type'] === 'access' || $_GET['type'] === 'store')) {
    $type = $_GET['type'];
}

$logfile = "/var/log/squid/{$type}.log";

if ($type === 'access' || $type === 'store') {
    $logformat = 'unix';
    $logsplit = 1;
} else {
    $logsplit = 2;
}

if ($type == "access" && is_file("/var/log/squid.log")) {
    // work around to be able to show access log entries when send using syslog.
    // since we can't share our target file (clog vs plain log), we need to switch to the one in use.
    // the most recent written one seems to be the easiest solution here, to avoid config.xml access.
    if (!is_file($logfile) || filemtime("/var/log/squid.log") > filemtime($logfile)) {
        $logformat = "keep";
        $logfile = "/var/log/squid.log";
        $logclog = true;
        $logsplit = 3;
    }
}


$logpills = array();
$logpills[] = array(gettext('Cache'), true, '/diag_logs_proxy.php?type=cache');
$logpills[] = array(gettext('Access'), false, '/diag_logs_proxy.php?type=access');
$logpills[] = array(gettext('Store'), false, '/diag_logs_proxy.php?type=store');

$service_hook = 'squid';

require_once 'diag_logs_template.inc';
