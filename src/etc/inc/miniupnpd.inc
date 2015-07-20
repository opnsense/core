<?php

require_once("util.inc");
require_once("functions.inc");

/* MiniUPnPd */

function upnp_notice ($msg) { log_error("miniupnpd: {$msg}"); }
function upnp_warn ($msg) { log_error("miniupnpd: {$msg}"); }

function upnp_running () {
	if((int)exec('/bin/pgrep -a miniupnpd | /usr/bin/wc -l') > 0)
		return true;
	return false;
}

function upnp_write_config($file, $text) {
	$handle = fopen($file, 'w');
	if(!$handle) {
		upnp_warn("Could not open {$file} for writing.");
		return;
	}
	fwrite($handle, $text);
	fclose($handle);
}

function upnp_uuid() {
	/* md5 hash of wan mac */
	$uuid = md5(get_interface_mac(get_real_interface("wan")));
	/* put uuid in correct format 8-4-4-4-12 */
	return substr($uuid,0,8).'-'.substr($uuid,9,4).'-'.substr($uuid,13,4).'-'.substr($uuid,17,4).'-'.substr($uuid,21,12);
}

function sync_package_miniupnpd() {
	global $config;
	global $input_errors;

	$upnp_config = $config['installedpackages']['miniupnpd']['config'][0];
	$config_file = '/var/etc/miniupnpd.conf';

	if (!isset($upnp_config['ext_iface']) || empty($upnp_config['ext_iface']))
		$ext_ifname = get_real_interface();
	else {
		$if = convert_friendly_interface_to_real_interface_name($upnp_config['ext_iface']);
		if ($if != $upnp_config['ext_iface'])
			$ext_ifname = $if;
		else {
			$ext_ifname = get_real_interface();
			upnp_warn("Could not resolve real interface for {$upnp_config['ext_iface']}, defaulting to WAN");
		}
	}

	$config_text = "ext_ifname={$ext_ifname}\n";
	$config_text .= "port=2189\n";

	$ifaces_active = '';

	/* since config is written before this file invoked we don't need to read post data */
	if(!empty($upnp_config['enable']) && !empty($upnp_config['iface_array'])) {
		$iface_array = explode(',', $upnp_config['iface_array']);

		foreach($iface_array as $iface) {
			/* Setting the same internal and external interface is not allowed. */
			if ($iface == $upnp_config['ext_iface'])
				continue;
			$if = convert_friendly_interface_to_real_interface_name($iface);
			/* above function returns iface if fail */
			if($if!=$iface) {
				$addr = find_interface_ip($if);
				$bits = find_interface_subnet($if);
				/* check that the interface has an ip address before adding parameters */
				if (is_ipaddr($addr)) {
					$config_text .= "listening_ip={$if}\n";
					if(!$ifaces_active) {
						$webgui_ip = $addr;
						$ifaces_active = $iface;
					} else
						$ifaces_active .= ", {$iface}";
				} else
					upnp_warn("Interface {$iface} has no ip address, ignoring");
			} else
				upnp_warn("Could not resolve real interface for {$iface}");
		}

		if (!empty($ifaces_active)) {
			/* override wan ip address, common for carp, etc */
			if($upnp_config['overridewanip'])
				$config_text .= "ext_ip={$upnp_config['overridewanip']}\n";

			$download = $upnp_config['download']*1000;
			$upload = $upnp_config['upload']*1000;

			/* set upload and download bitrates */
			if(!empty($download) && !empty($upload)) {
				$config_text .= "bitrate_down={$download}\n";
				$config_text .= "bitrate_up={$upload}\n";
			}

			/* enable logging of packets handled by miniupnpd rules */
			if($upnp_config['logpackets'])
				$config_text .= "packet_log=yes\n";

			/* enable system uptime instead of miniupnpd uptime */
			if($upnp_config['sysuptime'])
				$config_text .= "system_uptime=yes\n";

			/* set webgui url */
			if(!empty($config['system']['webgui']['protocol'])) {
				$config_text .= "presentation_url={$config['system']['webgui']['protocol']}://{$webgui_ip}";
				if(!empty($config['system']['webgui']['port']))
					$config_text .= ":{$config['system']['webgui']['port']}";
				$config_text .= "/\n";
			}

			/* set uuid and serial */
			$config_text .= "uuid=".upnp_uuid()."\n";
			$config_text .= "serial=".strtoupper(substr(upnp_uuid(),0,8))."\n";

			/* set model number */
			$config_text .= "model_number=".file_get_contents("/usr/local/opnsense/version/opnsense")."\n";

			/* upnp access restrictions */
			for($i=1; $i<=4; $i++) {
				if($upnp_config["permuser{$i}"])
					$config_text .= "{$upnp_config["permuser{$i}"]}\n";
			}

			if($upnp_config['permdefault'])
				$config_text .= "deny 0-65535 0.0.0.0/0 0-65535\n";

			/* Allow UPnP or NAT-PMP as requested */
			$config_text .= "enable_upnp="   . ( $upnp_config['enable_upnp']   ? "yes\n" : "no\n" );
			$config_text .= "enable_natpmp=" . ( $upnp_config['enable_natpmp'] ? "yes\n" : "no\n" );

			/* write out the configuration */
			upnp_write_config($config_file, $config_text);

			/* if miniupnpd not running start it */
			if(!upnp_running()) {
				upnp_notice("Starting service on interface: {$ifaces_active}");
				upnp_action('start');
			}
			/* or restart miniupnpd if settings were changed */
			else {
				upnp_notice("Restarting service on interface: {$ifaces_active}");
				upnp_action('restart');
			}
		}
	} else {
		/* user does not want miniupnpd running */
		/* lets stop the service and remove the rc file */

		if (file_exists($config_file)) {
			if(!$upnp_config['enable'])
				upnp_notice('Stopping service: miniupnpd disabled');
			else
				upnp_notice('Stopping service: no interfaces selected');

			upnp_action('stop');
			@unlink($config_file);
		}
	}
}
