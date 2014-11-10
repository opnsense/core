<?php
    $active_tab = isset($active_tab) ? $active_tab : $_SERVER['PHP_SELF'];
	$tab_array = array();
	$tab_array[] = array(gettext("Manual Update"), $active_tab == "/system_firmware.php", "system_firmware.php");
	$tab_array[] = array(gettext("Auto Update"), $active_tab == "/system_firmware_check.php", "system_firmware_check.php");
	$tab_array[] = array(gettext("Updater Settings"), $active_tab == "/system_firmware_settings.php", "system_firmware_settings.php");
	if($g['hidedownloadbackup'] == false)
		$tab_array[] = array(gettext("Restore Full Backup"), $active_tab == "/system_firmware_restorefullbackup.php", "system_firmware_restorefullbackup.php");
	display_top_tabs($tab_array);
?>