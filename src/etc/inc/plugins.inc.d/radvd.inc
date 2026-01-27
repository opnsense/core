<?php

/*
 * Copyright (C) 2014-2026 Franco Fichtner <franco@opnsense.org>
 * Copyright (C) 2010 Ermal Luçi
 * Copyright (C) 2005-2006 Colin Smith <ethethlay@gmail.com>
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

function radvd_configure()
{
    return [
        'dhcp' => ['radvd_configure_dhcp:3'],
        'local' => ['radvd_configure_do'],
        'radvd' => ['radvd_configure_do'],
    ];
}

function radvd_enabled($mdl)
{
    global $config;

    $explicit_off = [];

    /* handle manually configured DHCP6 server settings first */
    foreach ($mdl->entries->iterateItems() as $entry) {
        $ifnm = $entry->interface->getValue();
        if (isset($config['interfaces'][$ifnm]['enable']) && !$entry->enabled->isEmpty()) {
            return true;
        } elseif ($entry->enabled->isEmpty()) {
            $explicit_off[] = $ifnm;
        }
    }

    /* handle DHCP-PD prefixes and 6RD dynamic interfaces */
    foreach (legacy_config_get_interfaces(['virtual' => false]) as $ifnm => $ifcfg) {
        if (in_array($ifnm, $explicit_off)) {
            continue;
        }
        if (isset($ifcfg['enable']) && ($ifcfg['ipaddrv6'] ?? 'none') == 'track6' && !isset($ifcfg['dhcpd6track6allowoverride'])) {
            return true;
        }
    }

    return false;
}

function radvd_services()
{
    $mdl = new OPNsense\Radvd\Radvd();
    $services = [];

    if (radvd_enabled($mdl)) {
        $pconfig = [];
        $pconfig['name'] = 'radvd';
        $pconfig['description'] = gettext('Router Advertisements');
        $pconfig['php']['restart'] = ['radvd_configure_do'];
        $pconfig['php']['start'] = ['radvd_configure_do'];
        $pconfig['pidfile'] = '/var/run/radvd.pid';
        $services[] = $pconfig;
    }

    return $services;
}

function radvd_xmlrpc_sync()
{
    return [[
        'help' => gettext('Synchronize router advertisements.'),
        'description' => gettext('Router Advertisements'),
        'section' => 'OPNsense.radvd',
        'services' => ['radvd'],
        'id' => 'radvd',
    ]];
}

function radvd_configure_dhcp($verbose = false, $family = null, $ignorelist = [])
{
    if ($family == null || $family == 'inet6') {
        radvd_configure_do($verbose, $ignorelist);
    }
}

function radvd_configure_do($verbose = false, $ignorelist = [])
{
    global $config;

    $radvd_conf_file = '/var/etc/radvd.conf';
    $radvd_pid_file = '/var/run/radvd.pid';
    $mdl = new OPNsense\Radvd\Radvd();

    if (!radvd_enabled($mdl)) {
        killbypid($radvd_pid_file);
        @unlink($radvd_conf_file);
        @unlink($radvd_pid_file);
        return;
    }

    service_log('Starting router advertisement service...', $verbose);

    $ifconfig_details = legacy_interfaces_details();
    $radvdconf = "# Automatically generated, do not edit\n";

    /* Process all links which need the router advertise daemon */
    $radvdifs = [];

    /* handle manually configured DHCP6 server settings first */
    foreach ($mdl->entries->iterateItems() as $entry) {
        $dhcpv6if = $entry->interface->getValue();

        if (($config['interfaces'][$dhcpv6if]['ipaddrv6'] ?? 'none') == 'track6' && !isset($config['interfaces'][$dhcpv6if]['dhcpd6track6allowoverride'])) {
            /* need to check now to disable automatic case below */
            if ($entry->enabled->isEmpty()) {
                $ignorelist[$dhcpv6if] = '1';
            }
            continue;
        } elseif (!isset($config['interfaces'][$dhcpv6if]['enable'])) {
            $radvdconf .= "# Skipping disabled interface {$dhcpv6if}\n";
            continue;
        } elseif (isset($ignorelist[$dhcpv6if])) {
            $radvdconf .= "# Skipping ignored interface {$dhcpv6if}\n";
            continue;
        } elseif ($entry->enabled->isEmpty()) {
            $radvdconf .= "# Skipping unset interface {$dhcpv6if}\n";
            continue;
        }

        $carp_mode = false;
        $src_addr = false;
        $ifcfgipv6 = null;

        if (!$entry->AdvRASrcAddress->isEmpty()) {
            $ifcfgipv6 = $entry->AdvRASrcAddress->getValue();
        } elseif (!$entry->Base6Interface->isEmpty()) {
            $ifcfgipv6 = get_interface_ipv6($entry->Base6Interface->getValue());
        } else {
            $ifcfgipv6 = get_interface_ipv6($dhcpv6if);
        }

        if (!is_ipaddrv6($ifcfgipv6)) {
            $radvdconf .= "# Skipping addressless interface {$dhcpv6if}\n";
            continue;
        }

        $device = get_real_interface($dhcpv6if, 'inet6');
        $radvdifs[$device] = 1;

        $mtu = legacy_interface_stats($device)['mtu'];

        if (isset($config['interfaces'][$dhcpv6if]['track6-interface'])) {
            $realtrackif = get_real_interface($config['interfaces'][$dhcpv6if]['track6-interface'], 'inet6');

            $trackmtu = legacy_interface_stats($realtrackif)['mtu'];
            if (!empty($trackmtu) && !empty($mtu)) {
                if ($trackmtu < $mtu) {
                    $mtu = $trackmtu;
                }
            }
        }

        if (!$entry->AdvLinkMTU->isEmpty() && !empty($mtu)) {
            if ($entry->AdvLinkMTU->getValue() < $mtu) {
                $mtu = $entry->AdvLinkMTU->getValue();
            } else {
                log_msg("Skipping AdvLinkMTU configuration since it cannot be applied on {$dhcpv6if}", LOG_WARNING);
            }
        }
        $radvdconf .= "# Generated RADVD config for manual assignment on {$dhcpv6if}\n";
        $radvdconf .= "interface {$device} {\n";
        $radvdconf .= "    AdvSendAdvert on;\n";
        $radvdconf .= "    MinRtrAdvInterval {$entry->MinRtrAdvInterval->getValue()};\n";
        $radvdconf .= "    MaxRtrAdvInterval {$entry->MaxRtrAdvInterval->getValue()};\n";
        if ($entry->AdvDefaultLifetime->isSet()) {
            $radvdconf .= "    AdvDefaultLifetime {$entry->AdvDefaultLifetime->getValue()};\n";
        }
        $radvdconf .= sprintf("    AdvLinkMTU %s;\n", !empty($mtu) ? $mtu : 0);
        $radvdconf .= "    AdvDefaultPreference {$entry->AdvDefaultPreference->getValue()};\n";

        switch ($entry->mode->getValue()) {
            case 'assist':
            case 'managed':
                $radvdconf .= "    AdvManagedFlag on;\n";
                $radvdconf .= "    AdvOtherConfigFlag on;\n";
                break;
            case 'stateless':
                $radvdconf .= "    AdvManagedFlag off;\n";
                $radvdconf .= "    AdvOtherConfigFlag on;\n";
                break;
            default:
                break;
        }

        $stanzas = [];

        if (!$entry->Base6Interface->isEmpty()) {
            $stanzas[] = 'base6';
        } else {
            list (, $networkv6) = interfaces_primary_address6($dhcpv6if, $ifconfig_details);
            if (is_subnetv6($networkv6)) {
                $stanzas[] = $networkv6;
            }
        }

        foreach (config_read_array('virtualip', 'vip') as $vip) {
            if ($vip['interface'] != $dhcpv6if || !is_ipaddrv6($vip['subnet'])) {
                continue;
            }

            if (is_linklocal($vip['subnet'])) {
                if ($ifcfgipv6 == $vip['subnet']) {
                    $carp_mode = !empty($vip['vhid']);
                    $src_addr = true;
                }
                continue;
            }

            if ($vip['subnet_bits'] == '128' || !empty($vip['nobind'])) {
                continue;
            }

            /* force subnet to 64 as per radvd complaint "prefix length should be 64 for xzy" */
            $subnetv6 = gen_subnetv6($vip['subnet'], 64);
            $stanzas[] = "{$subnetv6}/64";
        }

        if ($src_addr) {
            /* inject configured link-local address into the RA message */
            $radvdconf .= "    AdvRASrcAddress {\n";
            $radvdconf .= "        {$ifcfgipv6};\n";
            $radvdconf .= "    };\n";
        }

        if ($carp_mode) {
            /* to avoid wrong MAC being stuck during failover */
            $radvdconf .= "    AdvSourceLLAddress off;\n";
        }

        /* to avoid final advertisement with zero router lifetime */
        $radvdconf .= "    RemoveAdvOnExit " . (!$entry->RemoveAdvOnExit->isEmpty() ? $entry->RemoveAdvOnExit->getValue() : ($carp_mode ? 'off' : 'on')) . ";\n";

        /* VIPs may duplicate readings from system */
        $stanzas = array_unique($stanzas);

        foreach ($stanzas as $stanza) {
            if ($stanza === 'base6') {
                $radvdconf .= "    prefix ::/64 {\n";
                $baseif = get_real_interface($entry->Base6Interface->getValue(), 'inet6');
                $radvdconf .= "        Base6Interface {$baseif};\n";
            } else {
                $radvdconf .= "    prefix {$stanza} {\n";
            }
            $radvdconf .= "        DeprecatePrefix " . (!$entry->DeprecatePrefix->isEmpty() ? $entry->DeprecatePrefix->getValue() : ($carp_mode ? 'off' : 'on')) . ";\n";
            switch ($entry->mode->getValue()) {
                case 'assist':
                case 'stateless':
                case 'unmanaged':
                    $radvdconf .= "        AdvOnLink on;\n";
                    $radvdconf .= "        AdvAutonomous on;\n";
                    break;
                case 'managed':
                    $radvdconf .= "        AdvOnLink on;\n";
                    $radvdconf .= "        AdvAutonomous off;\n";
                    break;
                case 'router':
                    $radvdconf .= "        AdvOnLink off;\n";
                    $radvdconf .= "        AdvAutonomous off;\n";
                    break;
                default:
                    break;
            }
            if (!$entry->AdvValidLifetime->isEmpty()) {
                $radvdconf .= "        AdvValidLifetime {$entry->AdvValidLifetime->getValue()};\n";
            }
            if (!$entry->AdvPreferredLifetime->isEmpty()) {
                $radvdconf .= "        AdvPreferredLifetime {$entry->AdvPreferredLifetime->getValue()};\n";
            }
            $radvdconf .= "    };\n";
        }

        if (!$entry->routes->isEmpty()) {
            foreach ($entry->routes->getValues() as $raroute) {
                $radvdconf .= "    route {$raroute} {\n";
                $radvdconf .= "        RemoveRoute " . (!$entry->RemoveRoute->isEmpty() ? $entry->RemoveRoute->getValue() : ($carp_mode ? 'off' : 'on')) . ";\n";
                if (!$entry->AdvRouteLifetime->isEmpty()) {
                    $radvdconf .= "        AdvRouteLifetime {$entry->AdvRouteLifetime->getValue()};\n";
                }
                $radvdconf .= "    };\n";
            }
        }

        if (!$entry->nat64prefix->isEmpty()) {
            $radvdconf .= "    nat64prefix {$entry->nat64prefix->getValue()} {\n";
            $radvdconf .= "    };\n";
        }

        $dnssl = [];
        $rdnss = [];

        /* advertise both DNS servers and domains via RA (RFC 8106) if allowed */
        if (!$entry->dns->isEmpty()) {
            $searchlist_tmp = $entry->DNSSL->getValues();
            $dnslist_tmp = $entry->RDNSS->getValues();

            if (count($dnslist_tmp)) {
                /* take these servers then */
            } elseif (!empty(service_by_filter(['dns_ports' => '53']))) {
                if (is_ipaddrv6($ifcfgipv6)) {
                    $dnslist_tmp[] = $ifcfgipv6;
                } else {
                    log_msg("radvd_configure_do(manual) found no suitable IPv6 address on {$dhcpv6if}({$device})", LOG_WARNING);
                }
            } elseif (!empty($config['system']['dnsserver'][0])) {
                $dnslist_tmp = $config['system']['dnsserver'];
            }

            foreach ($dnslist_tmp as $server) {
                if (!is_ipaddrv6($server)) {
                    continue;
                }
                if (count($rdnss) >= 3) {
                    log_msg("The radvd RDNSS entry $server cannot be added due to too many addresses.", LOG_WARNING);
                    continue;
                }
                $rdnss[] = $server;
            }

            if (count($searchlist_tmp)) {
                $dnssl = $searchlist_tmp;
            } elseif (!empty($config['system']['domain'])) {
                $dnssl = [$config['system']['domain']];
            }
        }

        if (count($rdnss)) {
            $radvdconf .= "    RDNSS " . implode(" ", $rdnss) . " {\n";
            if (!$entry->AdvRDNSSLifetime->isEmpty()) {
                $radvdconf .= "        AdvRDNSSLifetime {$entry->AdvRDNSSLifetime->getValue()};\n";
            }
            $radvdconf .= "    };\n";
        }

        if (count($dnssl)) {
            $radvdconf .= "    DNSSL " . implode(" ", $dnssl) . " {\n";
            if (!$entry->AdvDNSSLLifetime->isEmpty()) {
                $radvdconf .= "        AdvDNSSLLifetime {$entry->AdvDNSSLLifetime->getValue()};\n";
            }
            $radvdconf .= "    };\n";
        }

        $radvdconf .= "};\n";
    }

    /* handle DHCP-PD prefixes and 6RD dynamic interfaces */
    foreach (array_keys(get_configured_interface_with_descr()) as $if) {
        if (($config['interfaces'][$if]['ipaddrv6'] ?? 'none') != 'track6' || isset($config['interfaces'][$if]['dhcpd6track6allowoverride'])) {
            /* handled by manual case */
            continue;
        } elseif (empty($config['interfaces'][$config['interfaces'][$if]['track6-interface']])) {
            $radvdconf .= "# Skipping defunct interface {$if}\n";
            continue;
        } elseif (!isset($config['interfaces'][$if]['enable'])) {
            $radvdconf .= "# Skipping disabled interface {$if}\n";
            continue;
        } elseif (isset($ignorelist[$if])) {
            $radvdconf .= "# Skipping ignored interface {$if}\n";
            continue;
        }

        $trackif = $config['interfaces'][$if]['track6-interface'];
        $device = get_real_interface($if, 'inet6');

        /* prevent duplicate entries, manual overrides */
        if (isset($radvdifs[$device])) {
            continue;
        }

        $autotype = isset($config['interfaces'][$trackif]['ipaddrv6']) ? $config['interfaces'][$trackif]['ipaddrv6'] : 'unknown';

        if (!in_array($autotype, ['6rd', '6to4', 'dhcp6'])) {
            $radvdconf .= "# Skipping unsupported {$autotype} interface {$if}\n";
            continue;
        }

        $radvdifs[$device] = 1;

        $realtrackif = get_real_interface($trackif, 'inet6');

        $mtu = legacy_interface_stats($device)['mtu'];
        $trackmtu = legacy_interface_stats($realtrackif)['mtu'];
        if (!empty($trackmtu) && !empty($mtu)) {
            if ($trackmtu < $mtu) {
                $mtu = $trackmtu;
            }
        }

        $dnslist = [];

        list ($ifcfgipv6, $networkv6) = interfaces_primary_address6($if, $ifconfig_details);

        if (!empty(service_by_filter(['dns_ports' => '53']))) {
            if (is_ipaddrv6($ifcfgipv6)) {
                $dnslist[] = $ifcfgipv6;
            } else {
                log_msg("radvd_configure_do(auto) found no suitable IPv6 address on {$if}({$device})", LOG_WARNING);
            }
        } elseif (!empty($config['system']['dnsserver'])) {
            foreach ($config['system']['dnsserver'] as $server) {
                if (!is_ipaddrv6($server)) {
                    continue;
                }
                if (count($dnslist) >= 3) {
                    log_msg("The radvd RDNSS entry $server cannot be added due to too many addresses.", LOG_WARNING);
                    continue;
                }
                $dnslist[] = $server;
            }
        }

        $radvdconf .= "# Generated RADVD config for {$autotype} assignment from {$trackif} on {$if}\n";
        $radvdconf .= "interface {$device} {\n";
        $radvdconf .= "    AdvSendAdvert on;\n";
        $radvdconf .= sprintf("    AdvLinkMTU %s;\n", !empty($mtu) ? $mtu : 0);
        $radvdconf .= "    AdvManagedFlag on;\n";
        $radvdconf .= "    AdvOtherConfigFlag on;\n";

        if (!empty($networkv6)) {
            $radvdconf .= "    prefix {$networkv6} {\n";
            $radvdconf .= "        DeprecatePrefix on;\n";
            $radvdconf .= "        AdvOnLink on;\n";
            $radvdconf .= "        AdvAutonomous on;\n";
            $radvdconf .= "    };\n";
        }

        foreach (config_read_array('virtualip', 'vip') as $vip) {
            if ($vip['interface'] != $if || !is_ipaddrv6($vip['subnet']) || $vip['subnet_bits'] == '128') {
                continue;
            }

            if (is_linklocal($vip['subnet']) || !empty($vip['nobind'])) {
                continue;
            }

            /* force subnet to 64 as per radvd complaint "prefix length should be 64 for xzy" */
            $subnetv6 = gen_subnetv6($vip['subnet'], 64);
            $vipnetv6 = "{$subnetv6}/64";

            if ($vipnetv6 == $networkv6) {
                continue;
            }

            $radvdconf .= "    prefix {$vipnetv6} {\n";
            $radvdconf .= "        DeprecatePrefix on;\n";
            $radvdconf .= "        AdvOnLink on;\n";
            $radvdconf .= "        AdvAutonomous on;\n";
            $radvdconf .= "    };\n";
        }

        if (count($dnslist) > 0) {
            $radvdconf .= "    RDNSS " . implode(" ", $dnslist) . " { };\n";
        }
        if (!empty($config['system']['domain'])) {
            $radvdconf .= "    DNSSL {$config['system']['domain']} { };\n";
        }
        $radvdconf .= "};\n";
    }

    file_safe($radvd_conf_file, $radvdconf);

    if (count($radvdifs)) {
        $last_version = @file_get_contents("{$radvd_conf_file}.last");
        $this_version = shell_safe('/bin/cat %s | sha256', $radvd_conf_file);

        if (isvalidpid($radvd_pid_file) && $last_version == $this_version) {
            killbypid($radvd_pid_file, 'HUP');
        } else {
            killbypid($radvd_pid_file);
            file_safe("{$radvd_conf_file}.last", $this_version);
            mwexecf('/usr/local/sbin/radvd -d1 -p %s -C %s -m syslog', [
                $radvd_pid_file,
                $radvd_conf_file,
            ]);
        }
    } else {
        /* stop on invalid configuration for legacy condition above */
        killbypid($radvd_pid_file);
        @unlink("{$radvd_conf_file}.last");
    }

    service_log("done.\n", $verbose);
}
