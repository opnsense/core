<?php
	$tab_array = array();
	$tab_array[0] = array(gettext("Overview"), $_SERVER['PHP_SELF'] == '/diag_ipsec.php', "diag_ipsec.php");
	$tab_array[1] = array(gettext("Leases"), $_SERVER['PHP_SELF'] == '/diag_ipsec_leases.php', "diag_ipsec_leases.php");
	$tab_array[2] = array(gettext("SAD"), $_SERVER['PHP_SELF'] == '/diag_ipsec_sad.php', "diag_ipsec_sad.php");
	$tab_array[3] = array(gettext("SPD"), $_SERVER['PHP_SELF'] == '/diag_ipsec_spd.php', "diag_ipsec_spd.php");
	$tab_array[4] = array(gettext("Logs"), $_SERVER['PHP_SELF'] == '/diag_logs_ipsec.php', "diag_logs_ipsec.php");
	display_top_tabs($tab_array);
?>