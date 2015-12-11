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
	$logfile = '/var/log/pptps.log';
}

$logtype = 'pptp';

$tab_array = array();
$tab_array[] = array(gettext("PPTP Logins"), $mode != "raw", "/diag_logs_pptp.php");
$tab_array[] = array(gettext("PPTP Raw"), $mode == "raw", "/diag_logs_pptp.php?mode=raw");

require_once 'diag_logs_vpn.inc';
