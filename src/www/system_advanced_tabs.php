<div class="alert alert-info alert-dismissible" role="alert">
    <button type="button" class="close" data-dismiss="alert"><span aria-hidden="true">&times;</span><span class="sr-only">Close</span></button>
    <strong><?=gettext("NOTE:"); ?></strong>&nbsp;<?=gettext("The options on this page are intended for use by advanced users only."); ?>
</div>

<?php
    $active_tab = isset($active_tab) ? $active_tab : $_SERVER['PHP_SELF'];
	$tab_array = array();
	$tab_array[] = array(gettext("General"), $active_tab == "/system_general.php", "system_general.php");
	$tab_array[] = array(gettext("Admin Access"), $active_tab == "/system_advanced_admin.php", "system_advanced_admin.php");
	$tab_array[] = array(gettext("Firewall / NAT"), $active_tab == "/system_advanced_firewall.php", "system_advanced_firewall.php");
	$tab_array[] = array(gettext("Networking"), $active_tab == "/system_advanced_network.php", "system_advanced_network.php");
	$tab_array[] = array(gettext("Miscellaneous"), $active_tab == "/system_advanced_misc.php", "system_advanced_misc.php");
	$tab_array[] = array(gettext("System Tunables"), $active_tab == "/system_advanced_sysctl.php", "system_advanced_sysctl.php");
	$tab_array[] = array(gettext("Notifications"), $active_tab == "/system_advanced_notifications.php", "system_advanced_notifications.php");
	display_top_tabs($tab_array);
?>
