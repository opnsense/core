<?php

/*
	Copyright (C) 2015 Franco Fichtner <franco@opnsense.org>
	Copyright (C) 2004-2010 Scott Ullrich <sullrich@gmail.com>
	Copyright (C) 2003-2004 Manuel Kasper <mk@neon1.net>
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

function set_networking_interfaces_ports()
{
	global $config;

	$fp = fopen('php://stdin', 'r');
	$yes_no_prompt = '[y|n]? ';
	$interactive = true;
	$key = null;

	$iflist = get_interface_list();

	echo PHP_EOL . gettext('Press any key to start the manual interface assignment:  ');

	$key = timeout(7);
	if (!isset($key)) {
		$interactive = false;
	}

	if ($key != "\n") {
		echo PHP_EOL;
	}

	echo <<<EOD

Valid interfaces are:

EOD;

	if (!is_array($iflist)) {
		echo gettext("No interfaces found!") . "\n";
		$iflist = array();
	} else {
		foreach ($iflist as $iface => $ifa) {
			echo sprintf("% -7s%s %s %s\n", $iface, $ifa['mac'],
				$ifa['up'] ? "  (up)" : "(down)", $ifa['dmesg']);
		}
	}

	$ifnames = array_keys($iflist);

	echo <<<EOD

You now have the opportunity to configure VLANs.  If you don't require VLANs
for initial connectivity, say no here and use the GUI to configure VLANs later.

Do you want to set up VLANs now ${yes_no_prompt}
EOD;
	if ($interactive) {
		$key = chop(fgets($fp));
	} else {
		$key = 'n';
		echo $key . PHP_EOL;
	}

	if (in_array($key, array('y', 'Y'))) {
		vlan_setup($iflist, $fp);
	}

	if (isset($config['vlans']['vlan'])) {
		echo "\n\n" . gettext("VLAN interfaces:") . "\n\n";
		foreach ($config['vlans']['vlan'] as $vlan) {
			echo sprintf("% -16s%s\n", "{$vlan['if']}_vlan{$vlan['tag']}",
				"VLAN tag {$vlan['tag']}, parent interface {$vlan['if']}");
			$iflist[$vlan['if'] . '_vlan' . $vlan['tag']] = array();
		}
	}

	echo <<<EOD

If you do not know the names of your interfaces, you may choose to use
auto-detection. In that case, disconnect all interfaces now before
hitting 'a' to initiate auto detection.

EOD;

	do {
		echo "\n" . gettext("Enter the WAN interface name or 'a' for auto-detection:") . " ";

		if ($interactive) {
			$wanif = chop(fgets($fp));
		} else {
			$wanif = $ifnames[0];
			echo $wanif . PHP_EOL;
		}

		if ($wanif == '') {
			break;
		}

		if ($wanif == 'a') {
			$wanif = autodetect_interface('WAN', $fp);
		}

		if (!array_key_exists($wanif, $iflist)) {
			printf("\n" . gettext("Invalid interface name '%s'") . "\n", $wanif);
			unset($wanif);
		}
	} while (!$wanif);

	do {
		printf(gettext("%sEnter the LAN interface name or 'a' for auto-detection %s" .
		    "NOTE: this enables full Firewalling/NAT mode.%s" .
			"(or nothing if finished):%s"), "\n", "\n", "\n", " ");

		if ($interactive) {
			$lanif = chop(fgets($fp));
		} else {
			$lanif = '';
			echo $lanif . PHP_EOL;
		}

		if ($lanif == '') {
			break;
		}

		if ($lanif == 'a') {
			$lanif = autodetect_interface('LAN', $fp);
		}

		if (!array_key_exists($lanif, $iflist)) {
			printf(gettext("%sInvalid interface name '%s'%s"), "\n", $lanif, "\n");
			unset($lanif);
		}

		if ($wanif && $lanif == $wanif) {
			unset($lanif);
			echo <<<EOD

Error: you cannot assign the same interface name twice!

EOD;
		}
	} while (!$lanif);

	while ($lanif != '') {
		/* optional interfaces */
		$optif = array();
		$i = 0;

		while (1) {
			if ($optif[$i])
				$i++;
			$io = $i + 1;

			if($config['interfaces']['opt' . $io]['descr'])
				printf(gettext("%sOptional interface %s description found: %s"), "\n", $io, $config['interfaces']['opt' . $io]['descr']);

			printf(gettext("%sEnter the Optional %s interface name or 'a' for auto-detection%s" .
				"(or nothing if finished):%s"), "\n", $io, "\n", " ");

			$optif[$i] = chop(fgets($fp));

			if ($optif[$i]) {
				if ($optif[$i] === "a") {
					$ad = autodetect_interface(gettext("Optional") . " " . $io, $fp);
					if ($ad)
						$optif[$i] = $ad;
					else
						unset($optif[$i]);
				} else if (!array_key_exists($optif[$i], $iflist)) {
					printf(gettext("%sInvalid interface name '%s'%s"), "\n", $optif[$i], "\n");
					unset($optif[$i]);
					continue;
				}
			} else {
				unset($optif[$i]);
				break;
			}
		}

		/* check for double assignments */
		$ifarr = array_merge(array($lanif, $wanif), $optif);

		$again = false;

		for ($i = 0; $i < (count($ifarr)-1); $i++) {
			for ($j = ($i+1); $j < count($ifarr); $j++) {
				if ($ifarr[$i] == $ifarr[$j]) {
					$again = true;
					echo <<<EOD

Error: you cannot assign the same interface name twice!

EOD;
				}
			}
		}

		if ($again) {
			continue;
		}

		echo "\n" . gettext("The interfaces will be assigned as follows:") . "\n\n";

		if ($wanif != '') {
			echo "WAN  -> " . $wanif . "\n";
		}
		if ($lanif != '') {
			echo "LAN  -> " . $lanif . "\n";
		}
		for ($i = 0; $i < count($optif); $i++) {
			echo "OPT" . ($i+1) . " -> " . $optif[$i] . "\n";
		}

		echo <<<EOD

Do you want to proceed ${yes_no_prompt}
EOD;
		if ($interactive) {
			$key = chop(fgets($fp));
		} else {
			$key = 'y';
			echo $key . PHP_EOL;
		}

		if (in_array($key, array('y', 'Y'))) {
			break;
		}
	}

	if ($lanif) {
		if (!is_array($config['interfaces']['lan'])) {
			$config['interfaces']['lan'] = array();
		}
		$config['interfaces']['lan']['if'] = $lanif;
		$config['interfaces']['lan']['enable'] = true;
		if (match_wireless_interface($lanif)) {
			if (is_array($config['interfaces']['lan']) &&
				(!is_array($config['interfaces']['lan']['wireless']))) {
				$config['interfaces']['lan']['wireless'] = array();
			}
		} else {
			if (isset($config['interfaces']['lan'])) {
				unset($config['interfaces']['lan']['wireless']);
			}
		}
	} else {
		if(isset($config['interfaces']['lan']['if']))
			mwexec("/sbin/ifconfig " . $config['interfaces']['lan']['if'] . " delete");
		if(isset($config['interfaces']['lan']))
			unset($config['interfaces']['lan']);
		if(isset($config['dhcpd']['lan']))
			unset($config['dhcpd']['lan']);
		if(isset($config['interfaces']['lan']['if']))
			unset($config['interfaces']['lan']['if']);
		if(isset($config['interfaces']['wan']['blockpriv']))
			unset($config['interfaces']['wan']['blockpriv']);
		if(isset($config['nat']))
			unset($config['nat']);
	}

	if ($wanif) {
		if (!is_array($config['interfaces']['wan'])) {
			$config['interfaces']['wan'] = array();
		}
		$config['interfaces']['wan']['if'] = $wanif;
		$config['interfaces']['wan']['enable'] = true;
		$config['interfaces']['wan']['ipaddr'] = 'dhcp';
		$config['interfaces']['wan']['ipaddrv6'] = 'dhcpv6';

		if (match_wireless_interface($wanif)) {
			if (is_array($config['interfaces']['wan']) &&
				(!is_array($config['interfaces']['wan']['wireless']))) {
				$config['interfaces']['wan']['wireless'] = array();
			}
		} else {
			if (isset($config['interfaces']['wan'])) {
				unset($config['interfaces']['wan']['wireless']);
			}
		}
	} else {
		if (isset($config['interfaces']['wan']))
			unset($config['interfaces']['wan']);
	}

	for ($i = 0; $i < count($optif); $i++) {
		if (!is_array($config['interfaces']['opt' . ($i+1)]))
			$config['interfaces']['opt' . ($i+1)] = array();

		$config['interfaces']['opt' . ($i+1)]['if'] = $optif[$i];

		if (match_wireless_interface($optif[$i])) {
			if (!is_array($config['interfaces']['opt' . ($i+1)]['wireless']))
				$config['interfaces']['opt' . ($i+1)]['wireless'] = array();
		} else {
			unset($config['interfaces']['opt' . ($i+1)]['wireless']);
		}

		if (empty($config['interfaces']['opt' . ($i+1)]['descr'])) {
			$config['interfaces']['opt' . ($i+1)]['descr'] = "OPT" . ($i+1);
			unset($config['interfaces']['opt' . ($i+1)]['enable']);
		}
	}

	/* remove all other (old) optional interfaces */
	for (; isset($config['interfaces']['opt' . ($i+1)]); $i++) {
		unset($config['interfaces']['opt' . ($i+1)]);
	}

	printf(gettext("%sWriting configuration..."), "\n");
	write_config("Console assignment of interfaces");
	printf(gettext("done.%s"), "\n");

	fclose($fp);
}

function autodetect_interface($ifname, $fp)
{
	$iflist_prev = get_interface_list('media');
	echo <<<EOD

Connect the {$ifname} interface now and make sure that the link is up.
Then press ENTER to continue.

EOD;
	fgets($fp);
	$iflist = get_interface_list("media");

	foreach ($iflist_prev as $ifn => $ifa) {
		if (!$ifa['up'] && $iflist[$ifn]['up']) {
			printf(gettext("Detected link-up on interface %s.%s"), $ifn, "\n");
			return $ifn;
		}
	}

	printf(gettext("No link-up detected.%s"), "\n");

	return null;
}

function vlan_setup($iflist, $fp)
{
	global $config;

	$yes_no_prompt = '[y|n]? ';

	if (is_array($config['vlans']['vlan']) && count($config['vlans']['vlan'])) {

	echo <<<EOD

WARNING: all existing VLANs will be cleared if you proceed!

Do you want to proceed ${yes_no_prompt}
EOD;

	if (strcasecmp(chop(fgets($fp)), "y") != 0)
		return;
	}

	$config['vlans']['vlan'] = array();
	echo "\n";

	$vlanif = 0;

	while (1) {
		$vlan = array();

		echo "\n\n" . gettext("VLAN Capable interfaces:") . "\n\n";
		if(!is_array($iflist)) {
			echo gettext("No interfaces found!") . "\n";
		} else {
			$vlan_capable=0;
			foreach ($iflist as $iface => $ifa) {
				if (is_jumbo_capable($iface)) {
					echo sprintf("% -8s%s%s\n", $iface, $ifa['mac'],
						$ifa['up'] ? "   (up)" : "");
					$vlan_capable++;
				}
			}
		}

		if($vlan_capable == 0) {
			echo gettext("No VLAN capable interfaces detected.") . "\n";
			return;
		}

		echo "\n" . gettext("Enter the parent interface name for the new VLAN (or nothing if finished):") . " ";
		$vlan['if'] = chop(fgets($fp));

		if ($vlan['if']) {
			if (!array_key_exists($vlan['if'], $iflist) or
			    !is_jumbo_capable($vlan['if'])) {
				printf(gettext("%sInvalid interface name '%s'%s"), "\n", $vlan['if'], "\n");
				continue;
			}
		} else {
			break;
		}

		echo gettext("Enter the VLAN tag (1-4094):") . " ";
		$vlan['tag'] = chop(fgets($fp));
		$vlan['vlanif'] = "{$vlan['if']}_vlan{$vlan['tag']}";
		if (!is_numericint($vlan['tag']) || ($vlan['tag'] < 1) || ($vlan['tag'] > 4094)) {
			printf(gettext("%sInvalid VLAN tag '%s'%s"), "\n", $vlan['tag'], "\n");
			continue;
		}

		$config['vlans']['vlan'][] = $vlan;
		$vlanif++;
	}
}
