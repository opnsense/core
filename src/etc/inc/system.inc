<?php

/*
 * Copyright (C) 2016-2023 Franco Fichtner <franco@opnsense.org>
 * Copyright (C) 2004-2007 Scott Ullrich <sullrich@gmail.com>
 * Copyright (C) 2003-2004 Manuel Kasper <mk@neon1.net>
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * 1. Redistributions of source code must retain the above copyright notice,
 *    this list of conditions and the following disclaimer.
 *
 * 2. Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in the
 *    documentation and/or other materials provided with the distribution.
 *
 * THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
 * INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 * AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 * AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 * OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 * INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
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

    service_log('Starting power daemon...', $verbose);

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

    service_log("done.\n", $verbose);
}

function system_sysctl_defaults()
{
    global $config;

    return [
        'hw.ibrs_disable' => [ 'default' => '0' ],
        'hw.ixl.enable_head_writeback' => [ 'default' => '0', 'required' => true ],
        'hw.syscons.kbd_reboot' => [ 'default' => '0' ],
        'hw.uart.console' => [ 'default' => 'io:0x3f8,br:' . system_console_speed(), 'type' => 't' ], /* XXX support comconsole_port if needed */
        'kern.coredump' => [ 'default' => '0', 'required' => true ],
        'kern.ipc.maxsockbuf' => [ 'default' => '4262144' ],
        'kern.randompid' => [ 'default' => '1' ],
        'net.enc.in.ipsec_bpf_mask' => [ 'default' => '2', 'required' => true ], /* after processing */
        'net.enc.in.ipsec_filter_mask' => [ 'default' => '2', 'required' => true ], /* after processing */
        'net.enc.out.ipsec_bpf_mask' => [ 'default' => '1', 'required' => true ], /* before processing */
        'net.enc.out.ipsec_filter_mask' => [ 'default' => '1', 'required' => true ], /* before processing */
        'net.inet.icmp.drop_redirect' => [ 'default' => '1', 'required' => true ],
        'net.inet.icmp.icmplim' => [ 'default' => '0' ],
        'net.inet.icmp.log_redirect' => [ 'default' => '0' ],
        'net.inet.icmp.reply_from_interface' => [ 'default' => '1', 'required' => true ],
        'net.inet.ip.accept_sourceroute' => [ 'default' => '0' ],
        'net.inet.ip.forwarding' => [ 'default' => '1', 'required' => true ],
        'net.inet.ip.intr_queue_maxlen' => [ 'default' => '1000', 'required' => true ],
        'net.inet.ip.portrange.first' => [ 'default' => '1024' ],
        'net.inet.ip.random_id' => [ 'default' => '1' ],
        'net.inet.ip.redirect' => [ 'default' => '0' ],
        'net.inet.ip.sourceroute' => [ 'default' => '0' ],
        'net.inet.tcp.blackhole' => [ 'default' => '2' ],
        'net.inet.tcp.delayed_ack' => [ 'default' => '0' ],
        'net.inet.tcp.drop_synfin' => [ 'default' => '1' ],
        'net.inet.tcp.log_debug' => [ 'default' => '0' ],
        'net.inet.tcp.recvspace' => [ 'default' => '65228' ],
        'net.inet.tcp.sendspace' => [ 'default' => '65228' ],
        'net.inet.tcp.syncookies' => [ 'default' => '1' ],
        'net.inet.tcp.tso' => [ 'default' => '1' ],
        'net.inet.udp.blackhole' => [ 'default' => '1' ],
        'net.inet.udp.checksum' => [ 'default' => 1 ],
        'net.inet.udp.maxdgram' => [ 'default' => '57344' ],
        'net.inet6.ip6.accept_rtadv' => [ 'default' => isset($config['system']['ipv6allow']) ? '1' : '0', 'required' => true ],
        'net.inet6.ip6.forwarding' => [ 'default' => '1', 'required' => true ],
        'net.inet6.ip6.intr_queue_maxlen' => [ 'default' => '1000', 'required' => true ],
        'net.inet6.ip6.prefer_tempaddr' => [ 'default' => '0' ],
        'net.inet6.ip6.redirect' => [ 'default' => '0' ],
        'net.inet6.ip6.rfc6204w3' => [ 'default' => isset($config['system']['ipv6allow']) ? '1' : '0', 'required' => true ],
        'net.inet6.ip6.use_tempaddr' => [ 'default' => '0' ],
        'net.link.bridge.pfil_bridge' => [ 'default' => '0' ],
        'net.link.bridge.pfil_local_phys' => [ 'default' => '0' ],
        'net.link.bridge.pfil_member' => [ 'default' => '1' ],
        'net.link.bridge.pfil_onlyip' => [ 'default' => '0' ],
        'net.link.ether.inet.log_arp_movements' => [ 'default' => isset($config['system']['sharednet']) ? '0' : '1', 'required' => true ],
        'net.link.ether.inet.log_arp_wrong_iface' => [ 'default' => isset($config['system']['sharednet']) ? '0' : '1', 'required' => true ],
        'net.link.tap.user_open' => [ 'default' => '1' ],
        'net.link.vlan.mtag_pcp' => [ 'default' => '1', 'required' => true ],
        'net.local.dgram.maxdgram' => [ 'default' => '8192', 'required' => true ],
        'net.pf.share_forward' => [ 'default' => !empty($config['system']['pf_share_forward']) ? '1' : '0', 'required' => true ],
        'net.pf.share_forward6' => [ 'default' => !empty($config['system']['pf_share_forward']) ? '1' : '0', 'required' => true ],
        'net.route.multipath' => [ 'default' => '0', 'required' => true ],
        'security.bsd.see_other_gids' => [ 'default' => '0' ],
        'security.bsd.see_other_uids' => [ 'default' => '0' ],
        'vfs.read_max' => [ 'default' => '32' ],
        'vfs.zfs.dirty_data_sync_percent' => [ 'default' => '5', 'required' => true ],
        'vfs.zfs.txg.timeout' => [ 'default' => '90', 'required' => true ],
        'vm.pmap.pti' => [ 'default' => '1' ],
    ];
}

function system_sysctl_get()
{
    $defaults = system_sysctl_defaults();
    $sysctls = [];

    foreach ($defaults as $name => $info) {
        /* compile list of required sysctls not necessarily present in config */
        if (!empty($info['required'])) {
            $sysctls[$name] = 'default';
        }
    }

    foreach (config_read_array('sysctl', 'item') as $tunable) {
        $sysctls[$tunable['tunable']] = $tunable['value'];
    }

    foreach ($sysctls as $key => &$value) {
        if ($value != 'default') {
            continue;
        }

        if (!empty($defaults[$key])) {
            $value = $defaults[$key]['default'];
        } else {
            log_msg('warning: ignoring missing default tunable request: ' . $key, LOG_WARNING);
            unset($sysctls[$key]);
        }
    }

    ksort($sysctls);

    return $sysctls;
}

function system_resolvconf_host_routes()
{
    $routes = [];

    foreach (get_nameservers(null, true) as $dnsserver) {
        if (isset($routes[$dnsserver['host']])) {
            log_msg("Duplicated DNS route ignored for {$dnsserver['host']} on {$dnsserver['interface']}", LOG_WARNING);
            continue;
        }

        $routes[$dnsserver['host']] = $dnsserver['gateway'];
    }

    return $routes;
}

function system_resolvconf_generate($verbose = false)
{
    service_log('Generating /etc/resolv.conf...', $verbose);

    $syscfg = config_read_array('system');
    $resolvconf = '';
    $routes = [];
    $search = [];

    mwexecf(
        '/etc/rc.d/ip6addrctl %s',
        isset($syscfg['prefer_ipv4']) ? 'prefer_ipv4' : 'prefer_ipv6'
    );

    if (!empty($syscfg['domain'])) {
        $resolvconf = "domain {$syscfg['domain']}\n";
    }

    if (!isset($syscfg['dnslocalhost']) && !empty(service_by_filter(['dns_ports' => '53']))) {
        $resolvconf .= "nameserver 127.0.0.1\n";
    }

    $routes = system_resolvconf_host_routes();

    foreach (array_keys($routes) as $host) {
        $resolvconf .= "nameserver {$host}\n";
    }

    $search = get_searchdomains();

    if (count($search)) {
        $result = $search[0];
        /* resolv.conf search keyword limit is 6 domains, 256 characters */
        foreach (range(2, 6) as $len) {
            if (count($search) < $len) {
                break;
            }
            $temp = implode(' ', array_slice($search, 0, $len));
            if (strlen($temp) >= 256) {
                break;
            }
            $result = $temp;
        }
        $resolvconf .= "search {$result}\n";
    }

    $tempfile = tempnam('/tmp', 'resolv.conf');
    file_put_contents($tempfile, $resolvconf);
    chmod($tempfile, 0644);

    rename($tempfile, '/etc/resolv.conf');

    /* setup static routes for DNS servers as configured */
    foreach ($routes as $host => $gateway) {
        if (!empty($gateway)) {
            system_host_route($host, $gateway);
        }
    }

    service_log("done.\n", $verbose);
}

function get_locale_list()
{
    $locales = [];

    /* first one is the default */
    $locales['en_US'] = gettext('English');
    $locales['zh_CN'] = gettext('Chinese (Simplified)');
    $locales['zh_TW'] = gettext('Chinese (Traditional)');
    $locales['cs_CZ'] = gettext('Czech');
    $locales['fr_FR'] = gettext('French');
    $locales['de_DE'] = gettext('German');
    $locales['it_IT'] = gettext('Italian');
    $locales['ja_JP'] = gettext('Japanese');
    $locales['ko_KR'] = gettext('Korean');
    $locales['no_NO'] = gettext('Norwegian');
    $locales['pl_PL'] = gettext('Polish');
    $locales['pt_BR'] = gettext('Portuguese (Brazil)');
    $locales['pt_PT'] = gettext('Portuguese (Portugal)');
    $locales['ru_RU'] = gettext('Russian');
    $locales['es_ES'] = gettext('Spanish');
    $locales['tr_TR'] = gettext('Turkish');
    $locales['vi_VN'] = gettext('Vietnamese');

    if (!product::getInstance()->development()) {
        /* below 30% progress or currently unvetted */
        unset($locales['vi_VN']);
    }

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
    $syscfg = config_read_array('system');
    $master_list = [];
    $search_list = [];

    if (!empty($syscfg['domain'])) {
        $master_list[] = $syscfg['domain'];
    }

    if (!empty($syscfg['dnssearchdomain'])) {
        if ($syscfg['dnssearchdomain'] == '.') {
            /* pass root only */
            return [$syscfg['dnssearchdomain']];
        }

        /* add custom search entries after default domain before potential provider entries */
        $master_list[] = $syscfg['dnssearchdomain'];
    }

    if (isset($syscfg['dnsallowoverride'])) {
        /* return domains as required by configuration */
        $list = shell_safe('/usr/local/sbin/ifctl -sl');
        if (!empty($list)) {
            $search_list = explode("\n", $list);
        }
    }

    foreach ($search_list as $fdns) {
        $contents = file($fdns, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (is_array($contents)) {
            foreach ($contents as $dns) {
                if (is_fqdn($dns)) {
                    $master_list[] = $dns;
                }
            }
        }
    }

    return array_unique($master_list);
}

function get_nameservers($interface = null, $with_gateway = false)
{
    global $config;

    $gateways = new \OPNsense\Routing\Gateways();
    $syscfg = config_read_array('system');
    $exclude_interfaces = [];
    $master_list = [];
    $dns_lists = [];

    if (!empty($interface)) {
        /* only acquire servers provided for this interface */
        $realif = get_real_interface($interface);
        $realifv6 = get_real_interface($interface, 'inet6');
        $list = shell_safe('/usr/local/sbin/ifctl -4nli %s', $realif);
        if (!empty($list)) {
            $dns_lists[] = $list;
        }
        $list = shell_safe('/usr/local/sbin/ifctl -6nli %s', $realifv6);
        if (!empty($list)) {
            $dns_lists[] = $list;
        }
    } elseif (isset($syscfg['dnsallowoverride'])) {
        /* return dynamic servers as required by configuration */
        $list = shell_safe('/usr/local/sbin/ifctl -nl');
        if (!empty($list)) {
            $dns_lists = explode("\n", $list);
        }
    }

    if (isset($syscfg['dnsallowoverride_exclude'])) {
        /* by design this only works on dynamic servers, not static ones */
        foreach (explode(',', $syscfg['dnsallowoverride_exclude']) as $intf) {
            if (isset($config['interfaces'][$intf])) {
                $exclude_interfaces[] = get_real_interface($intf);
                $exclude_interfaces[] = get_real_interface($intf, 'inet6');
            }
        }
    }

    foreach ($dns_lists as $fdns) {
        /* inspect dynamic servers registered in the system files */
        $intf = [];

        /* XXX this is ifctl logic we eventually need to pass down */
        $intf[0] = preg_replace('/(_[^_]+|:.+)$/', '', basename($fdns));
        $intf[1] = preg_replace('/^.+_/', '', basename($fdns));
        $intf[1] = strpos($intf[1], 'v6') === false ? '4' : '6';

        if (in_array($intf[0], $exclude_interfaces)) {
            continue;
        }

        $contents = @file($fdns, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!is_array($contents)) {
            continue;
        }

        if ($with_gateway) {
            /* router file is available for connectivity creating nameserver files */
            $gw = shell_safe('/usr/local/sbin/ifctl -%s -ri %s', [$intf[1], $intf[0]]);
            if (is_linklocal($gw) && strpos($gw, '%') === false) {
                $gw .= "%{$intf[0]}";
            }
        }

        foreach ($contents as $dns) {
            if (empty($dns) || !is_ipaddr($dns)) {
                continue;
            }

            if ($with_gateway) {
                $master_list[] = [
                    'host' => $dns,
                    'gateway' => $gw,
                    'interface' => convert_real_interface_to_friendly_interface_name($intf[0]),
                    'source' => 'interface',
                ];
            } else {
                $master_list[] = $dns;
            }
        }
    }

    for ($dnscounter = 1; $dnscounter < 9; $dnscounter++) {
        /* inspect static servers and optional gateway assignment */
        $dns = $syscfg['dnsserver'][$dnscounter - 1] ?? null;
        if (empty($dns) || !is_ipaddr($dns)) {
            continue;
        }

        $gwkey = "dns{$dnscounter}gw";
        $gateway = null;
        $dnsif = null;

        if (!empty($syscfg[$gwkey]) && $syscfg[$gwkey] != 'none') {
            /* if a gateway is attached figure out the interface and address */
            $dnsif = $gateways->getInterfaceName($syscfg[$gwkey]);
            $dnsgw = $gateways->getAddress($syscfg[$gwkey]);

            if (empty($dnsif) || empty($dnsgw)) {
                /* do not present servers for defunct gateways */
                continue;
            }

            if (!empty($interface) && $interface != $dnsif) {
                /* not looking for this one */
                continue;
            }

            $gateway = $dnsgw;
        } elseif (!empty($interface)) {
            /* discard because not attached to any interface */
            continue;
        }

        if ($with_gateway) {
            $master_list[] = [
                'host' => $dns,
                'gateway' => $gateway,
                'interface' => $dnsif,
                'source' => 'config',
            ];
        } else {
            $master_list[] = $dns;
        }
    }

    if (!$with_gateway) {
        $master_list = array_unique($master_list);
    }

    return $master_list;
}

function system_hosts_generate($verbose = false)
{
    service_log('Generating /etc/hosts...', $verbose);

    $syscfg = config_read_array('system');

    $hosts = "127.0.0.1\tlocalhost\tlocalhost.{$syscfg['domain']}\n";
    $hosts .= "::1\t\tlocalhost\tlocalhost.{$syscfg['domain']}\n";

    $if = get_primary_interface_from_list();
    if (!empty($if)) {
        $cfgip = get_interface_ip($if);
        if (!empty($cfgip)) {
            $hosts .= "{$cfgip}\t{$syscfg['hostname']}\t{$syscfg['hostname']}.{$syscfg['domain']}\n";
        }
        $cfgip = get_interface_ipv6($if);
        if (!empty($cfgip)) {
            $hosts .= "{$cfgip}\t{$syscfg['hostname']}\t{$syscfg['hostname']}.{$syscfg['domain']}\n";
        }
    }

    file_put_contents('/etc/hosts', $hosts);

    service_log("done.\n", $verbose);
}

function system_resolver_configure($verbose = false)
{
    system_resolvconf_generate($verbose);
    system_hosts_generate($verbose);
}

function system_hostname_configure($verbose = false)
{
    service_log('Setting hostname: ', $verbose);

    $syscfg = config_read_array('system');

    $hostname = "{$syscfg['hostname']}.{$syscfg['domain']}";

    mwexecf('/bin/hostname %s', $hostname);

    service_log("{$hostname}\n", $verbose);
}

function system_host_route($host, $gateway)
{
    if (is_ipaddrv4($gateway)) {
        $family = 'inet';
    } elseif (is_ipaddrv6($gateway)) {
        $family = 'inet6';
    } else {
        log_msg("ROUTING: not a valid host gateway address: '{$gateway}'", LOG_ERR);
        return;
    }

    /*
     * If the gateway is the same as the host we do not touch the route
     * as this is not needed and it may also break the routing table.
     */
    if ($host == $gateway) {
        return;
    }

    mwexecf('/sbin/route delete -host -%s %s', [$family, $host], true);
    mwexecf('/sbin/route add -host -%s %s %s', [$family, $host, $gateway]);
}

function system_interface_route($gw, $routes)
{
    $interface = $gw['interface'];
    $gateway = $gw['gateway'] ?? 'missing';
    $far = !empty($gw['fargw']);
    $device = $gw['if'];
    $family = 'inet';

    if (!is_ipaddrv4($gateway)) {
        log_msg("ROUTING: not a valid {$interface} interface gateway address: '{$gateway}'", LOG_ERR);
        return;
    }

    if (!$far) {
        /* special case tries to turn on far gateway when required for dynamic gateway */
        $dynamicgw = trim(@file_get_contents("/tmp/{$device}_router") ?? '');
        if (!empty($dynamicgw) && $gateway === $dynamicgw) {
            list (, $network) = interfaces_primary_address($interface);
            if (!empty($network) && !ip_in_subnet($gateway, $network)) {
                /*
                 * If we do not fail primary network detection and the local address
                 * is in not the same network as the gateway address set far flag.
                 */
                $far = 1;

                log_msg("ROUTING: treating '{$gateway}' as far gateway for '{$network}'");
            }
        }
    }

    if (!$far) {
        /* nothing to do */
        return;
    }

    foreach ($routes as $route) {
        /* find host routes on the link for the underlying device */
        if ($route['netif'] != $device) {
            continue;
        } elseif ($route['proto'] != 'ipv4') {
            continue;
        } elseif (strpos($route['gateway'], 'link#') !== 0) {
            continue;
        /* XXX may consider "S" as well for manually assigned */
        } elseif (strpos($route['flags'], 'H') === false) {
            continue;
        }

        /* keeping previous, but do not log this noisy operation */
        return;
    }

    log_msg("ROUTING: setting {$family} interface route to {$gateway} via {$device}");

    /* never remove interface routes -- we only try to add if missing */
    mwexecf('/sbin/route add -%s %s -interface %s', [$family, $gateway, $device]);
}

function system_default_route($gw, $routes)
{
    $interface = $gw['interface'];
    $gateway = $gw['gateway'];
    $device = $gw['if'];

    if (is_ipaddrv4($gateway)) {
        $family = 'inet';
    } elseif (is_ipaddrv6($gateway)) {
        $family = 'inet6';
    } else {
        log_msg("ROUTING: not a valid default gateway address: '{$gateway}'", LOG_ERR);
        return;
    }

    if (is_linklocal($gateway)) {
        /* XXX always inet6 but why not do this elsewhere */
        $gateway .= "%{$device}";
    }

    foreach ($routes as $route) {
        /* find out if the default route matches what we want to set */
        if ($route['proto'] != ($family == 'inet6' ? 'ipv6' : 'ipv4')) {
            continue;
        } elseif ($route['destination'] != 'default') {
            continue;
        } elseif ($route['gateway'] != $gateway) {
            continue;
        }

        log_msg("ROUTING: keeping {$family} default route to {$gateway}");
        return;
    }

    log_msg("ROUTING: setting {$family} default route to {$gateway}");

    mwexecf('/sbin/route delete -%s default', [$family], true);
    mwexecf('/sbin/route add -%s default %s', [$family, $gateway]);
}

function system_routing_configure($verbose = false, $interface = null, $monitor = true, $family = null)
{
    global $config;

    service_log(sprintf('Setting up routes%s...', empty($interface) ? '' : " for {$interface}"), $verbose);

    if (!empty($interface)) {
        log_msg("ROUTING: entering configure using '{$interface}'", LOG_DEBUG);
    } else {
        log_msg("ROUTING: entering configure using defaults", LOG_DEBUG);
    }

    $ifdetails = legacy_interfaces_details();
    $gateways = new \OPNsense\Routing\Gateways();
    $down_gateways = isset($config['system']['gw_switch_default']) ? return_down_gateways() : [];
    $routes = json_decode(configd_run('interface routes list -n json'), true) ?? [];

    foreach ($gateways->gatewaysIndexedByName() as $gateway) {
        /* check if we need to add a required interface route to the gateway (IPv4 only) */
        if ($gateway['ipprotocol'] !== 'inet' || ($family !== null && $family !== 'inet')) {
            continue;
        } elseif (!empty($interface) && $interface != $gateway['interface']) {
            continue;
        }

        if (empty($ifdetails[$gateway['if']]['ipv4'][0])) {
            log_msg("ROUTING: refusing to set interface route on addressless {$gateway['interface']}({$gateway['if']})", LOG_WARNING);
            continue;
        }

        system_interface_route($gateway, $routes);
    }

    if (!empty($down_gateways)) {
        log_msg(sprintf('ROUTING: ignoring down gateways: %s', implode(', ', $down_gateways)), LOG_DEBUG);
    }

    foreach (['inet' => 'ipv4', 'inet6' => 'ipv6'] as $ipproto => $type) {
        if ($family !== null && $family !== $ipproto) {
            continue;
        }

        $gateway = $gateways->getDefaultGW($down_gateways, $ipproto);
        if (empty($gateway['gateway'])) {
            continue;
        }

        if (isset($config['system']['gw_switch_default']) || empty($interface) || $interface == $gateway['interface']) {
            if (empty($ifdetails[$gateway['if']][$type][0])) {
                log_msg("ROUTING: refusing to set {$ipproto} gateway on addressless {$gateway['interface']}({$gateway['if']})", LOG_ERR);
                continue;
            }

            log_msg("ROUTING: configuring {$ipproto} default gateway on {$gateway['interface']}", LOG_INFO);

            system_default_route($gateway, $routes);
        }
    }

    $static_routes = get_staticroutes(false);
    if (count($static_routes)) {
        $gateways_arr = $gateways->gatewaysIndexedByName(false, true);
        foreach ($static_routes as $rtent) {
            if (empty($gateways_arr[$rtent['gateway']])) {
                log_msg(sprintf('ROUTING: gateway IP could not be found for %s', $rtent['network']), LOG_WARNING);
                continue;
            }
            $gateway = $gateways_arr[$rtent['gateway']];
            if (!empty($interface) && $interface != $gateway['interface']) {
                continue;
            }

            if (is_subnetv4($rtent['network'])) {
                $ipproto = 'inet';
            } elseif (is_subnetv6($rtent['network'])) {
                $ipproto = 'inet6';
            } else {
                log_msg(sprintf('ROUTING: cannot add static route to "%s"', $rtent['network']), LOG_ERR);
                continue;
            }

            if ($family !== null && $family !== $ipproto) {
                continue;
            }

            $interfacegw = $gateway['if'];
            $gatewayip = $gateway['gateway'] ?? '';
            $fargw = !empty($gateway['fargw']) && $gateway['ipprotocol'] != 'inet6';
            $blackhole = '';

            switch ($rtent['gateway']) {
                case 'Null4':
                case 'Null6':
                    $blackhole = '-blackhole';
                    break;
                default:
                    break;
            }

            /* XXX some day we might want to convert to safer mwexecf() */
            $cmd = " -{$ipproto} {$blackhole} " . escapeshellarg($rtent['network']) . " ";

            if (!empty($rtent['disabled'])) {
                mwexec("/sbin/route delete {$cmd}", true);
            } else {
                if (is_ipaddr($gatewayip)) {
                    mwexec("/sbin/route delete {$cmd}", true);
                    if ($fargw) {
                        mwexecf('/sbin/route delete -%s %s -interface %s ', [$ipproto, $gatewayip, $interfacegw], true);
                        mwexecf('/sbin/route add -%s %s -interface %s', [$ipproto, $gatewayip, $interfacegw]);
                    } elseif (is_linklocal($gatewayip) && strpos($gatewayip, '%') === false) {
                        $gatewayip .= "%{$interfacegw}";
                    }
                    mwexec("/sbin/route add" . $cmd . escapeshellarg($gatewayip));
                } elseif (!empty($interfacegw)) {
                    mwexec("/sbin/route delete" . $cmd . "-interface " . escapeshellarg($interfacegw), true);
                    mwexec("/sbin/route add" . $cmd . "-interface " . escapeshellarg($interfacegw));
                }
            }
        }
    }

    service_log("done.\n", $verbose);

    if ($monitor) {
        if (!empty($interface)) {
            $reloads = [];

            foreach ($gateways->gatewaysIndexedByName() as $name => $gateway) {
                if ($family !== null && $family !== $gateway['ipprotocol']) {
                    continue;
                }

                if ($interface == $gateway['interface']) {
                    $reloads[] = $name;
                }
            }

            foreach ($reloads as $reload) {
                plugins_configure('monitor', $verbose, [$reload]);
            }
        } else {
            plugins_configure('monitor', $verbose);
        }
    }
}

function system_syslog_start($verbose = false)
{
    service_log('Configuring system logging...', $verbose);

    configd_run('template reload OPNsense/Syslog');
    mwexecf('/usr/local/opnsense/scripts/syslog/generate_certs');

    $last_version = @file_get_contents('/var/run/syslog-ng.version');
    $this_version = shell_safe('syslog-ng -V | sha256');

    if (isvalidpid('/var/run/syslog-ng.pid') && $last_version == $this_version) {
        mwexecf('/usr/local/sbin/syslog-ng-ctl reload');
    } else {
        mwexecf('/usr/sbin/service syslog-ng restart');
    }

    file_put_contents('/var/run/syslog-ng.version', $this_version);

    service_log("done.\n", $verbose);
}

function system_syslog_stop()
{
    mwexecf('/usr/sbin/service syslog-ng stop');
}

function system_syslog_reset($verbose = false)
{
    $it = new RecursiveDirectoryIterator('/var/log');

    foreach (new RecursiveIteratorIterator($it) as $file) {
        if ($file->isFile() && strpos($file->getFilename(), '.log') > -1) {
            if (strpos($file->getFilename(), 'flowd') === false) {
                @unlink((string)$file);
            }
        }
    }

    system_syslog_start($verbose);
    plugins_configure('dhcp', $verbose);
}

/*
 *     get_memory()
 *     returns an array listing the amount of
 *     memory installed in the hardware
 *     [0] net memory available for the OS (FreeBSD) after some is taken by BIOS, video or whatever - e.g. 235 MBytes
 *     [1] real (actual) memory of the system, should be the size of the RAM card/s - e.g. 256 MBytes
 */
function get_memory()
{
    $physmem = get_single_sysctl("hw.physmem");
    $realmem = get_single_sysctl("hw.realmem");
    /* convert from bytes to megabytes */
    return array(($physmem / 1048576),($realmem / 1048576));
}

function system_firmware_configure($verbose = false)
{
    service_log('Writing firmware settings:', $verbose);

    $scripts = glob('/usr/local/opnsense/scripts/firmware/repos/*');
    natsort($scripts);

    foreach ($scripts as $script) {
        $basename = basename($script);
        if ($basename == 'README' || strpos($basename, '.pgksave') !== false) {
            continue;
        } elseif (!is_executable($script)) {
            continue;
        }

         /* run the script in passthru() but avoid standard output from this side */
         passthru($script . '> /dev/null');

        /* make a note about repo being handled */
        service_log(' ' . preg_replace('/\..*?$/', '', $basename));
    }

    service_log("\n");
}

function system_trust_configure($verbose = false)
{
    global $config;

    service_log('Writing trust files...', $verbose);

    /*
     * Write separate files because certcl will blacklist the whole file
     * if the first certificate matches.  Unfortunately the blacklisting
     * can also be harmful because it overrides the user choice given
     * through our GUI autorities section.
     *
     * 1. Clear all files (a subdirectory is not supported)
     * 2. Write all files
     * 3. Execute rehash to fill /etc/ssl/certs
     * 4. Pull all content from /etc/ssl/certs
     * 5. Write bundle files with all /etc/ssl/certs content
     */

    $ca_files = '/usr/local/share/certs/ca-root-opnsense-%s';

    foreach (glob(sprintf($ca_files, '*')) as $ca_unlink) {
        @unlink($ca_unlink);
    }

    foreach (config_read_array('ca') as $i => $entry) {
        if (!empty($entry['crt'])) {
            // Split and cleans ca certificates, one entry could contain multiple certs if a user imported a bundle
            // avoid expired ca's from being considered as valid alternatives.
            $certlist = str_replace("\r", '', base64_decode($entry['crt']));
            $user_cas = [""];
            foreach (explode("\n", $certlist) as $row) {
                $user_cas[count($user_cas) - 1] .= $row . "\n";
                if (strpos($row, '---END') > 0) {
                    $user_cas[] = "";
                }
            }
            $ca = "# OPNsense trust authority: {$entry['descr']}\n";
            $ca_count = 0;
            $include_intermediates = !empty($config['system']['store_intermediate_certs']);
            foreach ($user_cas as $user_ca) {
                if (!empty(trim($user_ca))) {
                    $certinfo = @openssl_x509_parse($user_ca);
                    $certext = !empty($certinfo['extensions']) ? $certinfo['extensions'] : [];
                    $authoritykey = "";
                    if (!empty($certext['authorityKeyIdentifier'])) {
                        $authoritykey = trim(str_replace('keyid:', '', preg_split("/\r\n|\n|\r/", $certext['authorityKeyIdentifier'])[0]));
                    }
                    $subjectkey = $certext['subjectKeyIdentifier'];
                    $is_self_signed = empty($authoritykey) || $subjectkey == $authoritykey;

                    $error_line = "";
                    if (!empty($certinfo['validTo']) && $certinfo['validTo_time_t'] < time()) {
                        $error_line = sprintf(
                            "(system local trust) refusing to import %s from %s (expired @ %s)",
                            $certinfo['name'],
                            $entry['descr'],
                            date('r', $certinfo['validFrom_time_t'])
                        );
                    } elseif (!$include_intermediates && !$is_self_signed) {
                        $error_line = sprintf(
                            "(system local trust) skip intermediate certificate %s from %s",
                            $certinfo['name'],
                            $entry['descr']
                        );
                    }
                    if (!empty($error_line)) {
                        syslog(LOG_NOTICE, $error_line);
                        $ca .= "#" .  str_replace("\n", "", $error_line);
                    } else {
                        $ca .= $user_ca;
                        $ca_count += 1;
                    }
                }
            }

            if ($ca_count) {
                $ca_file = sprintf($ca_files, $i . '.crt');
                file_put_contents(sprintf($ca_file, $i), $ca);
                chmod(sprintf($ca_file, $i), 0644);
            }
        }
    }

    service_log("done.\n", $verbose);

    /* collects all trusted certificates into /etc/ssl/certs directory */
    passthru('/usr/local/opnsense/scripts/system/certctl.py rehash');

    service_log('Writing trust bundles...', $verbose);

    /* collect all the file content and write it to compatibility bundle locations */
    $ca_bundle = [];
    foreach (glob('/etc/ssl/certs/*.[0-9]') as $file) {
        $ca_bundle[] = file_get_contents($file);
    }
    $ca_bundle = join("\n", $ca_bundle);
    foreach (['/etc/ssl/cert.pem', '/usr/local/etc/ssl/cert.pem', '/usr/local/openssl/cert.pem'] as $pem) {
        @unlink($pem); /* do not clobber symlink target */
        file_put_contents($pem, $ca_bundle);
        chmod($pem, 0644);
    }

    configd_run('template reload OPNsense/Trust');

    service_log("done.\n", $verbose);
}

function system_timezone_configure($verbose = false)
{
    $syscfg = config_read_array('system');

    service_log('Setting timezone: ', $verbose);

    /* extract appropriate timezone file */
    $timezone = $syscfg['timezone'];
    $timezones = get_zoneinfo();

    /* reset to default if empty or nonexistent */
    if (
        empty($timezone) || !in_array($timezone, $timezones) ||
        !file_exists(sprintf('/usr/share/zoneinfo/%s', $timezone))
    ) {
        $timezone = 'Etc/UTC';
    }

    /* apply timezone */
    if (file_exists(sprintf('/usr/share/zoneinfo/%s', $timezone))) {
        copy(sprintf('/usr/share/zoneinfo/%s', $timezone), '/etc/localtime');
    }

    service_log("{$timezone}\n", $verbose);
}

function system_sysctl_configure($verbose = false)
{
    service_log('Setting up extended sysctls...', $verbose);

    $todo = [];
    $new_values = system_sysctl_get();
    $current_values = get_sysctl(array_keys($new_values));
    foreach ($new_values as $prop => $value) {
        if (isset($current_values[$prop]) && $current_values[$prop] != $value) {
            $todo[$prop] = $value;
        }
    }
    set_sysctl($todo);

    service_log("done.\n", $verbose);
}

function system_kernel_configure($verbose = false)
{
    global $config;

    service_log('Configuring kernel modules...', $verbose);

    /*
     * Vital kernel modules can go missing on reboot due to
     * /boot/loader.conf not materialising.  This is still
     * an UFS problem, despite claims otherwise.  In any case,
     * load all the modules again to make sure.
     *
     * Keep in sync with /usr/local/etc/erc.loader.d/20-modules
     */
    $mods = [
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
    ];

    if (!empty($config['system']['crypto_hardware'])) {
        foreach (explode(',', $config['system']['crypto_hardware']) as $crypto) {
            log_msg(sprintf('Loading %s cryptographic accelerator module.', $crypto), LOG_INFO);
            $mods[] = $crypto;
        }
    }

    if (!empty($config['system']['thermal_hardware'])) {
        log_msg(sprintf('Loading %s thermal monitor module.', $config['system']['thermal_hardware']), LOG_INFO);
        $mods[] = $config['system']['thermal_hardware'];
    }

    foreach ($mods as $mod) {
        mwexecf('/sbin/kldload %s', $mod, true);
    }

    /* we now have /dev/pf, time to fix permissions for proxies */
    chgrp('/dev/pf', 'proxy');
    chmod('/dev/pf', 0660);

    service_log("done.\n", $verbose);
}

function system_devd_configure($verbose = false)
{
    service_log('Starting device manager...', $verbose);

    mwexec('/sbin/devd');
    /* historic sleep */
    sleep(1);

    service_log("done.\n", $verbose);
}

function system_cron_configure($verbose = false)
{
    function generate_cron_job($command, $minute = '0', $hour = '*', $monthday = '*', $month = '*', $weekday = '*')
    {
        $cron_item = [];

        $cron_item['minute'] = $minute;
        $cron_item['hour'] = $hour;
        $cron_item['mday'] = $monthday;
        $cron_item['month'] = $month;
        $cron_item['wday'] = $weekday;
        $cron_item['command'] = $command;

        return $cron_item;
    }

    $autocron = [];

    service_log('Configuring CRON...', $verbose);

    foreach (plugins_cron() as $cron_plugin) {
        /*
         * We are stuffing jobs inside 'autocron' to be able to
         * deprecate this at a later time.  Ideally all of the
         * services should use a single cron-model, which this is
         * not.  At least this plugin function helps us to divide
         * and conquer the code bits...  :)
         */
        if (!empty($cron_plugin['autocron'])) {
            $autocron[] = call_user_func_array('generate_cron_job', $cron_plugin['autocron']);
        }
    }

    $crontab_contents = "# DO NOT EDIT THIS FILE -- OPNsense auto-generated file\n";
    $crontab_contents .= "#\n";
    $crontab_contents .= "# User-defined crontab files can be loaded via /etc/cron.d\n";
    $crontab_contents .= "# or /usr/local/etc/cron.d and follow the same format as\n";
    $crontab_contents .= "# /etc/crontab, see the crontab(5) manual page.\n";
    $crontab_contents .= "SHELL=/bin/sh\n";
    $crontab_contents .= "PATH=/etc:/bin:/sbin:/usr/bin:/usr/sbin:/usr/local/bin:/usr/local/sbin\n";
    $crontab_contents .= "REQUESTS_CA_BUNDLE=/etc/ssl/cert.pem\n";
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

    configd_run('template reload OPNsense/Cron');

    mwexec('/etc/rc.d/cron restart');

    service_log("done.\n", $verbose);
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

function system_console_speed()
{
    global $config;

    $speed = 115200;

    if (!empty($config['system']['serialspeed']) && is_numeric($config['system']['serialspeed'])) {
        $speed = $config['system']['serialspeed'];
    }

    return $speed;
}

function system_login_configure($verbose = false)
{
    global $config;

    service_log('Configuring login behaviour...', $verbose);

    /* depends on user account locking */
    local_sync_accounts();

    $serialspeed = system_console_speed();

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
        $serial_options = ["-S{$serialspeed}"];
        if ($console_selection[0] == 'comconsole') {
            $serial_options[] = '-h';
        }
        if (in_array('vidconsole', $console_selection)) {
            $serial_options[] = '-D';
        }
        @file_put_contents('/boot.config', join(' ', $serial_options) . "\n");
        $new_boot_config['comconsole_speed'] = '"' . $serialspeed . '"';
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

    $new_loader_conf .= "# dynamically generated console settings follow\n";
    foreach ($new_boot_config as $param => $value) {
        if (!empty($value)) {
            $new_loader_conf .= "{$param}={$value}\n";
        } else {
            $new_loader_conf .= "#{$param}\n";
        }
    }
    $new_loader_conf .= "\n";

    $new_loader_conf .= "# dynamically generated tunables settings follow\n";
    foreach (system_sysctl_get() as $param => $value) {
        $new_loader_conf .= "{$param}=\"{$value}\"\n";
    }

    /* write merged file back to target location */
    @file_put_contents('/boot/loader.conf', $new_loader_conf);

    /* setup /etc/ttys */
    $etc_ttys_lines = explode("\n", file_get_contents('/etc/ttys'));
    $fd = fopen('/etc/ttys', 'w');

    $on_off_secure_u = $serial_enabled ? (!empty($config['system']['serialusb']) ? 'on' : 'onifconsole') . ' secure' : 'off secure';
    $on_off_secure_v = $virtual_enabled ? 'onifexists secure' : 'off secure';
    if (isset($config['system']['disableconsolemenu'])) {
        $console_type = 'Pc';
        $serial_type = '3wire.' . $serialspeed;
    } else {
        $console_type = 'al.Pc';
        $serial_type = 'al.3wire.' . $serialspeed;
    }

    foreach ($etc_ttys_lines as $tty) {
        /* virtual terminals */
        foreach (['ttyv0', 'ttyv1', 'ttyv2', 'ttyv3', 'ttyv4', 'ttyv5', 'ttyv6', 'ttyv7'] as $virtualport) {
            if (strpos($tty, $virtualport) === 0) {
                fwrite($fd, "{$virtualport}\t\"/usr/libexec/getty {$console_type}\"\t\txterm\t{$on_off_secure_v}\n");
                continue 2;
            }
        }

        /* serial terminals */
        foreach (['tty%s0', 'tty%s1', 'tty%s2', 'tty%s3'] as $serialport) {
            $serialport = sprintf($serialport, !empty($config['system']['serialusb']) ? 'U' : 'u');
            if (stripos($tty, $serialport) === 0) {
                fwrite($fd, "{$serialport}\t\"/usr/libexec/getty {$serial_type}\"\tvt100\t{$on_off_secure_u}\n");
                continue 2;
            }
        }

        /* xen terminal */
        if (strpos($tty, 'xc0') === 0) {
            $on_off_secure_w = $virtual_enabled ? 'onifconsole secure' : 'off secure';
            fwrite($fd, "xc0\t\"/usr/libexec/getty {$console_type}\"\t\txterm\t{$on_off_secure_w}\n");
            continue;
        }

        /* XXX rcons currently not tied to serial setting */

        if (!empty($tty)) {
            /* all other lines stay the same */
            fwrite($fd, $tty . "\n");
        }
    }

    fclose($fd);

    configd_run('template reload OPNsense/Auth');

    service_log("done.\n", $verbose);

    /* force init(8) to reload /etc/ttys */
    mwexec('/bin/kill -HUP 1');
}

function reset_factory_defaults($sync = true)
{
    mwexec('/bin/rm -fr /conf/* /var/log/* /root/.history');
    mwexec('/usr/local/sbin/opnsense-beep stop');

    /* as we go through a special case directly shut down */
    $shutdown_cmd = '/sbin/shutdown -op now';
    if ($sync) {
        mwexec($shutdown_cmd);
        while (true) {
            sleep(1);
        }
    } else {
        mwexec_bg($shutdown_cmd);
    }
}
