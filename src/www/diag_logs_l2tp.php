<?php

if (htmlspecialchars($_POST['mode']))
	$mode = htmlspecialchars($_POST['mode']);
elseif (htmlspecialchars($_GET['mode']))
	$mode = htmlspecialchars($_GET['mode']);
else
	$mode = "login";

if ($mode != 'raw') {
	$logfile = '/var/log/vpn.log';
} else {
	$logfile = '/var/log/l2tps.log';
}

$logtype = 'l2tp';

$tab_array = array();
$tab_array[] = array(gettext("L2TP Logins"), $mode != "raw", "/diag_logs_l2tp.php");
$tab_array[] = array(gettext("L2TP Raw"), $mode == "raw", "/diag_logs_l2tp.php?mode=raw");

require_once 'diag_logs_vpn.inc';
