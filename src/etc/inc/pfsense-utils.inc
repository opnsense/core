<?php

/*
 * Copyright (C) 2004-2007 Scott Ullrich <sullrich@gmail.com>
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
 *
 */

/****f* legacy/have_ruleint_access
 * NAME
 *   have_ruleint_access
 * INPUTS
 *	none
 * RESULT
 *   returns true if user has access to edit a specific firewall interface
 ******/
function have_ruleint_access($if) {
	$security_url = "firewall_rules.php?if=". strtolower($if);
	if(isAllowedPage($security_url))
		return true;
	return false;
}


/****f* legacy/is_private_ip
 * NAME
 *   is_private_ip
 * INPUTS
 *	none
 * RESULT
 *   returns true if an ip address is in a private range
 ******/
function is_private_ip($iptocheck) {
	$isprivate = false;
	$ip_private_list=array(
		"10.0.0.0/8",
		"100.64.0.0/10",
		"172.16.0.0/12",
		"192.168.0.0/16",
	);
	foreach($ip_private_list as $private) {
		if(ip_in_subnet($iptocheck,$private)==true)
			$isprivate = true;
	}
	return $isprivate;
}

/****f* legacy/get_dns_servers
 * NAME
 *   get_dns_servres - get system dns servers
 * INPUTS
 *   $dns_servers - an array of the dns servers
 * RESULT
 *   null
 ******/
function get_dns_servers() {
	$dns_servers = array();
	$dns_s = file("/etc/resolv.conf", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
	foreach($dns_s as $dns) {
		$matches = "";
		if (preg_match("/nameserver (.*)/", $dns, $matches))
			$dns_servers[] = $matches[1];
	}
	return array_unique($dns_servers);
}

/****f* legacy/enable_hardware_offloading
 * NAME
 *   enable_hardware_offloading - Enable a NIC's supported hardware features.
 * INPUTS
 *   $interface	- string containing the physical interface to work on.
 * RESULT
 *   null
 * NOTES
 *   This function only supports the fxp driver's loadable microcode.
 ******/
function enable_hardware_offloading($interface)
{
	global $config;

	if (isset($config['system']['do_not_use_nic_microcode'])) {
		return;
	}

	/* translate wan, lan, opt -> real interface if needed */
	$int = get_real_interface($interface);
	if (empty($int)) {
		return;
	}
	$int_family = preg_split('/[0-9]+/', $int);
	$supported_ints = array('fxp');
	if (in_array($int_family, $supported_ints)) {
		if (does_interface_exist($int)) {
			legacy_interface_flags($int, 'link0');
		}
	}
}

/****f* legacy/setup_polling
 * NAME
 *   sets up polling
 * INPUTS
 *
 * RESULT
 *   null
 * NOTES
 *
 ******/
function setup_polling()
{
	global $config;

	if (isset($config['system']['polling'])) {
		set_single_sysctl("kern.polling.idle_poll", "1");
	} else {
		set_single_sysctl("kern.polling.idle_poll", "0");
	}
}

function set_language($lang)
{
	$lang_encoding = $lang . '.UTF-8';
	$textdomain = 'OPNsense';

	putenv('LANG=' . $lang_encoding);
	setlocale(LC_ALL, $lang_encoding);
	textdomain($textdomain);
	bindtextdomain($textdomain, '/usr/local/share/locale');
	bind_textdomain_codeset($textdomain, $lang_encoding);
}

/****f* legacy/setup_microcode
 * NAME
 *   enumerates all interfaces and calls enable_hardware_offloading which
 *   enables a NIC's supported hardware features.
 * INPUTS
 *
 * RESULT
 *   null
 * NOTES
 *   This function only supports the fxp driver's loadable microcode.
 ******/
function setup_microcode() {

	/* if list */
	$ifs = get_interface_arr();

	foreach($ifs as $if)
		enable_hardware_offloading($if);
}

/****f* legacy/get_carp_status
 * NAME
 *   get_carp_status - Return whether CARP is enabled or disabled.
 * RESULT
 *   boolean	- true if CARP is enabled, false if otherwise.
 ******/
function get_carp_status() {
	/* grab the current status of carp */
	$status = get_single_sysctl('net.inet.carp.allow');
	return (intval($status) > 0);
}

/*
 * get_carp_interface_status($carpinterface): returns the status of a carp ip
 */
function get_carp_interface_status($carpinterface) {
	$carp_query = "";

	/* XXX: Need to fidn a better way for this! */
	list ($interface, $vhid) = explode("_vip", $carpinterface);
	$interface = get_real_interface($interface);
	exec("/sbin/ifconfig $interface | /usr/bin/grep -v grep | /usr/bin/grep carp: | /usr/bin/grep 'vhid {$vhid}'", $carp_query);
	foreach($carp_query as $int) {
		if(stristr($int, "MASTER"))
			return gettext("MASTER");
		if(stristr($int, "BACKUP"))
			return gettext("BACKUP");
		if(stristr($int, "INIT"))
			return gettext("INIT");
	}
	return;
}

/*
 *  backup_config_section($section): returns as an xml file string of
 *                                   the configuration section
 */
function backup_config_section($section_name) {
	global $config;
	$new_section = &$config[$section_name];
	/* generate configuration XML */
	$xmlconfig = dump_xml_config($new_section, $section_name);
	$xmlconfig = str_replace("<?xml version=\"1.0\"?>", "", $xmlconfig);
	return $xmlconfig;
}

/* KEEP THIS: unbreaks syntax highlighting <? */

/*
 *  restore_config_section($section_name, new_contents): restore a configuration section,
 *                                                  and write the configuration out
 *                                                  to disk/cf.
 */
function restore_config_section($section_name, $new_contents)
{
	global $config;

	$tmpxml = '/tmp/tmpxml';

	$fout = fopen($tmpxml, 'w');
	fwrite($fout, $new_contents);
	fclose($fout);

	$xml = parse_xml_config($tmpxml, null);
	if (isset($xml['pfsense'])) {
		$xml = $xml['pfsense'];
	} elseif (isset($xml['m0n0wall'])) {
		$xml = $xml['m0n0wall'];
	} elseif (isset($xml['opnsense'])) {
		$xml = $xml['opnsense'];
	}
	if (isset($xml[$section_name])) {
		$section_xml = $xml[$section_name];
	} else {
		$section_xml = -1;
	}

	@unlink($tmpxml);

	if ($section_xml === -1) {
		return false;
	}

	$config[$section_name] = &$section_xml;
	write_config(sprintf(gettext("Restored %s of config file (maybe from CARP partner)"), $section_name));
	disable_security_checks();

	return true;
}

/*
 *  merge_config_section($section_name, new_contents):   restore a configuration section,
 *                                                  and write the configuration out
 *                                                  to disk/cf.  But preserve the prior
 *													structure if needed
 */
function merge_config_section($section_name, $new_contents)
{
	global $config;
	$fname = '/tmp/tmp-' . time();
	$fout = fopen($fname, "w");
	fwrite($fout, $new_contents);
	fclose($fout);
	$section_xml = parse_xml_config($fname, $section_name);
	$config[$section_name] = $section_xml;
	unlink($fname);
	write_config(sprintf(gettext("Restored %s of config file (maybe from CARP partner)"), $section_name));
	disable_security_checks();
	return;
}

/*
 * host_firmware_version(): Return the versions used in this install
 */
function host_firmware_version()
{
	global $config;

	return array(
		'firmware' => array('version' => file_get_contents('/usr/local/opnsense/version/opnsense')),
		'kernel' => array('version' => file_get_contents('/usr/local/opnsense/version/os-update.kernel')),
		'base' => array('version' => file_get_contents('/usr/local/opnsense/version/os-update.base')),
		'config_version' => $config['version']
	);
}


/****f* legacy/reload_interfaces_sync
 * NAME
 *   reload_interfaces - reload all interfaces
 * INPUTS
 *   none
 * RESULT
 *   none
 ******/
function reload_interfaces_sync()
{
	global $config;

	/* parse config.xml again */
	$config = parse_config();

	/* enable routing */
	system_routing_enable();

	/* set up interfaces */
	interfaces_configure();
}

/****f* legacy/reload_all
 * NAME
 *   reload_all - triggers a reload of all settings
 *   * INPUTS
 *   none
 * RESULT
 *   none
 ******/
function reload_all()
{
	configd_run("service reload all", true);
}

/****f* legacy/reload_interfaces
 * NAME
 *   reload_interfaces - triggers a reload of all interfaces
 * INPUTS
 *   none
 * RESULT
 *   none
 ******/
function reload_interfaces()
{
	configd_run("interface reload");
}

/****f* legacy/reload_all_sync
 * NAME
 *   reload_all - reload all settings
 *   * INPUTS
 *   none
 * RESULT
 *   none
 ******/
function reload_all_sync()
{
	global $config;

	/* parse config.xml again */
	$config = parse_config();

	/* set up our timezone */
	system_timezone_configure();

	/* set up our hostname */
	system_hostname_configure();

	/* make hosts file */
	system_hosts_generate();

	/* generate resolv.conf */
	system_resolvconf_generate();

	/* enable routing */
	system_routing_enable();

	/* set up interfaces */
	interfaces_configure();

	/* start dyndns service */
	services_dyndns_configure();

	/* configure cron service */
	configure_cron();

	/* start the NTP client */
	system_ntp_configure();

	/* restart sshd */
	mwexec_bg('/usr/local/etc/rc.sshd');

	/* restart webConfigurator if needed */
	mwexec_bg('/usr/local/etc/rc.restart_webgui');
}

function setup_serial_port($when = 'save', $path = '')
{
	global $config;

	$serial_enabled = isset($config['system']['enableserial']);

	$prefix = "";
	if (($when == "upgrade") && (!empty($path)) && is_dir($path.'/boot/'))
		$prefix = "/tmp/{$path}";
	$boot_config_file = "{$path}/boot.config";
	$loader_conf_file = "{$path}/boot/loader.conf";
	/* serial console - write out /boot.config */
	if(file_exists($boot_config_file))
		$boot_config = file_get_contents($boot_config_file);
	else
		$boot_config = "";

	$serialspeed = (is_numeric($config['system']['serialspeed'])) ? $config['system']['serialspeed'] : '115200';
	if (!is_install_media()) {
		$boot_config_split = explode("\n", $boot_config);
		$fd = fopen($boot_config_file,"w");
		if($fd) {
			foreach($boot_config_split as $bcs) {
				if(stristr($bcs, "-D") || stristr($bcs, "-h")) {
					/* DONT WRITE OUT, WE'LL DO IT LATER */
				} else {
					if($bcs <> "")
						fwrite($fd, "{$bcs}\n");
				}
			}
			if ($serial_enabled) {
				fwrite($fd, "-S{$serialspeed} -D\n");
			}
			fclose($fd);
		}

		/* serial console - write out /boot/loader.conf */
		if ($when == "upgrade")
			system("echo \"Reading {$loader_conf_file}...\" >> /conf/upgrade_log.txt");
		$boot_config = file_get_contents($loader_conf_file);
		$boot_config_split = explode("\n", $boot_config);
		if(count($boot_config_split) > 0) {
			$new_boot_config = array();
			// Loop through and only add lines that are not empty, and which
			//  do not contain a console directive.
			foreach($boot_config_split as $bcs)
				if(!empty($bcs)
					&& (stripos($bcs, "console") === false)
					&& (stripos($bcs, "boot_multicons") === false)
					&& (stripos($bcs, "boot_serial") === false)
					&& (stripos($bcs, "hw.usb.no_pf") === false)
					&& (stripos($bcs, "autoboot_delay") === false))
					$new_boot_config[] = $bcs;

			if ($serial_enabled) {
				$new_boot_config[] = 'boot_multicons="YES"';
				$new_boot_config[] = 'boot_serial="YES"';
				$primaryconsole = $config['system']['primaryconsole'];
				switch ($primaryconsole) {
					case "video":
						$new_boot_config[] = 'console="vidconsole,comconsole"';
						break;
					case "serial":
					default:
						$new_boot_config[] = 'console="comconsole,vidconsole"';
				}
			}
			$new_boot_config[] = 'comconsole_speed="' . $serialspeed . '"';
			$new_boot_config[] = 'hw.usb.no_pf="1"';
			$new_boot_config[] = 'autoboot_delay="3"';

			file_put_contents($loader_conf_file, implode("\n", $new_boot_config) . "\n");
		}
	}
	$ttys = file_get_contents("/etc/ttys");
	$ttys_split = explode("\n", $ttys);
	$fd = fopen("/etc/ttys", "w");

	$on_off = $serial_enabled ? 'on' : 'off';

	if (isset($config['system']['disableconsolemenu'])) {
		$console_type = 'Pc';
		$serial_type = 'std.' . $serialspeed;
	} else {
		$console_type = 'al.Pc';
		$serial_type = 'al.' . $serialspeed;
	}
	foreach($ttys_split as $tty) {
		if (stristr($tty, "ttyv0"))
			fwrite($fd, "ttyv0	\"/usr/libexec/getty {$console_type}\"	cons25	on	secure\n");
		else if (stristr($tty, "ttyu0"))
			fwrite($fd, "ttyu0	\"/usr/libexec/getty {$serial_type}\"	cons25	{$on_off}	secure\n");
		else
			fwrite($fd, $tty . "\n");
	}
	unset($on_off, $console_type, $serial_type);
	fclose($fd);
	reload_ttys();
}

function reload_ttys()
{
	/* force init(8) to reload /etc/ttys */
	exec('/bin/kill -HUP 1');
}

/* radvd enabled on any interfaces? */
function is_radvd_enabled() {
	global $config;

	if (!isset($config['dhcpdv6']) || !is_array($config['dhcpdv6'])) {
		$config['dhcpdv6'] = array();
	}

	$dhcpdv6cfg = $config['dhcpdv6'];
	$Iflist = get_configured_interface_list();

	/* handle manually configured DHCP6 server settings first */
	foreach ($dhcpdv6cfg as $dhcpv6if => $dhcpv6ifconf) {
		if(!isset($config['interfaces'][$dhcpv6if]['enable']))
			continue;

		if(!isset($dhcpv6ifconf['ramode']))
			$dhcpv6ifconf['ramode'] = $dhcpv6ifconf['mode'];

		if($dhcpv6ifconf['ramode'] == "disabled")
			continue;

		$ifcfgipv6 = get_interface_ipv6($dhcpv6if);
		if(!is_ipaddrv6($ifcfgipv6))
			continue;

		return true;
	}

	/* handle DHCP-PD prefixes and 6RD dynamic interfaces */
	foreach ($Iflist as $if => $ifdescr) {
		if(!isset($config['interfaces'][$if]['track6-interface']))
			continue;
		if(!isset($config['interfaces'][$if]['enable']))
			continue;

		$ifcfgipv6 = get_interface_ipv6($if);
		if(!is_ipaddrv6($ifcfgipv6))
			continue;

		$ifcfgsnv6 = get_interface_subnetv6($if);
		$subnetv6 = gen_subnetv6($ifcfgipv6, $ifcfgsnv6);

		if(!is_ipaddrv6($subnetv6))
			continue;

		return true;
	}

	return false;
}

/* Any PPPoE servers enabled? */
function is_pppoe_server_enabled() {
	global $config;

	$pppoeenable = false;

	if (!isset($config['pppoes']['pppoe']) || !is_array($config['pppoes']['pppoe']))
		return false;

	foreach ($config['pppoes']['pppoe'] as $pppoes)
		if ($pppoes['mode'] == 'server')
			$pppoeenable = true;

	return $pppoeenable;
}

function convert_seconds_to_hms($sec){
	$min=$hrs=0;
	if ($sec != 0){
		$min = floor($sec/60);
		$sec %= 60;
	}
	if ($min != 0){
		$hrs = floor($min/60);
		$min %= 60;
	}
	if ($sec < 10)
		$sec = "0".$sec;
	if ($min < 10)
		$min = "0".$min;
	if ($hrs < 10)
		$hrs = "0".$hrs;
	$result = $hrs.":".$min.":".$sec;
	return $result;
}

/* Compute the total uptime from the ppp uptime log file in the conf directory */

function get_ppp_uptime($port){
	if (file_exists("/conf/{$port}.log")){
		$saved_time = file_get_contents("/conf/{$port}.log");
		$uptime_data = explode("\n",$saved_time);
		$sec=0;
		foreach($uptime_data as $upt) {
			$sec += substr($upt, 1 + strpos($upt, " "));
		}
		return convert_seconds_to_hms($sec);
	} else {
		$total_time = gettext("No history data found!");
		return $total_time;
	}
}

//returns interface information
function get_interface_info($ifdescr)
{
	global $config;

	$ifinfo = array();
	if (empty($config['interfaces'][$ifdescr]))
		return;
	$ifinfo['hwif'] = $config['interfaces'][$ifdescr]['if'];
	$ifinfo['if'] = get_real_interface($ifdescr);

	$chkif = $ifinfo['if'];
	$ifinfotmp = pfSense_get_interface_addresses($chkif);
	$ifinfo['status'] = $ifinfotmp['status'];
	if (empty($ifinfo['status'])) {
		$ifinfo['status'] = 'down';
	}
	$ifinfo['macaddr'] = $ifinfotmp['macaddr'];
	$ifinfo['ipaddr'] = $ifinfotmp['ipaddr'];
	$ifinfo['subnet'] = $ifinfotmp['subnet'];
	$ifinfo['linklocal'] = get_interface_linklocal($ifdescr);
	$ifinfo['ipaddrv6'] = get_interface_ipv6($ifdescr);
	$ifinfo['subnetv6'] = get_interface_subnetv6($ifdescr);
	if (isset($ifinfotmp['link0'])) {
		$link0 = 'down';
	}
	$ifinfotmp = legacy_interface_stats($chkif);
	$ifinfo['inerrs'] = $ifinfotmp['input errors'];
	$ifinfo['outerrs'] = $ifinfotmp['output errors'];
	$ifinfo['collisions'] = $ifinfotmp['collisions'];

	/* Use pfctl for non wrapping 64 bit counters */
	/* Pass */
	exec("/sbin/pfctl -vvsI -i {$chkif}", $pfctlstats);
	$pf_in4_pass = preg_split("/ +/ ", $pfctlstats[3]);
	$pf_out4_pass = preg_split("/ +/", $pfctlstats[5]);
	$pf_in6_pass = preg_split("/ +/ ", $pfctlstats[7]);
	$pf_out6_pass = preg_split("/ +/", $pfctlstats[9]);
	$in4_pass = $pf_in4_pass[5];
	$out4_pass = $pf_out4_pass[5];
	$in4_pass_packets = $pf_in4_pass[3];
	$out4_pass_packets = $pf_out4_pass[3];
	$in6_pass = $pf_in6_pass[5];
	$out6_pass = $pf_out6_pass[5];
	$in6_pass_packets = $pf_in6_pass[3];
	$out6_pass_packets = $pf_out6_pass[3];
	$ifinfo['inbytespass'] = $in4_pass + $in6_pass;
	$ifinfo['outbytespass'] = $out4_pass + $out6_pass;
	$ifinfo['inpktspass'] = $in4_pass_packets + $in6_pass_packets;
	$ifinfo['outpktspass'] = $out4_pass_packets + $out6_pass_packets;

	/* Block */
	$pf_in4_block = preg_split("/ +/", $pfctlstats[4]);
	$pf_out4_block = preg_split("/ +/", $pfctlstats[6]);
	$pf_in6_block = preg_split("/ +/", $pfctlstats[8]);
	$pf_out6_block = preg_split("/ +/", $pfctlstats[10]);
	$in4_block = $pf_in4_block[5];
	$out4_block = $pf_out4_block[5];
	$in4_block_packets = $pf_in4_block[3];
	$out4_block_packets = $pf_out4_block[3];
	$in6_block = $pf_in6_block[5];
	$out6_block = $pf_out6_block[5];
	$in6_block_packets = $pf_in6_block[3];
	$out6_block_packets = $pf_out6_block[3];
	$ifinfo['inbytesblock'] = $in4_block + $in6_block;
	$ifinfo['outbytesblock'] = $out4_block + $out6_block;
	$ifinfo['inpktsblock'] = $in4_block_packets + $in6_block_packets;
	$ifinfo['outpktsblock'] = $out4_block_packets + $out6_block_packets;

	$ifinfo['inbytes'] = $in4_pass + $in6_pass;
	$ifinfo['outbytes'] = $out4_pass + $out6_pass;
	$ifinfo['inpkts'] = $in4_pass_packets + $in6_pass_packets;
	$ifinfo['outpkts'] = $out4_pass_packets + $out6_pass_packets;

	$ifconfiginfo = "";
	$link_type = $config['interfaces'][$ifdescr]['ipaddr'];
	switch ($link_type) {
	/* DHCP? -> see if dhclient is up */
	case "dhcp":
		/* see if dhclient is up */
		if (find_dhclient_process($ifinfo['if']) != 0)
			$ifinfo['dhcplink'] = "up";
		else
			$ifinfo['dhcplink'] = "down";

		break;
	/* PPPoE/PPTP/L2TP interface? -> get status from virtual interface */
	case "pppoe":
	case "pptp":
	case "l2tp":
		if ($ifinfo['status'] == "up" && !isset($link0))
			/* get PPPoE link status for dial on demand */
			$ifinfo["{$link_type}link"] = "up";
		else
			$ifinfo["{$link_type}link"] = "down";

		break;
	/* PPP interface? -> get uptime for this session and cumulative uptime from the persistant log file in conf */
	case "ppp":
		if ($ifinfo['status'] == "up")
			$ifinfo['ppplink'] = "up";
		else
			$ifinfo['ppplink'] = "down" ;

		if (empty($ifinfo['status']))
			$ifinfo['status'] = "down";

		if (is_array($config['ppps']['ppp']) && count($config['ppps']['ppp'])) {
			foreach ($config['ppps']['ppp'] as $pppid => $ppp) {
				if ($config['interfaces'][$ifdescr]['if'] == $ppp['if'])
					break;
			}
		}
		$dev = $ppp['ports'];
		if ($config['interfaces'][$ifdescr]['if'] != $ppp['if'] || empty($dev))
			break;
		if (!file_exists($dev)) {
			$ifinfo['nodevice'] = 1;
			$ifinfo['pppinfo'] = $dev . " " . gettext("device not present! Is the modem attached to the system?");
		}

		$usbmodemoutput = array();
		exec("usbconfig", $usbmodemoutput);
		$mondev = "/tmp/3gstats.{$ifdescr}";
		if(file_exists($mondev)) {
			$cellstats = file($mondev);
			/* skip header */
			$a_cellstats = explode(",", $cellstats[1]);
			if(preg_match("/huawei/i", implode("\n", $usbmodemoutput))) {
				$ifinfo['cell_rssi'] = huawei_rssi_to_string($a_cellstats[1]);
				$ifinfo['cell_mode'] = huawei_mode_to_string($a_cellstats[2], $a_cellstats[3]);
				$ifinfo['cell_simstate'] = huawei_simstate_to_string($a_cellstats[10]);
				$ifinfo['cell_service'] = huawei_service_to_string(trim($a_cellstats[11]));
			}
			if(preg_match("/zte/i", implode("\n", $usbmodemoutput))) {
				$ifinfo['cell_rssi'] = zte_rssi_to_string($a_cellstats[1]);
				$ifinfo['cell_mode'] = zte_mode_to_string($a_cellstats[2], $a_cellstats[3]);
				$ifinfo['cell_simstate'] = zte_simstate_to_string($a_cellstats[10]);
				$ifinfo['cell_service'] = zte_service_to_string(trim($a_cellstats[11]));
			}
			$ifinfo['cell_upstream'] = $a_cellstats[4];
			$ifinfo['cell_downstream'] = trim($a_cellstats[5]);
			$ifinfo['cell_sent'] = $a_cellstats[6];
			$ifinfo['cell_received'] = trim($a_cellstats[7]);
			$ifinfo['cell_bwupstream'] = $a_cellstats[8];
			$ifinfo['cell_bwdownstream'] = trim($a_cellstats[9]);
		}
		// Calculate cumulative uptime for PPP link. Useful for connections that have per minute/hour contracts so you don't go over!
		if (isset($ppp['uptime']))
			$ifinfo['ppp_uptime_accumulated'] = "(".get_ppp_uptime($ifinfo['if']).")";
		break;
	default:
		break;
	}

	if (file_exists("/var/run/{$link_type}_{$ifdescr}.pid")) {
		$sec = trim(`/usr/local/sbin/ppp-uptime.sh {$ifinfo['if']}`);
		$ifinfo['ppp_uptime'] = convert_seconds_to_hms($sec);
	}

	if ($ifinfo['status'] == "up") {
		/* try to determine media with ifconfig */
		unset($ifconfiginfo);
		exec("/sbin/ifconfig " . $ifinfo['if'], $ifconfiginfo);
		$wifconfiginfo = array();
		if(is_interface_wireless($ifdescr)) {
			exec("/sbin/ifconfig {$ifinfo['if']} list sta", $wifconfiginfo);
			array_shift($wifconfiginfo);
		}
		$matches = "";
		foreach ($ifconfiginfo as $ici) {

			/* don't list media/speed for wireless cards, as it always
			   displays 2 Mbps even though clients can connect at 11 Mbps */
			if (preg_match("/media: .*? \((.*?)\)/", $ici, $matches)) {
				$ifinfo['media'] = $matches[1];
			} else if (preg_match("/media: Ethernet (.*)/", $ici, $matches)) {
				$ifinfo['media'] = $matches[1];
			} else if (preg_match("/media: IEEE 802.11 Wireless Ethernet (.*)/", $ici, $matches)) {
				$ifinfo['media'] = $matches[1];
			}

			if (preg_match("/status: (.*)$/", $ici, $matches)) {
				if ($matches[1] != "active")
					$ifinfo['status'] = $matches[1];
				if($ifinfo['status'] == gettext("running"))
					$ifinfo['status'] = gettext("up");
			}
			if (preg_match("/channel (\S*)/", $ici, $matches)) {
				$ifinfo['channel'] = $matches[1];
			}
			if (preg_match("/ssid (\".*?\"|\S*)/", $ici, $matches)) {
				if ($matches[1][0] == '"')
					$ifinfo['ssid'] = substr($matches[1], 1, -1);
				else
					$ifinfo['ssid'] = $matches[1];
			}
			if (preg_match("/laggproto (.*)$/", $ici, $matches)) {
				$ifinfo['laggproto'] = $matches[1];
			}
			if (preg_match("/laggport: (.*)$/", $ici, $matches)) {
				$ifinfo['laggport'][] = $matches[1];
			}
		}
		foreach($wifconfiginfo as $ici) {
			$elements = preg_split("/[ ]+/i", $ici);
			if ($elements[0] != "") {
				$ifinfo['bssid'] = $elements[0];
			}
			if ($elements[3] != "") {
				$ifinfo['rate'] = $elements[3];
			}
			if ($elements[4] != "") {
				$ifinfo['rssi'] = $elements[4];
			}

		}
		/* lookup the gateway */
		if (interface_has_gateway($ifdescr)) {
			$ifinfo['gateway'] = get_interface_gateway($ifdescr);
			$ifinfo['gatewayv6'] = get_interface_gateway_v6($ifdescr);
		}
	}

	$bridge = "";
	$bridge = link_interface_to_bridge($ifdescr);
	if($bridge) {
		$bridge_text = `/sbin/ifconfig {$bridge}`;
		if(stristr($bridge_text, "blocking") <> false) {
			$ifinfo['bridge'] = "<b><font color='red'>" . gettext("blocking") . "</font></b> - " . gettext("check for ethernet loops");
			$ifinfo['bridgeint'] = $bridge;
		} else if(stristr($bridge_text, "learning") <> false) {
			$ifinfo['bridge'] = gettext("learning");
			$ifinfo['bridgeint'] = $bridge;
		} else if(stristr($bridge_text, "forwarding") <> false) {
			$ifinfo['bridge'] = gettext("forwarding");
			$ifinfo['bridgeint'] = $bridge;
		}
	}

	return $ifinfo;
}

function add_hostname_to_watch($hostname) {
	if(!is_dir("/var/db/dnscache")) {
		mkdir("/var/db/dnscache");
	}
	$result = array();
	if((is_fqdn($hostname)) && (!is_ipaddr($hostname))) {
		$domrecords = array();
		$domips = array();
		exec("host -t A " . escapeshellarg($hostname), $domrecords, $rethost);
		if($rethost == 0) {
			foreach($domrecords as $domr) {
				$doml = explode(" ", $domr);
				$domip = $doml[3];
				/* fill array with domain ip addresses */
				if(is_ipaddr($domip)) {
					$domips[] = $domip;
				}
			}
		}
		sort($domips);
		$contents = "";
		if(! empty($domips)) {
			foreach($domips as $ip) {
				$contents .= "$ip\n";
			}
		}
		file_put_contents("/var/db/dnscache/$hostname", $contents);
		/* Remove empty elements */
		$result = array_filter(explode("\n", $contents), 'strlen');
	}
	return $result;
}

function is_fqdn($fqdn) {
	$hostname = false;
	if(preg_match("/[-A-Z0-9\.]+\.[-A-Z0-9\.]+/i", $fqdn)) {
		$hostname = true;
	}
	if(preg_match("/\.\./", $fqdn)) {
		$hostname = false;
	}
	if(preg_match("/^\./i", $fqdn)) {
		$hostname = false;
	}
	if(preg_match("/\//i", $fqdn)) {
		$hostname = false;
	}
	return($hostname);
}

function default_state_size()
{
	/* get system memory amount */
	$memory = get_memory();
	$physmem = $memory[0];

	/* Be cautious and only allocate 10% of system memory to the state table */
	$max_states = (int) ($physmem/10)*1000;

	return $max_states;
}


/*
 * load_crypto() - Load crypto modules if enabled in config.
 */
function load_crypto()
{
	global $config;

	$crypto_modules = array('glxsb', 'aesni');

	if (!isset($config['system']['crypto_hardware']) || !in_array($config['system']['crypto_hardware'], $crypto_modules)) {
		return false;
	}

	if (!empty($config['system']['crypto_hardware']) && !is_module_loaded($config['system']['crypto_hardware'])) {
		log_error("Loading {$config['system']['crypto_hardware']} cryptographic accelerator module.");
		mwexec("/sbin/kldload {$config['system']['crypto_hardware']}");
	}
}

/*
 * load_thermal_hardware() - Load temperature monitor kernel module
 */
function load_thermal_hardware()
{
	global $config;

	$thermal_hardware_modules = array('coretemp', 'amdtemp');

	if (!isset($config['system']['thermal_hardware']) || !in_array($config['system']['thermal_hardware'], $thermal_hardware_modules)) {
		return false;
	}

	if (!empty($config['system']['thermal_hardware']) && !is_module_loaded($config['system']['thermal_hardware'])) {
		log_error("Loading {$config['system']['thermal_hardware']} thermal monitor module.");
		mwexec("/sbin/kldload {$config['system']['thermal_hardware']}");
	}
}

function download_file($url, $destination, $verify_ssl = false, $connect_timeout = 60, $timeout = 0)
{
	global $config, $g;

	$fp = fopen($destination, "wb");

	if (!$fp)
		return false;

	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $verify_ssl);
	curl_setopt($ch, CURLOPT_FILE, $fp);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $connect_timeout);
	curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
	curl_setopt($ch, CURLOPT_HEADER, false);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
	curl_setopt($ch, CURLOPT_USERAGENT, $g['product_name'] . '/' . rtrim(file_get_contents("/usr/local/opnsense/version/opnsense")));

	if (!empty($config['system']['proxyurl'])) {
		curl_setopt($ch, CURLOPT_PROXY, $config['system']['proxyurl']);
		if (!empty($config['system']['proxyport']))
			curl_setopt($ch, CURLOPT_PROXYPORT, $config['system']['proxyport']);
		if (!empty($config['system']['proxyuser']) && !empty($config['system']['proxypass'])) {
			@curl_setopt($ch, CURLOPT_PROXYAUTH, CURLAUTH_ANY | CURLAUTH_ANYSAFE);
			curl_setopt($ch, CURLOPT_PROXYUSERPWD, "{$config['system']['proxyuser']}:{$config['system']['proxypass']}");
		}
	}

	@curl_exec($ch);
	$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	fclose($fp);
	curl_close($ch);
	return ($http_code == 200) ? true : $http_code;
}

/* Split() is being DEPRECATED as of PHP 5.3.0 and REMOVED as of PHP 6.0.0. Relying on this feature is highly discouraged. */
if(!function_exists("split")) {
	function split($separator, $haystack, $limit = null) {
		log_error("deprecated split() call with separator '{$separator}'");
		return preg_split($separator, $haystack, $limit);
	}
}

function update_alias_names_upon_change($section, $field, $new_alias_name, $origname)
{
	global $config, $pconfig;

	if (!$origname) {
		return;
	}

	$sectionref = &$config;
	foreach($section as $sectionname) {
		if(is_array($sectionref) && isset($sectionref[$sectionname]))
			$sectionref = &$sectionref[$sectionname];
		else
			return;
	}

	if(is_array($sectionref)) {
		foreach($sectionref as $itemkey => $item) {
			$fieldfound = true;
			$fieldref = &$sectionref[$itemkey];
			foreach($field as $fieldname) {
				if(is_array($fieldref) && isset($fieldref[$fieldname]))
					$fieldref = &$fieldref[$fieldname];
				else {
					$fieldfound = false;
					break;
				}
			}
			if($fieldfound && $fieldref == $origname) {
				$fieldref = $new_alias_name;
			}
		}
	}
}


function process_alias_unzip($temp_filename) {
	if(!file_exists("/usr/local/bin/unzip")) {
		log_error(gettext("Alias archive is a .zip file which cannot be decompressed because utility is missing!"));
		return false;
	}
	rename("{$temp_filename}/aliases", "{$temp_filename}/aliases.zip");
	mwexec("/usr/local/bin/unzip {$temp_filename}/aliases.tgz -d {$temp_filename}/aliases/");
	unlink("{$temp_filename}/aliases.zip");
	$files_to_process = return_dir_as_array("{$temp_filename}/");
	/* foreach through all extracted files and build up aliases file */
	$fd = @fopen("{$temp_filename}/aliases", "w");
	if (!$fd) {
		log_error(sprintf(gettext('Could not open %s/aliases for writing!'), $temp_filename));
		return false;
	}
	foreach($files_to_process as $f2p) {
		$tmpfd = @fopen($f2p, 'r');
		if (!$tmpfd) {
			log_error(sprintf(gettext('The following file could not be read %s from %s'), $f2p, $temp_filename));
			continue;
		}
		while (($tmpbuf = fread($tmpfd, 65536)) !== FALSE)
			fwrite($fd, $tmpbuf);
		fclose($tmpfd);
		unlink($f2p);
	}
	fclose($fd);
	unset($tmpbuf);

	return true;
}

function process_alias_tgz($temp_filename) {
	if(!file_exists('/usr/bin/tar')) {
		log_error(gettext("Alias archive is a .tar/tgz file which cannot be decompressed because utility is missing!"));
		return false;
	}
	rename("{$temp_filename}/aliases", "{$temp_filename}/aliases.tgz");
	mwexec("/usr/bin/tar xzf {$temp_filename}/aliases.tgz -C {$temp_filename}/aliases/");
	unlink("{$temp_filename}/aliases.tgz");
	$files_to_process = return_dir_as_array("{$temp_filename}/");
	/* foreach through all extracted files and build up aliases file */
	$fd = @fopen("{$temp_filename}/aliases", "w");
	if (!$fd) {
		log_error(sprintf(gettext('Could not open %s/aliases for writing!'), $temp_filename));
		return false;
	}
	foreach($files_to_process as $f2p) {
		$tmpfd = @fopen($f2p, 'r');
		if (!$tmpfd) {
			log_error(sprintf(gettext('The following file could not be read %s from %s'), $f2p, $temp_filename));
			continue;
		}
		while (($tmpbuf = fread($tmpfd, 65536)) !== FALSE)
			fwrite($fd, $tmpbuf);
		fclose($tmpfd);
		unlink($f2p);
	}
	fclose($fd);
	unset($tmpbuf);

	return true;
}

function process_alias_urltable($name, $url, $freq, $forceupdate=false) {
	global $config;

	$urltable_prefix = "/var/db/aliastables/";
	$urltable_filename = $urltable_prefix . $name . ".txt";

	// Make the aliases directory if it doesn't exist
	if (!file_exists($urltable_prefix)) {
		mkdir($urltable_prefix);
	} elseif (!is_dir($urltable_prefix)) {
		unlink($urltable_prefix);
		mkdir($urltable_prefix);
	}

	// If the file doesn't exist or is older than update_freq days, fetch a new copy.
	if (!file_exists($urltable_filename)
		|| ((time() - filemtime($urltable_filename)) > ($freq * 86400 - 90))
		|| $forceupdate) {

		// Try to fetch the URL supplied
		@unlink("{$urltable_filename}.tmp");
		$verify_ssl = isset($config['system']['checkaliasesurlcert']);
		if (download_file($url, "{$urltable_filename}.tmp", $verify_ssl)) {
			mwexec("/usr/bin/sed -E 's/\;.*//g; /^[[:space:]]*($|#)/d' ". escapeshellarg($urltable_filename . ".tmp") . " > " . escapeshellarg($urltable_filename));
			if (alias_get_type($name) == "urltable_ports") {
				$ports = explode("\n", file_get_contents($urltable_filename));
				$ports = group_ports($ports);
				file_put_contents($urltable_filename, implode("\n", $ports));
			}
			@unlink("{$urltable_filename}.tmp");
		} else {
			touch($urltable_filename);
		}
		return true;
	} else {
		// File exists, and it doesn't need updated.
		return -1;
	}
}


/* This xml 2 array function is courtesy of the php.net comment section on xml_parse.
 * it is roughly 4 times faster then our existing pfSense parser but due to the large
 * size of the RRD xml dumps this is required.
 * The reason we do not use it for pfSense is that it does not know about array fields
 * which causes it to fail on array fields with single items. Possible Todo?
 */
function xml2array($contents, $get_attributes = 1, $priority = 'tag')
{
	if (!function_exists('xml_parser_create'))
	{
		return array ();
	}
	$parser = xml_parser_create('');
	xml_parser_set_option($parser, XML_OPTION_TARGET_ENCODING, "UTF-8");
	xml_parser_set_option($parser, XML_OPTION_CASE_FOLDING, 0);
	xml_parser_set_option($parser, XML_OPTION_SKIP_WHITE, 1);
	xml_parse_into_struct($parser, trim($contents), $xml_values);
	xml_parser_free($parser);
	if (!$xml_values)
		return; //Hmm...
	$xml_array = array ();
	$parents = array ();
	$opened_tags = array ();
	$arr = array ();
	$current = & $xml_array;
	$repeated_tag_index = array ();
	foreach ($xml_values as $data)
	{
		unset ($attributes, $value);
		extract($data);
		$result = array ();
		$attributes_data = array ();
		if (isset ($value))
		{
			if ($priority == 'tag')
				$result = $value;
			else
				$result['value'] = $value;
		}
		if (isset ($attributes) and $get_attributes)
		{
			foreach ($attributes as $attr => $val)
			{
				if ($priority == 'tag')
					$attributes_data[$attr] = $val;
				else
					$result['attr'][$attr] = $val; //Set all the attributes in a array called 'attr'
			}
		}
		if ($type == "open")
		{
			$parent[$level -1] = & $current;
			if (!is_array($current) or (!in_array($tag, array_keys($current))))
			{
				$current[$tag] = $result;
				if ($attributes_data)
					$current[$tag . '_attr'] = $attributes_data;
				$repeated_tag_index[$tag . '_' . $level] = 1;
				$current = & $current[$tag];
			}
			else
			{
				if (isset ($current[$tag][0]))
				{
					$current[$tag][$repeated_tag_index[$tag . '_' . $level]] = $result;
					$repeated_tag_index[$tag . '_' . $level]++;
				}
				else
				{
					$current[$tag] = array (
						$current[$tag],
						$result
						);
					$repeated_tag_index[$tag . '_' . $level] = 2;
					if (isset ($current[$tag . '_attr']))
					{
						$current[$tag]['0_attr'] = $current[$tag . '_attr'];
						unset ($current[$tag . '_attr']);
					}
				}
				$last_item_index = $repeated_tag_index[$tag . '_' . $level] - 1;
				$current = & $current[$tag][$last_item_index];
			}
		}
		elseif ($type == "complete")
		{
			if (!isset ($current[$tag]))
			{
				$current[$tag] = $result;
				$repeated_tag_index[$tag . '_' . $level] = 1;
				if ($priority == 'tag' and $attributes_data)
					$current[$tag . '_attr'] = $attributes_data;
			}
			else
			{
				if (isset ($current[$tag][0]) and is_array($current[$tag]))
				{
					$current[$tag][$repeated_tag_index[$tag . '_' . $level]] = $result;
					if ($priority == 'tag' and $get_attributes and $attributes_data)
					{
						$current[$tag][$repeated_tag_index[$tag . '_' . $level] . '_attr'] = $attributes_data;
					}
					$repeated_tag_index[$tag . '_' . $level]++;
				}
				else
				{
					$current[$tag] = array (
						$current[$tag],
						$result
						);
					$repeated_tag_index[$tag . '_' . $level] = 1;
					if ($priority == 'tag' and $get_attributes)
					{
						if (isset ($current[$tag . '_attr']))
						{
							$current[$tag]['0_attr'] = $current[$tag . '_attr'];
							unset ($current[$tag . '_attr']);
						}
						if ($attributes_data)
						{
							$current[$tag][$repeated_tag_index[$tag . '_' . $level] . '_attr'] = $attributes_data;
						}
					}
					$repeated_tag_index[$tag . '_' . $level]++; //0 and 1 index is already taken
				}
			}
		}
		elseif ($type == 'close')
		{
			$current = & $parent[$level -1];
		}
	}
	return ($xml_array);
}

/* sort by interface only, retain the original order of rules that apply to
   the same interface */
function filter_rules_sort() {
	global $config;

	/* mark each rule with the sequence number (to retain the order while sorting) */
	for ($i = 0; isset($config['filter']['rule'][$i]); $i++)
		$config['filter']['rule'][$i]['seq'] = $i;

	usort($config['filter']['rule'], "filter_rules_compare");

	/* strip the sequence numbers again */
	for ($i = 0; isset($config['filter']['rule'][$i]); $i++)
		unset($config['filter']['rule'][$i]['seq']);
}
function filter_rules_compare($a, $b) {
	if (isset($a['floating']) && isset($b['floating']))
		return $a['seq'] - $b['seq'];
	else if (isset($a['floating']))
		return -1;
	else if (isset($b['floating']))
		return 1;
	else if ($a['interface'] == $b['interface'])
		return $a['seq'] - $b['seq'];
	else
		return compare_interface_friendly_names($a['interface'], $b['interface']);
}


/****f* legacy/load_mac_manufacturer_table
 * NAME
 *   load_mac_manufacturer_table
 * INPUTS
 *   none
 * RESULT
 *   returns associative array with MAC-Manufacturer pairs
 ******/
function load_mac_manufacturer_table() {
	/* load MAC-Manufacture data from the file */
	$macs = false;
	if (file_exists("/usr/local/share/nmap/nmap-mac-prefixes"))
		$macs=file("/usr/local/share/nmap/nmap-mac-prefixes");
	if ($macs){
		foreach ($macs as $line){
			if (preg_match('/([0-9A-Fa-f]{6}) (.*)$/', $line, $matches)){
				/* store values like this $mac_man['000C29']='VMware' */
				$mac_man["$matches[1]"]=$matches[2];
			}
		}
		return $mac_man;
	} else
		return -1;

}

/****f* legacy/is_ipaddr_configured
 * NAME
 *   is_ipaddr_configured
 * INPUTS
 *   IP Address to check.
 * RESULT
 *   returns true if the IP Address is
 *   configured and present on this device.
*/
function is_ipaddr_configured($ipaddr, $ignore_if = "", $check_localip = false, $check_subnets = false) {
	global $config;

	$isipv6 = is_ipaddrv6($ipaddr);

	if ($check_subnets) {
		$iflist = get_configured_interface_list();
		foreach ($iflist as $if => $ifname) {
			if ($ignore_if == $if)
				continue;

			if ($isipv6 === true) {
				$bitmask = get_interface_subnetv6($if);
				$subnet = gen_subnetv6(get_interface_ipv6($if), $bitmask);
			} else {
				$bitmask = get_interface_subnet($if);
				$subnet = gen_subnet(get_interface_ip($if), $bitmask);
			}

			if (ip_in_subnet($ipaddr, $subnet . '/' . $bitmask))
				return true;
		}
	} else {
		if ($isipv6 === true)
			$interface_list_ips = get_configured_ipv6_addresses();
		else
			$interface_list_ips = get_configured_ip_addresses();

		foreach($interface_list_ips as $if => $ilips) {
			/* Also ignore CARP interfaces, it'll be checked below */
			if ($ignore_if == $if || strstr($ignore_if, "_vip"))
				continue;
			if (strcasecmp($ipaddr, $ilips) == 0)
				return true;
		}
	}

	$interface_list_vips = get_configured_vips_list(true);
	foreach ($interface_list_vips as $id => $vip) {
		if ($ignore_if == $vip['if'])
			continue;
		if (strcasecmp($ipaddr, $vip['ipaddr']) == 0)
			return true;
	}

	if ($check_localip) {
		if (is_array($config['pptpd']) && !empty($config['pptpd']['localip']) && (strcasecmp($ipaddr, $config['pptpd']['localip']) == 0))
			return true;

		if (!is_array($config['l2tp']) && !empty($config['l2tp']['localip']) && (strcasecmp($ipaddr, $config['l2tp']['localip']) == 0))
			return true;
	}

	return false;
}



/* Returns the calculated bit length of the prefix delegation from the WAN interface */
/* DHCP-PD is variable, calculate from the prefix-len on the WAN interface */
/* 6rd is variable, calculate from 64 - (v6 prefixlen - (32 - v4 prefixlen)) */
/* 6to4 is 16 bits, e.g. 65535 */
function calculate_ipv6_delegation_length($if) {
	global $config;

	if(!isset($config['interfaces'][$if]) || !is_array($config['interfaces'][$if])) {
		return false;
	} elseif (!isset($config['interfaces'][$if]['ipaddrv6'])) {
		return (0);
	}

	switch($config['interfaces'][$if]['ipaddrv6']) {
		case "6to4":
			$pdlen = 16;
			break;
		case "6rd":
			$rd6cfg = $config['interfaces'][$if];
			$rd6plen = explode("/", $rd6cfg['prefix-6rd']);
			$pdlen = (64 - ($rd6plen[1] + (32 - $rd6cfg['prefix-6rd-v4plen'])));
			break;
		case "dhcp6":
			$dhcp6cfg = $config['interfaces'][$if];
			$pdlen = $dhcp6cfg['dhcp6-ia-pd-len'];
			break;
		default:
			$pdlen = 0;
			break;
	}
	return($pdlen);
}

function huawei_rssi_to_string($rssi) {
	$dbm = array();
	$i = 0;
	$dbstart = -113;
	while($i < 32) {
		$dbm[$i] = $dbstart + ($i * 2);
		$i++;
	}
	$percent = round(($rssi / 31) * 100);
	$string = "rssi:{$rssi} level:{$dbm[$rssi]}dBm percent:{$percent}%";
	return $string;
}

function huawei_mode_to_string($mode, $submode) {
	$modes[0] = "None";
	$modes[1] = "AMPS";
	$modes[2] = "CDMA";
	$modes[3] = "GSM/GPRS";
	$modes[4] = "HDR";
	$modes[5] = "WCDMA";
	$modes[6] = "GPS";

	$submodes[0] = "No Service";
	$submodes[1] = "GSM";
	$submodes[2] = "GPRS";
	$submodes[3] = "EDGE";
	$submodes[4] = "WCDMA";
	$submodes[5] = "HSDPA";
	$submodes[6] = "HSUPA";
	$submodes[7] = "HSDPA+HSUPA";
	$submodes[8] = "TD-SCDMA";
	$submodes[9] = "HSPA+";
	$string = "{$modes[$mode]}, {$submodes[$submode]} Mode";
	return $string;
}

function huawei_service_to_string($state) {
	$modes[0] = "No";
	$modes[1] = "Restricted";
	$modes[2] = "Valid";
	$modes[3] = "Restricted Regional";
	$modes[4] = "Powersaving";
	$string = "{$modes[$state]} Service";
	return $string;
}

function huawei_simstate_to_string($state) {
	$modes[0] = "Invalid SIM/locked";
	$modes[1] = "Valid SIM";
	$modes[2] = "Invalid SIM CS";
	$modes[3] = "Invalid SIM PS";
	$modes[4] = "Invalid SIM CS/PS";
	$modes[255] = "Missing SIM";
	$string = "{$modes[$state]} State";
	return $string;
}

function zte_rssi_to_string($rssi) {
	return huawei_rssi_to_string($rssi);
}

function zte_mode_to_string($mode, $submode) {
	$modes[0] = "No Service";
	$modes[1] = "Limited Service";
	$modes[2] = "GPRS";
	$modes[3] = "GSM";
	$modes[4] = "UMTS";
	$modes[5] = "EDGE";
	$modes[6] = "HSDPA";

	$submodes[0] = "CS_ONLY";
	$submodes[1] = "PS_ONLY";
	$submodes[2] = "CS_PS";
	$submodes[3] = "CAMPED";
	$string = "{$modes[$mode]}, {$submodes[$submode]} Mode";
	return $string;
}

function zte_service_to_string($state) {
	$modes[0] = "Initializing";
	$modes[1] = "Network Lock error";
	$modes[2] = "Network Locked";
	$modes[3] = "Unlocked or correct MCC/MNC";
	$string = "{$modes[$state]} Service";
	return $string;
}

function zte_simstate_to_string($state) {
	$modes[0] = "No action";
	$modes[1] = "Network lock";
	$modes[2] = "(U)SIM card lock";
	$modes[3] = "Network Lock and (U)SIM card Lock";
	$string = "{$modes[$state]} State";
	return $string;
}
