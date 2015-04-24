<?php

/*
 * Copyright (C) 2005-2006 Colin Smith (ethethlay@gmail.com)
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * 1. Redistributions of source code must retain the above copyright notice,
 * this list of conditions and the following disclaimer.
 *
 * 2. Redistributions in binary form must reproduce the above copyright
 * notice, this list of conditions and the following disclaimer in the
 * documentation and/or other materials provided with the distribution.
 *
 * THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
 * INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 * AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 * AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 * OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 * INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * RISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 */

require_once("globals.inc");
require_once("captiveportal.inc");
require_once("openvpn.inc");
require_once("ipsec.inc");
require_once("vpn.inc");
require_once("vslb.inc");
require_once("gwlb.inc");

if (!function_exists('get_services')) {

	function get_services() {
		global $config;
		$services = array();

		/*    Add services that are in the base.
		 *
		 */
		if (is_radvd_enabled()) {
			$pconfig = array();
			$pconfig['name'] = "radvd";
			$pconfig['description'] = gettext("Router Advertisement Daemon");
			$services[] = $pconfig;
		}

		if (isset($config['dnsmasq']['enable'])) {
			$pconfig = array();
			$pconfig['name'] = "dnsmasq";
			$pconfig['description'] = gettext("DNS Forwarder");
			$services[] = $pconfig;
		}

		if (isset($config['unbound']['enable'])) {
			$pconfig = array();
			$pconfig['name'] = "unbound";
			$pconfig['description'] = gettext("Unbound DNS Forwarder");
			$services[] = $pconfig;
		}

		$pconfig = array();
		$pconfig['name'] = "ntpd";
		$pconfig['description'] = gettext("NTP clock sync");
		$services[] = $pconfig;

		if (is_array($config['captiveportal'])) {
			foreach ($config['captiveportal'] as $zone => $setting) {
				if (isset($setting['enable'])) {
					$pconfig = array();
					$pconfig['name'] = "captiveportal";
					$pconfig['zone'] = $zone;
					$pconfig['description'] = gettext("Captive Portal") . ": ".htmlspecialchars($setting['zone']);
					$services[] = $pconfig;
				}
			}
		}

		$iflist = array();
		$ifdescrs = get_configured_interface_list();
		foreach ($ifdescrs as $if) {
			$oc = $config['interfaces'][$if];
			if ($oc['if'] && (!link_interface_to_bridge($if)))
				$iflist[$if] = $if;
		}

		if (isset($config['dhcrelay']['enable'])) {
			$pconfig = array();
			$pconfig['name'] = "dhcrelay";
			$pconfig['description'] = gettext("DHCP Relay");
			$services[] = $pconfig;
		}

		if (isset($config['dhcrelay6']['enable'])) {
			$pconfig = array();
			$pconfig['name'] = "dhcrelay6";
			$pconfig['description'] = gettext("DHCPv6 Relay");
			$services[] = $pconfig;
		}

		if (is_dhcp_server_enabled()) {
			$pconfig = array();
			$pconfig['name'] = "dhcpd";
			$pconfig['description'] = gettext("DHCP Service");
			$services[] = $pconfig;
		}

		$gateways_arr = return_gateways_array();
		if (is_array($gateways_arr)) {
			$pconfig = array();
			$pconfig['name'] = "apinger";
			$pconfig['description'] = gettext("Gateway Monitoring Daemon");
			$services[] = $pconfig;
		}

		if (isset($config['snmpd']['enable'])) {
			$pconfig = array();
			$pconfig['name'] = "bsnmpd";
			$pconfig['description'] = gettext("SNMP Service");
			$services[] = $pconfig;
		}

		if (is_array($config['igmpproxy']['igmpentry']) && (count($config['igmpproxy']['igmpentry']) > 0)) {
			$pconfig = array();
			$pconfig['name'] = "igmpproxy";
			$pconfig['description'] = gettext("IGMP proxy");
			$services[] = $pconfig;
		}

		if (isset($config['installedpackages']['miniupnpd']) && $config['installedpackages']['miniupnpd']['config'][0]['enable']) {
			$pconfig = array();
			$pconfig['name'] = "miniupnpd";
			$pconfig['description'] = gettext("UPnP Service");
			$services[] = $pconfig;
		}

		if (isset($config['ipsec']['enable'])) {
			$pconfig = array();
			$pconfig['name'] = "ipsec";
			$pconfig['description'] = gettext("IPsec VPN");
			$services[] = $pconfig;
		}

		if (isset($config['system']['ssh']['enabled'])) {
			$pconfig = array();
			$pconfig['name'] = 'sshd';
			$pconfig['description'] = gettext('Secure Shell Daemon');
			$services[] = $pconfig;
		}

		foreach (array('server', 'client') as $mode) {
			if (isset($config['openvpn']["openvpn-{$mode}"])) {
				foreach ($config['openvpn']["openvpn-{$mode}"] as $id => $setting) {
					if (!isset($setting['disable'])) {
						$pconfig = array();
						$pconfig['name'] = "openvpn";
						$pconfig['mode'] = $mode;
						$pconfig['id'] = $id;
						$pconfig['vpnid'] = $setting['vpnid'];
						$pconfig['description'] = gettext("OpenVPN") . " ".$mode.": ".htmlspecialchars($setting['description']);
						$services[] = $pconfig;
					}
				}
			}
		}

		if (count($config['load_balancer']['virtual_server']) && count($config['load_balancer']['lbpool'])) {
			$pconfig = array();
			$pconfig['name'] = "relayd";
			$pconfig['description'] = gettext("Server load balancing daemon");
			$services[] = $pconfig;
		}
		return $services;
	}

}

if (!function_exists('find_service_by_name')) {

	function find_service_by_name($name) {
		$services = get_services();
		foreach ($services as $service)
			if ($service["name"] == $name)
				return $service;
		return array();
	}

}

if (!function_exists('find_service_by_openvpn_vpnid')) {

	function find_service_by_openvpn_vpnid($vpnid) {
		$services = get_services();
		foreach ($services as $service)
			if (($service["name"] == "openvpn") && isset($service["vpnid"]) && ($service["vpnid"] == $vpnid))
				return $service;
		return array();
	}


}

if (!function_exists('find_service_by_cp_zone')) {


	function find_service_by_cp_zone($zone) {
		$services = get_services();
		foreach ($services as $service)
			if (($service["name"] == "captiveportal") && isset($service["zone"]) && ($service["zone"] == $zone))
				return $service;
		return array();
	}


}

if (!function_exists('service_name_compare')) {


	function service_name_compare($a, $b) {
		if (strtolower($a['name']) == strtolower($b['name']))
			return 0;
		return (strtolower($a['name']) < strtolower($b['name'])) ? -1 : 1;
	}


}


if (!function_exists('get_service_status')) {


	function get_service_status($service) {
		global $g;
		switch ($service['name']) {
			case "openvpn":
				$running = isvalidpid("/var/run/openvpn_{$service['mode']}{$service['vpnid']}.pid");
				break;
			case "captiveportal":
				$running = isvalidpid("/var/run/lighty-{$service['zone']}-CaptivePortal.pid");
				if (isset($config['captiveportal'][$service['zone']]['httpslogin']))
					$running = $running && isvalidpid("/var/run/lighty-{$service['zone']}-CaptivePortal-SSL.pid");
				break;
			case "vhosts-http":
				$running = isvalidpid("/var/run/vhosts-http.pid");
				break;
			case "dhcrelay6":
				$running = isvalidpid("/var/run/dhcrelay6.pid");
				break;
			case 'ipsec':
				$running = isvalidpid("/var/run/charon.pid");
				break;
			default:
				$running = is_process_running($service['name']);
		}
		return $running;
	}


}

if (!function_exists('get_service_status_icon')) {


	function get_service_status_icon($service, $withtext = true, $smallicon = false) {
		global $g;
		$output = "";
		if(get_service_status($service)) {
			$statustext = gettext("Running");
			$output .= '<span class="btn btn-success"><span class="glyphicon glyphicon-play" title="'.sprintf(gettext('%s Service is'),$service['name']).' '.$statustext.'" data-toggle="tooltip" data-placement="bottom" ></span></span>&nbsp;';
		} else {
			$statustext = gettext("Stopped") ;
			$output .= '<span class="btn btn-danger"><span class="glyphicon glyphicon-stop" title="'.sprintf(gettext('%s Service is'),$service['name']).' '.$statustext.'" data-toggle="tooltip" data-placement="bottom" ></span></span>&nbsp;';

		}
		return $output;
	}


}

if (!function_exists('get_service_control_links')) {


	function get_service_control_links($service, $addname = false) {
		global $g;
		$output = "";
		$stitle = ($addname) ? $service['name'] . " " : "";
		if(get_service_status($service)) {
			switch ($service['name']) {
				case "openvpn":
					$output .= "<a href='status_services.php?mode=restartservice&amp;service={$service['name']}&amp;vpnmode={$service['mode']}&amp;id={$service['vpnid']}' class=\"btn btn-default\">";
					break;
				case "captiveportal":
					$output .= "<a href='status_services.php?mode=restartservice&amp;service={$service['name']}&amp;zone={$service['zone']}' class=\"btn btn-default\">";
					break;
				default:
					$output .= "<a href='status_services.php?mode=restartservice&amp;service={$service['name']}' class=\"btn btn-default\">";
			}
			$output .= "<span data-toggle=\"tooltip\" data-placement=\"bottom\" title='" . sprintf(gettext("Restart %sService"),$stitle) . "' class=\"glyphicon glyphicon-refresh\"/></a>\n";
			switch ($service['name']) {
				case "openvpn":
					$output .= "<a href='status_services.php?mode=stopservice&amp;service={$service['name']}&amp;vpnmode={$service['mode']}&amp;id={$service['vpnid']}' class=\"btn btn-default\">";
					break;
				case "captiveportal":
					$output .= "<a href='status_services.php?mode=stopservice&amp;service={$service['name']}&amp;zone={$service['zone']}' class=\"btn btn-default\">";
					break;
				default:
					$output .= "<a href='status_services.php?mode=stopservice&amp;service={$service['name']}' class=\"btn btn-default\">";
					break;
			}
			$output .= "<span data-toggle=\"tooltip\" data-placement=\"bottom\" title='" . sprintf(gettext("Stop %sService"),$stitle) . "' class=\"glyphicon glyphicon-stop\" />";
			$output .= "</a>";
		} else {
			switch ($service['name']) {
				case "openvpn":
					$output .= "<a href='status_services.php?mode=startservice&amp;service={$service['name']}&amp;vpnmode={$service['mode']}&amp;id={$service['vpnid']}' class=\"btn btn-default\">";
					break;
				case "captiveportal":
					$output .= "<a href='status_services.php?mode=startservice&amp;service={$service['name']}&amp;zone={$service['zone']}' class=\"btn btn-default\">";
					break;
				default:
					$output .= "<a href='status_services.php?mode=startservice&amp;service={$service['name']}' class=\"btn btn-default\">";
					break;
			}
			$output .= "<span data-toggle=\"tooltip\" data-placement=\"bottom\" title='" . sprintf(gettext("Start %sService"),$stitle) . "' alt='start' class=\"glyphicon glyphicon-play\"/></a>\n";
		}
		return $output;
	}


}

if (!function_exists('service_control_start')) {


	function service_control_start($name, $extras) {
		global $g;
		switch($name) {
			case 'radvd':
				services_radvd_configure();
				break;
			case 'captiveportal':
				captiveportal_configure();
				break;
			case 'ntpd':
				system_ntp_configure();
				break;
			case 'apinger':
				setup_gateways_monitor();
				break;
			case 'bsnmpd':
				services_snmpd_configure();
				break;
			case 'dhcrelay':
				services_dhcrelay_configure();
				break;
			case 'dhcrelay6':
				services_dhcrelay6_configure();
				break;
			case 'dnsmasq':
				services_dnsmasq_configure();
				break;
			case 'dhcpd':
				services_dhcpd_configure();
				break;
			case 'igmpproxy':
				services_igmpproxy_configure();
				break;
			case 'miniupnpd':
				upnp_action('start');
				break;
			case 'ipsec':
				vpn_ipsec_force_reload();
				break;
			case 'sshd':
				configd_run("sshd restart");
				break;
			case 'openvpn':
				$vpnmode = isset($extras['vpnmode']) ? htmlspecialchars($extras['vpnmode']) : htmlspecialchars($extras['mode']);
				if (($vpnmode == "server") || ($vpnmode == "client")) {
					$id = isset($extras['vpnid']) ? htmlspecialchars($extras['vpnid']) : htmlspecialchars($extras['id']);
					$configfile = "/var/etc/openvpn/{$vpnmode}{$id}.conf";
					if (file_exists($configfile))
						openvpn_restart_by_vpnid($vpnmode, $id);
				}
				break;
			case 'relayd':
				relayd_configure();
				break;
			default:
				log_error(gettext("Could not start unknown service `%s'", $name));
				break;
		}
		return sprintf(gettext("%s has been started."),htmlspecialchars($name));
	}

}

if (!function_exists('service_control_stop')) {


	function service_control_stop($name, $extras) {
		global $g;
		switch($name) {
			case 'radvd':
				killbypid("/var/run/radvd.pid");
				break;
			case 'captiveportal':
				$zone = htmlspecialchars($extras['zone']);
				killbypid("/var/run/lighty-{$zone}-CaptivePortal.pid");
				killbypid("/var/run/lighty-{$zone}-CaptivePortal-SSL.pid");
				break;
			case 'ntpd':
				killbyname("ntpd");
				break;
			case 'apinger':
				killbypid("/var/run/apinger.pid");
				break;
			case 'bsnmpd':
				killbypid("/var/run/snmpd.pid");
				break;
			case 'choparp':
				killbyname("choparp");
				break;
			case 'dhcpd':
				killbyname("dhcpd");
				break;
			case 'dhcrelay':
				killbypid("/var/run/dhcrelay.pid");
				break;
			case 'dhcrelay6':
				killbypid("/var/run/dhcrelay6.pid");
				break;
			case 'dnsmasq':
				killbypid("/var/run/dnsmasq.pid");
				break;
			case 'unbound':
				killbypid("/var/run/unbound.pid");
				break;
			case 'igmpproxy':
				killbyname("igmpproxy");
				break;
			case 'miniupnpd':
				upnp_action('stop');
				break;
			case 'sshd':
				killbyname("sshd");
				break;
			case 'ipsec':
				exec("/usr/local/sbin/ipsec stop");
				break;
			case 'openvpn':
				$vpnmode = htmlspecialchars($extras['vpnmode']);
				if (($vpnmode == "server") or ($vpnmode == "client")) {
					$id = htmlspecialchars($extras['id']);
					$pidfile = "/var/run/openvpn_{$vpnmode}{$id}.pid";
					killbypid($pidfile);
				}
				break;
			case 'relayd':
				mwexec('pkill relayd');
				break;
			default:
				log_error(gettext("Could not stop unknown service `%s'", $name));
				break;
		}
		return sprintf(gettext("%s has been stopped."), htmlspecialchars($name));
	}


}

if (!function_exists('service_control_restart')) {


	function service_control_restart($name, $extras) {
		global $g;
		switch($name) {
			case 'radvd':
				services_radvd_configure();
				break;
			case 'captiveportal':
				captiveportal_configure();
				break;
			case 'ntpd':
				system_ntp_configure();
				break;
			case 'apinger':
				killbypid("/var/run/apinger.pid");
				setup_gateways_monitor();
				break;
			case 'bsnmpd':
				services_snmpd_configure();
				break;
			case 'dhcrelay':
				services_dhcrelay_configure();
				break;
			case 'dhcrelay6':
				services_dhcrelay6_configure();
				break;
			case 'dnsmasq':
				services_dnsmasq_configure();
				break;
			case 'unbound':
				services_unbound_configure();
				break;
			case 'dhcpd':
				services_dhcpd_configure();
				break;
			case 'igmpproxy':
				services_igmpproxy_configure();
				break;
			case 'miniupnpd':
				upnp_action('restart');
				break;
			case 'ipsec':
				vpn_ipsec_force_reload();
				break;
			case 'sshd':
				configd_run("sshd restart");
				break;
			case 'openvpn':
				$vpnmode = htmlspecialchars($extras['vpnmode']);
				if ($vpnmode == "server" || $vpnmode == "client") {
					$id = htmlspecialchars($extras['id']);
					$configfile = "/var/etc/openvpn/{$vpnmode}{$id}.conf";
					if (file_exists($configfile))
						openvpn_restart_by_vpnid($vpnmode, $id);
				}
				break;
			case 'relayd':
				relayd_configure(true);
				break;
			default:
				log_error(gettext("Could not restart unknown service `%s'", $name));
				break;
		}
		return sprintf(gettext("%s has been restarted."),htmlspecialchars($name));
	}
}
