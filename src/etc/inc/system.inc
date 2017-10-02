<?php

/*
    Copyright (C) 2016-2017 Franco Fichtner <franco@opnsense.org>
    Copyright (C) 2004-2007 Scott Ullrich <sullrich@gmail.com>
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

function system_powerd_configure($verbose = false)
{
    global $config;

    if (is_process_running('powerd')) {
        exec('/usr/bin/killall powerd');
    }

    if (!isset($config['system']['powerd_enable'])) {
        return;
    }

    if ($verbose) {
        echo 'Starting power daemon...';
        flush();
    }

    $ac_mode = 'hadp';
    if (!empty($config['system']['powerd_ac_mode'])) {
        $ac_mode = $config['system']['powerd_ac_mode'];
    }

    $battery_mode = 'hadp';
    if (!empty($config['system']['powerd_battery_mode'])) {
        $battery_mode = $config['system']['powerd_battery_mode'];
    }

    $normal_mode = 'hadp';
    if (!empty($config['system']['powerd_normal_mode'])) {
        $normal_mode = $config['system']['powerd_normal_mode'];
    }

    mwexecf(
        '/usr/sbin/powerd -b %s -a %s -n %s',
        array($battery_mode, $ac_mode, $normal_mode)
    );

    if ($verbose) {
        echo "done.\n";
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
        'net.enc.in.ipsec_bpf_mask' => '2', /* after processing */
        'net.enc.in.ipsec_filter_mask' => '2', /* after processing */
        'net.enc.out.ipsec_bpf_mask' => '1', /* before processing */
        'net.enc.out.ipsec_filter_mask' => '1', /* before processing */
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

function system_resolvconf_generate($verbose = false)
{
    global $config;

    $syscfg = $config['system'];

    if ($verbose) {
        echo 'Generating /etc/resolv.conf...';
        flush();
    }

    if (!empty($syscfg['domain'])) {
        $resolvconf = "domain {$syscfg['domain']}\n";
    }

    if (((isset($config['dnsmasq']['enable']) && (empty($config['dnsmasq']['interface']) || in_array("lo0", explode(",", $config['dnsmasq']['interface']))))
      || (isset($config['unbound']['enable'])) && (empty($config['unbound']['active_interface']) || in_array("lo0", explode(",", $config['unbound']['active_interface']))))
      && !isset($config['system']['dnslocalhost'])) {
        $resolvconf .= "nameserver 127.0.0.1\n";
    }

    if (isset($syscfg['dnsallowoverride'])) {
        foreach (get_searchdomains() as $searchserver) {
            $resolvconf .= "search {$searchserver}\n";
        }
        foreach (get_nameservers() as $nameserver) {
            $resolvconf .= "nameserver $nameserver\n";
        }
    }

    if (isset($syscfg['dnsserver'][0])) {
        foreach ($syscfg['dnsserver'] as $ns) {
            $resolvconf .= "nameserver $ns\n";
        }
    }

    $dnslock = lock('resolvconf', LOCK_EX);

    file_put_contents('/etc/resolv.conf', $resolvconf);
    chmod('/etc/resolv.conf', 0644);

    /* setup static routes for DNS servers. */
    for ($dnscounter = 1; $dnscounter < 9; $dnscounter++) {
        /* setup static routes for dns servers */
        $dnsgw = "dns{$dnscounter}gw";
        if (isset($config['system'][$dnsgw])) {
            $gwname = $config['system'][$dnsgw];
            if (($gwname <> "") && ($gwname <> "none")) {
                $gatewayip = lookup_gateway_ip_by_name($gwname);
                if (is_ipaddrv4($gatewayip)) {
                    /* dns server array starts at 0 */
                    $dnscountermo = $dnscounter - 1;
                    system_host_route($syscfg['dnsserver'][$dnscountermo], $gatewayip);
                }
                if (is_ipaddrv6($gatewayip)) {
                    /* dns server array starts at 0 */
                    $dnscountermo = $dnscounter - 1;
                    system_host_route($syscfg['dnsserver'][$dnscountermo], $gatewayip);
                }
            }
        }
    }

    unlock($dnslock);

    if ($verbose) {
        echo "done.\n";
    }
}

function get_locale_list()
{
    $locales = array();

    /* first one is the default */
    $locales['en_US'] = gettext('English');
    $locales['cs_CZ'] = gettext('Czech');
    $locales['zh_CN'] = gettext('Chinese (Simplified)');
    $locales['nl_NL'] = gettext('Dutch');
    $locales['fr_FR'] = gettext('French');
    $locales['de_DE'] = gettext('German');
    $locales['it_IT'] = gettext('Italian');
    $locales['ja_JP'] = gettext('Japanese');
    $locales['pt_BR'] = gettext('Portuguese (Brazil)');
    $locales['pt_PT'] = gettext('Portuguese (Portugal)');
    $locales['ru_RU'] = gettext('Russian');
    $locales['es_ES'] = gettext('Spanish');
    $locales['tr_TR'] = gettext('Turkish');

    return $locales;
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

function get_zoneinfo()
{
    $zones = timezone_identifiers_list(DateTimeZone::ALL ^ DateTimeZone::UTC);

    $etcs = glob('/usr/share/zoneinfo/Etc/*');
    foreach ($etcs as $etc) {
        $zones[] = ltrim($etc, '/usr/share/zoneinfo/');
    }

    natsort($zones);

    return $zones;
}

function get_searchdomains()
{
    $master_list = array();

    $search_list = glob('/var/etc/searchdomain_*');

    if (is_array($search_list)) {
        foreach ($search_list as $fdns) {
            $contents = file($fdns, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if (!is_array($contents)) {
                continue;
            }
            foreach ($contents as $dns) {
                if (!empty($dns) && is_hostname($dns)) {
                    $master_list[] = $dns;
                }
            }
        }
    }

    return array_unique($master_list);
}

function get_dns_servers()
{
    $dns_servers = array();

    $dns_s = file('/etc/resolv.conf', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach($dns_s as $dns) {
        $matches = '';
        if (preg_match('/nameserver ([^ ]+)/', $dns, $matches)) {
            $dns_servers[] = $matches[1];
        }
    }

    return array_unique($dns_servers);
}

function get_nameservers()
{
    $master_list = array();

    $dns_lists = glob('/var/etc/nameserver_*');

    if (is_array($dns_lists)) {
        foreach ($dns_lists as $fdns) {
            $contents = file($fdns, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if (!is_array($contents)) {
                continue;
            }
            foreach ($contents as $dns) {
                if (!empty($dns) && is_ipaddr($dns)) {
                    $master_list[] = $dns;
                }
            }
        }
    }

    return array_unique($master_list);
}

function system_hosts_generate($verbose = false)
{
    global $config;

    if ($verbose) {
        echo 'Generating /etc/hosts...';
        flush();
    }

    $syscfg = $config['system'];

    $hosts = "127.0.0.1\tlocalhost localhost.{$syscfg['domain']}\n";

    if (isset($config['interfaces']['lan'])) {
        $cfgip = get_interface_ip("lan");
        if (is_ipaddr($cfgip)) {
            $hosts .= "{$cfgip}\t{$syscfg['hostname']}.{$syscfg['domain']} {$syscfg['hostname']}\n";
        }
    } else {
        $sysiflist = get_configured_interface_list();
        foreach ($sysiflist as $sysif) {
            if (!interface_has_gateway($sysif)) {
                $cfgip = get_interface_ip($sysif);
                if (is_ipaddr($cfgip)) {
                    $hosts .= "{$cfgip}\t{$syscfg['hostname']}.{$syscfg['domain']} {$syscfg['hostname']}\n";
                    break;
                }
            }
        }
    }

    file_put_contents('/etc/hosts', $hosts);

    plugins_configure('hosts');

    if ($verbose) {
        echo "done.\n";
    }
}

function system_hostname_configure($verbose = false)
{
    global $config;

    if ($verbose) {
        echo 'Setting hostname: ';
        flush();
    }

    $syscfg = $config['system'];

    $hostname = "{$syscfg['hostname']}.{$syscfg['domain']}";

    /* set hostname */
    mwexecf('/bin/hostname %s', $hostname);

    /* Setup host GUID ID.  This is used by ZFS. */
    mwexec("/etc/rc.d/hostid start");

    if ($verbose) {
        echo "{$hostname}\n";
    }
}

function system_host_route($host, $gateway, $delete = true, $add = true)
{
    if (is_ipaddrv4($gateway)) {
        $family = 'inet';
    } elseif (is_ipaddrv6($gateway)) {
        $family = 'inet6';
    } else {
        return;
    }

    if ($delete) {
        mwexecf('/sbin/route delete -host -%s %s', array($family, $host), true);
    }

    if ($add) {
        mwexecf('/sbin/route add -host -%s %s %s', array($family, $host, $gateway));
    }
}

function system_default_route($gateway, $interface = null)
{
    if (is_ipaddrv4($gateway)) {
        $family = 'inet';
    } elseif (is_ipaddrv6($gateway)) {
        $family = 'inet6';
        if (is_linklocal($gateway)) {
            $gateway .= "%{$interface}";
        }
        /* IPv6 does not support far gateway notion */
        $interface = null;
    } else {
        return;
    }

    mwexecf('/sbin/route delete -%s default', array($family), true);
    if (!empty($interface)) {
        mwexecf('/sbin/route delete -%s %s -interface %s', array($family, $gateway, $interface), true);
        mwexecf('/sbin/route add -%s %s -interface %s', array($family, $gateway, $interface));
    }
    mwexecf('/sbin/route add -%s default %s', array($family, $gateway));
}

function system_routing_configure($interface = '', $verbose = false)
{
    global $config;

    if ($verbose) {
        echo 'Setting up routes...';
        flush();
    }

    $gatewayip = "";
    $interfacegw = "";
    $foundgw = false;
    $gatewayipv6 = "";
    $interfacegwv6 = "";
    $foundgwv6 = false;
    $fargw = false;

    /* XXX eventually this file-based workaround must be removed */
    foreach (glob('/tmp/*_defaultgw{,v6}', GLOB_BRACE) as $to_delete) {
        @unlink($to_delete);
    }

    /* tack on all the hard defined gateways as well */
    if (isset($config['gateways']['gateway_item'])) {
        foreach ($config['gateways']['gateway_item'] as $gateway) {
            if (isset($gateway['defaultgw'])) {
                if ($gateway['ipprotocol'] != "inet6" && (is_ipaddrv4($gateway['gateway']) || $gateway['gateway'] == "dynamic")) {
                    if (strstr($gateway['gateway'], ":")) {
                        continue;
                    }
                    if ($gateway['gateway'] == "dynamic") {
                        $gateway['gateway'] = get_interface_gateway($gateway['interface']);
                    }
                    $fargw = isset($gateway['fargw']);
                    $gatewayip = $gateway['gateway'];
                    $interfacegw = $gateway['interface'];
                    if (!empty($gateway['interface'])) {
                        $defaultif = get_real_interface($gateway['interface']);
                        if ($defaultif) {
                            @file_put_contents("/tmp/{$defaultif}_defaultgw", $gateway['gateway']);
                        }
                    }
                    $foundgw = true;
                } elseif ($gateway['ipprotocol'] == "inet6" && (is_ipaddrv6($gateway['gateway']) || $gateway['gateway'] == "dynamic")) {
                    if ($gateway['gateway'] == "dynamic") {
                        $gateway['gateway'] = get_interface_gateway_v6($gateway['interface']);
                    }
                    $gatewayipv6 = $gateway['gateway'];
                    $interfacegwv6 = $gateway['interface'];
                    if (!empty($gateway['interface'])) {
                        $defaultifv6 = get_real_interface($gateway['interface']);
                        if ($defaultifv6) {
                            @file_put_contents("/tmp/{$defaultifv6}_defaultgwv6", $gateway['gateway']);
                        }
                    }
                    $foundgwv6 = true;
                }
            }
            if ($foundgw === true && $foundgwv6 === true) {
                break;
            }
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
    elseif (is_ipaddrv4($gatewayip)) {
        log_error("ROUTING: setting IPv4 default route to {$gatewayip}");
        system_default_route($gatewayip, $fargw ? $defaultif : null);
    }

    if (!empty($interface) && $interface != $interfacegwv6)
      ;
    elseif (is_ipaddrv6($gatewayipv6)) {
        log_error("ROUTING: setting IPv6 default route to {$gatewayipv6}");
        system_default_route($gatewayipv6, $defaultifv6);
    }

    system_staticroutes_configure($interface);

    set_sysctl(array(
      'net.inet.ip.forwarding' => '1',
      'net.inet6.ip6.forwarding' => '1'
    ));

    if ($verbose) {
        echo "done.\n";
    }

    return 0;
}

function add_hostname_to_watch($hostname)
{
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

        /* Remove empty elements */
        $result = array_filter(explode("\n", $contents), 'strlen');
    }

    return $result;
}

function system_staticroutes_configure($interface = '')
{
    global $config, $aliastable;

    $filterdns_list = array();

    $static_routes = get_staticroutes(false, true);
    if (count($static_routes)) {
        $gateways_arr = return_gateways_array(false, true);

        foreach ($static_routes as $rtent) {
            if (empty($gateways_arr[$rtent['gateway']])) {
                log_error(sprintf('Static Routes: Gateway IP could not be found for %s', $rtent['network']));
                continue;
            }
            $gateway = $gateways_arr[$rtent['gateway']];
            if (!empty($interface) && $interface != $gateway['friendlyiface']) {
                continue;
            }

            $interfacegw = $gateway['interface'];
            $gatewayip = $gateway['gateway'];
            $fargw = isset($gateway['fargw']) && $gateway['ipprotocol'] != 'inet6';
            $blackhole = '';

            switch ($rtent['gateway']) {
                case 'Null4':
                case 'Null6':
                    $blackhole = '-blackhole';
                    break;
                default:
                    break;
            }

            if (!is_fqdn($rtent['network']) && !is_subnet($rtent['network'])) {
                continue;
            }

            if (is_subnet($rtent['network'])) {
                $ips = array($rtent['network']);
            } else {
                if (!isset($rtent['disabled'])) {
                    $filterdns_list[] = $rtent['network'];
                }
                $ips = add_hostname_to_watch($rtent['network']);
            }

            if (isset($rtent['disabled'])) {
                foreach ($ips as $ip) {
                    if (!is_subnet($ip)) {
                        if (is_ipaddrv4($ip)) {
                            $ip .= "/32";
                        } elseif (is_ipaddrv6($ip)) {
                            $ip .= "/128";
                        }
                    }
                    $inet = (is_subnetv6($ip) ? "-inet6" : "-inet");
                    mwexec("/sbin/route delete {$inet} " . escapeshellarg($ip));
                }
                continue;
            }

            foreach ($ips as $ip) {
                if (!is_subnet($ip)) {
                    if (is_ipaddrv4($ip)) {
                        $ip .= "/32";
                    } elseif (is_ipaddrv6($ip)) {
                        $ip .= "/128";
                    }
                }

                $inet = (is_subnetv6($ip) ? "-inet6" : "-inet");
                $cmd = " {$inet} {$blackhole} " . escapeshellarg($ip) . " ";

                if (is_subnet($ip)) {
                    if (is_ipaddr($gatewayip)) {
                        mwexec("/sbin/route delete".$cmd . escapeshellarg($gatewayip));
                        if ($fargw) {
                            mwexecf('/sbin/route delete %s %s -interface %s', array($inet, $gatewayip, $interfacegw));
                            mwexecf('/sbin/route add %s %s -interface %s', array($inet, $gatewayip, $interfacegw));
                        }
                        mwexec("/sbin/route add".$cmd . escapeshellarg($gatewayip));
                    } elseif (!empty($interfacegw)) {
                        mwexec("/sbin/route delete".$cmd . "-interface " . escapeshellarg($interfacegw));
                        mwexec("/sbin/route add".$cmd . "-interface " . escapeshellarg($interfacegw));
                    }
                } else {
                    log_error(sprintf('Cannot add static route to: %s', $ip));
                }
            }
        }
    }

    if (count($filterdns_list)) {
        $hostnames = "";
        array_unique($filterdns_list);
        foreach ($filterdns_list as $hostname) {
            $hostnames .= "cmd {$hostname} '/usr/local/opnsense/service/configd_ctl.py routedns reload'\n";
        }
        file_put_contents("/var/etc/filterdns-route.hosts", $hostnames);
        if (isvalidpid('/var/run/filterdns-route.pid')) {
            killbypid('/var/run/filterdns-route.pid', 'HUP');
        } else {
            mwexec("/usr/local/sbin/filterdns -p /var/run/filterdns-route.pid -i 60 -c /var/etc/filterdns-route.hosts -d 1");
        }
    }

    return 0;
}

function system_syslogd_fixup_server($server)
{
    /* If it's an IPv6 IP alone, encase it in brackets */
    if (is_ipaddrv6($server)) {
        return "[$server]";
    } else {
        return $server;
    }
}

function system_syslogd_get_remote_servers($syslogcfg, $facility = "*.*") {
    // Rather than repeatedly use the same code, use this function to build a list of remote servers.
    $facility .= " ".
    $remote_servers = "";
    $pad_to  = 56;
    $padding = ceil(($pad_to - strlen($facility))/8)+1;
    if(!empty($syslogcfg['remoteserver'])) {
        $remote_servers .= "{$facility}" . str_repeat("\t", $padding) . "@" . system_syslogd_fixup_server($syslogcfg['remoteserver']) . "\n";
    }
    if(!empty($syslogcfg['remoteserver2'])) {
        $remote_servers .= "{$facility}" . str_repeat("\t", $padding) . "@" . system_syslogd_fixup_server($syslogcfg['remoteserver2']) . "\n";
    }
    if(!empty($syslogcfg['remoteserver3'])) {
        $remote_servers .= "{$facility}" . str_repeat("\t", $padding) . "@" . system_syslogd_fixup_server($syslogcfg['remoteserver3']) . "\n";
    }
    return $remote_servers;
}

function system_syslogd_extra_local($logsocket)
{
    $logdir = dirname($logsocket);

    if (!is_dir($logdir)) {
        /* create if needed to avoid startup error */
        mwexecf('/bin/mkdir -p %s', $logdir);
    }

    /* emit extra args for syslogd invoke */
    return exec_safe('-l %s ', $logsocket);
}

function system_syslogd_start($verbose = false)
{
    global $config;

    if ($verbose) {
        echo 'Starting syslog...';
        flush();
    }

    configd_run('template reload OPNsense/Syslog');

    mwexec('/etc/rc.d/hostid start');

    $syslogcfg = $config['syslog'];

    $log_directive = '%';
    $syslogd_extra = '';

    if (isset($syslogcfg)) {
        $syslogconf = '';

        $syslogconfs = array();

        foreach (plugins_syslog() as $plugin_name => $plugin_details) {
            $syslogconfs[$plugin_name] = $plugin_details;
        }

        /*
         * XXX Standard syslog configs overwrite plugins, but we can
         * get rid of this behaviour by wrapping this local array using
         * the key as a "name" entry in the array...
         */
        $syslogconfs['dhcpd'] = array('facility' => array('dhcpd', 'dhcrelay', 'dhclient', 'dhcp6c'), 'local' => '/var/dhcpd/var/run/log', 'remote' => 'dhcp');
        $syslogconfs['filter'] = array('facility' => array('filterlog'), 'remote' => 'filter');
        $syslogconfs['gateways'] = array('facility' => array('apinger'), 'remote' => 'apinger');
        $syslogconfs['portalauth'] = array('facility' => array('captiveportal'), 'remote' => 'portalauth');
        $syslogconfs['ppps'] = array('facility' => array('ppp'));
        $syslogconfs['resolver'] = array('facility' => array('filterdns', 'unbound'), 'remote' => 'dns');
        $syslogconfs['routing'] = array('facility' => array('radvd', 'routed', 'rtsold', 'olsrd', 'zebra', 'ospfd', 'bgpd', 'miniupnpd'));
        $syslogconfs['wireless'] = array('facility' => array('hostapd'), 'remote' => 'hostapd');

        $separatelogfacilities = array();
        foreach ($syslogconfs as $logTopic => $logConfig) {
            $syslogconf .= "!".implode(',', $logConfig['facility'])."\n";
            $separatelogfacilities = array_merge($logConfig['facility'], $separatelogfacilities);
            if (!isset($syslogcfg['disablelocallogging'])) {
                $syslogconf .= "*.*                {$log_directive}/var/log/{$logTopic}.log\n";
            }
            if (!empty($logConfig['remote']) && !empty($syslogcfg[$logConfig['remote']]) && !empty($syslogcfg['enable'])) {
                $syslogconf .= system_syslogd_get_remote_servers($syslogcfg, "*.*");
            }
            if (!empty($logConfig['local'])) {
                $syslogd_extra .= system_syslogd_extra_local($logConfig['local']);
            }
        }

        asort($separatelogfacilities);
        $facilitylist = implode(',', array_unique($separatelogfacilities));
        $syslogconf .= "!-{$facilitylist}\n";
        if (!isset($syslogcfg['disablelocallogging'])) {
            $syslogconf .= <<<EOD
local3.*              {$log_directive}/var/log/vpn.log
local7.*              {$log_directive}/var/log/dhcpd.log
*.notice;kern.debug;lpr.info;mail.crit;daemon.none;    {$log_directive}/var/log/system.log
news.err;local0.none;local3.none;local4.none;      {$log_directive}/var/log/system.log
local7.none              {$log_directive}/var/log/system.log
security.*              {$log_directive}/var/log/system.log
auth.info;authpriv.info;daemon.info        {$log_directive}/var/log/system.log
auth.info;authpriv.info;user.*        |exec /usr/local/sbin/sshlockout_pf 15
*.emerg                *

EOD;
        }
        if (!empty($syslogcfg['enable'])) {
            if (isset($syslogcfg['vpn'])) {
                $syslogconf .= system_syslogd_get_remote_servers($syslogcfg, "local3.*");
            }
            if (isset($syslogcfg['dns'])) {
                /* XXX needs testing */
                $syslogconf .= system_syslogd_get_remote_servers($syslogcfg, "local5.*");
            }
            if (isset($syslogcfg['portalauth'])) {
                $syslogconf .= system_syslogd_get_remote_servers($syslogcfg, "local4.*");
            }
            if (isset($syslogcfg['dhcp'])) {
                $syslogconf .= system_syslogd_get_remote_servers($syslogcfg, "local7.*");
            }
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
        }

        file_put_contents('/var/etc/syslog.conf', $syslogconf);

        if (!empty($syslogcfg['sourceip'])) {
            if ($syslogcfg['ipproto'] == "ipv6") {
                $ifaddr = is_ipaddr($syslogcfg['sourceip']) ? $syslogcfg['sourceip'] : get_interface_ipv6($syslogcfg['sourceip']);
                if (!is_ipaddr($ifaddr)) {
                    $ifaddr = get_interface_ip($syslogcfg['sourceip']);
                }
            } else {
                $ifaddr = is_ipaddr($syslogcfg['sourceip']) ? $syslogcfg['sourceip'] : get_interface_ip($syslogcfg['sourceip']);
                if (!is_ipaddr($ifaddr)) {
                    $ifaddr = get_interface_ipv6($syslogcfg['sourceip']);
                }
            }
            if (is_ipaddr($ifaddr)) {
                $syslogd_extra .= exec_safe('-b %s ', $ifaddr);
            }
        }

        $syslogd_extra .= exec_safe('-f %s ', '/var/etc/syslog.conf');

        // setup log files for all facilities including default
        $default_logfile_size = !empty($syslogcfg['logfilesize']) ? $syslogcfg['logfilesize'] : '511488';
        $syslog_files = array_keys($syslogconfs);
        $syslog_files = array_merge($syslog_files, array('system', 'vpn', 'lighttpd'));
        foreach ($syslog_files as $syslog_fn) {
            $filename = "/var/log/".basename($syslog_fn).".log";
            if (!file_exists($filename)) {
                mwexecf('/usr/local/sbin/clog -i -s %s %s', array($default_logfile_size, $filename));
            }
            mwexecf('chmod 0600 %s', array($filename));
        }
    }

    if (isvalidpid('/var/run/syslog.pid')) {
        killbypid('/var/run/syslog.pid', 'HUP');
    } else {
        mwexecf_bg("/usr/local/sbin/syslogd -s -c -c -P %s {$syslogd_extra}", '/var/run/syslog.pid');
    }

    if ($verbose) {
        echo "done.\n";
    }
}

function system_clear_log($logfile, $restart_syslogd = true)
{
    if ($restart_syslogd) {
        killbyname('syslogd');
    }

    foreach (glob($logfile . '.*') as $rotated) {
        @unlink($rotated);
    }

    /* preserve file ownership and permissions */
    if (file_exists($logfile)) {
        $handle = fopen($logfile, 'r+');
        if ($handle) {
            ftruncate($handle, 0);
            fclose($handle);
        }
    }

    if ($restart_syslogd) {
        system_syslogd_start();
    }
}

function system_clear_clog($logfile, $restart_syslogd = true)
{
    global $config;

    if ($restart_syslogd) {
        killbyname('syslogd');
    }

    $log_size = isset($config['syslog']['logfilesize']) ? $config['syslog']['logfilesize'] : '511488';
    mwexecf('/usr/local/sbin/clog -i -s %s %s', array($log_size, $logfile));

    if ($restart_syslogd) {
        system_syslogd_start();
    }
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

function system_firmware_configure($verbose = false)
{
    global $config;

    if ($verbose) {
        echo 'Writing firmware setting...';
        flush();
    }

    /* rewrite the config via the defaults */
    $origin_conf = '/usr/local/etc/pkg/repos/origin.conf';
    copy("${origin_conf}.sample", $origin_conf);

    if (!empty($config['system']['firmware']['mirror'])) {
        mwexecf(
            '/usr/local/sbin/opnsense-update %s %s',
            array('-sm', str_replace('/', '\/', $config['system']['firmware']['mirror']))
        );
    }

    if (!empty($config['system']['firmware']['flavour'])) {
        mwexecf(
            '/usr/local/sbin/opnsense-update -sn %s',
            str_replace('/', '\/', sprintf(
                "%s{$config['system']['firmware']['flavour']}",
                /* if there is no directory slash we always treat it with default ABI prefix */
                strpos($config['system']['firmware']['flavour'], '/') === false ?
                    trim(file_get_contents('/usr/local/opnsense/version/opnsense.abi')) . '/' : ''
            ))
        );
    }

    if ($verbose) {
        echo "done.\n";
    }
}

function system_timezone_configure($verbose = false)
{
    global $config;

    $syscfg = $config['system'];

    if ($verbose) {
        echo 'Setting timezone...';
        flush();
    }

    /* extract appropriate timezone file */
    $timezone = $syscfg['timezone'];
    $timezones = get_zoneinfo();

    /* reset to default if empty or nonexistent */
    if (empty($timezone) || !in_array($timezone, $timezones) ||
        !file_exists(sprintf('/usr/share/zoneinfo/%s', $timezone))) {
        $timezone = 'Etc/UTC';
    }

    /* apply timezone */
    if (file_exists(sprintf('/usr/share/zoneinfo/%s', $timezone))) {
        copy(sprintf('/usr/share/zoneinfo/%s', $timezone), '/etc/localtime');
    }

    if ($verbose) {
        echo "done.\n";
    }
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

function system_sysctl_configure($verbose = false)
{
    if ($verbose) {
        echo 'Setting up extended sysctls...';
        flush();
    }

    activate_sysctls();
    system_arp_wrong_if();

    if ($verbose) {
        echo "done.\n";
    }
}

function system_arp_wrong_if()
{
    global $config;

    set_sysctl(array(
        'net.link.ether.inet.log_arp_wrong_iface' => isset($config['system']['sharednet']) ? '0' : '1',
        'net.link.ether.inet.log_arp_movements' => isset($config['system']['sharednet']) ? '0' : '1',
    ));
}

function get_possible_listen_ips($include_ipv6_link_local = false, $include_loopback = true) {
    global $config;
    $interfaces = get_configured_interface_with_descr();
    $carplist = get_configured_carp_interface_list();
    $listenips = array();
    foreach ($carplist as $cif => $carpip) {
        $interfaces[$cif] = $carpip." (".get_vip_descr($carpip).")";
    }
    $aliaslist = get_configured_ip_aliases_list();
    foreach ($aliaslist as $aliasip => $aliasif) {
        $interfaces[$aliasip] = $aliasip." (".get_vip_descr($aliasip).")";
    }
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
    if ($include_loopback) {
        $tmp["name"]  = "Localhost";
        $tmp["value"] = "lo0";
        $listenips[] = $tmp;
    }
    foreach (array('server', 'client') as $mode) {
        if (isset($config['openvpn']["openvpn-{$mode}"]) && is_array($config['openvpn']["openvpn-{$mode}"])) {
            foreach ($config['openvpn']["openvpn-{$mode}"] as $id => $setting) {
                if (!isset($setting['disable'])) {
                    $vpn = array();
                    $vpn['value'] = 'ovpn' . substr($mode, 0, 1) . $setting['vpnid'];
                    $vpn['name'] = "OpenVPN {$mode}: " . htmlspecialchars($setting['description']);
                    $listenips[] = $vpn;
                }
            }
        }
    }
    return $listenips;
}

function system_kernel_configure($verbose = false)
{
    global $config;

    if ($verbose) {
        echo 'Configuring kernel modules...';
        flush();
    }

    /*
     * Vital kernel modules can go missing on reboot due to
     * /boot/loader.conf not materialising.  This is still
     * an UFS problem, despite claims otherwise.  In any case,
     * load all the modules again to make sure.
     *
     * Keep in sync with /usr/local/etc/erc.loader.d/20-modules
     */
    $mods = array(
        'carp',
        'if_bridge',
        'if_enc',
        'if_gif',
        'if_gre',
        'if_lagg',
        'if_tap',
        'if_tun',
        'if_vlan',
        'pf',
        'pflog',
        'pfsync',
    );

    if (!empty($config['system']['crypto_hardware'])) {
        log_error(sprintf('Loading %s cryptographic accelerator module.', $config['system']['crypto_hardware']));
        $mods[] = $config['system']['crypto_hardware'];
    }
    if (!empty($config['system']['cryptodev_enable'])) {
        log_error('Loading cryptodev kernel module.');
        $mods[] = 'cryptodev';
    }

    if (!empty($config['system']['thermal_hardware'])) {
        log_error(sprintf('Loading %s thermal monitor module.', $config['system']['thermal_hardware']));
        $mods[] = $config['system']['thermal_hardware'];
    }

    foreach ($mods as $mod) {
        mwexecf('/sbin/kldload %s', $mod, true);
    }

    /* we now have /dev/pf, time to fix permissions for proxies */
    chgrp('/dev/pf', 'proxy');
    chmod('/dev/pf', 0660);

    if ($verbose) {
        echo "done.\n";
    }
}

function system_devd_configure($verbose = false)
{
    if ($verbose) {
        echo 'Starting device manager...';
        flush();
    }

    exec('/sbin/devd');
    /* historic sleep */
    sleep(1);

    if ($verbose) {
        echo "done.\n";
    }
}

function system_cron_configure($verbose = false, $defer = false)
{
    global $config;

    function generate_cron_job($command, $minute = '0', $hour = '*', $monthday = '*', $month = '*', $weekday = '*')
    {
        $cron_item = array();

        $cron_item['minute'] = $minute;
        $cron_item['hour'] = $hour;
        $cron_item['mday'] = $monthday;
        $cron_item['month'] = $month;
        $cron_item['wday'] = $weekday;
        $cron_item['command'] = $command;

        return $cron_item;
    }

    $autocron = array();

    if ($verbose) {
        echo 'Configuring CRON...';
        flush();
    }

    foreach (plugins_cron() as $cron_plugin) {
        /*
         * We are stuffing jobs inside 'autocron' to be able to
         * depreceate this at a later time.  Ideally all of the
         * services should use a single cron-model, which this is
         * not.  At least this plugin function helps us to divide
         * and conquer the code bits...  :)
         */
        if (!empty($cron_plugin['autocron'])) {
            $autocron[] = call_user_func_array('generate_cron_job', $cron_plugin['autocron']);
        }
    }

    /* hourly */
    $autocron[] = generate_cron_job('/usr/local/sbin/expiretable -v -t 3600 webConfiguratorlockout', '1');
    $autocron[] = generate_cron_job('/usr/local/sbin/expiretable -v -t 3600 sshlockout', '2');
    $autocron[] = generate_cron_job('/usr/local/sbin/expiretable -v -t 3600 virusprot', '3');
    $autocron[] = generate_cron_job('/usr/local/etc/rc.expireaccounts', '5');

    /* daily */
    $autocron[] = generate_cron_job('/usr/local/etc/rc.update_alias_url_data', '1', '1');
    $autocron[] = generate_cron_job('/usr/local/etc/rc.update_urltables', '6', '1');

    /* every now and then */
    $autocron[] = generate_cron_job('/usr/local/sbin/ping_hosts.sh', '*/4');

    if (!empty($config['system']['rrdbackup'])) {
       $autocron[] = generate_cron_job('/usr/local/etc/rc.backup_rrd', '0', '*/' . $config['system']['rrdbackup']);
    }

    if (!empty($config['system']['dhcpbackup'])) {
        $autocron[] = generate_cron_job('/usr/local/etc/rc.backup_dhcpleases', '0', '*/' . $config['system']['dhcpbackup']);
    }

    if (!empty($config['system']['netflowbackup'])) {
        $autocron[] = generate_cron_job('/usr/local/etc/rc.backup_netflow', '0', '*/' . $config['system']['netflowbackup']);
    }

    if (!empty($config['system']['captiveportalbackup'])) {
        $autocron[] = generate_cron_job('/usr/local/etc/rc.backup_captiveportal', '0', '*/' . $config['system']['captiveportalbackup']);
    }

    if (!empty($config['system']['remotebackup']['GDriveEnabled'])) {
        $autocron[] = generate_cron_job('/usr/local/opnsense/scripts/remote_backup.php', 0, 1);
    }

    /* bogons fetch always set in default config.xml */
    switch ($config['system']['bogons']['interval']) {
        case 'daily':
            $autocron[] = generate_cron_job('/usr/local/etc/rc.update_bogons cron', '1', '3', '*', '*', '*');
            break;
        case 'weekly':
            $autocron[] = generate_cron_job('/usr/local/etc/rc.update_bogons cron', '1', '3', '*', '*', '0');
            break;
        case 'monthly':
        default:
            $autocron[] = generate_cron_job('/usr/local/etc/rc.update_bogons cron', '1', '3', '1', '*', '*');
            break;
    }

    $crontab_contents = "# DO NOT EDIT THIS FILE -- OPNsense auto-generated file\n";
    $crontab_contents .= "#\n";
    $crontab_contents .= "# User-defined crontab files can be loaded via /etc/cron.d\n";
    $crontab_contents .= "# or /usr/local/etc/cron.d and follow the same format as\n";
    $crontab_contents .= "# /etc/crontab, see the crontab(5) manual page.\n";
    $crontab_contents .= "SHELL=/bin/sh\n";
    $crontab_contents .= "PATH=/etc:/bin:/sbin:/usr/bin:/usr/sbin:/usr/local/bin:/usr/local/sbin\n";
    $crontab_contents .= "#minute\thour\tmday\tmonth\twday\tcommand\n";

    foreach ($autocron as $item) {
        $crontab_contents .= "{$item['minute']}\t";
        $crontab_contents .= "{$item['hour']}\t";
        $crontab_contents .= "{$item['mday']}\t";
        $crontab_contents .= "{$item['month']}\t";
        $crontab_contents .= "{$item['wday']}\t";
        $crontab_contents .= "({$item['command']}) > /dev/null\n";
    }

    file_put_contents('/var/cron/tabs/root', $crontab_contents);

    if (!$defer) {
        configd_run('cron restart');
    }

    if ($verbose) {
        echo "done.\n";
    }
}

function system_console_mutable()
{
    /* this function name is a pun :) */

    global $config;

    return isset($config['system']['primaryconsole']) &&
        ($config['system']['primaryconsole'] == 'serial' ||
        $config['system']['primaryconsole'] == 'null');
}

function system_console_mute()
{
    if (system_console_mutable()) {
        exec('/sbin/conscontrol mute on');
    }
}

function system_console_unmute()
{
    if (system_console_mutable()) {
        exec('/sbin/conscontrol mute off');
    }
}

function system_console_types()
{
    return array(
        /* sorted by usage */
        'video' => array('value' => 'vidconsole', 'name' => gettext('VGA Console')),
        'serial' => array('value' => 'comconsole', 'name' => gettext('Serial Console')),
        'efi' => array('value' => 'efi', 'name' => gettext('EFI Console')),
        'null' => array('value' => 'nullconsole', 'name' => gettext('Mute Console')),
    );
}

function system_login_configure($verbose = false)
{
    global $config;

    if ($verbose) {
        echo 'Configuring login behaviour...';
        flush();
    }

    /* depends on user account locking */
    local_sync_accounts();

    configd_run('template reload OPNsense/Auth');

    $serialspeed = (!empty($config['system']['serialspeed']) && is_numeric($config['system']['serialspeed'])) ? $config['system']['serialspeed'] : '115200';

    $new_boot_config = array();
    $new_boot_config['comconsole_speed'] = null;
    $new_boot_config['boot_multicons'] = null;
    $new_boot_config['boot_serial'] = null;
    $new_boot_config['kern.vty'] = null;
    $new_boot_config['console'] = null;

    $console_types = system_console_types();
    $console_selection = array();

    foreach (array('primaryconsole', 'secondaryconsole') as $console_order) {
        if (!empty($config['system'][$console_order]) && isset($console_types[$config['system'][$console_order]])) {
            $console_selection[] = $console_types[$config['system'][$console_order]]['value'];
        }
    }

    $console_selection = array_unique($console_selection);

    $output_enabled = count($console_selection) != 1 || !in_array('nullconsole', $console_selection);
    $virtual_enabled = !count($console_selection) || in_array('vidconsole', $console_selection) ||
        in_array('efi', $console_selection);
    $serial_enabled = in_array('comconsole', $console_selection);

    if (count($console_selection)) {
        $new_boot_config['console'] = '"' . implode(',', $console_selection) . '"';
        if (count($console_selection) >= 2) {
            $new_boot_config['boot_multicons'] = '"YES"';
        }
    }

    if ($serial_enabled) {
        @file_put_contents('/boot.config', "-S{$serialspeed} -D\n");
        $new_boot_config['comconsole_speed'] = '"'.$serialspeed.'"';
        $new_boot_config['boot_serial'] = '"YES"';
    } elseif (!$output_enabled) {
        @file_put_contents('/boot.config', "-q -m\n");
    } else {
        @unlink('/boot.config');
    }

    if (empty($config['system']['usevirtualterminal'])) {
        $new_boot_config['kern.vty'] = '"sc"';
    }

    /* reload static values from rc.loader.d */
    mwexecf('/usr/local/etc/rc.loader');

    /* copy settings already there */
    $new_loader_conf = @file_get_contents('/boot/loader.conf');

    /* append our console options */
    $new_loader_conf .= "# dynamically generated settings follow\n";
    foreach ($new_boot_config as $param => $value) {
        if (!empty($value)) {
            $new_loader_conf .= "{$param}={$value}\n";
        } else {
            $new_loader_conf .= "#${param}\n";
        }
    }

    /* write merged file back to target location */
    @file_put_contents('/boot/loader.conf', $new_loader_conf);

    /* setup /etc/ttys */
    $etc_ttys_lines = explode("\n", file_get_contents('/etc/ttys'));
    $fd = fopen('/etc/ttys', 'w');
    $on_off_secure_u = $serial_enabled ? 'onifconsole secure' : 'off secure';
    $on_off_secure_v = $virtual_enabled ? 'on  secure' : 'off secure';
    /* XXX serial type uses 3wire nowadays */
    if (isset($config['system']['disableconsolemenu'])) {
        $console_type = 'Pc';
        $serial_type = 'std.' . $serialspeed;
    } else {
        $console_type = 'al.Pc';
        $serial_type = 'al.' . $serialspeed;
    }
    foreach ($etc_ttys_lines as $tty) {
        /* virtual terminals */
        foreach (array('ttyv0', 'ttyv1', 'ttyv2', 'ttyv3', 'ttyv4', 'ttyv5', 'ttyv6', 'ttyv7') as $virtualport) {
            if (strpos($tty, $virtualport) === 0) {
                fwrite($fd, "${virtualport}\t\"/usr/libexec/getty {$console_type}\"\t\txterm\t${on_off_secure_v}\n");
                continue 2;
            }
        }
        /* serial terminals */
        foreach (array('ttyu0', 'ttyu1', 'ttyu2', 'ttyu3') as $serialport) {
            if (strpos($tty, $serialport) === 0) {
                fwrite($fd, "{$serialport}\t\"/usr/libexec/getty {$serial_type}\"\tvt100\t{$on_off_secure_u}\n");
                continue 2;
            }
        }

        if (!empty($tty)) {
            /* all other lines stay the same */
            fwrite($fd, $tty . "\n");
        }
    }
    fclose($fd);

    if ($verbose) {
        echo "done.\n";
    }

    /* force init(8) to reload /etc/ttys */
    exec('/bin/kill -HUP 1');
}

/****f* config/reset_factory_defaults
 * NAME
 *   reset_factory_defaults - Reset the system to its default configuration.
 ******/
function reset_factory_defaults($sync = true)
{
    mwexec('/bin/rm -r /conf/*');
    disable_security_checks();

    mwexec('/usr/local/sbin/beep.sh stop');

    /* as we go through a special case directly reboot */
    $shutdown_cmd = '/sbin/shutdown -or now';
    if ($sync) {
        mwexec($shutdown_cmd);
    } else {
        mwexec_bg($shutdown_cmd);
    }
}
