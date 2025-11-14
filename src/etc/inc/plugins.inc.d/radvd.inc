<?php

/*
 * Copyright (C) 2014-2024 Franco Fichtner <franco@opnsense.org>
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
    ];
}

function radvd_enabled()
{
    global $config;
    $explicit_off = [];

    /* handle manually configured DHCP6 server settings first */
    foreach (config_read_array('dhcpdv6') as $dhcpv6if => $dhcpv6ifconf) {
        if (isset($config['interfaces'][$dhcpv6if]['enable']) && isset($dhcpv6ifconf['ramode']) && $dhcpv6ifconf['ramode'] != 'disabled') {
            return true;
        } elseif (isset($dhcpv6ifconf['ramode']) && $dhcpv6ifconf['ramode'] == 'disabled') {
            $explicit_off[] = $dhcpv6if;
        }
    }
    /* handle DHCP-PD prefixes and 6RD dynamic interfaces */
    foreach (legacy_config_get_interfaces(array('virtual' => false)) as $ifnm => $ifcfg) {
        if (in_array($ifnm, $explicit_off)) {
            continue;
        }
        if (isset($ifcfg['enable']) && !empty($ifcfg['track6-interface']) && !isset($ifcfg['dhcpd6track6allowoverride'])) {
            return true;
        }
    }

    return false;
}

function radvd_services()
{
    $services = [];

    if (radvd_enabled()) {
        $pconfig = [];
        $pconfig['name'] = 'radvd';
        $pconfig['description'] = gettext('Router Advertisement Daemon');
        $pconfig['php']['restart'] = ['radvd_configure_do'];
        $pconfig['php']['start'] = ['radvd_configure_do'];
        $pconfig['pidfile'] = '/var/run/radvd.pid';
        $services[] = $pconfig;
    }

    return $services;
}

function radvd_configure_dhcp($verbose = false, $family = null, $blacklist = [])
{
    if ($family == null || $family == 'inet6') {
        radvd_configure_do($verbose, $blacklist);
    }
}

function radvd_configure_do($verbose = false, $blacklist = [])
{
    global $config;

    $radvd_conf_file = '/var/etc/radvd.conf';
    $radvd_pid_file = '/var/run/radvd.pid';

    if (!radvd_enabled()) {
        killbypid($radvd_pid_file);
        return;
    }

    service_log('Starting router advertisement service...', $verbose);

    $ifconfig_details = legacy_interfaces_details();
    $radvdconf = "# Automatically generated, do not edit\n";

    /* Process all links which need the router advertise daemon */
    $radvdifs = array();

    /* handle manually configured DHCP6 server settings first */
    foreach (config_read_array('dhcpdv6') as $dhcpv6if => $dhcpv6ifconf) {
        if (isset($config['interfaces'][$dhcpv6if]['track6-interface']) && !isset($config['interfaces'][$dhcpv6if]['dhcpd6track6allowoverride'])) {
            /* handled by automatic case */
            continue;
        } elseif (!isset($config['interfaces'][$dhcpv6if]['enable'])) {
            $radvdconf .= "# Skipping disabled interface {$dhcpv6if}\n";
            continue;
        } elseif (isset($blacklist[$dhcpv6if])) {
            $radvdconf .= "# Skipping blacklisted interface {$dhcpv6if}\n";
            continue;
        } elseif (!isset($dhcpv6ifconf['ramode']) || $dhcpv6ifconf['ramode'] == 'disabled') {
            $radvdconf .= "# Skipping unset interface {$dhcpv6if}\n";
            continue;
        }

        $carp_mode = false;
        $src_addr = false;

        $ifcfgipv6 = get_interface_ipv6(!empty($dhcpv6ifconf['rainterface']) ? $dhcpv6ifconf['rainterface'] : $dhcpv6if);
        if (!is_ipaddrv6($ifcfgipv6) && !isset($config['interfaces'][$dhcpv6if]['dhcpd6track6allowoverride'])) {
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

        if (!empty($dhcpv6ifconf['AdvLinkMTU']) && !empty($mtu)) {
            if ($dhcpv6ifconf['AdvLinkMTU'] < $mtu) {
                $mtu = $dhcpv6ifconf['AdvLinkMTU'];
            } else {
                log_msg("Skipping AdvLinkMTU configuration since it cannot be applied on {$dhcpv6if}", LOG_WARNING);
            }
        }

        $radvdconf .= "# Generated RADVD config for manual assignment on {$dhcpv6if}\n";
        $radvdconf .= "interface {$device} {\n";
        $radvdconf .= "\tAdvSendAdvert on;\n";
        $radvdconf .= sprintf("\tMinRtrAdvInterval %s;\n", !empty($dhcpv6ifconf['ramininterval']) ? $dhcpv6ifconf['ramininterval'] : '200');
        $radvdconf .= sprintf("\tMaxRtrAdvInterval %s;\n", !empty($dhcpv6ifconf['ramaxinterval']) ? $dhcpv6ifconf['ramaxinterval'] : '600');
        if (!empty($dhcpv6ifconf['AdvDefaultLifetime'])) {
            $radvdconf .= sprintf("\tAdvDefaultLifetime %s;\n", $dhcpv6ifconf['AdvDefaultLifetime']);
        }
        $radvdconf .= sprintf("\tAdvLinkMTU %s;\n", !empty($mtu) ? $mtu : 0);

        switch ($dhcpv6ifconf['rapriority']) {
            case "low":
                $radvdconf .= "\tAdvDefaultPreference low;\n";
                break;
            case "high":
                $radvdconf .= "\tAdvDefaultPreference high;\n";
                break;
            default:
                $radvdconf .= "\tAdvDefaultPreference medium;\n";
                break;
        }

        switch ($dhcpv6ifconf['ramode']) {
            case 'assist':
            case 'managed':
                $radvdconf .= "\tAdvManagedFlag on;\n";
                $radvdconf .= "\tAdvOtherConfigFlag on;\n";
                break;
            case 'stateless':
                $radvdconf .= "\tAdvManagedFlag off;\n";
                $radvdconf .= "\tAdvOtherConfigFlag on;\n";
                break;
            default:
                break;
        }

        if (!empty($dhcpv6ifconf['ranodefault'])) {
            $radvdconf .= "\tAdvDefaultLifetime 0;\n";
        }

        $stanzas = [];

        list (, $networkv6) = interfaces_primary_address6($dhcpv6if, $ifconfig_details);
        if (is_subnetv6($networkv6)) {
            $stanzas[] = $networkv6;
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
            $radvdconf .= "\tAdvRASrcAddress {\n";
            $radvdconf .= "\t\t{$ifcfgipv6};\n";
            $radvdconf .= "\t};\n";
        }

        if ($carp_mode) {
            /* to avoid wrong MAC being stuck during failover */
            $radvdconf .= "\tAdvSourceLLAddress off;\n";
            /* to avoid final advertisement with zero router lifetime */
            $radvdconf .= "\tRemoveAdvOnExit off;\n";
        }

        /* VIPs may duplicate readings from system */
        $stanzas = array_unique($stanzas);

        foreach ($stanzas as $stanza) {
            $radvdconf .= "\tprefix {$stanza} {\n";
            $radvdconf .= "\t\tDeprecatePrefix " . (!empty($dhcpv6ifconf['AdvDeprecatePrefix']) ? $dhcpv6ifconf['AdvDeprecatePrefix'] : ($carp_mode ? 'off' : 'on')) . ";\n";
            switch ($dhcpv6ifconf['ramode']) {
                case 'assist':
                case 'stateless':
                case 'unmanaged':
                    $radvdconf .= "\t\tAdvOnLink on;\n";
                    $radvdconf .= "\t\tAdvAutonomous on;\n";
                    break;
                case 'managed':
                    $radvdconf .= "\t\tAdvOnLink on;\n";
                    $radvdconf .= "\t\tAdvAutonomous off;\n";
                    break;
                case 'router':
                    $radvdconf .= "\t\tAdvOnLink off;\n";
                    $radvdconf .= "\t\tAdvAutonomous off;\n";
                    break;
                default:
                    break;
            }
            if (!empty($dhcpv6ifconf['AdvValidLifetime'])) {
                $radvdconf .= sprintf("\t\tAdvValidLifetime %s;\n", $dhcpv6ifconf['AdvValidLifetime']);
            }
            if (!empty($dhcpv6ifconf['AdvPreferredLifetime'])) {
                $radvdconf .= sprintf("\t\tAdvPreferredLifetime %s;\n", $dhcpv6ifconf['AdvPreferredLifetime']);
            }
            $radvdconf .= "\t};\n";
        }

        if (!empty($dhcpv6ifconf['raroutes'])) {
            foreach (explode(',', $dhcpv6ifconf['raroutes']) as $raroute) {
                $radvdconf .= "\troute {$raroute} {\n";
                $radvdconf .= "\t\tRemoveRoute " . (!empty($dhcpv6ifconf['AdvRemoveRoute']) ? $dhcpv6ifconf['AdvRemoveRoute'] : ($carp_mode ? 'off' : 'on')) . ";\n";
                if (!empty($dhcpv6ifconf['AdvRouteLifetime'])) {
                    $radvdconf .= "\t\tAdvRouteLifetime {$dhcpv6ifconf['AdvRouteLifetime']};\n";
                }
                $radvdconf .= "\t};\n";
            }
        }

        $dnslist = [];
        $dnssl = null;

        /* advertise both DNS servers and domains via RA (RFC 8106) if allowed */
        if (!isset($dhcpv6ifconf['radisablerdnss'])) {
            $dnslist_tmp = [];

            if (isset($dhcpv6ifconf['rasamednsasdhcp6']) && !empty($dhcpv6ifconf['dnsserver'][0])) {
                $dnslist_tmp = $dhcpv6ifconf['dnsserver'];
            } elseif (!isset($dhcpv6ifconf['rasamednsasdhcp6']) && !empty($dhcpv6ifconf['radnsserver'][0])) {
                $dnslist_tmp = $dhcpv6ifconf['radnsserver'];
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
                if (count($dnslist) >= 3) {
                    log_msg("The radvd RDNSS entry $server cannot be added due to too many addresses.", LOG_WARNING);
                    continue;
                }
                $dnslist[] = $server;
            }

            if (isset($dhcpv6ifconf['rasamednsasdhcp6']) && !empty($dhcpv6ifconf['domainsearchlist'])) {
                $dnssl = implode(' ', explode(';', $dhcpv6ifconf['domainsearchlist']));
            } elseif (!isset($dhcpv6ifconf['rasamednsasdhcp6']) && !empty($dhcpv6ifconf['radomainsearchlist'])) {
                $dnssl = implode(' ', explode(';', $dhcpv6ifconf['radomainsearchlist']));
            } elseif (!empty($config['system']['domain'])) {
                $dnssl = $config['system']['domain'];
            }
        }

        if (count($dnslist) > 0) {
            $radvdconf .= "\tRDNSS " . implode(" ", $dnslist) . " {\n";
            if (!empty($dhcpv6ifconf['AdvRDNSSLifetime'])) {
                $radvdconf .= "\t\tAdvRDNSSLifetime {$dhcpv6ifconf['AdvRDNSSLifetime']};\n";
            }
            $radvdconf .= "\t};\n";
        }

        if (!empty($dnssl)) {
            $radvdconf .= "\tDNSSL {$dnssl} {\n";
            if (!empty($dhcpv6ifconf['AdvDNSSLLifetime'])) {
                $radvdconf .= "\t\tAdvDNSSLLifetime {$dhcpv6ifconf['AdvDNSSLLifetime']};\n";
            }
            $radvdconf .= "\t};\n";
        }

        $radvdconf .= "};\n";
    }

    /* handle DHCP-PD prefixes and 6RD dynamic interfaces */
    foreach (array_keys(get_configured_interface_with_descr()) as $if) {
        if (!isset($config['interfaces'][$if]['track6-interface']) || isset($config['interfaces'][$if]['dhcpd6track6allowoverride'])) {
            /* handled by manual case */
            continue;
        } elseif (empty($config['interfaces'][$config['interfaces'][$if]['track6-interface']])) {
            $radvdconf .= "# Skipping defunct interface {$if}\n";
            continue;
        } elseif (!isset($config['interfaces'][$if]['enable'])) {
            $radvdconf .= "# Skipping disabled interface {$if}\n";
            continue;
        } elseif (isset($blacklist[$if])) {
            $radvdconf .= "# Skipping blacklisted interface {$if}\n";
            continue;
        } elseif (!empty($config['dhcpdv6'][$if]) && !empty($config['dhcpdv6'][$if]['ramode']) && $config['dhcpdv6'][$if]['ramode'] == 'disabled') {
            $radvdconf .= "# Skipping explicit disabled interface {$if}\n";
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
        $radvdconf .= "\tAdvSendAdvert on;\n";
        $radvdconf .= sprintf("\tAdvLinkMTU %s;\n", !empty($mtu) ? $mtu : 0);
        $radvdconf .= "\tAdvManagedFlag on;\n";
        $radvdconf .= "\tAdvOtherConfigFlag on;\n";

        if (!empty($networkv6)) {
            $radvdconf .= "\tprefix {$networkv6} {\n";
            $radvdconf .= "\t\tDeprecatePrefix on;\n";
            $radvdconf .= "\t\tAdvOnLink on;\n";
            $radvdconf .= "\t\tAdvAutonomous on;\n";
            $radvdconf .= "\t};\n";
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

            $radvdconf .= "\tprefix {$vipnetv6} {\n";
            $radvdconf .= "\t\tDeprecatePrefix on;\n";
            $radvdconf .= "\t\tAdvOnLink on;\n";
            $radvdconf .= "\t\tAdvAutonomous on;\n";
            $radvdconf .= "\t};\n";
        }

        if (count($dnslist) > 0) {
            $radvdconf .= "\tRDNSS " . implode(" ", $dnslist) . " { };\n";
        }
        if (!empty($config['system']['domain'])) {
            $radvdconf .= "\tDNSSL {$config['system']['domain']} { };\n";
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
            mwexecf('/usr/local/sbin/radvd -p %s -C %s -m syslog', [
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
