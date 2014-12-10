<?php
    $active_tab = isset($active_tab) ? $active_tab : $_SERVER['PHP_SELF'];

	$tab_array = array();
	$tab_array[] = array(gettext("CAs"), $active_tab == "/system_camanager.php", "system_camanager.php");
	$tab_array[] = array(gettext("Certificates"), $active_tab == "/system_certmanager.php", "system_certmanager.php");
	$tab_array[] = array(gettext("Certificate Revocation"), $active_tab == "/system_crlmanager.php", "system_crlmanager.php");
	display_top_tabs($tab_array);
?>