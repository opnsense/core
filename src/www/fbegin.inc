<?php

/*
	Copyright (C) 2014 Deciso B.V.
	Copyright (C) 2008 Scott Ullrich <sullrich@gmail.com>
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

require_once("globals.inc");
require_once("functions.inc");
require_once("shortcuts.inc");
require_once("service-utils.inc");

/* Determine automated help URL. Should output the page name and
   parameters separately */
$uri_split = "";
preg_match("/\/(.*)\?(.*)/", $_SERVER["REQUEST_URI"], $uri_split);

/* If there was no match, there were no parameters, just grab the filename
   Otherwise, use the matched filename from above. */
if (empty($uri_split[0])) {
	$pagename = ltrim($_SERVER["REQUEST_URI"], '/');
} else {
	$pagename = $uri_split[1];
}
/* If the page name is still empty, the user must have requested / (index.php) */
if (empty($pagename)) {
	$pagename = "index.php";
}

/* If the filename is pkg_edit.php or wizard.php, reparse looking
	for the .xml filename */
if (($pagename == "pkg.php") || ($pagename == "pkg_edit.php") || ($pagename == "wizard.php")) {
	$param_split = explode('&', $uri_split[2]);
	foreach ($param_split as $param) {
		if (substr($param, 0, 4) == "xml=") {
			$xmlfile = explode('=', $param);
			$pagename = $xmlfile[1];
		}
	}
}

/* Build the full help URL. */
$helpurl .= "{$g['help_base_url']}?page={$pagename}";

function return_ext_menu($section) {
	global $config;
	$htmltext = "";
	$extarray = array();
	if($config['installedpackages']['menu'] <> "") {
		foreach($config['installedpackages']['menu'] as $menuitem) {
			if($menuitem['section'] != $section) continue;
			if($menuitem['url'] <> "") {
				$test_url=$menuitem['url'];
				$addresswithport = getenv("HTTP_HOST");
				$colonpos = strpos($addresswithport, ":");
				if ($colonpos !== False){
					//my url is actually just the IP address of the OPNsense box
					$myurl = substr($addresswithport, 0, $colonpos);
				} else {
					$myurl = $addresswithport;
				}
				$description = str_replace('$myurl', $myurl, $menuitem['url']);
			} else {
				$description = '/pkg.php?xml=' . $menuitem['configfile'];
				$test_url=$description;
			}
			 if(isAllowedPage($test_url)){
				$extarray[] = array($menuitem['name'], $description);
			 }
		}
	}
	return $extarray;
}

function output_menu($arrayitem, $target = null) {
	foreach ($arrayitem as $item) {
		if (isAllowedPage($item[1]) || $item[1]=="/index.php?logout") {


			$attr = sprintf("href=\"%s\"", htmlentities($item[1]));
			if ($target) {
				$attr .= sprintf(" target=\"%s\"", htmlentities($target));
			}
			$class = "list-group-item";


			$check = substr($item[1],1,-3);
			if (stripos($_SERVER['PHP_SELF'], $check) !== false) {
				$class .= " active";
			}

			if ($item['class']) {
				$class .= " {$item['class']}";
			}
			$attr .= sprintf(" class=\"%s\"", htmlentities($class));
			if ($item['style']) {
				$attr .= sprintf(" style=\"%s\"", htmlentities($item['style']));
			}
			//echo "<li>\n";
			printf("<a %s>%s</a>\n", $attr, $item[0]);
			//echo "</li>\n";
		}
	}
}


function active_menu($arrayitem, $pagename) {
	$pagename = "/".ltrim($pagename,"/");

	foreach ($arrayitem as $item) {
		$check = substr($item[1],1,-3);

		if (stripos($pagename, $check) !== false) {
			return true;
		}
	}

	return false;
}

function active_main_menu() {
	$options = func_get_args();

	foreach ($options as $uri) {
		if (substr($_SERVER['PHP_SELF'], 0, strlen($uri)) == $uri) {
			return true;
		}
	}

	return false;
}


// System
$system_menu = array();
$system_menu[] = array(gettext("Advanced"), "/system_advanced_admin.php");
$system_menu[] = array(gettext("Firmware"), "/system_firmware_check.php");
$system_menu[] = array(gettext("General Setup"), "/system_general.php");
$system_menu[] = array(gettext("High Avail. Sync"), "/system_hasync.php");
//if ($g['platform'] == "pfSense" or $g['platform'] == "nanobsd")
//	$system_menu[] = array(gettext("Packages"), "/system_pkg_mgr_installed.php");
$system_menu[] = array(gettext("Setup Wizard"), "/wizard.php?xml=setup_wizard.xml");
$system_menu[] = array(gettext("Routing"), "/system_gateways.php");
$system_menu[] = array(gettext("Cert Manager"), "/system_camanager.php");
if (!isAllowedPage("system_usermanager.php*"))
	$system_menu[] = array(gettext("User Manager"), "/system_usermanager_passwordmg.php");
else
	$system_menu[] = array(gettext("User Manager"), "/system_usermanager.php");
$system_menu = msort(array_merge($system_menu, return_ext_menu("System")),0);

// Interfaces
$interfaces_menu = array();
if (!isset($config['system']['webgui']['noassigninterfaces']))
	$interfaces_menu[] = array(gettext("(assign)"), "/interfaces_assign.php");
$opts = get_configured_interface_with_descr(false, true);
foreach ($opts as $oif => $odescr)
	if (!isset($config['interfaces'][$oif]['ovpn']))
		$interfaces_menu[] = array(htmlspecialchars($odescr), "/interfaces.php?if={$oif}");
$interfaces_menu = msort(array_merge($interfaces_menu, return_ext_menu("Interfaces")),0);

// Firewall
$firewall_menu = array();
$firewall_menu[] = array(gettext("Aliases"), "/firewall_aliases.php");
$firewall_menu[] = array(gettext("NAT"), "/firewall_nat.php");
$firewall_menu[] = array(gettext("Rules"), "/firewall_rules.php");
$firewall_menu[] = array(gettext("Schedules"), "/firewall_schedule.php");
$firewall_menu[] = array(gettext("Traffic Shaper"), "/firewall_shaper.php");
$firewall_menu[] = array(gettext("Virtual IPs"), "/firewall_virtual_ip.php");
$firewall_menu = msort(array_merge($firewall_menu, return_ext_menu("Firewall")),0);

// Services
$services_menu = array();
$services_menu[] = array(gettext("Captive Portal"), "/services_captiveportal.php");
$services_menu[] = array(gettext("DNS Forwarder"), "/services_dnsmasq.php");
$services_menu[] = array(gettext("DNS Resolver"), "/services_unbound.php");
$services_menu[] = array(gettext("DHCP Relay"), "/services_dhcp_relay.php");
$services_menu[] = array(gettext("DHCPv6 Relay"), "/services_dhcpv6_relay.php");
if($g['services_dhcp_server_enable']) {
	$services_menu[] = array(gettext("DHCP Server"), "/services_dhcp.php");
	$services_menu[] = array(gettext("DHCPv6 Server/RA"), "/services_dhcpv6.php");
}
$services_menu[] = array(gettext("Dynamic DNS"), "/services_dyndns.php");
$services_menu[] = array(gettext("IGMP proxy"), "/services_igmpproxy.php");
$services_menu[] = array(gettext("Load Balancer"), "/load_balancer_pool.php");
$services_menu[] = array(gettext("NTP"), "/services_ntpd.php");
$services_menu[] = array(gettext("PPPoE Server"), "/services_vpn_pppoe.php");
$services_menu[] = array(gettext("SNMP"), "/services_snmp.php");
if(count($config['interfaces']) > 1) {
	/* no use for UPnP in single-interface deployments
	remove to reduce user confusion
	*/
	$services_menu[] = array(gettext("UPnP &amp; NAT-PMP"), "/pkg_edit.php?xml=miniupnpd.xml");
}
$services_menu[] = array(gettext("Wake on LAN"), "/services_wol.php");
$services_menu = msort(array_merge($services_menu, return_ext_menu("Services")),0);

// VPN
$vpn_menu = array();
$vpn_menu[] = array(gettext("IPsec"), "/vpn_ipsec.php");
$vpn_menu[] = array(gettext("OpenVPN"), "/vpn_openvpn_server.php");
$vpn_menu[] = array(gettext("PPTP"), "/vpn_pptp.php");
$vpn_menu[] = array(gettext("L2TP"), "/vpn_l2tp.php");
$vpn_menu = msort(array_merge($vpn_menu, return_ext_menu("VPN")),0);

// Status
$status_menu = array();
//if (count($config['captiveportal']) > 0)
	$status_menu[] = array(gettext("Captive Portal"), "/status_captiveportal.php");
$status_menu[] = array(gettext("CARP (failover)"), "/carp_status.php");
$status_menu[] = array(gettext("Gateways"), "/status_gateways.php");
$status_menu[] = array(gettext("DHCP Leases"), "/status_dhcp_leases.php");
$status_menu[] = array(gettext("DHCPv6 Leases"), "/status_dhcpv6_leases.php");
$status_menu[] = array(gettext("Filter Reload"), "/status_filter_reload.php");
$status_menu[] = array(gettext("Interfaces"), "/status_interfaces.php");
$status_menu[] = array(gettext("IPsec"), "/diag_ipsec.php");
$status_menu[] = array(gettext("Load Balancer"), "/status_lb_pool.php");
$status_menu[] = array(gettext("NTP"), "/status_ntpd.php");
$status_menu[] = array(gettext("OpenVPN"), "/status_openvpn.php");
//if ($g['platform'] == "pfSense")
//	$status_menu[] = array(gettext("Package Logs"), "/diag_pkglogs.php"); // Remove currently unused feature
$status_menu[] = array(gettext("Queues"), "/status_queues.php");
$status_menu[] = array(gettext("RRD Graphs"), "/status_rrd_graph.php");
$status_menu[] = array(gettext("Services"), "/status_services.php");
$status_menu[] = array(gettext("System Logs"), "/diag_logs.php");
$status_menu[] = array(gettext("Traffic Graph"), "/status_graph.php?if=wan");
if(count($config['interfaces']) > 1)
	$status_menu[] = array(gettext("UPnP &amp; NAT-PMP"), "/status_upnp.php");
$ifentries = get_configured_interface_with_descr();
foreach ($ifentries as $ent => $entdesc) {
	if (is_array($config['interfaces'][$ent]['wireless']) &&
		preg_match($g['wireless_regex'], $config['interfaces'][$ent]['if']))
		$wifdescrs[$ent] = $entdesc;
}
if (count($wifdescrs) > 0)
	$status_menu[] = array(gettext("Wireless"), "/status_wireless.php");
$status_menu = msort(array_merge($status_menu, return_ext_menu("Status")),0);

// Diagnostics
$diagnostics_menu = array();
$diagnostics_menu[] = array(gettext("ARP Table"), "/diag_arp.php");
$diagnostics_menu[] = array(gettext("Authentication"), "/diag_authentication.php");
$diagnostics_menu[] = array(gettext("Backup/Restore"), "/diag_backup.php");
$diagnostics_menu[] = array(gettext("Crash Reporter"), "/crash_reporter.php");
$diagnostics_menu[] = array(gettext("DNS Lookup"), "/diag_dns.php");
$diagnostics_menu[] = array(gettext("NDP Table"), "/diag_ndp.php" );
$diagnostics_menu[] = array(gettext("Factory Defaults"), "/diag_defaults.php");
$diagnostics_menu[] = array(gettext("Halt System"), "/diag_halt.php" );
$diagnostics_menu[] = array(gettext("Limiter Info"), "/diag_limiter_info.php" );
$diagnostics_menu[] = array(gettext("pfInfo"), "/diag_pf_info.php");
$diagnostics_menu[] = array(gettext("pfTop"), "/diag_system_pftop.php");
$diagnostics_menu[] = array(gettext("Ping"), "/diag_ping.php");
$diagnostics_menu[] = array(gettext("Reboot"), "/diag_reboot.php");
//$diagnostics_menu[] = array(gettext("Edit File"), "/diag_edit.php"); // Removed file editor for security reasons
$diagnostics_menu[] = array(gettext("Packet Capture"), "/diag_packet_capture.php");
$diagnostics_menu[] = array(gettext("Traceroute"), "/diag_traceroute.php");
$diagnostics_menu[] = array(gettext("SMART Status"), "/diag_smart.php");
$diagnostics_menu[] = array(gettext("System Activity"), "/diag_system_activity.php");
$diagnostics_menu[] = array(gettext("Test Port"), "/diag_testport.php");
$diagnostics_menu[] = array(gettext("Routes"), "/diag_routes.php");
$diagnostics_menu[] = array(gettext("Sockets"), "/diag_sockets.php" );;
$diagnostics_menu[] = array(gettext("States"), "/diag_dump_states.php");
$diagnostics_menu[] = array(gettext("States Summary"), "/diag_states_summary.php");
$diagnostics_menu[] = array(gettext("Tables"), "/diag_tables.php");


if(file_exists("/var/run/gmirror_active"))
	$diagnostics_menu[] = array(gettext("GEOM Mirrors"), "/diag_gmirror.php" );


if($g['platform'] == "nanobsd")
	$diagnostics_menu[] = array(gettext("NanoBSD"), "/diag_nanobsd.php");

if (isset($config['system']['developer'])) {
	$diagnostics_menu[] = array(gettext("Restart HTTPD"), "/restart_httpd.php", "style" => "font-weight: bold; color: yellow;");

}
$diagnostics_menu = msort(array_merge($diagnostics_menu, return_ext_menu("Diagnostics")),0);

//$gold_menu = array();
//$gold_menu[] = array(gettext("pfSense Gold"), "https://www.pfsense.org/gold");
//$gold_menu = msort(array_merge($gold_menu, return_ext_menu("Gold")),0);

if(! $g['disablehelpmenu']) {
	$help_menu = array();
	$help_menu[] = array(gettext("About this Page"), $helpurl);
	if($g['product_name'] == "pfSense")
	$help_menu[] = array(gettext("Bug Database"), "https://github.com/opnsense/core/issues");
	$help_menu[] = array(gettext("User Forum"), "http://forum.opnsense.org/");
	$help_menu[] = array(gettext("Documentation"), "http://wiki.opnsense.org/");
	$help_menu[] = array(gettext("Developers Wiki"), "http://wiki.opnsense.org/index.php/developers");
	$help_menu[] = array(gettext("Paid Support"), "http://opnsense.org/support-overview/commercial-support/");
	//$help_menu[] = array(gettext("pfSense Book"), "https://www.pfsense.org/j.php?jumpto=book");
	//$help_menu[] = array(gettext("Search portal"), "https://www.pfsense.org/j.php?jumpto=searchportal");
	$help_menu[] = array(gettext("FreeBSD Handbook"), "https://www.pfsense.org/j.php?jumpto=fbsdhandbook");
	$help_menu = msort(array_merge($help_menu, return_ext_menu("Help")),0);
}

function add_to_menu($url, $name) {
	if (isAllowedPage($url))
		echo "<a href=\"{$url}\" class=\"list-group-item\">{$name}</a>\n";
}


/* display a top alert bar if need be */
$need_alert_display = false;
$found_notices = are_notices_pending();
if($found_notices == true) {
	$notices = get_notices();
	if(!$notices) {
		$need_alert_display = true;
		$display_text = print_notices($notices) . "<br />";
	}
}
if($need_alert_display == true) {
    echo "<div class=\"col-xs-12\"><div class=\"alert alert-info\"><p>".$display_text."</p></div></div>";
}

/* if upgrade in progress, alert user */
if(is_subsystem_dirty('packagelock')) {
	$pgtitle = array(gettext("System"),gettext("Package Manager"));
	print_info_box(gettext("Packages are currently being reinstalled in the background.<p>Do not make changes in the GUI until this is complete.") . "<p><img src='/themes/{$g['theme']}/images/icons/icon_fw-update.gif' alt='firmware update' />");
}
	$pgtitle_output = true;
?>


<header class="page-head">

	<nav class="navbar navbar-default" role="navigation">

		<div class="container-fluid">
			<div class="navbar-header">
				<a class="navbar-brand" href="/">
				<img class="brand-logo" src="/themes/<?=$g['theme'];?>/assets/images/default-logo.png" height="30" width="150"/>
				<img class="brand-icon" src="/themes/<?=$g['theme'];?>/assets/images/icon-logo.png" height="30" width="29"/>
				</a>
				<button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#navigation">
					<span class="sr-only">Toggle navigation</span>
					<span class="icon-bar"></span>
					<span class="icon-bar"></span>
					<span class="icon-bar"></span>
				</button>
			</div>

			<div class="collapse navbar-collapse">
				<ul class="nav navbar-nav navbar-right">
					<li id="menu_messages">
					<?php
						echo get_menu_messages();
					?>
					</li>
					<?php


						echo '<li>'.get_shortcut_main_link($shortcut_section, false).'</li>';
						echo '<li>'.get_shortcut_status_link($shortcut_section, false).'</li>';
						echo '<li>'.get_shortcut_log_link($shortcut_section, false).'</li>';

						?>



					<?php if(! $g['disablehelpicon']): ?>
					<li><a href="<?php echo $helpurl; ?>" target="_blank" title="<?php echo gettext("Help for items on this page"); ?>">Help</a></li>
					<?php endif; ?>
					<li class="active"><a href="/index.php?logout">Logout</a></li>
				</ul>

			</div>
		</div>
	</nav>

</header>

<main class="page-content col-sm-10 col-sm-push-2 ">

	<aside id="navigation" class="page-side col-xs-12 col-sm-2 hidden-xs">

		<div class="row">
				<nav class="page-side-nav" role="navigation">

					<div class="list-group" id="mainmenu">

						<?php $active = active_main_menu("/system"); ?>
						<a href="#system" class="list-group-item <?php if ($active):?>active-menu-title<? endif; ?>" data-toggle="collapse" data-parent="#mainmenu"><span class="glyphicon glyphicon-dashboard __iconspacer"></span><?php echo gettext("System"); ?></a>

						<div class="collapse <?php if ($active):?>active-menu in<? endif; ?>" id="system">
							<?php
								output_menu($system_menu);
							?>
						</div>

						<?php $active = active_main_menu("/interface"); ?>
						<a href="#interfaces" class="list-group-item <?php if ($active):?>active-menu-title<? endif; ?>" data-toggle="collapse" data-parent="#mainmenu"><span class="glyphicon glyphicon-wrench __iconspacer"></span><?php echo gettext("Interfaces"); ?></a>

						<div class="collapse <?php if ($active):?>active-menu in<? endif; ?>" id="interfaces">
							<?php
								output_menu($interfaces_menu);
							?>
						</div>

						<?php $active = active_main_menu("/firewall"); ?>
						<a href="#firewall" class="list-group-item <?php if ($active):?>active-menu-title<? endif; ?>" data-toggle="collapse" data-parent="#mainmenu"><span class="glyphicon glyphicon-fire __iconspacer"></span><?php echo gettext("Firewall"); ?></a>

						<div class="collapse <?php if ($active):?>active-menu in<? endif; ?>" id="firewall">
							<?php
								output_menu($firewall_menu);
							?>
						</div>

						<?php $active = active_main_menu("/service", "/load_balancer", "/pkg_edit"); ?>
						<a href="#services" class="list-group-item <?php if ($active):?>active-menu-title<? endif; ?>" data-toggle="collapse" data-parent="#mainmenu"><span class="glyphicon glyphicon-cog __iconspacer"></span><?php echo gettext("Services"); ?></a>

						<div class="collapse <?php if ($active):?>active-menu in<? endif; ?>" id="services">
							<?php
								output_menu($services_menu);
							?>
						</div>

						<?php $active = active_main_menu("/vpn"); ?>
						<a href="#vpn" class="list-group-item <?php if ($active):?>active-menu-title<? endif; ?>" data-toggle="collapse" data-parent="#mainmenu"><span class="glyphicon glyphicon-lock __iconspacer"></span><?php echo gettext("VPN"); ?></a>

						<div class="collapse <?php if ($active):?>active-menu in<? endif; ?>" id="vpn">
							<?php
								output_menu($vpn_menu);
							?>
						</div>

						<?php $active = active_main_menu("/status", "/carp_status", "/diag_ipsec", "/diag_logs"); ?>
						<a href="#statusmenu" class="list-group-item <?php if ($active):?>active-menu-title<? endif; ?>" data-toggle="collapse" data-parent="#mainmenu"><span class="glyphicon glyphicon-tasks __iconspacer"></span><?php echo gettext("Status"); ?></a>

						<div class="collapse <?php if ($active):?>active-menu in<? endif; ?>" id="statusmenu">
							<?php
								output_menu($status_menu);
							?>
						</div>

						<?php $active = active_main_menu("/diag", "/crash_reporter"); ?>
						<a href="#diagnostics" class="list-group-item <?php if ($active):?>active-menu-title<? endif; ?>" data-toggle="collapse" data-parent="#mainmenu"><span class="glyphicon glyphicon-stats __iconspacer"></span><?php echo gettext("Diagnostics"); ?></a>

						<div class="collapse <?php if ($active):?>active-menu in<? endif; ?>" id="diagnostics">
							<?php
								output_menu($diagnostics_menu);
							?>
						</div>

						<?php if(! $g['disablehelpmenu']): ?>
						<?php $active = active_main_menu("/help"); ?>
						<a href="#help" class="list-group-item <?php if ($active):?>active-menu-title<? endif; ?>" data-toggle="collapse" data-parent="#mainmenu"><span class="glyphicon glyphicon-question-sign __iconspacer"></span><?php echo gettext("Help"); ?></a>

						<div class="collapse <?php if ($active):?>active-menu in<? endif; ?>" id="help">
							<?php
								output_menu($help_menu, "_blank");
							?>
						</div>
						<?php endif; ?>
					</div>

			</nav>

		</div>

	</aside>

	<div class="row">

		<header class="page-content-head">
			<div class="container-fluid">
			    <form action="<?=$_SERVER['REQUEST_URI'];?>" method="post">
				<ul class="list-inline">
					<li class="__mb"><h1><?=genhtmltitle($pgtitle);?></h1></li>

					<li class="btn-group-container">

						<?php

						if (!$hide_service_status && !empty($shortcuts[$shortcut_section]['service'])) {
							$ssvc = array();
							switch ($shortcut_section) {
								case "openvpn":
									$ssvc = find_service_by_openvpn_vpnid($vpnid);
									break;
								case "captiveportal":
									$ssvc = find_service_by_cp_zone($cpzone);
									break;
								default:
									$ssvc = find_service_by_name($shortcuts[$shortcut_section]['service']);

							}
							if (!empty($ssvc)) {
								echo get_service_status_icon($ssvc, false);
								echo get_service_control_links($ssvc, false);
							}
						}

						?>

						<? if (!empty($main_buttons)): foreach ($main_buttons as $button): ?>
							<a href="<?=$button['href'];?>" class="btn btn-primary"><span class="glyphicon glyphicon-plus-sign __iconspacer"></span><?=$button['label'];?></a>
						<? endforeach; endif; ?>

						<? if (isset($widgetfiles)): ?>


							<a href="#" id="updatepref" style="display:none" onclick="return updatePref();" class="btn btn-primary"><?=gettext("Save Settings");?></a>

							<button type="button" class="btn btn-default" data-toggle="modal" data-target="#modal_widgets"><span class="glyphicon glyphicon-plus-sign __iconspacer"></span>Add widget</button>
						<? endif; ?>

						<!-- <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#modal_widgets"><span class="glyphicon glyphicon-plus-sign __iconspacer"></span>Add widget</button> -->
					</li>
				</ul>
			    </form>
			</div>
		</header>
