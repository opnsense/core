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
	$logfile = '/var/log/poes.log';
}

$logtype = 'poes';

$tab_array = array();
$tab_array[] = array(gettext("PPPoE Logins"), $mode != "raw", "/diag_logs_poes.php");
$tab_array[] = array(gettext("PPPoE Raw"), $mode == "raw", "/diag_logs_poes.php?mode=raw");

require_once 'diag_logs_vpn.inc';
