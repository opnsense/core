<?php

$type = 'cache';
if (isset($_GET['type']) && ($_GET['type'] === 'access' || $_GET['type'] === 'store')) {
	$type = $_GET['type'];
}

$logfile = "/var/log/squid/{$type}.log";
$logclog = false;

$logpills = array();
$logpills[] = array(gettext('Cache'), true, '/diag_logs_proxy.php?type=cache');
$logpills[] = array(gettext('Access'), false, '/diag_logs_proxy.php?type=access');
$logpills[] = array(gettext('Store'), false, '/diag_logs_proxy.php?type=store');

require_once 'services.inc';
$shortcut_section = 'squid';

require_once 'diag_logs_template.inc';
