<?php
/*
	Copyright (C) 2014 Deciso B.V.
	All rights reserved.

	Redistribution and use in source and binary forms, with or without
	modification, are permitted provided that the following conditions are met:

	1. Redistributions of source code must retain the above copyright notice,
	   this list of conditions and the following disclaimer.

	2. Redistributions in binary form must reproduce the above copyright
	   notice, this list of conditions and the following disclaimer in the
	   documentation and/or other materials provided with the distribution.

	THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
	INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
	AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
	AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
	OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
	SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
	INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
	CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
	ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
	POSSIBILITY OF SUCH DAMAGE.
*/
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
		$tab_array[] = array(gettext("Proxy"), $active_tab == "/diag_logs_proxy.php", "diag_logs_proxy.php");
		$tab_array[] = array(gettext("Settings"), $active_tab == "/diag_logs_settings.php", "diag_logs_settings.php");
		display_top_tabs($tab_array);
?>
