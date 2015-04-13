<?php
			$tab_array = array();
			$tab_array[0] = array(gettext("Tunnels"), $_SERVER['PHP_SELF'] == "/vpn_ipsec.php", "vpn_ipsec.php");
			$tab_array[1] = array(gettext("Mobile clients"), $_SERVER['PHP_SELF'] == "/vpn_ipsec_mobile.php", "vpn_ipsec_mobile.php");
			$tab_array[2] = array(gettext("Pre-Shared Keys"), $_SERVER['PHP_SELF'] == "/vpn_ipsec_keys.php", "vpn_ipsec_keys.php");
			$tab_array[3] = array(gettext("Advanced Settings"), $_SERVER['PHP_SELF'] == "/vpn_ipsec_settings.php", "vpn_ipsec_settings.php");
			display_top_tabs($tab_array);
?>
