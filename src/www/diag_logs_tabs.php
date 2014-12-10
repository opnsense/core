<?php
		$active_tab = isset($active_tab) ? $active_tab : $_SERVER['PHP_SELF'];
		$tab_array = array();
		$tab_array[] = array(gettext("System"), $active_tab == "/diag_logs.php", "diag_logs.php");
		$tab_array[] = array(gettext("Firewall"), $active_tab == "/diag_logs_filter.php", "diag_logs_filter.php");
		$tab_array[] = array(gettext("DHCP"), $active_tab == "/diag_logs_dhcp.php", "diag_logs_dhcp.php");
		$tab_array[] = array(gettext("Portal Auth"), $active_tab == "/diag_logs_auth.php", "diag_logs_auth.php");
		$tab_array[] = array(gettext("IPsec"), $active_tab == "/diag_logs_ipsec.php", "diag_logs_ipsec.php");
		$tab_array[] = array(gettext("PPP"), $active_tab == "/diag_logs_ppp.php", "diag_logs_ppp.php");
		$tab_array[] = array(gettext("VPN"), $active_tab == "/diag_logs_vpn.php", "diag_logs_vpn.php");
		$tab_array[] = array(gettext("Load Balancer"), $active_tab == "/diag_logs_relayd.php", "diag_logs_relayd.php");
		$tab_array[] = array(gettext("OpenVPN"), $active_tab == "/diag_logs_openvpn.php", "diag_logs_openvpn.php");
		$tab_array[] = array(gettext("NTP"), $active_tab == "/diag_logs_ntpd.php", "diag_logs_ntpd.php");
		$tab_array[] = array(gettext("Settings"), $active_tab == "/diag_logs_settings.php", "diag_logs_settings.php");
		display_top_tabs($tab_array);
?>