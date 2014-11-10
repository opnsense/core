<?php
	
$tab_group 	= 	isset($tab_group) ? $tab_group : 'system';
$tab_array 	= 	array();

if ($tab_group == 'vpn') {

	$tab_array[] = array(gettext("PPTP Logins"),
				(($vpntype == "pptp") && ($mode != "raw")),
				"/diag_logs_vpn.php?vpntype=pptp");
	$tab_array[] = array(gettext("PPTP Raw"),
				(($vpntype == "pptp") && ($mode == "raw")),
				"/diag_logs_vpn.php?vpntype=pptp&amp;mode=raw");
	$tab_array[] = array(gettext("PPPoE Logins"),
				(($vpntype == "poes") && ($mode != "raw")),
				"/diag_logs_vpn.php?vpntype=poes");
	$tab_array[] = array(gettext("PPPoE Raw"),
				(($vpntype == "poes") && ($mode == "raw")),
				"/diag_logs_vpn.php?vpntype=poes&amp;mode=raw");
	$tab_array[] = array(gettext("L2TP Logins"),
				(($vpntype == "l2tp") && ($mode != "raw")),
				"/diag_logs_vpn.php?vpntype=l2tp");
	$tab_array[] = array(gettext("L2TP Raw"),
				(($vpntype == "l2tp") && ($mode == "raw")),
				"/diag_logs_vpn.php?vpntype=l2tp&amp;mode=raw");
	
}

else if ($tab_group == 'firewall') {
	
	$tab_array[] = array(gettext("Normal View"), true, "/diag_logs_filter.php");
	$tab_array[] = array(gettext("Dynamic View"), false, "/diag_logs_filter_dynamic.php");
	$tab_array[] = array(gettext("Summary View"), false, "/diag_logs_filter_summary.php");
	
}

else {
	$tab_array[] = array(gettext("General"), true, "/diag_logs.php");
	$tab_array[] = array(gettext("Gateways"), false, "/diag_logs_gateways.php");
	$tab_array[] = array(gettext("Routing"), false, "/diag_logs_routing.php");
	$tab_array[] = array(gettext("Resolver"), false, "/diag_logs_resolver.php");
	$tab_array[] = array(gettext("Wireless"), false, "/diag_logs_wireless.php");
}

?>

<ul class="nav nav-pills" role="tablist"><? foreach ($tab_array as $tab): ?>
	<li role="presentation" <? if (str_replace('amp;','', $tab[2]) == $_SERVER['REQUEST_URI']):?>class="active"<? endif; ?>><a href="<?=$tab[2];?>"><?=$tab[0];?></a></li>
<? endforeach; ?></ul><br />