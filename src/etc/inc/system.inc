<?php

/*
	Copyright (C) 2003-2004 Manuel Kasper <mk@neon1.net>.
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

function activate_powerd()
{
	global $config;

	if (is_process_running('powerd')) {
		exec('/usr/bin/killall powerd');
	}

	if(isset($config['system']['powerd_enable'])) {
		$ac_mode = "hadp";
		if (!empty($config['system']['powerd_ac_mode']))
			$ac_mode = $config['system']['powerd_ac_mode'];

		$battery_mode = "hadp";
		if (!empty($config['system']['powerd_battery_mode']))
			$battery_mode = $config['system']['powerd_battery_mode'];

		$normal_mode = "hadp";
		if (!empty($config['system']['powerd_normal_mode']))
			$normal_mode = $config['system']['powerd_normal_mode'];

		mwexec("/usr/sbin/powerd -b $battery_mode -a $ac_mode -n $normal_mode");
	}
}

function get_default_sysctl_value($id)
{
	$sysctls = array(
		"debug.pfftpproxy" => "0",
		"hw.syscons.kbd_reboot" => "0",
		"kern.ipc.maxsockbuf" => "4262144",
		"kern.randompid" => "347",
		"kern.random.sys.harvest.interrupt" => 0,
		"kern.random.sys.harvest.point_to_point" => 0,
		"kern.random.sys.harvest.ethernet" => 0,
		"kern.filedelay" => "5",
		"kern.dirdelay" => "4",
		"kern.metadelay" => "3",
		"net.bpf.zerocopy_enable" => 1,
		"net.inet.ip.portrange.first" => "1024",
		"net.inet.tcp.blackhole" => "2",
		"net.inet.udp.blackhole" => "1",
		"net.inet.ip.random_id" => "1",
		"net.inet.tcp.drop_synfin" => "1",
		"net.inet.ip.redirect" => "1",
		"net.inet6.ip6.redirect" => "1",
		"net.inet6.ip6.use_tempaddr" => "0",
		"net.inet6.ip6.prefer_tempaddr" => "0",
		"net.inet.tcp.syncookies" => "1",
		"net.inet.tcp.recvspace" => "65228",
		"net.inet.tcp.sendspace" => "65228",
		"net.inet.ip.fastforwarding" => "0",
		'net.inet.ip.sourceroute' => '0',
		'net.inet.ip.accept_sourceroute' => '0',
		'net.inet.icmp.drop_redirect' => '0',
		'net.inet.icmp.log_redirect' => '0',
		"net.inet.tcp.delayed_ack" => "0",
		"net.inet.udp.maxdgram" => "57344",
		"net.inet.ip.intr_queue_maxlen" => "1000",
		"net.inet.tcp.log_debug" => "0",
		"net.inet.tcp.tso" => "1",
		"net.inet.icmp.icmplim" => "0",
		"net.inet.ip.process_options" => 0,
		"net.inet.udp.checksum" => 1,
		"net.link.bridge.pfil_onlyip" => "0",
		"net.link.bridge.pfil_member" => "1",
		"net.link.bridge.pfil_bridge" => "0",
		"net.link.tap.user_open" => "1",
		"net.route.netisr_maxqlen" => 1024,
		"net.inet.icmp.reply_from_interface" => 1,
		"vfs.read_max" => "32",
	);

	if (isset($sysctls[$id])) {
		return $sysctls[$id];
	}

	return null;
}

function activate_sysctls()
{
	global $config;

	$sysctls = array(
		"net.enc.in.ipsec_bpf_mask" => "0x0002",
		"net.enc.in.ipsec_filter_mask" => "0x0002",
		"net.enc.out.ipsec_bpf_mask" => "0x0001",
		"net.enc.out.ipsec_filter_mask" => "0x0001",
	);

	if (isset($config['sysctl']['item'])) {
		foreach($config['sysctl']['item'] as $tunable) {
			if ($tunable['value'] == 'default') {
				$value = get_default_sysctl_value($tunable['tunable']);
			} else {
				$value = $tunable['value'];
			}

			$sysctls[$tunable['tunable']] = $value;
		}
	}

	set_sysctl($sysctls);
}

function system_resolvconf_generate($dynupdate = false)
{
	global $config;

	$syscfg = $config['system'];

	// Do not create blank domain lines, it breaks tools like dig.
	if($syscfg['domain'])
		$resolvconf = "domain {$syscfg['domain']}\n";

	if (((isset($config['dnsmasq']['enable']) && (empty($config['dnsmasq']['interface']) || in_array("lo0", explode(",", $config['dnsmasq']['interface']))))
		|| (isset($config['unbound']['enable'])) && (empty($config['unbound']['active_interface']) || in_array("lo0", explode(",", $config['unbound']['active_interface']))))
		&& !isset($config['system']['dnslocalhost']))
		$resolvconf .= "nameserver 127.0.0.1\n";

	if (isset($syscfg['dnsallowoverride'])) {
		/* get dynamically assigned DNS servers (if any) */
		$ns = array_unique(get_searchdomains());
		foreach($ns as $searchserver) {
			if($searchserver)
				$resolvconf .= "search {$searchserver}\n";
		}
		$ns = array_unique(get_nameservers());
		foreach($ns as $nameserver) {
			if($nameserver)
				$resolvconf .= "nameserver $nameserver\n";
		}
	}
	if (isset($syscfg['dnsserver']) && is_array($syscfg['dnsserver'])) {
		foreach ($syscfg['dnsserver'] as $ns) {
			if ($ns)
				$resolvconf .= "nameserver $ns\n";
		}
	}

	// Add EDNS support
	if (isset($config['unbound']['enable']) && isset($config['unbound']['edns']))
		$resolvconf .= "options edns0\n";

	$dnslock = lock('resolvconf', LOCK_EX);

	$fd = fopen('/etc/resolv.conf', 'w');
	if (!$fd) {
		printf("Error: cannot open resolv.conf in system_resolvconf_generate().\n");
		unlock($dnslock);
		return 1;
	}

	fwrite($fd, $resolvconf);
	fclose($fd);
	chmod('/etc/resolv.conf', 0644);

	if (!file_exists("/var/run/booting")) {
		/* restart dhcpd (nameservers may have changed) */
		if (!$dynupdate)
			services_dhcpd_configure();
	}

	/* setup static routes for DNS servers. */
	for ($dnscounter=1; $dnscounter<5; $dnscounter++) {
		/* setup static routes for dns servers */
		$dnsgw = "dns{$dnscounter}gw";
		if (isset($config['system'][$dnsgw])) {
			$gwname = $config['system'][$dnsgw];
			if (($gwname <> "") && ($gwname <> "none")) {
				$gatewayip = lookup_gateway_ip_by_name($gwname);
				if (is_ipaddrv4($gatewayip)) {
					/* dns server array starts at 0 */
					$dnscountermo = $dnscounter - 1;
					mwexec("/sbin/route delete -host " . $syscfg['dnsserver'][$dnscountermo]);
					mwexec("/sbin/route add -host " . $syscfg['dnsserver'][$dnscountermo] . " {$gatewayip}");
				}
				if (is_ipaddrv6($gatewayip)) {
					/* dns server array starts at 0 */
					$dnscountermo = $dnscounter - 1;
					mwexec("/sbin/route delete -host -inet6 " . $syscfg['dnsserver'][$dnscountermo]);
					mwexec("/sbin/route add -host -inet6 " . $syscfg['dnsserver'][$dnscountermo] . " {$gatewayip}");
				}
			}
		}
	}

	unlock($dnslock);

	return 0;
}

function get_country_codes()
{
    $dn_cc = array();

    $iso3166_tab = '/usr/local/opnsense/contrib/tzdata/iso3166.tab';
    if (file_exists($iso3166_tab)) {
        $dn_cc_file = file($iso3166_tab);
        foreach ($dn_cc_file as $line) {
            if (preg_match('/^([A-Z][A-Z])\t(.*)$/', $line, $matches)) {
                $dn_cc[$matches[1]] = trim($matches[2]);
            }
        }
    }

    return $dn_cc;
}

function get_firmware_mirrors()
{
	$mirrors = array();

	$mirrors['default'] = '(default)';
	$mirrors['https://opnsense.aivian.org'] = 'Aivian (Shaoxing, CN)';
	$mirrors['https://opnsense.c0urier.net'] = 'c0urier.net (Lund, SE)';
	$mirrors['https://fleximus.org/mirror/opnsense'] = 'Fleximus (Roubaix, FR)';
	$mirrors['http://mirror.ams1.nl.leaseweb.net/opnsense'] = 'LeaseWeb (Amsterdam, NL)';
	$mirrors['http://mirror.fra10.de.leaseweb.net/opnsense'] = 'LeaseWeb (Frankfurt, DE)';
	$mirrors['http://mirror.sfo12.us.leaseweb.net/opnsense'] = 'LeaseWeb (San Francisco, US)';
	$mirrors['http://mirror.wdc1.us.leaseweb.net/opnsense'] = 'LeaseWeb (Washington, D.C., US)';
	$mirrors['http://mirrors.nycbug.org/pub/opnsense'] = 'NYC*BUG (New York, US)';
	$mirrors['http://pkg.opnsense.org'] = 'OPNsense (Amsterdam, NL)';

	return $mirrors;
}

function get_firmware_flavours()
{
	$flavours = array();

	$flavours['default'] = '(default)';
	$flavours['libressl'] = 'LibreSSL';
	$flavours['latest'] = 'OpenSSL';

	return $flavours;
}

function get_zoneinfo()
{
	return timezone_identifiers_list(DateTimeZone::ALL ^ DateTimeZone::UTC);
}

function get_searchdomains()
{
	global $config;

	$master_list = array();

	// Read in dhclient nameservers
	$search_list = glob("/var/etc/searchdomain_*");
	if (is_array($search_list)) {
		foreach($search_list as $fdns) {
			$contents = file($fdns, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
			if (!is_array($contents))
				continue;
			foreach ($contents as $dns) {
				if(is_hostname($dns))
					$master_list[] = $dns;
			}
		}
	}

	return $master_list;
}

function get_nameservers()
{
	global $config;
	$master_list = array();

	// Read in dhclient nameservers
	$dns_lists = glob("/var/etc/nameserver_*");
	if (is_array($dns_lists)) {
		foreach($dns_lists as $fdns) {
			$contents = file($fdns, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
			if (!is_array($contents))
				continue;
			foreach ($contents as $dns) {
				if(is_ipaddr($dns))
					$master_list[] = $dns;
			}
		}
	}

	// Read in any extra nameservers
	if(file_exists("/var/etc/nameservers.conf")) {
		$dns_s = file("/var/etc/nameservers.conf", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
		if(is_array($dns_s)) {
			foreach($dns_s as $dns)
				if (is_ipaddr($dns))
					$master_list[] = $dns;
		}
	}

	return $master_list;
}

function system_hosts_generate()
{
	global $config;

	$syscfg = $config['system'];
	$dnsmasqcfg = $config['dnsmasq'];

	$hosts = "127.0.0.1	localhost localhost.{$syscfg['domain']}\n";
	$lhosts = "";
	$dhosts = "";

	if (isset($config['interfaces']['lan'])) {
		$cfgip = get_interface_ip("lan");
		if (is_ipaddr($cfgip))
			$hosts .= "{$cfgip}	{$syscfg['hostname']}.{$syscfg['domain']} {$syscfg['hostname']}\n";
	} else {
		$sysiflist = get_configured_interface_list();
		foreach ($sysiflist as $sysif) {
			if (!interface_has_gateway($sysif)) {
				$cfgip = get_interface_ip($sysif);
				if (is_ipaddr($cfgip)) {
					$hosts .= "{$cfgip}	{$syscfg['hostname']}.{$syscfg['domain']} {$syscfg['hostname']}\n";
					break;
				}
			}
		}
	}

	if (isset($dnsmasqcfg['enable'])) {
		if (!isset($dnsmasqcfg['hosts']) || !is_array($dnsmasqcfg['hosts']))
			$dnsmasqcfg['hosts'] = array();

		foreach ($dnsmasqcfg['hosts'] as $host) {
			if ($host['host'])
				$lhosts .= "{$host['ip']}	{$host['host']}.{$host['domain']} {$host['host']}\n";
			else
				$lhosts .= "{$host['ip']}	{$host['domain']}\n";
			if (!isset($host['aliases']) || !is_array($host['aliases']) || !is_array($host['aliases']['item'])) {
				continue;
			}
			foreach ($host['aliases']['item'] as $alias) {
				if ($alias['host'])
					$lhosts .= "{$host['ip']}	{$alias['host']}.{$alias['domain']} {$alias['host']}\n";
				else
					$lhosts .= "{$host['ip']}	{$alias['domain']}\n";
			}
		}
		if (isset($dnsmasqcfg['regdhcpstatic']) && is_array($config['dhcpd'])) {
			foreach ($config['dhcpd'] as $dhcpif => $dhcpifconf) {
				if (isset($dhcpifconf['staticmap']) && isset($dhcpifconf['enable'])) {
					foreach ($dhcpifconf['staticmap'] as $host) {
						if ($host['ipaddr'] && $host['hostname'] && $host['domain']) {
							$dhosts .= "{$host['ipaddr']}	{$host['hostname']}.{$host['domain']} {$host['hostname']}\n";
						} elseif ($host['ipaddr'] && $host['hostname'] && $dhcpifconf['domain']) {
							$dhosts .= "{$host['ipaddr']}	{$host['hostname']}.{$dhcpifconf['domain']} {$host['hostname']}\n";
						} elseif ($host['ipaddr'] && $host['hostname']) {
							$dhosts .= "{$host['ipaddr']}	{$host['hostname']}.{$syscfg['domain']} {$host['hostname']}\n";
						}
					}
				}
			}
		}
		if (isset($dnsmasqcfg['regdhcpstatic']) && is_array($config['dhcpdv6'])) {
			foreach ($config['dhcpdv6'] as $dhcpif => $dhcpifconf) {
				if (isset($dhcpifconf['staticmap']) && isset($dhcpifconf['enable'])) {
					foreach ($dhcpifconf['staticmap'] as $host) {
						if ($host['ipaddrv6'] && $host['hostname'] && $host['domain']) {
							$dhosts .= "{$host['ipaddrv6']}	{$host['hostname']}.{$host['domain']} {$host['hostname']}\n";
						} elseif ($host['ipaddrv6'] && $host['hostname'] && $dhcpifconf['domain']) {
							$dhosts .= "{$host['ipaddrv6']}	{$host['hostname']}.{$dhcpifconf['domain']} {$host['hostname']}\n";
						} elseif ($host['ipaddrv6'] && $host['hostname']) {
							$dhosts .= "{$host['ipaddrv6']}	{$host['hostname']}.{$syscfg['domain']} {$host['hostname']}\n";
						}
					}
				}
			}
		}

		if (isset($dnsmasqcfg['dhcpfirst'])) {
			$hosts .= $dhosts . $lhosts;
		} else {
			$hosts .= $lhosts . $dhosts;
		}
	}

	/*
	 * Do not remove this because dhcpleases monitors with kqueue
	 * it needs to be * killed before writing to hosts files.
	 */
	killbypid('/var/run/dhcpleases.pid');

	$fd = fopen('/etc/hosts', 'w');
	if (!$fd) {
		log_error("Error: cannot open hosts file in system_hosts_generate().\n");
		return 1;
	}
	fwrite($fd, $hosts);
	fclose($fd);

	if (isset($config['unbound']['enable'])) {
		unbound_hosts_generate();
	}

	system_dhcpleases_configure();

	return 0;
}

function system_dhcpleases_configure()
{
	global $config, $g;

	/* Start the monitoring process for dynamic dhcpclients. */
	if ((isset($config['dnsmasq']['enable']) && isset($config['dnsmasq']['regdhcp']))
		|| (isset($config['unbound']['enable']) && isset($config['unbound']['regdhcp']))) {
		/* Make sure we do not error out */
		mwexec("/bin/mkdir -p {$g['dhcpd_chroot_path']}/var/db");
		if (!file_exists("{$g['dhcpd_chroot_path']}/var/db/dhcpd.leases")) {
			@touch("{$g['dhcpd_chroot_path']}/var/db/dhcpd.leases");
		}
		if (isvalidpid('/var/run/dhcpleases.pid')) {
			killbypid('/var/run/dhcpleases.pid', 'HUP');
		} else {
			/* To ensure we do not start multiple instances of dhcpleases, perform some clean-up first. */
			killbyname('dhcpleases');
			@unlink('/var/run/dhcpleases.pid');
			if (isset($config['unbound']['enable'])) {
				$dns_pid = 'unbound.pid';
			} else {
				$dns_pid = 'dnsmasq.pid';
			}
			mwexecf(
				'/usr/local/sbin/dhcpleases -l %s -d %s -p %s -h %s',
				array(
					"{$g['dhcpd_chroot_path']}/var/db/dhcpd.leases",
					$config['system']['domain'],
					"/var/run/{$dns_pid}",
					'/etc/hosts'
				)
			);
		}
	} else {
		killbypid('/var/run/dhcpleases.pid');
	}
}

function system_hostname_configure()
{
	global $config;

	$syscfg = $config['system'];

	/* set hostname */
	$status = mwexec("/bin/hostname " .
		escapeshellarg("{$syscfg['hostname']}.{$syscfg['domain']}"));

    /* Setup host GUID ID.  This is used by ZFS. */
	mwexec("/etc/rc.d/hostid start");

	return $status;
}

function system_routing_configure($interface = '')
{
	global $config;

	$gatewayip = "";
	$interfacegw = "";
	$foundgw = false;
	$gatewayipv6 = "";
	$interfacegwv6 = "";
	$foundgwv6 = false;
	/* tack on all the hard defined gateways as well */
	if (isset($config['gateways']['gateway_item'])) {
		array_map('unlink', glob('/tmp/*_defaultgw{,v6}', GLOB_BRACE));
		foreach	($config['gateways']['gateway_item'] as $gateway) {
			if (isset($gateway['defaultgw'])) {
				if ($gateway['ipprotocol'] != "inet6" && (is_ipaddrv4($gateway['gateway']) || $gateway['gateway'] == "dynamic")) {
					if(strstr($gateway['gateway'], ":"))
						continue;
					if ($gateway['gateway'] == "dynamic")
						$gateway['gateway'] = get_interface_gateway($gateway['interface']);
					$gatewayip = $gateway['gateway'];
					$interfacegw = $gateway['interface'];
					if (!empty($gateway['interface'])) {
						$defaultif = get_real_interface($gateway['interface']);
						if ($defaultif)
							@file_put_contents("/tmp/{$defaultif}_defaultgw", $gateway['gateway']);
					}
					$foundgw = true;
				} else if ($gateway['ipprotocol'] == "inet6" && (is_ipaddrv6($gateway['gateway']) || $gateway['gateway'] == "dynamic")) {
					if ($gateway['gateway'] == "dynamic")
						$gateway['gateway'] = get_interface_gateway_v6($gateway['interface']);
					$gatewayipv6 = $gateway['gateway'];
					$interfacegwv6 = $gateway['interface'];
					if (!empty($gateway['interface'])) {
						$defaultifv6 = get_real_interface($gateway['interface']);
						if ($defaultifv6)
							@file_put_contents("/tmp/{$defaultifv6}_defaultgwv6", $gateway['gateway']);
					}
					$foundgwv6 = true;
				}
			}
			if ($foundgw === true && $foundgwv6 === true)
				break;
		}
	}
	if (!$foundgw) {
		$defaultif = get_real_interface("wan");
		$interfacegw = "wan";
		$gatewayip = get_interface_gateway("wan");
		@touch("/tmp/{$defaultif}_defaultgw");
	}
	if (!$foundgwv6) {
		$defaultifv6 = get_real_interface("wan");
		$interfacegwv6 = "wan";
		$gatewayipv6 = get_interface_gateway_v6("wan");
		@touch("/tmp/{$defaultif}_defaultgwv6");
	}

	if (!empty($interface) && $interface != $interfacegw)
		;
	else if (is_ipaddrv4($gatewayip)) {
		log_error("ROUTING: remove current default route to $gatewayip");
		mwexec("/sbin/route delete default");
		log_error("ROUTING: setting default route to $gatewayip");
		mwexec("/sbin/route add -inet default " . escapeshellarg($gatewayip));
	}

	if (!empty($interface) && $interface != $interfacegwv6)
		;
	else if (is_ipaddrv6($gatewayipv6)) {
		$ifscope = "";
		if (is_linklocal($gatewayipv6))
			$ifscope = "%{$defaultifv6}";
		log_error("ROUTING: setting IPv6 default route to {$gatewayipv6}{$ifscope}");
		mwexec("/sbin/route delete -inet6 default " . escapeshellarg("{$gatewayipv6}{$ifscope}"));
		mwexec("/sbin/route add -inet6 default " . escapeshellarg("{$gatewayipv6}{$ifscope}"));
	}

	system_staticroutes_configure($interface, false);

	return 0;
}

/* Compare the current hostname DNS to the DNS cache we made
 * if it has changed we return the old records
 * if no change we return false */
function compare_hostname_to_dnscache($hostname) {
        if(!is_dir("/var/db/dnscache")) {
                mkdir("/var/db/dnscache");
        }
        $hostname = trim($hostname);
        if(is_readable("/var/db/dnscache/{$hostname}")) {
                $oldcontents = file_get_contents("/var/db/dnscache/{$hostname}");
        } else {
                $oldcontents = "";
        }
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
        }

        if(trim($oldcontents) != trim($contents)) {
                log_error(sprintf(gettext('DNSCACHE: Found old IP %1$s and new IP %2$s'), $oldcontents, $contents));
                return ($oldcontents);
        } else {
                return false;
        }
}

function system_staticroutes_configure($interface = '', $update_dns = false)
{
	global $config, $aliastable;

	$filterdns_list = array();

	$static_routes = get_staticroutes(false, true);
	if (count($static_routes)) {
		$gateways_arr = return_gateways_array(false, true);

		foreach ($static_routes as $rtent) {
			if (empty($gateways_arr[$rtent['gateway']])) {
				log_error(sprintf(gettext("Static Routes: Gateway IP could not be found for %s"), $rtent['network']));
				continue;
			}
			$gateway = $gateways_arr[$rtent['gateway']];
			if (!empty($interface) && $interface != $gateway['friendlyiface'])
				continue;

			$gatewayip = $gateway['gateway'];
			$interfacegw = $gateway['interface'];

			$blackhole = "";
			if (!strcasecmp("Null", substr($rtent['gateway'], 0, 3)))
				$blackhole = "-blackhole";

			if (!is_fqdn($rtent['network']) && !is_subnet($rtent['network']))
				continue;

			$dnscache = array();
			if ($update_dns === true) {
				if (is_subnet($rtent['network']))
					continue;
				$dnscache = explode("\n", trim(compare_hostname_to_dnscache($rtent['network'])));
				if (empty($dnscache))
					continue;
			}

			if (is_subnet($rtent['network']))
				$ips = array($rtent['network']);
			else {
				if (!isset($rtent['disabled']))
					$filterdns_list[] = $rtent['network'];
				$ips = add_hostname_to_watch($rtent['network']);
			}

			foreach ($dnscache as $ip) {
				if (in_array($ip, $ips))
					continue;
				mwexec("/sbin/route delete " . escapeshellarg($ip), true);
			}

			if (isset($rtent['disabled'])) {
				/* XXX: This is a bit dangerous in case of routing daemons!? */
				foreach ($ips as $ip)
					mwexec("/sbin/route delete " . escapeshellarg($ip), true);
				continue;
			}

			foreach ($ips as $ip) {
				if (is_ipaddrv4($ip))
					$ip .= "/32";
				else if (is_ipaddrv6($ip))
					$ip .= "/128";

				$inet = (is_subnetv6($ip) ? "-inet6" : "-inet");

				$cmd = " {$inet} {$blackhole} " . escapeshellarg($ip) . " ";

				if (is_subnet($ip))
					if (is_ipaddr($gatewayip)) {
						mwexec("/sbin/route delete".$cmd . escapeshellarg($gatewayip));
						mwexec("/sbin/route add".$cmd . escapeshellarg($gatewayip));
					}
					else if (!empty($interfacegw)) {
						mwexec("/sbin/route delete".$cmd . "-iface " . escapeshellarg($interfacegw));
						mwexec("/sbin/route add".$cmd . "-iface " . escapeshellarg($interfacegw));
					}
			}
		}
		unset($gateways_arr);
	}
	unset($static_routes);

	if ($update_dns === false) {
		if (count($filterdns_list)) {
			$interval = 60;
			$hostnames = "";
			array_unique($filterdns_list);
			foreach ($filterdns_list as $hostname)
				$hostnames .= "cmd {$hostname} '/usr/local/opnsense/service/configd_ctl.py routedns reload'\n";
			file_put_contents("/var/etc/filterdns-route.hosts", $hostnames);
			unset($hostnames);

			if (isvalidpid('/var/run/filterdns-route.pid')) {
				killbypid('/var/run/filterdns-route.pid', 'HUP');
			} else {
				mwexec("/usr/local/sbin/filterdns -p /var/run/filterdns-route.pid -i {$interval} -c /var/etc/filterdns-route.hosts -d 1");
			}
		} else {
			killbypid('/var/run/filterdns-route.pid');
		}
	}
	unset($filterdns_list);

	return 0;
}

function system_routing_enable()
{
	global $config;

	set_sysctl(array(
		"net.inet.ip.forwarding" => "1",
		"net.inet6.ip6.forwarding" => "1"
	));
}

function system_syslogd_fixup_server($server) {
	/* If it's an IPv6 IP alone, encase it in brackets */
	if (is_ipaddrv6($server))
		return "[$server]";
	else
		return $server;
}

function system_syslogd_get_remote_servers($syslogcfg, $facility = "*.*") {
	// Rather than repeatedly use the same code, use this function to build a list of remote servers.
	$facility .= " ".
	$remote_servers = "";
	$pad_to  = 56;
	$padding = ceil(($pad_to - strlen($facility))/8)+1;
	if($syslogcfg['remoteserver'])
		$remote_servers .= "{$facility}" . str_repeat("\t", $padding) . "@" . system_syslogd_fixup_server($syslogcfg['remoteserver']) . "\n";
	if($syslogcfg['remoteserver2'])
		$remote_servers .= "{$facility}" . str_repeat("\t", $padding) . "@" . system_syslogd_fixup_server($syslogcfg['remoteserver2']) . "\n";
	if($syslogcfg['remoteserver3'])
		$remote_servers .= "{$facility}" . str_repeat("\t", $padding) . "@" . system_syslogd_fixup_server($syslogcfg['remoteserver3']) . "\n";
	return $remote_servers;
}

function system_syslogd_start()
{
	global $config, $g;
	$retval = null;

	/* XXX temporary hook for newsyslog.conf regeneration */
	configd_run('template reload OPNsense.Syslog');

	mwexec('/etc/rc.d/hostid start');

	$syslogcfg = $config['syslog'];

	if (file_exists("/var/run/booting"))
		echo gettext("Starting syslog...");

	// Which logging type are we using this week??
	if (isset($config['system']['disablesyslogclog'])) {
		$log_directive = "";
		$log_create_directive = "/usr/bin/touch ";
		$log_size = "";
	} else { // Defaults to CLOG
		$log_directive = "%";
		$log_size = isset($config['syslog']['logfilesize']) ? $config['syslog']['logfilesize'] : "10240";
		$log_create_directive = "/usr/local/sbin/clog -i -s ";
	}

	$syslogd_extra = "";
	if (isset($syslogcfg)) {
		$separatelogfacilities = array('ntp','ntpd','ntpdate','charon','openvpn','pptps','poes','l2tps','relayd','hostapd','dnsmasq','filterdns','unbound','dhcpd','dhcrelay','dhclient','dhcp6c','apinger','radvd','routed','olsrd','zebra','ospfd','bgpd','miniupnpd','filterlog');
		$syslogconf = "";

		$facilitylist = implode(',', array_unique($separatelogfacilities));
		$syslogconf .= "!radvd,routed,olsrd,zebra,ospfd,bgpd,miniupnpd\n";
		if (!isset($syslogcfg['disablelocallogging']))
			$syslogconf .= "*.*								{$log_directive}/var/log/routing.log\n";

		$syslogconf .= "!ntp,ntpd,ntpdate\n";
		if (!isset($syslogcfg['disablelocallogging']))
			$syslogconf .= "*.*								{$log_directive}/var/log/ntpd.log\n";

		$syslogconf .= "!ppp\n";
		if (!isset($syslogcfg['disablelocallogging']))
			$syslogconf .= "*.*								{$log_directive}/var/log/ppps.log\n";

		$syslogconf .= "!pptps\n";
		if (!isset($syslogcfg['disablelocallogging']))
			$syslogconf .= "*.*								{$log_directive}/var/log/pptps.log\n";

		$syslogconf .= "!poes\n";
		if (!isset($syslogcfg['disablelocallogging']))
			$syslogconf .= "*.*								{$log_directive}/var/log/poes.log\n";

		$syslogconf .= "!l2tps\n";
		if (!isset($syslogcfg['disablelocallogging']))
			$syslogconf .= "*.*								{$log_directive}/var/log/l2tps.log\n";

		$syslogconf .= "!charon\n";
		if (!isset($syslogcfg['disablelocallogging']))
			$syslogconf .= "*.*								{$log_directive}/var/log/ipsec.log\n";
		if (isset($syslogcfg['vpn']))
			$syslogconf .= system_syslogd_get_remote_servers($syslogcfg, "*.*");

		$syslogconf .= "!openvpn\n";
		if (!isset($syslogcfg['disablelocallogging']))
			$syslogconf .= "*.*								{$log_directive}/var/log/openvpn.log\n";
		if (isset($syslogcfg['vpn']))
			$syslogconf .= system_syslogd_get_remote_servers($syslogcfg, "*.*");

		$syslogconf .= "!apinger\n";
		if (!isset($syslogcfg['disablelocallogging']))
			$syslogconf .= "*.*								{$log_directive}/var/log/gateways.log\n";
		if (isset($syslogcfg['apinger']))
			$syslogconf .= system_syslogd_get_remote_servers($syslogcfg, "*.*");

		$syslogconf .= "!dnsmasq,filterdns,unbound\n";
		if (!isset($syslogcfg['disablelocallogging']))
			$syslogconf .= "*.*								{$log_directive}/var/log/resolver.log\n";

		$syslogconf .= "!dhcpd,dhcrelay,dhclient,dhcp6c\n";
		if (!isset($syslogcfg['disablelocallogging']))
			$syslogconf .= "*.*								{$log_directive}/var/log/dhcpd.log\n";
		if (isset($syslogcfg['dhcp']))
			$syslogconf .= system_syslogd_get_remote_servers($syslogcfg, "*.*");

		$syslogconf .= "!relayd\n";
		if (!isset($syslogcfg['disablelocallogging']))
			$syslogconf .= "*.*								{$log_directive}/var/log/relayd.log\n";
		if (isset($syslogcfg['relayd']))
			$syslogconf .= system_syslogd_get_remote_servers($syslogcfg, "*.*");

		$syslogconf .= "!hostapd\n";
		if (!isset($syslogcfg['disablelocallogging']))
			$syslogconf .= "*.*								{$log_directive}/var/log/wireless.log\n";
		if (isset($syslogcfg['hostapd']))
			$syslogconf .= system_syslogd_get_remote_servers($syslogcfg, "*.*");

		$syslogconf .= "!filterlog\n";
		$syslogconf .= "*.*								{$log_directive}/var/log/filter.log\n";
		if (isset($syslogcfg['filter']))
			$syslogconf .= system_syslogd_get_remote_servers($syslogcfg, "*.*");

		$syslogconf .= "!-{$facilitylist}\n";
		if (!isset($syslogcfg['disablelocallogging']))
			$syslogconf .= <<<EOD
local3.*							{$log_directive}/var/log/vpn.log
local4.*							{$log_directive}/var/log/portalauth.log
local7.*							{$log_directive}/var/log/dhcpd.log
*.notice;kern.debug;lpr.info;mail.crit;daemon.none;		{$log_directive}/var/log/system.log
news.err;local0.none;local3.none;local4.none;			{$log_directive}/var/log/system.log
local7.none							{$log_directive}/var/log/system.log
security.*							{$log_directive}/var/log/system.log
auth.info;authpriv.info;daemon.info				{$log_directive}/var/log/system.log
auth.info;authpriv.info						|exec /usr/local/sbin/sshlockout_pf 15
*.emerg								*

EOD;
		if (isset($syslogcfg['vpn']))
			$syslogconf .= system_syslogd_get_remote_servers($syslogcfg, "local3.*");
		if (isset($syslogcfg['portalauth']))
			$syslogconf .= system_syslogd_get_remote_servers($syslogcfg, "local4.*");
		if (isset($syslogcfg['dhcp']))
			$syslogconf .= system_syslogd_get_remote_servers($syslogcfg, "local7.*");
		if (isset($syslogcfg['system'])) {
			$syslogconf .= system_syslogd_get_remote_servers($syslogcfg, "*.notice;kern.debug;lpr.info;mail.crit;");
			$syslogconf .= system_syslogd_get_remote_servers($syslogcfg, "news.err;local0.none;local3.none;local7.none");
			$syslogconf .= system_syslogd_get_remote_servers($syslogcfg, "security.*");
			$syslogconf .= system_syslogd_get_remote_servers($syslogcfg, "auth.info;authpriv.info;daemon.info");
			$syslogconf .= system_syslogd_get_remote_servers($syslogcfg, "*.emerg");
		}
		if (isset($syslogcfg['logall'])) {
			// Make everything mean everything, including facilities excluded above.
			$syslogconf .= "!*\n";
			$syslogconf .= system_syslogd_get_remote_servers($syslogcfg, "*.*");
		}

		/* write syslog.conf */
		if (!@file_put_contents("/var/etc/syslog.conf", $syslogconf)) {
			printf(gettext("Error: cannot open syslog.conf in system_syslogd_start().%s"), "\n");
			unset($syslogconf);
			return 1;
		}
		unset($syslogconf);

		// Ensure that the log directory exists
		if (!is_dir("{$g['dhcpd_chroot_path']}/var/run"))
			exec("/bin/mkdir -p {$g['dhcpd_chroot_path']}/var/run");

		$sourceip = "";
		if (!empty($syslogcfg['sourceip'])) {
			if ($syslogcfg['ipproto'] == "ipv6") {
				$ifaddr = is_ipaddr($syslogcfg['sourceip']) ? $syslogcfg['sourceip'] : get_interface_ipv6($syslogcfg['sourceip']);
				if (!is_ipaddr($ifaddr))
					$ifaddr = get_interface_ip($syslogcfg['sourceip']);
			} else {
				$ifaddr = is_ipaddr($syslogcfg['sourceip']) ? $syslogcfg['sourceip'] : get_interface_ip($syslogcfg['sourceip']);
				if (!is_ipaddr($ifaddr))
					$ifaddr = get_interface_ipv6($syslogcfg['sourceip']);
			}
			if (is_ipaddr($ifaddr)) {
				$sourceip = "-b {$ifaddr}";
			}
		}

		$syslogd_extra = "-f /var/etc/syslog.conf {$sourceip}";
	}

	if (isvalidpid('/var/run/syslog.pid')) {
		killbypid('/var/run/syslog.pid', 'HUP');
	} else {
		$retval = mwexec_bg("/usr/local/sbin/syslogd -s -c -c -l {$g['dhcpd_chroot_path']}/var/run/log -P /var/run/syslog.pid {$syslogd_extra}");
	}

	if (file_exists("/var/run/booting")) {
		echo gettext("done.") . "\n";
	}

	return $retval;
}

function system_webgui_start()
{
	global $config;

	chdir('/usr/local/www');

	/* defaults */
	$portarg = "80";
	$crt = "";
	$key = "";
	$ca = "";

	/* non-standard port? */
	if (isset($config['system']['webgui']['port']) && $config['system']['webgui']['port'] <> "")
		$portarg = "{$config['system']['webgui']['port']}";

	if ($config['system']['webgui']['protocol'] == "https") {
		// Ensure that we have a webConfigurator CERT
		$cert =& lookup_cert($config['system']['webgui']['ssl-certref']);
		if(!is_array($cert) && !$cert['crt'] && !$cert['prv']) {
			if (!is_array($config['ca']))
				$config['ca'] = array();
			$a_ca =& $config['ca'];
			if (!is_array($config['cert']))
				$config['cert'] = array();
			$a_cert =& $config['cert'];
			log_error("Creating SSL Certificate for this host");
			$cert = array();
			$cert['refid'] = uniqid();
			$cert['descr'] = gettext("webConfigurator default");
			mwexec(
				/* XXX ought to be replaced by PHP calls */
				'/usr/local/bin/openssl req -new ' .
				'-newkey rsa:4096 -sha256 -days 365 -nodes -x509 ' .
				'-subj "/C=NL/ST=Zuid-Holland/L=Middelharnis/O=OPNsense" ' .
				'-keyout /tmp/ssl.key -out /tmp/ssl.crt'
			);
			$crt = file_get_contents('/tmp/ssl.crt');
			$key = file_get_contents('/tmp/ssl.key');
			unlink('/tmp/ssl.key');
			unlink('/tmp/ssl.crt');
			cert_import($cert, $crt, $key);
			$a_cert[] = $cert;
			$config['system']['webgui']['ssl-certref'] = $cert['refid'];
			write_config(gettext("Importing HTTPS certificate"));
			if(!$config['system']['webgui']['port'])
				$portarg = "443";
			$ca = ca_chain($cert);
		} else {
			$crt = base64_decode($cert['crt']);
			$key = base64_decode($cert['prv']);
			if(!$config['system']['webgui']['port'])
				$portarg = "443";
			$ca = ca_chain($cert);
		}
	}

	/* generate lighttpd configuration */
	system_generate_lighty_config("/var/etc/lighty-webConfigurator.conf",
		$crt, $key, $ca, "lighty-webConfigurator.pid", $portarg, "/usr/local/www/",
		"cert.pem", "ca.pem");

	/* kill any running lighttpd */
	killbypid('/var/run/lighty-webConfigurator.pid');

	sleep(1);

	/* regenerate the php.ini files in case the setup has changed */
	mwexec('/usr/local/etc/rc.php_ini_setup');

	/* attempt to start lighthttpd and return true if ok */
	return !mwexec("/usr/local/sbin/lighttpd -f /var/etc/lighty-webConfigurator.conf");
}

/*
 *     get_memory()
 *     returns an array listing the amount of
 *     memory installed in the hardware
 *     [0] net memory available for the OS (FreeBSD) after some is taken by BIOS, video or whatever - e.g. 235 MBytes
 *     [1] real (actual) memory of the system, should be the size of the RAM card/s - e.g. 256 MBytes
 */
function get_memory() {
	$physmem = get_single_sysctl("hw.physmem");
	$realmem = get_single_sysctl("hw.realmem");
	/* convert from bytes to megabytes */
	return array(($physmem/1048576),($realmem/1048576));
}


function system_generate_lighty_config(
	$filename,
	$cert,
	$key,
	$ca,
	$pid_file,
	$port = 80,
	$document_root = '/usr/local/www/',
	$cert_location = 'cert.pem',
	$ca_location = 'ca.pem',
	$captive_portal = false)
{
	global $config;

	@mkdir('/tmp/lighttpdcompress');

	if ($captive_portal !== false)  {
		$captiveportal = ',"mod_evasive"';
		$http_rewrite_rules = "url.rewrite-once = ( \"(.*captiveportal.*)\" => \"$1\", \"(.*)\" => \"/index.php?zone={$captive_portal}&redirurl=$1\" )\n";

		if (!isset($config['captiveportal'][$captive_portal]['maxprocperip']) || empty($config['captiveportal'][$captive_portal]['maxprocperip'])) {
			$maxprocperip = 10;
		} else {
			$maxprocperip = $config['captiveportal'][$captive_portal]['maxprocperip'];
		}

		$captive_portal_mod_evasive = "evasive.max-conns-per-ip = {$maxprocperip}";

		$server_upload_dirs = "server.upload-dirs = ( \"/tmp/captiveportal/\" )\n";
		@mkdir('/tmp/captiveportal', 0555);
		$server_max_request_size = "server.max-request-size    = 384";
		$cgi_config = "";
	} else {
		$captiveportal = ",\"mod_cgi\"";
		$http_rewrite_rules = <<<EOD
# Phalcon ui and api routing
alias.url += ( "/ui/" => "/usr/local/opnsense/www/" )
alias.url += ( "/api/"  => "/usr/local/opnsense/www/" )
url.rewrite-if-not-file = ( "^/ui/(.*)$" => "/ui/index.php?_url=/$1" ,
                            "^/api/(.*)$" => "/api/api.php?_url=/$1"
)

EOD;
		$captive_portal_mod_evasive = "";
		$server_upload_dirs = "server.upload-dirs = ( \"/root/\", \"/tmp/\", \"/var/\" )\n";
		$server_max_request_size = "server.max-request-size    = 2097152";
		$cgi_config = "cgi.assign                 = ( \".cgi\" => \"\" )";
	}

	if (empty($port))
		$lighty_port = "80";
	else
		$lighty_port = $port;

	$memory = get_memory();
	$realmem = $memory[1];

	// Determine web GUI process settings and take into account low memory systems
	if ($realmem < 255)
		$max_procs = 1;
	else
		$max_procs = ($config['system']['webgui']['max_procs']) ? $config['system']['webgui']['max_procs'] : 2;

	// Ramp up captive portal max procs, assuming each PHP process can consume up to 64MB RAM
	if ($captive_portal !== false)  {
		if ($realmem > 135 and $realmem < 256) {
			$max_procs += 1; // 2 worker processes
		} else if ($realmem > 255 and $realmem < 513) {
			$max_procs += 2; // 3 worker processes
		} else if ($realmem > 512) {
			$max_procs += 4; // 6 worker processes
		}
		if ($max_procs > 1)
			$max_php_children = intval($max_procs/2);
		else
			$max_php_children = 1;

	} else {
		if ($realmem < 78)
			$max_php_children = 0;
		else
			$max_php_children = 1;
	}

	if(!isset($config['syslog']['nologlighttpd'])) {
		$lighty_use_syslog = <<<EOD
## where to send error-messages to
server.errorlog-use-syslog="enable"
EOD;
	}

        if ($captive_portal !== false) {
                $fast_cgi_path = "/tmp/php-fastcgi-{$captive_portal}.socket";
        } else {
                $fast_cgi_path = "/tmp/php-fastcgi.socket";
	}

	$fastcgi_config = <<<EOD
#### fastcgi module
## read fastcgi.txt for more info
fastcgi.server = ( ".php" =>
	( "localhost" =>
		(
			"socket" => "{$fast_cgi_path}",
			"max-procs" => {$max_procs},
			"bin-environment" => (
				"PHP_FCGI_CHILDREN" => "{$max_php_children}",
				"PHP_FCGI_MAX_REQUESTS" => "500"
			),
			"bin-path" => "/usr/local/bin/php-cgi"
		)
	)
)

EOD;

	$lighty_config = <<<EOD
#
# lighttpd configuration file
#
# use a it as base for lighttpd 1.0.0 and above
#
############ Options you really have to take care of ####################

## FreeBSD!
server.event-handler	= "freebsd-kqueue"
server.network-backend	= "writev"
#server.use-ipv6 = "enable"

## modules to load
server.modules              =   ( "mod_access", "mod_expire", "mod_compress", "mod_redirect",
	{$captiveportal}, "mod_fastcgi","mod_alias", "mod_rewrite"
)

server.max-keep-alive-requests = 15
server.max-keep-alive-idle = 30

## a static document-root, for virtual-hosting take look at the
## server.virtual-* options
server.document-root        = "{$document_root}"


{$http_rewrite_rules}

# Maximum idle time with nothing being written (php downloading)
server.max-write-idle = 999

{$lighty_use_syslog}

# files to check for if .../ is requested
server.indexfiles           = ( "index.php", "index.html",
                                "index.htm", "default.htm" )

# mimetype mapping
mimetype.assign             = (
  ".pdf"          =>      "application/pdf",
  ".sig"          =>      "application/pgp-signature",
  ".spl"          =>      "application/futuresplash",
  ".class"        =>      "application/octet-stream",
  ".ps"           =>      "application/postscript",
  ".torrent"      =>      "application/x-bittorrent",
  ".dvi"          =>      "application/x-dvi",
  ".gz"           =>      "application/x-gzip",
  ".pac"          =>      "application/x-ns-proxy-autoconfig",
  ".swf"          =>      "application/x-shockwave-flash",
  ".tar.gz"       =>      "application/x-tgz",
  ".tgz"          =>      "application/x-tgz",
  ".tar"          =>      "application/x-tar",
  ".zip"          =>      "application/zip",
  ".mp3"          =>      "audio/mpeg",
  ".m3u"          =>      "audio/x-mpegurl",
  ".wma"          =>      "audio/x-ms-wma",
  ".wax"          =>      "audio/x-ms-wax",
  ".ogg"          =>      "audio/x-wav",
  ".wav"          =>      "audio/x-wav",
  ".gif"          =>      "image/gif",
  ".jpg"          =>      "image/jpeg",
  ".jpeg"         =>      "image/jpeg",
  ".png"          =>      "image/png",
  ".svg"          =>      "image/svg+xml",
  ".xbm"          =>      "image/x-xbitmap",
  ".xpm"          =>      "image/x-xpixmap",
  ".xwd"          =>      "image/x-xwindowdump",
  ".css"          =>      "text/css",
  ".html"         =>      "text/html",
  ".htm"          =>      "text/html",
  ".js"           =>      "text/javascript",
  ".asc"          =>      "text/plain",
  ".c"            =>      "text/plain",
  ".conf"         =>      "text/plain",
  ".text"         =>      "text/plain",
  ".txt"          =>      "text/plain",
  ".dtd"          =>      "text/xml",
  ".xml"          =>      "text/xml",
  ".mpeg"         =>      "video/mpeg",
  ".mpg"          =>      "video/mpeg",
  ".mov"          =>      "video/quicktime",
  ".qt"           =>      "video/quicktime",
  ".avi"          =>      "video/x-msvideo",
  ".asf"          =>      "video/x-ms-asf",
  ".asx"          =>      "video/x-ms-asf",
  ".wmv"          =>      "video/x-ms-wmv",
  ".bz2"          =>      "application/x-bzip",
  ".tbz"          =>      "application/x-bzip-compressed-tar",
  ".tar.bz2"      =>      "application/x-bzip-compressed-tar"
 )

# Use the "Content-Type" extended attribute to obtain mime type if possible
#mimetypes.use-xattr        = "enable"

## deny access the file-extensions
#
# ~    is for backupfiles from vi, emacs, joe, ...
# .inc is often used for code includes which should in general not be part
#      of the document-root
url.access-deny             = ( "~", ".inc" )


######### Options that are good to be but not neccesary to be changed #######

## bind to port (default: 80)

EOD;

	$lighty_config .= "server.bind  = \"0.0.0.0\"\n";
	$lighty_config .= "server.port  = {$lighty_port}\n";
	$lighty_config .= "\$SERVER[\"socket\"]  == \"0.0.0.0:{$lighty_port}\" { }\n";
	$lighty_config .= "\$SERVER[\"socket\"]  == \"[::]:{$lighty_port}\" { \n";
	if($cert <> "" and $key <> "") {
		$lighty_config .= "\n";
		$lighty_config .= "## ssl configuration\n";
		$lighty_config .= "ssl.engine = \"enable\"\n";
		$lighty_config .= "ssl.pemfile = \"/var/etc/{$cert_location}\"\n\n";
		if($ca <> "")
			$lighty_config .= "ssl.ca-file = \"/var/etc/{$ca_location}\"\n\n";
	}
	$lighty_config .= " }\n";


	$lighty_config .= <<<EOD

## error-handler for status 404
#server.error-handler-404   = "/error-handler.html"
#server.error-handler-404   = "/error-handler.php"

## to help the rc.scripts
server.pid-file            = "/var/run/{$pid_file}"

## virtual directory listings
server.dir-listing         = "disable"

## enable debugging
debug.log-request-header   = "disable"
debug.log-response-header  = "disable"
debug.log-request-handling = "disable"
debug.log-file-not-found   = "disable"

# gzip compression
compress.cache-dir = "/tmp/lighttpdcompress/"
compress.filetype  = ("text/plain","text/css", "text/xml", "text/javascript" )

{$server_upload_dirs}

{$server_max_request_size}

{$fastcgi_config}

{$cgi_config}

{$captive_portal_mod_evasive}

expire.url = (
				"" => "access 50 hours",
        )

EOD;

	$cert = str_replace("\r", "", $cert);
	$key = str_replace("\r", "", $key);
	$ca = str_replace("\r", "", $ca);

	$cert = str_replace("\n\n", "\n", $cert);
	$key = str_replace("\n\n", "\n", $key);
	$ca = str_replace("\n\n", "\n", $ca);

	if($cert <> "" and $key <> "") {
		$fd = fopen("/var/etc/{$cert_location}", "w");
		if (!$fd) {
			printf(gettext("Error: cannot open cert.pem in system_webgui_start().%s"), "\n");
			return 1;
		}
		chmod("/var/etc/{$cert_location}", 0600);
		fwrite($fd, $cert);
		fwrite($fd, "\n");
		fwrite($fd, $key);
		fclose($fd);
		if(!(empty($ca) || (strlen(trim($ca)) == 0))) {
			$fd = fopen("/var/etc/{$ca_location}", "w");
			if (!$fd) {
				printf(gettext("Error: cannot open ca.pem in system_webgui_start().%s"), "\n");
				return 1;
			}
			chmod("/var/etc/{$ca_location}", 0600);
			fwrite($fd, $ca);
			fclose($fd);
		}
		$lighty_config .= "\n";
		$lighty_config .= "## " . gettext("ssl configuration") . "\n";
		$lighty_config .= "ssl.engine = \"enable\"\n";
		$lighty_config .= "ssl.pemfile = \"/var/etc/{$cert_location}\"\n\n";

		// Harden SSL a bit for PCI conformance testing
		$lighty_config .= "ssl.use-sslv2 = \"disable\"\n";

		$lighty_config .= 'ssl.cipher-list = "ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES256-GCM-SHA384:ECDHE-ECDSA-AES256-GCM-SHA384:DHE-RSA-AES128-GCM-SHA256:DHE-DSS-AES128-GCM-SHA256:kEDH+AESGCM:ECDHE-RSA-AES128-SHA256:ECDHE-ECDSA-AES128-SHA256:ECDHE-RSA-AES128-SHA:ECDHE-ECDSA-AES128-SHA:ECDHE-RSA-AES256-SHA384:ECDHE-ECDSA-AES256-SHA384:ECDHE-RSA-AES256-SHA:ECDHE-ECDSA-AES256-SHA:DHE-RSA-AES128-SHA256:DHE-RSA-AES128-SHA:DHE-DSS-AES128-SHA256:DHE-RSA-AES256-SHA256:DHE-DSS-AES256-SHA:DHE-RSA-AES256-SHA:AES128-GCM-SHA256:AES256-GCM-SHA384:AES128-SHA256:AES256-SHA256:AES128-SHA:AES256-SHA:AES:CAMELLIA:DES-CBC3-SHA:!aNULL:!eNULL:!EXPORT:!DES:!RC4:!MD5:!PSK:!aECDH:!EDH-DSS-DES-CBC3-SHA:!EDH-RSA-DES-CBC3-SHA:!KRB5-DES-CBC3-SHA"' . PHP_EOL;

		if(!(empty($ca) || (strlen(trim($ca)) == 0)))
			$lighty_config .= "ssl.ca-file = \"/var/etc/{$ca_location}\"\n\n";
	}

	// Add HTTP to HTTPS redirect
	if ($captive_portal === false && $config['system']['webgui']['protocol'] == "https" && !isset($config['system']['webgui']['disablehttpredirect'])) {
		if($lighty_port != "443") {
			$redirectport = ":{$lighty_port}";
		} else {
			$redirectport = "";
		}
		$lighty_config .= <<<EOD
\$SERVER["socket"] == ":80" {
	\$HTTP["host"] =~ "(.*)" {
		url.redirect = ( "^/(.*)" => "https://%1{$redirectport}/$1" )
	}
}
\$SERVER["socket"] == "[::]:80" {
	\$HTTP["host"] =~ "(.*)" {
		url.redirect = ( "^/(.*)" => "https://%1{$redirectport}/$1" )
	}
}
EOD;
	}

	$fd = fopen("{$filename}", "w");
	if (!$fd) {
		printf(gettext("Error: cannot open %s in system_generate_lighty_config().%s"), $filename, "\n");
		return 1;
	}
	fwrite($fd, $lighty_config);
	fclose($fd);

	return 0;

}

function system_firmware_configure()
{
	global $config;

	/* rewrite the config via the defaults */
	$origin_conf = '/usr/local/etc/pkg/repos/origin.conf';
	copy("${origin_conf}.sample", $origin_conf);

	if (isset($config['system']['firmware']['mirror'])) {
		/* XXX create a proper backend call */
		mwexecf(
			'/usr/local/sbin/opnsense-update -sm %s',
			str_replace('/', '\/', $config['system']['firmware']['mirror'])
		);
	}

	if (isset($config['system']['firmware']['flavour'])) {
		/* XXX create a proper backend call */
		mwexecf(
			'/usr/local/sbin/opnsense-update -sn %s',
			str_replace('/', '\/', $config['system']['firmware']['flavour'])
		);
	}
}

function system_timezone_configure()
{
	global $config;

	$syscfg = $config['system'];

	if (file_exists("/var/run/booting"))
		echo gettext("Setting timezone...");

	/* extract appropriate timezone file */
	$timezone = $syscfg['timezone'];
	$timezones = get_zoneinfo();

	// Reset to default if empty or not existend
	if (empty($timezone) || !in_array($timezone, $timezones)) {
		$timezone = 'Etc/UTC';
	}

	// Apply timezone
	copy(sprintf('/usr/share/zoneinfo/%s', $timezone), '/etc/localtime');

	mwexec("sync");

	if (file_exists("/var/run/booting"))
		echo gettext("done.") . "\n";
}

function system_ntp_setup_gps($serialport)
{
	global $config;
	$gps_device = '/dev/gps0';
	$serialport = '/dev/'.$serialport;

	if (!file_exists($serialport))
		return false;

	// Create symlink that ntpd requires
	@unlink($gps_device);
	symlink($serialport, $gps_device);

	/* Send the following to the GPS port to initialize the GPS */
	if (is_array($config['ntpd']) && is_array($config['ntpd']['gps']) && !empty($config['ntpd']['gps']['type'])) {
		$gps_init = base64_decode($config['ntpd']['gps']['initcmd']);
	}else{
		$gps_init = base64_decode('JFBVQlgsNDAsR1NWLDAsMCwwLDAqNTkNCiRQVUJYLDQwLEdMTCwwLDAsMCwwKjVDDQokUFVCWCw0MCxaREEsMCwwLDAsMCo0NA0KJFBVQlgsNDAsVlRHLDAsMCwwLDAqNUUNCiRQVUJYLDQwLEdTViwwLDAsMCwwKjU5DQokUFVCWCw0MCxHU0EsMCwwLDAsMCo0RQ0KJFBVQlgsNDAsR0dBLDAsMCwwLDANCiRQVUJYLDQwLFRYVCwwLDAsMCwwDQokUFVCWCw0MCxSTUMsMCwwLDAsMCo0Ng0KJFBVQlgsNDEsMSwwMDA3LDAwMDMsNDgwMCwwDQokUFVCWCw0MCxaREEsMSwxLDEsMQ==');
	}

	/* XXX: Why not file_put_contents to the device */
	@file_put_contents('/tmp/gps.init', $gps_init);
	`cat /tmp/gps.init > $serialport`;

	/* Add /etc/remote entry in case we need to read from the GPS with tip */
	if (intval(`grep -c '^gps0' /etc/remote`) == 0) {
		$gpsbaud = '4800';
		if (is_array($config['ntpd']) && is_array($config['ntpd']['gps']) && !empty($config['ntpd']['gps']['speed'])) {
			switch($config['ntpd']['gps']['speed']) {
				case '16':
					$gpsbaud = '9600';
					break;
				case '32':
					$gpsbaud = '19200';
					break;
				case '48':
					$gpsbaud = '38400';
					break;
				case '64':
					$gpsbaud = '57600';
					break;
				case '80':
					$gpsbaud = '115200';
					break;
			}
		}
		@file_put_contents("/etc/remote", "gps0:dv={$serialport}:br#{$gpsbaud}:pa=none:", FILE_APPEND);
	}

	return true;
}

function system_ntp_setup_pps($serialport)
{
	$pps_device = '/dev/pps0';
	$serialport = "/dev/{$serialport}";

	if (!file_exists($serialport)) {
		return false;
	}

	// Create symlink that ntpd requires
	@unlink($pps_device);
	@symlink($serialport, $pps_device);

	return true;
}

function system_ntp_configure($start_ntpd = true)
{
	global $config;

	$driftfile = '/var/db/ntpd.drift';
	$statsdir = '/var/log/ntp';
	$gps_device = '/dev/gps0';

	@mkdir($statsdir, 0755);

	if (!isset($config['ntpd']) || !is_array($config['ntpd'])) {
		$config['ntpd'] = array();
	}

	$ntpcfg = "# \n";
	$ntpcfg .= "# OPNsense ntp configuration file \n";
	$ntpcfg .= "# \n\n";
	$ntpcfg .= "tinker panic 0 \n";

	/* Add Orphan mode */
	$ntpcfg .= "# Orphan mode stratum\n";
	$ntpcfg .= 'tos orphan ';
	if (!empty($config['ntpd']['orphan'])) {
		$ntpcfg .= $config['ntpd']['orphan'];
	}else{
		$ntpcfg .= '12';
	}
	$ntpcfg .= "\n";

	/* Add PPS configuration */
	if (!empty($config['ntpd']['pps'])
		&& file_exists('/dev/'.$config['ntpd']['pps']['port'])
		&& system_ntp_setup_pps($config['ntpd']['pps']['port'])) {
		$ntpcfg .= "\n";
		$ntpcfg .= "# PPS Setup\n";
		$ntpcfg .= 'server 127.127.22.0';
		$ntpcfg .= ' minpoll 4 maxpoll 4';
		if (empty($config['ntpd']['pps']['prefer'])) { /*note: this one works backwards */
			$ntpcfg .= ' prefer';
		}
		if (!empty($config['ntpd']['pps']['noselect'])) {
			$ntpcfg .= ' noselect ';
		}
		$ntpcfg .= "\n";
		$ntpcfg .= 'fudge 127.127.22.0';
		if (!empty($config['ntpd']['pps']['fudge1'])) {
			$ntpcfg .= ' time1 ';
			$ntpcfg .= $config['ntpd']['pps']['fudge1'];
		}
		if (!empty($config['ntpd']['pps']['flag2'])) {
			$ntpcfg .= ' flag2 1';
		}
		if (!empty($config['ntpd']['pps']['flag3'])) {
			$ntpcfg .= ' flag3 1';
		}else{
			$ntpcfg .= ' flag3 0';
		}
		if (!empty($config['ntpd']['pps']['flag4'])) {
			$ntpcfg .= ' flag4 1';
		}
		if (!empty($config['ntpd']['pps']['refid'])) {
			$ntpcfg .= ' refid ';
			$ntpcfg .= $config['ntpd']['pps']['refid'];
		}
		$ntpcfg .= "\n";
	}
	/* End PPS configuration */

	/* Add GPS configuration */
        if (isset($config['ntpd']['gps']['port'])
		&& file_exists('/dev/'.$config['ntpd']['gps']['port'])
		&& system_ntp_setup_gps($config['ntpd']['gps']['port'])) {
		$ntpcfg .= "\n";
		$ntpcfg .= "# GPS Setup\n";
		$ntpcfg .= 'server 127.127.20.0 mode ';
		if (!empty($config['ntpd']['gps']['nmea']) || !empty($config['ntpd']['gps']['speed']) || !empty($config['ntpd']['gps']['subsec'])) {
			if (!empty($config['ntpd']['gps']['nmea'])) {
				$ntpmode = (int) $config['ntpd']['gps']['nmea'];
			}
			if (!empty($config['ntpd']['gps']['speed'])) {
				$ntpmode += (int) $config['ntpd']['gps']['speed'];
			}
			if (!empty($config['ntpd']['gps']['subsec'])) {
				$ntpmode += 128;
			}
			$ntpcfg .= (string) $ntpmode;
		}else{
			$ntpcfg .= '0';
		}
		$ntpcfg .= ' minpoll 4 maxpoll 4';
		if (empty($config['ntpd']['gps']['prefer'])) { /*note: this one works backwards */
			$ntpcfg .= ' prefer';
		}
		if (!empty($config['ntpd']['gps']['noselect'])) {
			$ntpcfg .= ' noselect ';
		}
		$ntpcfg .= "\n";
		$ntpcfg .= 'fudge 127.127.20.0';
		if (!empty($config['ntpd']['gps']['fudge1'])) {
			$ntpcfg .= ' time1 ';
			$ntpcfg .= $config['ntpd']['gps']['fudge1'];
		}
		if (!empty($config['ntpd']['gps']['fudge2'])) {
			$ntpcfg .= ' time2 ';
			$ntpcfg .= $config['ntpd']['gps']['fudge2'];
		}
		if (!empty($config['ntpd']['gps']['flag1'])) {
			$ntpcfg .= ' flag1 1';
		}else{
			$ntpcfg .= ' flag1 0';
		}
		if (!empty($config['ntpd']['gps']['flag2'])) {
			$ntpcfg .= ' flag2 1';
		}
		if (!empty($config['ntpd']['gps']['flag3'])) {
			$ntpcfg .= ' flag3 1';
		}else{
			$ntpcfg .= ' flag3 0';
		}
		if (!empty($config['ntpd']['gps']['flag4'])) {
			$ntpcfg .= ' flag4 1';
		}
		if (!empty($config['ntpd']['gps']['refid'])) {
			$ntpcfg .= ' refid ';
			$ntpcfg .= $config['ntpd']['gps']['refid'];
		}
		$ntpcfg .= "\n";
	}elseif (!empty($config['ntpd']['gpsport'])
		&& file_exists('/dev/'.$config['ntpd']['gpsport'])
		&& system_ntp_setup_gps($config['ntpd']['gpsport'])) {
		/* This handles a 2.1 and earlier config */
		$ntpcfg .= "# GPS Setup\n";
		$ntpcfg .= "server 127.127.20.0 mode 0 minpoll 4 maxpoll 4 prefer\n";
		$ntpcfg .= "fudge 127.127.20.0 time1 0.155 time2 0.000 flag1 1 flag2 0 flag3 1\n";
		// Fall back to local clock if GPS is out of sync?
		$ntpcfg .= "server 127.127.1.0\n";
		$ntpcfg .= "fudge 127.127.1.0 stratum 12\n";
	}
	/* End GPS configuration */

	$ntpcfg .= "\n\n# Upstream Servers\n";
	/* foreach through ntp servers and write out to ntpd.conf */
	foreach (explode(' ', $config['system']['timeservers']) as $ts) {
		$ntpcfg .= "server {$ts} iburst maxpoll 9";
		if (isset($config['ntpd']['prefer']) && substr_count($config['ntpd']['prefer'], $ts)) $ntpcfg .= ' prefer';
		if (isset($config['ntpd']['noselect']) && substr_count($config['ntpd']['noselect'], $ts)) $ntpcfg .= ' noselect';
		$ntpcfg .= "\n";
	}
	unset($ts);

	$ntpcfg .= "\n\n";
	$ntpcfg .= "disable monitor\n"; //prevent NTP reflection attack, see https://ics-cert.us-cert.gov/advisories/ICSA-14-051-04
	if (!empty($config['ntpd']['clockstats']) || !empty($config['ntpd']['loopstats']) || !empty($config['ntpd']['peerstats'])) {
		$ntpcfg .= "enable stats\n";
		$ntpcfg .= 'statistics';
		if (!empty($config['ntpd']['clockstats'])) {
			$ntpcfg .= ' clockstats';
		}
		if (!empty($config['ntpd']['loopstats'])) {
			$ntpcfg .= ' loopstats';
		}
		if (!empty($config['ntpd']['peerstats'])) {
			$ntpcfg .= ' peerstats';
		}
		$ntpcfg .= "\n";
	}
	$ntpcfg .= "statsdir {$statsdir}\n";
	$ntpcfg .= 'logconfig =syncall +clockall';
	if (!empty($config['ntpd']['logpeer'])) {
		$ntpcfg .= ' +peerall';
	}
	if (!empty($config['ntpd']['logsys'])) {
		$ntpcfg .= ' +sysall';
	}
	$ntpcfg .= "\n";
	$ntpcfg .= "driftfile {$driftfile}\n";
	/* Access restrictions */
	$ntpcfg .= 'restrict default';
	if (empty($config['ntpd']['kod'])) { /*note: this one works backwards */
		$ntpcfg .= ' kod limited';
	}
	if (empty($config['ntpd']['nomodify'])) { /*note: this one works backwards */
		$ntpcfg .= ' nomodify';
	}
	if (!empty($config['ntpd']['noquery'])) {
		$ntpcfg .= ' noquery';
	}
	if (empty($config['ntpd']['nopeer'])) { /*note: this one works backwards */
		$ntpcfg .= ' nopeer';
	}
	if (empty($config['ntpd']['notrap'])) { /*note: this one works backwards */
		$ntpcfg .= ' notrap';
	}
	if (!empty($config['ntpd']['noserve'])) {
		$ntpcfg .= ' noserve';
	}
	$ntpcfg .= "\nrestrict -6 default";
	if (empty($config['ntpd']['kod'])) { /*note: this one works backwards */
		$ntpcfg .= ' kod limited';
	}
	if (empty($config['ntpd']['nomodify'])) { /*note: this one works backwards */
		$ntpcfg .= ' nomodify';
	}
	if (!empty($config['ntpd']['noquery'])) {
		$ntpcfg .= ' noquery';
	}
	if (empty($config['ntpd']['nopeer'])) { /*note: this one works backwards */
		$ntpcfg .= ' nopeer';
	}
	if (!empty($config['ntpd']['noserve'])) {
		$ntpcfg .= ' noserve';
	}
	if (empty($config['ntpd']['notrap'])) { /*note: this one works backwards */
		$ntpcfg .= ' notrap';
	}
	$ntpcfg .= "\n";

	/* A leapseconds file is really only useful if this clock is stratum 1 */
	$ntpcfg .= "\n";
	if (!empty($config['ntpd']['leapsec'])) {
		$leapsec .= base64_decode($config['ntpd']['leapsec']);
		file_put_contents('/var/db/leap-seconds', $leapsec);
		$ntpcfg .= "leapfile /var/db/leap-seconds\n";
	}


	$interfaces = array();
	if (isset($config['ntpd']['interface'])) {
		$interfaces = explode(',', $config['ntpd']['interface']);
	}

	if (is_array($interfaces) && count($interfaces)) {
		$ntpcfg .= "interface ignore all\n";
		foreach ($interfaces as $interface) {
			if (!is_ipaddr($interface)) {
				$interface = get_real_interface($interface);
			}
			if (!empty($interface))
				$ntpcfg .= "interface listen {$interface}\n";
		}
	}

	/* open configuration for wrting or bail */
	if (!@file_put_contents('/var/etc/ntpd.conf', $ntpcfg)) {
		log_error("Could not open /var/etc/ntpd.conf for writing");
		return;
	}

	if (!$start_ntpd) {
		/* write out the config and delay startup */
		mwexec_bg('/usr/local/sbin/ntpdate_sync_once.sh');
		return;
	}

	/* if ntpd is running, kill it */
	while (isvalidpid('/var/run/ntpd.pid')) {
		killbypid('/var/run/ntpd.pid');
		usleep(200 * 1000);
	}

	/* if /var/empty does not exist, create it */
	@mkdir('/var/empty', 0775, true);

	/* start opentpd, set time now and use new config */
	mwexecf(
		'/usr/local/sbin/ntpd -g -c %s -p %s',
		array('/var/etc/ntpd.conf', '/var/run/ntpd.pid')
	);

	// Note that we are starting up
	log_error("NTPD is starting up.");
}

function system_halt($sync = false)
{
	$cmd ='/usr/local/etc/rc.halt';

	if (!$sync) {
		mwexec_bg($cmd);
	} else {
		mwexec($cmd);
	}
}

function system_reboot($sync = false)
{
	$cmd ='/usr/local/etc/rc.reboot';

	if (!$sync) {
		mwexec_bg($cmd);
	} else {
		mwexec($cmd);
	}
}

function system_console_configure()
{
	setup_serial_port();
}

function system_set_harddisk_standby()
{
	global $config;

	if (isset($config['system']['harddiskstandby'])) {
		if (file_exists("/var/run/booting")) {
			echo gettext('Setting hard disk standby... ');
		}

		$standby = $config['system']['harddiskstandby'];
		// Check for a numeric value
		if (is_numeric($standby)) {
			mwexec('/bin/sync');
			if (set_single_sysctl('hw.ata.standby', (int)$standby)) {
				// Reinitialize ATA-drives
				mwexec('/usr/local/sbin/atareinit');
				if (file_exists("/var/run/booting")) {
					echo gettext("done.") . "\n";
				}
			} else if (file_exists("/var/run/booting")) {
				echo gettext("failed!") . "\n";
			}
		} else if (file_exists("/var/run/booting")) {
			echo gettext("failed!") . "\n";
		}
	}
}

function system_setup_sysctl()
{
	global $config;

	activate_sysctls();

	if (isset($config['system']['sharednet'])) {
		system_disable_arp_wrong_if();
	}
}

function system_disable_arp_wrong_if()
{
	global $config;

	set_sysctl(array(
		"net.link.ether.inet.log_arp_wrong_iface" => "0",
		"net.link.ether.inet.log_arp_movements" => "0"
	));
}

function enable_watchdog()
{
	global $config;

	return;	/* XXX delete or repair please */

	$install_watchdog = false;
	$supported_watchdogs = array("Geode");
	$file = @file_get_contents('/var/run/dmesg.boot');
	foreach($supported_watchdogs as $sd) {
		if(stristr($file, "Geode")) {
			$install_watchdog = true;
		}
	}
	if($install_watchdog == true) {
		if(is_process_running("watchdogd"))
			mwexec("/usr/bin/killall watchdogd", true);
		exec("/usr/sbin/watchdogd");
	}
}



function get_possible_listen_ips($include_ipv6_link_local=false) {
	$interfaces = get_configured_interface_with_descr();
	$carplist = get_configured_carp_interface_list();
	$listenips = array();
	foreach ($carplist as $cif => $carpip)
		$interfaces[$cif] = $carpip." (".get_vip_descr($carpip).")";
	$aliaslist = get_configured_ip_aliases_list();
	foreach ($aliaslist as $aliasip => $aliasif)
		$interfaces[$aliasip] = $aliasip." (".get_vip_descr($aliasip).")";
	foreach ($interfaces as $iface => $ifacename) {
		$tmp["name"]  = $ifacename;
		$tmp["value"] = $iface;
		$listenips[] = $tmp;
		if ($include_ipv6_link_local) {
			$llip = find_interface_ipv6_ll(get_real_interface($iface));
			if (!empty($llip)) {
				$tmp["name"]  = "{$ifacename} IPv6 Link-Local";
				$tmp["value"] = $llip;
				$listenips[] = $tmp;
			}
		}
	}
	$tmp["name"]  = "Localhost";
	$tmp["value"] = "lo0";
	$listenips[] = $tmp;
	return $listenips;
}

function get_possible_traffic_source_addresses($include_ipv6_link_local=false) {
	global $config;
	$sourceips = get_possible_listen_ips($include_ipv6_link_local);
	foreach (array('server', 'client') as $mode) {
		if (isset($config['openvpn']["openvpn-{$mode}"]) && is_array($config['openvpn']["openvpn-{$mode}"])) {
			foreach ($config['openvpn']["openvpn-{$mode}"] as $id => $setting) {
				if (!isset($setting['disable'])) {
					$vpn = array();
					$vpn['value'] = 'ovpn' . substr($mode, 0, 1) . $setting['vpnid'];
					$vpn['name'] = gettext("OpenVPN") . " ".$mode.": ".htmlspecialchars($setting['description']);
					$sourceips[] = $vpn;
				}
			}
		}
	}
	return $sourceips;
}
