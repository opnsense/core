<?php

/*
 * Copyright (C) 2014-2021 Franco Fichtner <franco@opnsense.org>
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

function dhcpd_configure()
{
    return array(
        'dhcp' => array('dhcpd_dhcp_configure:3'),
        'dhcrelay' => array('dhcpd_dhcrelay_configure:2'),
        'local' => array('dhcpd_dhcp_configure', 'dhcpd_dhcrelay_configure'),
    );
}

function dhcpd_radvd_enabled()
{
    global $config;

    /* handle manually configured DHCP6 server settings first */
    foreach (config_read_array('dhcpdv6') as $dhcpv6if => $dhcpv6ifconf) {
        if (isset($config['interfaces'][$dhcpv6if]['enable']) && isset($dhcpv6ifconf['ramode']) && $dhcpv6ifconf['ramode'] != 'disabled') {
            return true;
        }
    }

    /* handle DHCP-PD prefixes and 6RD dynamic interfaces */
    foreach (legacy_config_get_interfaces(array('virtual' => false)) as $ifcfg) {
        if (isset($ifcfg['enable']) && !empty($ifcfg['track6-interface']) && !isset($ifcfg['dhcpd6track6allowoverride'])) {
            return true;
        }
    }

    return false;
}

function dhcpd_dhcpv6_enabled()
{
    global $config;

    /* handle manually configured DHCP6 server settings first */
    foreach (config_read_array('dhcpdv6') as $dhcpv6if => $dhcpv6ifconf) {
        if (isset($config['interfaces'][$dhcpv6if]['enable']) && isset($dhcpv6ifconf['enable'])) {
            return true;
        }
    }

    /* handle DHCP-PD prefixes and 6RD dynamic interfaces */
    foreach (legacy_config_get_interfaces(array('virtual' => false)) as $ifcfg) {
        if (isset($ifcfg['enable']) && !empty($ifcfg['track6-interface']) && !isset($ifcfg['dhcpd6track6allowoverride'])) {
            return true;
        }
    }

    return false;
}

function dhcpd_dhcpv4_enabled()
{
    global $config;

    foreach (config_read_array('dhcpd') as $dhcpif => $dhcpifconf) {
        if (isset($dhcpifconf['enable']) && !empty($config['interfaces'][$dhcpif])) {
            return true;
        }
    }

    return false;
}

function dhcpd_services()
{
    global $config;

    $services = array();

    if (dhcpd_radvd_enabled()) {
        $pconfig = array();
        $pconfig['name'] = "radvd";
        $pconfig['description'] = gettext("Router Advertisement Daemon");
        $pconfig['php']['restart'] = array('dhcpd_radvd_configure');
        $pconfig['php']['start'] = array('dhcpd_radvd_configure');
        $pconfig['pidfile'] = '/var/run/radvd.pid';
        $services[] = $pconfig;
    }

    if (isset($config['dhcrelay']['enable'])) {
        $pconfig = array();
        $pconfig['name'] = "dhcrelay";
        $pconfig['description'] = gettext("DHCPv4 Relay");
        $pconfig['php']['restart'] = array('dhcpd_dhcrelay4_configure');
        $pconfig['php']['start'] = array('dhcpd_dhcrelay4_configure');
        $pconfig['pidfile'] = '/var/run/dhcrelay.pid';
        $services[] = $pconfig;
    }

    if (isset($config['dhcrelay6']['enable'])) {
        $pconfig = array();
        $pconfig['name'] = "dhcrelay6";
        $pconfig['description'] = gettext("DHCPv6 Relay");
        $pconfig['php']['restart'] = array('dhcpd_dhcrelay6_configure');
        $pconfig['php']['start'] = array('dhcpd_dhcrelay6_configure');
        $pconfig['pidfile'] = '/var/run/dhcrelay6.pid';
        $services[] = $pconfig;
    }

    if (dhcpd_dhcpv4_enabled()) {
        $pconfig = array();
        $pconfig['name'] = 'dhcpd';
        $pconfig['description'] = gettext("DHCPv4 Server");
        $pconfig['php']['restart'] = array('dhcpd_dhcp4_configure');
        $pconfig['php']['start'] = array('dhcpd_dhcp4_configure');
        $pconfig['pidfile'] = '/var/dhcpd/var/run/dhcpd.pid';
        $services[] = $pconfig;
    }

    if (dhcpd_dhcpv6_enabled()) {
        $pconfig = array();
        $pconfig['name'] = 'dhcpd6';
        $pconfig['description'] = gettext("DHCPv6 Server");
        $pconfig['php']['restart'] = array('dhcpd_dhcp6_configure');
        $pconfig['php']['start'] = array('dhcpd_dhcp6_configure');
        $pconfig['pidfile'] = '/var/dhcpd/var/run/dhcpdv6.pid';
        $services[] = $pconfig;
    }

    return $services;
}

function dhcpd_generate_ipv6_from_mac($mac)
{
    $elements = explode(":", $mac);
    if (count($elements) != 6) {
        return false;
    }

    $i = 0;
    $ipv6 = "fe80::";
    foreach ($elements as $byte) {
        if ($i == 0) {
            $hexadecimal = substr($byte, 1, 2);
            $bitmap = base_convert($hexadecimal, 16, 2);
            $bitmap = str_pad($bitmap, 4, '0', STR_PAD_LEFT);
            $bitmap = substr($bitmap, 0, 2) . '1' . substr($bitmap, 3, 4);
            $byte = substr($byte, 0, 1) . base_convert($bitmap, 2, 16);
        }
        $ipv6 .= $byte;
        if ($i == 1) {
            $ipv6 .= ":";
        }
        if ($i == 3) {
            $ipv6 .= ":";
        }
        if ($i == 2) {
            $ipv6 .= "ff:fe";
        }

        $i++;
    }
    return $ipv6;
}

function dhcpd_get_pppoes_child_interfaces($ifpattern)
{
    $if_arr = array();
    if ($ifpattern == "") {
        return;
    }

    exec("ifconfig", $out, $ret);
    foreach ($out as $line) {
        if (preg_match("/^({$ifpattern}[0-9]+):/i", $line, $match)) {
            $if_arr[] = $match[1];
        }
    }
    return $if_arr;
}

function dhcpd_radvd_configure($verbose = false, $blacklist = array())
{
    global $config;

    killbypid('/var/run/radvd.pid', 'TERM', true);

    if (!dhcpd_radvd_enabled()) {
        return;
    }

    if ($verbose) {
        echo 'Starting router advertisement service...';
        flush();
    }

    $ifconfig_details = legacy_interfaces_details();
    $radvdconf = "# Automatically generated, do not edit\n";

    /* Process all links which need the router advertise daemon */
    $radvdifs = array();

    /* handle manually configured DHCP6 server settings first */
    foreach (config_read_array('dhcpdv6') as $dhcpv6if => $dhcpv6ifconf) {
        if (isset($config['interfaces'][$dhcpv6if]['track6-interface']) && !isset($config['interfaces'][$dhcpv6if]['dhcpd6track6allowoverride'])) {
            continue;
        } elseif (!isset($config['interfaces'][$dhcpv6if]['enable'])) {
            continue;
        } elseif (isset($blacklist[$dhcpv6if])) {
            $radvdconf .= "# Skipping blacklisted interface {$dhcpv6if}\n";
            continue;
        } elseif (!isset($dhcpv6ifconf['ramode']) || $dhcpv6ifconf['ramode'] == 'disabled') {
            continue;
        }

        /*
         * Check if we need to listen on a CARP interface.  A CARP setup will try
         * to leave the clients configured: RemoveRoute off / DeprecatePrefix off
         */
        $ifcfgipv6 = get_interface_ipv6(!empty($dhcpv6ifconf['rainterface']) && $dhcpv6ifconf['rainterface'] != 'static' ? $dhcpv6ifconf['rainterface'] : $dhcpv6if);
        if (!is_ipaddrv6($ifcfgipv6) && !isset($config['interfaces'][$dhcpv6if]['dhcpd6track6allowoverride'])) {
            continue;
        }

        $realif = get_real_interface($dhcpv6if, 'inet6');
        $radvdifs[$realif] = 1;

        $mtu = legacy_interface_stats($realif)['mtu'];

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
                log_error('Warning! Skipping AdvLinkMTU configuration since it cannot be applied.');
            }
        }

        $radvdconf .= "# Generated for DHCPv6 server $dhcpv6if\n";
        $radvdconf .= "interface {$realif} {\n";
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
            case 'managed':
            case 'assist':
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

        $stanzas = array();

        list ($ifcfgipv6, $networkv6) = interfaces_primary_address6($dhcpv6if, $realif, $ifconfig_details);
        if (is_subnetv6($networkv6)) {
            $stanzas[] = $networkv6;
        }

        foreach (config_read_array('virtualip', 'vip') as $vip) {
            if ($vip['interface'] == $dhcpv6if && is_ipaddrv6($vip['subnet'])) {
                $subnetv6 = gen_subnetv6($vip['subnet'], $vip['subnet_bits']);
                $stanzas[] = "{$subnetv6}/{$vip['subnet_bits']}";
            }
        }

        /* VIPs may duplicate readings from system */
        $stanzas = array_unique($stanzas);

        foreach ($stanzas as $stanza) {
            $radvdconf .= "\tprefix {$stanza} {\n";
            $radvdconf .= "\t\tDeprecatePrefix " . (!empty($dhcpv6ifconf['rainterface']) ? "off" : "on") . ";\n";
            switch ($dhcpv6ifconf['ramode']) {
                case 'managed':
                    $radvdconf .= "\t\tAdvOnLink on;\n";
                    $radvdconf .= "\t\tAdvAutonomous off;\n";
                    break;
                case 'router':
                    $radvdconf .= "\t\tAdvOnLink off;\n";
                    $radvdconf .= "\t\tAdvAutonomous off;\n";
                    break;
                case 'assist':
                case 'unmanaged':
                case 'stateless':
                    $radvdconf .= "\t\tAdvOnLink on;\n";
                    $radvdconf .= "\t\tAdvAutonomous on;\n";
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
                if (!empty($dhcpv6ifconf['rainterface'])) {
                    $radvdconf .= "\t\tRemoveRoute off;\n";
                }
                if (!empty($dhcpv6ifconf['AdvRouteLifetime'])) {
                    $radvdconf .= "\t\tAdvRouteLifetime {$dhcpv6ifconf['AdvRouteLifetime']};\n";
                }
                $radvdconf .= "\t};\n";
            }
        }

        $dnslist_tmp = [];

        if (isset($dhcpv6ifconf['rasamednsasdhcp6']) && !empty($dhcpv6ifconf['dnsserver'][0])) {
            $dnslist_tmp = $dhcpv6ifconf['dnsserver'];
        } elseif (!isset($dhcpv6ifconf['rasamednsasdhcp6']) && !empty($dhcpv6ifconf['radnsserver'][0])) {
            $dnslist_tmp = $dhcpv6ifconf['radnsserver'];
        } elseif (isset($config['dnsmasq']['enable']) || isset($config['unbound']['enable'])) {
            if (is_ipaddrv6($ifcfgipv6)) {
                $dnslist_tmp[] = $ifcfgipv6;
            } else {
                log_error("Warning! dhcpd_radvd_configure(manual) found no suitable IPv6 address on {$realif}");
            }
        } elseif (!empty($config['system']['dnsserver'][0])) {
            $dnslist_tmp = $config['system']['dnsserver'];
        }

        $dnslist = [];

        foreach ($dnslist_tmp as $server) {
            if (is_ipaddrv6($server)) {
                $dnslist[] = $server;
            }
        }

        if (isset($dhcpv6ifconf['radisablerdnss'])) {
            $dnslist = [];
        }

        if (count($dnslist) > 0) {
            $radvdconf .= "\tRDNSS " . implode(" ", $dnslist) . " {\n";
            if (!empty($dhcpv6ifconf['AdvRDNSSLifetime'])) {
                $radvdconf .= "\t\tAdvRDNSSLifetime {$dhcpv6ifconf['AdvRDNSSLifetime']};\n";
            }
            $radvdconf .= "\t};\n";
        }

        if (!empty($dhcpv6ifconf['radomainsearchlist'])) {
            $dnssl = implode(' ', explode(';', $dhcpv6ifconf['radomainsearchlist']));
        } elseif (!empty($config['system']['domain'])) {
            $dnssl = $config['system']['domain'];
        } else {
            $dnssl = null;
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
        if (!isset($config['interfaces'][$if]['track6-interface'])) {
            continue;
        } elseif (empty($config['interfaces'][$config['interfaces'][$if]['track6-interface']])) {
            continue;
        } elseif (!isset($config['interfaces'][$if]['enable'])) {
            continue;
        } elseif (isset($blacklist[$if])) {
            $radvdconf .= "# Skipping blacklisted interface {$if}\n";
            continue;
        }

        $trackif = $config['interfaces'][$if]['track6-interface'];
        $realif = get_real_interface($if, 'inet6');

        /* prevent duplicate entries, manual overrides */
        if (isset($radvdifs[$realif])) {
            continue;
        }

        $radvdifs[$realif] = 1;
        $autotype = 'unknown';

        if (isset($config['interfaces'][$trackif]['ipaddrv6'])) {
            $autotype = $config['interfaces'][$trackif]['ipaddrv6'];
        }

        $realtrackif = get_real_interface($trackif, 'inet6');

        $mtu = legacy_interface_stats($realif)['mtu'];
        $trackmtu = legacy_interface_stats($realtrackif)['mtu'];
        if (!empty($trackmtu) && !empty($mtu)) {
            if ($trackmtu < $mtu) {
                $mtu = $trackmtu;
            }
        }

        $dnslist = array();

        list ($ifcfgipv6, $networkv6) = interfaces_primary_address6($if, $realif, $ifconfig_details);

        if ($autotype == 'slaac') {
            /* XXX this may be incorrect and needs an override or revisit */
            $networkv6 = '2000::/64';
        } elseif (!is_subnetv6($networkv6)) {
            $networkv6 = '::/64';
        }

        if (isset($config['dnsmasq']['enable']) || isset($config['unbound']['enable'])) {
            if (is_ipaddrv6($ifcfgipv6)) {
                $dnslist[] = $ifcfgipv6;
            } else {
                log_error("Warning! dhcpd_radvd_configure(auto) found no suitable IPv6 address on {$realif}");
            }
        } elseif (!empty($config['system']['dnsserver'])) {
            foreach ($config['system']['dnsserver'] as $server) {
                if (is_ipaddrv6($server)) {
                    $dnslist[] = $server;
                }
            }
        }

        $radvdconf .= "# Generated config for {$autotype} delegation from {$trackif} on {$if}\n";
        $radvdconf .= "interface {$realif} {\n";
        $radvdconf .= "\tAdvSendAdvert on;\n";
        $radvdconf .= sprintf("\tAdvLinkMTU %s;\n", !empty($mtu) ? $mtu : 0);
        $radvdconf .= "\tAdvManagedFlag on;\n";
        $radvdconf .= "\tAdvOtherConfigFlag on;\n";

        if (!empty($networkv6)) {
            $radvdconf .= "\tprefix {$networkv6} {\n";
            if ($autotype == 'slaac') {
                /* XXX also of interest in the future, see hardcoded prefix above */
                $radvdconf .= "\t\tBase6Interface $realtrackif;\n";
            }
            $radvdconf .= "\t\tDeprecatePrefix on;\n";
            $radvdconf .= "\t\tAdvOnLink on;\n";
            $radvdconf .= "\t\tAdvAutonomous on;\n";
            $radvdconf .= "\t};\n";
        }

        foreach (config_read_array('virtualip', 'vip') as $vip) {
            if ($vip['interface'] != $if || !is_ipaddrv6($vip['subnet'])) {
                continue;
            }

            $subnetv6 = gen_subnetv6($vip['subnet'], $vip['subnet_bits']);
            $vipnetv6 = "{$subnetv6}/{$vip['subnet_bits']}";

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

    file_put_contents('/var/etc/radvd.conf', $radvdconf);

    if (count($radvdifs)) {
        mwexec('/usr/local/sbin/radvd -p /var/run/radvd.pid -C /var/etc/radvd.conf -m syslog');
    }

    if ($verbose) {
        echo "done.\n";
    }
}

function dhcpd_dhcpv4_leasesfile()
{
    return '/var/dhcpd/var/db/dhcpd.leases';
}

function dhcpd_dhcpv6_leasesfile()
{
    return '/var/dhcpd/var/db/dhcpd6.leases';
}

function dhcpd_dhcp_configure($verbose = false, $family = 'all', $blacklist = array())
{
    $dirs = array('/dev', '/etc', '/lib', '/run', '/usr', '/usr/local/sbin', '/var/db', '/var/run');

    foreach ($dirs as $dir) {
        mwexecf('/bin/mkdir -p %s', "/var/dhcpd{$dir}");
    }

    if (mwexecf('/sbin/mount -uw %s', '/var/dhcpd/dev', true)) {
        mwexecf('/sbin/mount -t devfs devfs %s', '/var/dhcpd/dev');
    }

    mwexecf('/usr/sbin/chown -R dhcpd:dhcpd %s', '/var/dhcpd');

    if ($family == 'all' || $family == 'inet') {
        dhcpd_dhcp4_configure($verbose);
    }

    if ($family == 'all' || $family == 'inet6') {
        dhcpd_dhcp6_configure($verbose, $blacklist);
        dhcpd_radvd_configure($verbose, $blacklist);
    }
}

function dhcpd_dhcp4_configure($verbose = false)
{
    global $config;

    $need_ddns_updates = false;
    $ddns_zones = array();

    killbypid('/var/dhcpd/var/run/dhcpd.pid', 'TERM', true);

    if (!dhcpd_dhcpv4_enabled()) {
        return;
    }

    /* Only consider DNS servers with IPv4 addresses for the IPv4 DHCP server. */
    $dns_arrv4 = array();
    if (!empty($config['system']['dnsserver'][0])) {
        foreach ($config['system']['dnsserver'] as $dnsserver) {
            if (is_ipaddrv4($dnsserver)) {
                $dns_arrv4[] = $dnsserver;
            }
        }
    }

    if ($verbose) {
        echo 'Starting DHCPv4 service...';
        flush();
    }

    $custoptions = "";
    $ifconfig_details = legacy_interfaces_details();
    foreach ($config['dhcpd'] as $dhcpif => $dhcpifconf) {
        if (isset($dhcpifconf['numberoptions']['item'])) {
            foreach ($dhcpifconf['numberoptions']['item'] as $itemidx => $item) {
                if (!empty($item['type'])) {
                    $itemtype = $item['type'];
                } else {
                    $itemtype = "text";
                }
                $custoptions .= "option custom-{$dhcpif}-{$itemidx} code {$item['number']} = {$itemtype};\n";
            }
        }
    }
    $dhcpdconf = <<<EOD
option domain-name "{$config['system']['domain']}";
option ldap-server code 95 = text;
option arch code 93 = unsigned integer 16; # RFC4578
option pac-webui code 252 = text;
{$custoptions}
default-lease-time 7200;
max-lease-time 86400;
log-facility local7;
one-lease-per-client true;
deny duplicates;
ping-check true;
update-conflict-detection false;
authoritative;

EOD;

    $dhcpdifs = array();
    $omapi_added = false;

    /*
     * loop through and determine if we need
     * to setup failover peer "bleh" entries
     */
    foreach ($config['dhcpd'] as $dhcpif => $dhcpifconf) {
        if (!isset($dhcpifconf['enable'])) {
            continue;
        }
        if (!empty($config['dhcpd'][$dhcpif]['staticmap'])) {
            interfaces_staticarp_configure($dhcpif);
        }

        if (!empty($dhcpifconf['failover_peerip'])) {
            $intip = get_interface_ip($dhcpif, $ifconfig_details);
            $failover_primary = false;
            if (!empty($config['virtualip']['vip'])) {
                foreach ($config['virtualip']['vip'] as $vipent) {
                    if ($vipent['interface'] == $dhcpif) {
                        $carp_nw = gen_subnet($vipent['subnet'], $vipent['subnet_bits']);
                        if (ip_in_subnet($dhcpifconf['failover_peerip'], "{$carp_nw}/{$vipent['subnet_bits']}")) {
                            /* this is the interface! */
                            if (is_numeric($vipent['advskew']) && (intval($vipent['advskew']) < 20)) {
                                $failover_primary = true;
                            }
                            break;
                        }
                    }
                }
            } else {
                log_error('Warning! DHCP Failover setup and no CARP virtual IPs defined!');
            }
            $dhcpdconf_pri = "";
            if ($failover_primary) {
                $my_port = "519";
                $peer_port = "520";
                $type = "primary";
                $dhcpdconf_pri  = "split 128;\n";
                if (isset($dhcpifconf['failover_split'])) {
                    $dhcpdconf_pri  = "split {$dhcpifconf['failover_split']};\n";
                }
                $dhcpdconf_pri .= "  mclt 600;\n";
            } else {
                $type = "secondary";
                $my_port = "520";
                $peer_port = "519";
            }

            if (is_ipaddrv4($intip)) {
                $dhcpdconf .= <<<EOPP
failover peer "dhcp_{$dhcpif}" {
  {$type};
  address {$intip};
  port {$my_port};
  peer address {$dhcpifconf['failover_peerip']};
  peer port {$peer_port};
  max-response-delay 10;
  max-unacked-updates 10;
  {$dhcpdconf_pri}
  load balance max seconds 3;
}
\n
EOPP;
            }
        }
    }

    $iflist = get_configured_interface_with_descr();
    $gwObject = new \OPNsense\Routing\Gateways($ifconfig_details);

    foreach ($config['dhcpd'] as $dhcpif => $dhcpifconf) {
        if (!isset($dhcpifconf['enable']) || !isset($iflist[$dhcpif])) {
            continue;
        }

        list ($ifcfgip, $ifcfgsn) = explode('/', find_interface_network(get_real_interface($dhcpif), false, $ifconfig_details));

        $subnet = gen_subnet($ifcfgip, $ifcfgsn);
        $subnetmask = gen_subnet_mask($ifcfgsn);

        if (!is_ipaddrv4($ifcfgip) || !is_subnetv4("{$subnet}/{$ifcfgsn}")) {
            log_error("Warning! dhcpd_dhcp4_configure() found no suitable IPv4 address on {$dhcpif}");
            continue;
        }

        $all_pools = array();
        $all_pools[] = $dhcpifconf;
        if (!empty($dhcpifconf['pool'])) {
            $all_pools = array_merge($all_pools, $dhcpifconf['pool']);
        }

        $dnscfg = "";

        if (!empty($dhcpifconf['domain'])) {
            $dnscfg .= "  option domain-name \"{$dhcpifconf['domain']}\";\n";
        }

        if (!empty($dhcpifconf['domainsearchlist'])) {
            $dnscfg .= "  option domain-search \"" . join("\",\"", preg_split("/[ ;]+/", $dhcpifconf['domainsearchlist'])) . "\";\n";
        }

        $newzone = array();

        if (isset($dhcpifconf['ddnsupdate'])) {
            $need_ddns_updates = true;
            if (!empty($dhcpifconf['ddnsdomain'])) {
                $newzone['domain-name'] = $dhcpifconf['ddnsdomain'];
                $dnscfg .= "  ddns-domainname \"{$dhcpifconf['ddnsdomain']}\";\n";
            } else {
                $newzone['domain-name'] = $config['system']['domain'];
            }
            $revsubnet = array_reverse(explode(".", $subnet));
            $subnetmask_rev = array_reverse(explode('.', $subnetmask));
            foreach ($subnetmask_rev as $octet) {
                if ($octet == "0") {
                    array_shift($revsubnet);
                }
            }
            $newzone['ptr-domain'] = implode(".", $revsubnet) . ".in-addr.arpa";
        }

        if (!empty($dhcpifconf['dnsserver'][0])) {
            $dnscfg .= "  option domain-name-servers " . join(",", $dhcpifconf['dnsserver']) . ";";
            if (!empty($newzone['domain-name'])) {
                $newzone['dns-servers'] = $dhcpifconf['dnsserver'];
            }
        } elseif (isset($config['dnsmasq']['enable']) || isset($config['unbound']['enable'])) {
            $dnscfg .= "  option domain-name-servers {$ifcfgip};";
            if ($newzone['domain-name'] && !empty($config['system']['dnsserver'][0])) {
                $newzone['dns-servers'] = $config['system']['dnsserver'];
            }
        } elseif (!empty($dns_arrv4)) {
            $dnscfg .= "  option domain-name-servers " . join(",", $dns_arrv4) . ";";
            if ($newzone['domain-name']) {
                $newzone['dns-servers'] = $dns_arrv4;
            }
        }

        /*
         * Create classes - These all contain comma-separated lists.
         * Join them into one big comma-separated string then split
         * them all up.
         */
        $all_mac_strings = array();
        foreach ($all_pools as $poolconf) {
            if (!empty($poolconf['mac_allow'])) {
                $all_mac_strings[] = $poolconf['mac_allow'];
            }
            if (!empty($poolconf['mac_deny'])) {
                $all_mac_strings[] = $poolconf['mac_deny'];
            }
        }
        $all_mac_list = array_unique(explode(',', implode(',', $all_mac_strings)));
        foreach ($all_mac_list as $mac) {
            if (!empty($mac)) {
                $dhcpdconf .= 'class "' . str_replace(':', '', $mac) . '" {' . "\n";
                // Skip the first octet of the MAC address - for media type, typically Ethernet ("01") and match the rest.
                $dhcpdconf .= '  match if substring (hardware, 1, ' . (substr_count($mac, ':') + 1) . ') = ' . $mac . ';' . "\n";
                $dhcpdconf .= '}' . "\n";
            }
        }

        $dhcpdconf .= "\nsubnet {$subnet} netmask {$subnetmask} {\n";

        // Setup pool options
        foreach ($all_pools as $poolconf) {
            $dhcpdconf .= "  pool {\n";
            /* is failover dns setup? */
            if (!empty($poolconf['dnsserver'][0])) {
                $dhcpdconf .= "    option domain-name-servers {$poolconf['dnsserver'][0]}";
                if (!empty($poolconf['dnsserver'][1])) {
                    $dhcpdconf .= ",{$poolconf['dnsserver'][1]}";
                }
                $dhcpdconf .= ";\n";
            }

            /* allow/deny MACs */
            if (!empty($poolconf['mac_allow'])) {
                $mac_allow_list = array_unique(explode(',', $poolconf['mac_allow']));
                foreach ($mac_allow_list as $mac) {
                    if (!empty($mac)) {
                        $dhcpdconf .= "    allow members of \"" . str_replace(':', '', $mac) . "\";\n";
                    }
                }
            }
            if (!empty($poolconf['mac_deny'])) {
                $mac_deny_list = array_unique(explode(',', $poolconf['mac_deny']));
                foreach ($mac_deny_list as $mac) {
                    if (!empty($mac)) {
                        $dhcpdconf .= "    deny members of \"" . str_replace(':', '', $mac) . "\";\n";
                    }
                }
            }

            if (!empty($poolconf['failover_peerip'])) {
                $dhcpdconf .= "    deny dynamic bootp clients;\n";
            }

            if (isset($poolconf['denyunknown'])) {
                $dhcpdconf .= "    deny unknown-clients;\n";
            }

            if (isset($poolconf['ignoreuids'])) {
                $dhcpdconf .= "    ignore-client-uids true;\n";
            }

            if (
                !empty($poolconf['gateway']) && $poolconf['gateway'] != "none"
                && (empty($dhcpifconf['gateway']) || $poolconf['gateway'] != $dhcpifconf['gateway'])
            ) {
                $dhcpdconf .= "    option routers {$poolconf['gateway']};\n";
            }

            if (!empty($dhcpifconf['failover_peerip'])) {
                $dhcpdconf .= "    failover peer \"dhcp_{$dhcpif}\";\n";
            }

            $pdnscfg = "";
            if (
                !empty($poolconf['domain'])
                && (empty($dhcpifconf['domain']) || $poolconf['domain'] != $dhcpifconf['domain'])
            ) {
                $pdnscfg .= "    option domain-name \"{$poolconf['domain']}\";\n";
            }

            if (
                !empty($poolconf['domainsearchlist'])
                && (empty($dhcpifconf['domainsearchlist']) || $poolconf['domainsearchlist'] != $dhcpifconf['domainsearchlist'])
            ) {
                $pdnscfg .= "    option domain-search \"" . join("\",\"", preg_split("/[ ;]+/", $poolconf['domainsearchlist'])) . "\";\n";
            }

            if (isset($poolconf['ddnsupdate'])) {
                if (
                    !empty($poolconf['ddnsdomain'])
                    && (empty($dhcpifconf['ddnsdomain']) || $poolconf['ddnsdomain'] != $dhcpifconf['ddnsdomain'])
                ) {
                    $pdnscfg .= "    ddns-domainname \"{$poolconf['ddnsdomain']}\";\n";

                    $newddnszone = array();
                    $newddnszone['domain-name'] = $poolconf['ddnsdomain'];
                    $newddnszone['dns-servers'] = array($poolconf['ddnsdomainprimary']);
                    $newddnszone['ddnsdomainkeyname'] = $poolconf['ddnsdomainkeyname'];
                    $newddnszone['ddnsdomainkey'] = $poolconf['ddnsdomainkey'];
                    $newddnszone['ddnsdomainalgorithm'] = !empty($poolconf['ddnsdomainalgorithm']) ? $poolconf['ddnsdomainalgorithm'] : "hmac-md5";
                    $ddns_zones[] = $newddnszone;
                }
                $pdnscfg .= "    ddns-update-style interim;\n";
            }

            if (
                !empty($poolconf['dnsserver'][0])
                && (empty($dhcpifconf['dnsserver'][0]) || $poolconf['dnsserver'][0] != $dhcpifconf['dnsserver'][0])
            ) {
                $pdnscfg .= "    option domain-name-servers " . join(",", $poolconf['dnsserver']) . ";\n";
            }
            $dhcpdconf .= "{$pdnscfg}";

            // default-lease-time
            if (
                !empty($poolconf['defaultleasetime'])
                && (empty($dhcpifconf['defaultleasetime']) || $poolconf['defaultleasetime'] != $dhcpifconf['defaultleasetime'])
            ) {
                $dhcpdconf .= "    default-lease-time {$poolconf['defaultleasetime']};\n";
            }

            // max-lease-time
            if (
                !empty($poolconf['maxleasetime'])
                && (empty($dhcpifconf['maxleasetime']) || $poolconf['maxleasetime'] != $dhcpifconf['maxleasetime'])
            ) {
                $dhcpdconf .= "    max-lease-time {$poolconf['maxleasetime']};\n";
            }
            // interface MTU
            if (
                !empty($poolconf['interface_mtu'])
                && (empty($dhcpifconf['interface_mtu']) || $poolconf['interface_mtu'] != $dhcpifconf['interface_mtu'])
            ) {
                $dhcpdconf .= "    option interface-mtu {$poolconf['interface_mtu']};\n";
            }

            // netbios-name*
            if (
                !empty($poolconf['winsserver'][0])
                && (empty($dhcpifconf['winsserver'][0]) || $poolconf['winsserver'][0] != $dhcpifconf['winsserver'][0])
            ) {
                $dhcpdconf .= "    option netbios-name-servers " . join(",", $poolconf['winsserver']) . ";\n";
                $dhcpdconf .= "    option netbios-node-type 8;\n";
            }

            // ntp-servers
            if (
                !empty($poolconf['ntpserver'][0])
                && (empty($dhcpifconf['ntpserver'][0]) || $poolconf['ntpserver'][0] != $dhcpifconf['ntpserver'][0])
            ) {
                $dhcpdconf .= "    option ntp-servers " . join(",", $poolconf['ntpserver']) . ";\n";
            }

            // tftp-server-name
            if (!empty($poolconf['tftp']) && (empty($dhcpifconf['tftp']) || $poolconf['tftp'] != $dhcpifconf['tftp'])) {
                $dhcpdconf .= "    option tftp-server-name \"{$poolconf['tftp']}\";\n";

                // bootfile-name
                if (!empty($poolconf['bootfilename']) && (empty($dhcpifconf['bootfilename']) || $poolconf['bootfilename'] != $dhcpifconf['bootfilename'])) {
                    $dhcpdconf .= "    option bootfile-name \"{$poolconf['bootfilename']}\";\n";
                }
            }

            // ldap-server
            if (!empty($poolconf['ldap']) && (empty($dhcpifconf['ldap']) || $poolconf['ldap'] != $dhcpifconf['ldap'])) {
                $dhcpdconf .= "    option ldap-server \"{$poolconf['ldap']}\";\n";
            }

            // net boot information
            if (isset($poolconf['netboot'])) {
                if (!empty($poolconf['nextserver']) && (empty($dhcpifconf['nextserver']) || $poolconf['nextserver'] != $dhcpifconf['nextserver'])) {
                    $dhcpdconf .= "    next-server {$poolconf['nextserver']};\n";
                }
                if (!empty($poolconf['filename']) && (empty($dhcpifconf['filename']) || $poolconf['filename'] != $dhcpifconf['filename'])) {
                    $dhcpdconf .= "    filename \"{$poolconf['filename']}\";\n";
                }
                if (!empty($poolconf['rootpath']) && (empty($dhcpifconf['rootpath']) || $poolconf['rootpath'] != $dhcpifconf['rootpath'])) {
                    $dhcpdconf .= "    option root-path \"{$poolconf['rootpath']}\";\n";
                }
            }
            $dhcpdconf .= "    range {$poolconf['range']['from']} {$poolconf['range']['to']};\n";
            $dhcpdconf .= "  }\n\n";
        }
        // End of settings inside pools

        if (!empty($dhcpifconf['gateway'])) {
            $routers = $dhcpifconf['gateway'] != "none" ? $dhcpifconf['gateway'] : null;
        } elseif ($gwObject->hasGateways("inet")) {
            // by default, add interface address in "option routers"
            $routers = $ifcfgip;
        } else {
            $routers = null;
        }
        if (!empty($routers)) {
            $dhcpdconf .= "  option routers {$routers};\n";
        }

        $dhcpdconf .= <<<EOD
$dnscfg

EOD;
        // default-lease-time
        if (!empty($dhcpifconf['defaultleasetime'])) {
            $dhcpdconf .= "  default-lease-time {$dhcpifconf['defaultleasetime']};\n";
        }

        // max-lease-time
        if (!empty($dhcpifconf['maxleasetime'])) {
            $dhcpdconf .= "  max-lease-time {$dhcpifconf['maxleasetime']};\n";
        }

        // min-secs
        if (!empty($dhcpifconf['minsecs'])) {
            $dhcpdconf .= "  min-secs {$dhcpifconf['minsecs']};\n";
        }

        // interface MTU
        if (!empty($dhcpifconf['interface_mtu'])) {
            $dhcpdconf .= "  option interface-mtu {$dhcpifconf['interface_mtu']};\n";
        }

        // netbios-name*
        if (!empty($dhcpifconf['winsserver'][0])) {
            $dhcpdconf .= "  option netbios-name-servers " . join(",", $dhcpifconf['winsserver']) . ";\n";
            $dhcpdconf .= "  option netbios-node-type 8;\n";
        }

        // ntp-servers
        if (!empty($dhcpifconf['ntpserver'][0])) {
            $dhcpdconf .= "  option ntp-servers " . join(",", $dhcpifconf['ntpserver']) . ";\n";
        }

        // tftp-server-name
        if (!empty($dhcpifconf['tftp'])) {
            $dhcpdconf .= "  option tftp-server-name \"{$dhcpifconf['tftp']}\";\n";

            // bootfile-name
            if (!empty($dhcpifconf['bootfilename'])) {
                $dhcpdconf .= "  option bootfile-name \"{$dhcpifconf['bootfilename']}\";\n";
            }
        }

        // add pac url if it applies
        if (isset($dhcpifconf['wpad']) && !empty($config['system']['hostname']) && !empty($config['system']['domain'])) {
            $protocol = !empty($config['system']['webgui']['protocol']) ? $config['system']['webgui']['protocol'] : 'https';
            // take hostname from system settings - it can be used to be resolved to anything based on client IP
            $host = implode('.', array('wpad', $config['system']['domain']));
            $default_port = (isset($config['system']['webgui']['protocol']) && $config['system']['webgui']['protocol'] == 'https') ? 443 : 80;
            $port = !empty($config['system']['webgui']['port']) ? $config['system']['webgui']['port'] : $default_port;
            $webui_url = "$protocol://$host:$port/wpad.dat";

            $dhcpdconf .= "  option pac-webui \"$webui_url\";\n";
        }

        // Handle option, number rowhelper values
        if (isset($dhcpifconf['numberoptions']['item'])) {
            $dhcpdconf .= "\n";
            foreach ($dhcpifconf['numberoptions']['item'] as $itemidx => $item) {
                if (empty($item['type']) || $item['type'] == "text") {
                    $dhcpdconf .= "  option custom-{$dhcpif}-{$itemidx} \"{$item['value']}\";\n";
                } else {
                    $dhcpdconf .= "  option custom-{$dhcpif}-{$itemidx} {$item['value']};\n";
                }
            }
        }

        // ldap-server
        if (!empty($dhcpifconf['ldap'])) {
            $dhcpdconf .= "  option ldap-server \"{$dhcpifconf['ldap']}\";\n";
        }

        // net boot information
        if (isset($dhcpifconf['netboot'])) {
            if (!empty($dhcpifconf['nextserver'])) {
                $dhcpdconf .= "  next-server {$dhcpifconf['nextserver']};\n";
            }
            if (!empty($dhcpifconf['filename']) && !empty($dhcpifconf['filename32']) && !empty($dhcpifconf['filename64'])) {
                $dhcpdconf .= "  if option arch = 00:06 {\n";
                $dhcpdconf .= "    filename \"{$dhcpifconf['filename32']}\";\n";
                $dhcpdconf .= "  } else if option arch = 00:07 {\n";
                $dhcpdconf .= "    filename \"{$dhcpifconf['filename64']}\";\n";
                $dhcpdconf .= "  } else if option arch = 00:09 {\n";
                $dhcpdconf .= "    filename \"{$dhcpifconf['filename64']}\";\n";
                $dhcpdconf .= "  } else {\n";
                $dhcpdconf .= "    filename \"{$dhcpifconf['filename']}\";\n";
                $dhcpdconf .= "  }\n\n";
            } elseif (!empty($dhcpifconf['filename'])) {
                $dhcpdconf .= "  filename \"{$dhcpifconf['filename']}\";\n";
            }
            if (!empty($dhcpifconf['rootpath'])) {
                $dhcpdconf .= "  option root-path \"{$dhcpifconf['rootpath']}\";\n";
            }
        }

        $dhcpdconf .= <<<EOD
}

EOD;

        /* add static mappings */
        if (!empty($dhcpifconf['staticmap'])) {
            foreach ($dhcpifconf['staticmap'] as $i => $sm) {
                $dhcpdconf .= "\nhost s_{$dhcpif}_{$i} {\n";
                if (!empty($sm['mac'])) {
                    $dhcpdconf .= "  hardware ethernet {$sm['mac']};\n";
                }

                if (!empty($sm['cid'])) {
                    $dhcpdconf .= "  option dhcp-client-identifier \"{$sm['cid']}\";\n";
                }

                if (!empty($sm['ipaddr'])) {
                    $dhcpdconf .= "  fixed-address {$sm['ipaddr']};\n";
                }

                if (!empty($sm['hostname'])) {
                    $dhhostname = str_replace(" ", "_", $sm['hostname']);
                    $dhhostname = str_replace(".", "_", $dhhostname);
                    $dhcpdconf .= "  option host-name \"{$dhhostname}\";\n";
                }
                if (!empty($sm['filename'])) {
                    $dhcpdconf .= "  filename \"{$sm['filename']}\";\n";
                }

                if (!empty($sm['rootpath'])) {
                    $dhcpdconf .= "  option root-path \"{$sm['rootpath']}\";\n";
                }

                if (!empty($sm['gateway']) && $sm['gateway'] != "none" && ($sm['gateway'] != $dhcpifconf['gateway'])) {
                    $dhcpdconf .= "  option routers {$sm['gateway']};\n";
                }

                $smdnscfg = "";
                if (!empty($sm['domain']) && ($sm['domain'] != $dhcpifconf['domain'])) {
                    $smdnscfg .= "  option domain-name \"{$sm['domain']}\";\n";
                }

                if (!empty($sm['domainsearchlist']) && ($sm['domainsearchlist'] != $dhcpifconf['domainsearchlist'])) {
                    $smdnscfg .= "  option domain-search \"" . join("\",\"", preg_split("/[ ;]+/", $sm['domainsearchlist'])) . "\";\n";
                }

                if (isset($sm['ddnsupdate'])) {
                    if (($sm['ddnsdomain'] != '') && ($sm['ddnsdomain'] != $dhcpifconf['ddnsdomain'])) {
                        $smdnscfg .= "    ddns-domainname \"{$sm['ddnsdomain']}\";\n";
                    }
                    $smdnscfg .= "    ddns-update-style interim;\n";
                }

                if (!empty($sm['dnsserver']) && ($sm['dnsserver'][0]) && ($sm['dnsserver'][0] != $dhcpifconf['dnsserver'][0])) {
                    $smdnscfg .= "  option domain-name-servers " . join(",", $sm['dnsserver']) . ";\n";
                }
                $dhcpdconf .= "{$smdnscfg}";

                // default-lease-time
                if (!empty($sm['defaultleasetime']) && ($sm['defaultleasetime'] != $dhcpifconf['defaultleasetime'])) {
                    $dhcpdconf .= "  default-lease-time {$sm['defaultleasetime']};\n";
                }

                // max-lease-time
                if (!empty($sm['maxleasetime']) && ($sm['maxleasetime'] != $dhcpifconf['maxleasetime'])) {
                    $dhcpdconf .= "  max-lease-time {$sm['maxleasetime']};\n";
                }

                // netbios-name*
                if (!empty($sm['winsserver']) && $sm['winsserver'][0] && ($sm['winsserver'][0] != $dhcpifconf['winsserver'][0])) {
                    $dhcpdconf .= "  option netbios-name-servers " . join(",", $sm['winsserver']) . ";\n";
                    $dhcpdconf .= "  option netbios-node-type 8;\n";
                }

                // ntp-servers
                if (!empty($sm['ntpserver']) && $sm['ntpserver'][0] && ($sm['ntpserver'][0] != $dhcpifconf['ntpserver'][0])) {
                    $dhcpdconf .= "  option ntp-servers " . join(",", $sm['ntpserver']) . ";\n";
                }

                // tftp-server-name
                if (!empty($sm['tftp']) && ($sm['tftp'] != $dhcpifconf['tftp'])) {
                    $dhcpdconf .= "  option tftp-server-name \"{$sm['tftp']}\";\n";

                    // bootfile-name
                    if (!empty($sm['bootfilename']) && ($sm['bootfilename'] != $dhcpifconf['bootfilename'])) {
                        $dhcpdconf .= "  option bootfile-name \"{$sm['bootfilename']}\";\n";
                    }
                }

                $dhcpdconf .= "}\n";
            }
        }

        $dhcpdifs[] = get_real_interface($dhcpif);
        if (!empty($newzone['domain-name']) && isset($dhcpifconf['ddnsupdate']) && is_ipaddrv4($dhcpifconf['ddnsdomainprimary'])) {
            $newzone['dns-servers'] = array($dhcpifconf['ddnsdomainprimary']);
            $newzone['ddnsdomainkeyname'] = $dhcpifconf['ddnsdomainkeyname'];
            $newzone['ddnsdomainkey'] = $dhcpifconf['ddnsdomainkey'];
            $newzone['ddnsdomainalgorithm'] = !empty($dhcpifconf['ddnsdomainalgorithm']) ? $dhcpifconf['ddnsdomainalgorithm'] : "hmac-md5";
            $ddns_zones[] = $newzone;
        }

        if ($dhcpifconf['omapi'] && !$omapi_added) {
            $dhcpdconf .= "\nomapi-port {$dhcpifconf['omapiport']};\n";
            if (isset($dhcpifconf['omapialgorithm']) && isset($dhcpifconf['omapikey'])) {
                $dhcpdconf .= "key omapi_key {\n";
                $dhcpdconf .= "  algorithm {$dhcpifconf['omapialgorithm']};\n";
                $dhcpdconf .= "  secret \"{$dhcpifconf['omapikey']}\";\n";
                $dhcpdconf .= "};\nomapi-key omapi_key;\n\n";

                /* make sure we only add this OMAPI block once */
                $omapi_added = true;
            }
        }
    }

    if ($need_ddns_updates) {
        $dhcpdconf .= "ddns-update-style interim;\n";
        $dhcpdconf .= "update-static-leases on;\n";
        $dhcpdconf .= dhcpd_zones($ddns_zones);
    }

    @file_put_contents('/var/dhcpd/etc/dhcpd.conf', $dhcpdconf);
    @touch('/var/dhcpd/var/db/dhcpd.leases');
    @unlink('/var/dhcpd/var/run/dhcpd.pid');

    /* fire up dhcpd in a chroot */
    if (count($dhcpdifs) > 0) {
        mwexec('/usr/local/sbin/dhcpd -user dhcpd -group dhcpd -chroot /var/dhcpd -cf /etc/dhcpd.conf -pf /var/run/dhcpd.pid ' . join(' ', $dhcpdifs));
    }

    if ($verbose) {
        print "done.\n";
    }
}

function dhcpd_zones($ddns_zones, $ipproto = 'inet')
{
    $dhcpdconf = '';
    if (is_array($ddns_zones)) {
        $added_zones = array();
        $added_keys = array();
        foreach ($ddns_zones as $zone) {
            $versionsuffix = $ipproto == "inet6" ? "6" : "";
            // We don't need to add zones multiple times.
            foreach (array($zone['domain-name'], $zone['ptr-domain']) as $domain) {
                if (!empty($domain) && !in_array($domain, $added_zones)) {
                    /* dhcpdconf2 is injected *after* the key */
                    $dhcpdconf2 = "zone {$domain}. {\n";
                    // XXX: $zone['dns-servers'] only contains one item, ref $newzone['dns-servers']
                    $dhcpdconf2 .= "  primary{$versionsuffix} {$zone['dns-servers'][0]};\n";
                    if (!empty($zone['ddnsdomainkeyname']) && !empty($zone['ddnsdomainkey'])) {
                        if (!in_array($zone['ddnsdomainkeyname'], $added_keys)) {
                            $dhcpdconf .= "key {$zone['ddnsdomainkeyname']} {\n";
                            $dhcpdconf .= "  algorithm {$zone['ddnsdomainalgorithm']};\n";
                            $dhcpdconf .= "  secret {$zone['ddnsdomainkey']};\n";
                            $dhcpdconf .= "}\n";
                            $added_keys[] = $zone['ddnsdomainkeyname'];
                        }
                        $dhcpdconf2 .= "  key {$zone['ddnsdomainkeyname']};\n";
                    }
                    $dhcpdconf2 .= "}\n";
                    $dhcpdconf .= $dhcpdconf2;
                    $added_zones[] = $domain;
                }
            }
        }
    }

    return $dhcpdconf;
}

function dhcpd_dhcp6_configure($verbose = false, $blacklist = array())
{
    global $config;

    killbypid('/var/dhcpd/var/run/dhcpdv6.pid', 'TERM', true);
    killbypid('/var/run/dhcpleases6.pid', 'TERM', true);

    if (!dhcpd_dhcpv6_enabled()) {
        return;
    }

    $iflist = get_configured_interface_with_descr();
    $ifconfig_details = legacy_interfaces_details();
    $dhcpdv6cfg = config_read_array('dhcpdv6');

    /* Only consider DNS servers with IPv6 addresses for the IPv6 DHCP server. */
    $dns_arrv6 = array();
    if (!empty($config['system']['dnsserver'][0])) {
        foreach ($config['system']['dnsserver'] as $dnsserver) {
            if (is_ipaddrv6($dnsserver)) {
                $dns_arrv6[] = $dnsserver;
            }
        }
    }

    if ($verbose) {
        echo 'Starting DHCPv6 service...';
        flush();
    }

    /* we add a fake entry for interfaces that are set to track6 another WAN */
    foreach (array_keys($iflist) as $ifname) {
        /* Do not put in the config an interface which is down */
        if (isset($blacklist[$ifname])) {
            continue;
        }
        if (!empty($config['interfaces'][$ifname]['track6-interface'])) {
            list ($ifcfgipv6) = interfaces_primary_address6($ifname, null, $ifconfig_details);
            if (!is_ipaddrv6($ifcfgipv6)) {
                continue;
            }

            $ifcfgipv6 = Net_IPv6::getNetmask($ifcfgipv6, 64);
            $ifcfgipv6arr = explode(':', $ifcfgipv6);

            if (!isset($config['interfaces'][$ifname]['dhcpd6track6allowoverride'])) {
                /* mock a real server */
                $dhcpdv6cfg[$ifname] = array();
                $dhcpdv6cfg[$ifname]['enable'] = true;

                /* fixed range */
                $ifcfgipv6arr[7] = '1000';
                $dhcpdv6cfg[$ifname]['range'] = array();
                $dhcpdv6cfg[$ifname]['range']['from'] = Net_IPv6::compress(implode(':', $ifcfgipv6arr));
                $ifcfgipv6arr[7] = '2000';
                $dhcpdv6cfg[$ifname]['range']['to'] = Net_IPv6::compress(implode(':', $ifcfgipv6arr));

                /* with enough room we can add dhcp6 prefix delegation */
                $pdlen = calculate_ipv6_delegation_length($config['interfaces'][$ifname]['track6-interface']);
                if ($pdlen > 2) {
                    $pdlenmax = $pdlen;
                    $pdlenhalf = $pdlenmax - 1;
                    $pdlenmin = 64 - ceil($pdlenhalf / 4);
                    $dhcpdv6cfg[$ifname]['prefixrange'] = array();
                    $dhcpdv6cfg[$ifname]['prefixrange']['prefixlength'] = $pdlenmin;

                    /* set the delegation start to half the current address block */
                    $range = Net_IPv6::parseAddress($ifcfgipv6, (64 - $pdlenmax));
                    $range['start'] = Net_IPv6::getNetmask($range['end'], (64 - $pdlenhalf));

                    /* set the end range to a multiple of the prefix delegation size, required by dhcpd */
                    $range = Net_IPv6::parseAddress($range['end'], (64 - $pdlenhalf));
                    $range['end'] = Net_IPv6::getNetmask($range['end'], (64 - round($pdlen / 2)));

                    $dhcpdv6cfg[$ifname]['prefixrange']['from'] = Net_IPv6::compress($range['start']);
                    $dhcpdv6cfg[$ifname]['prefixrange']['to'] = Net_IPv6::compress($range['end']);
                }
            } else {
                if (!empty($dhcpdv6cfg[$ifname]['range']['from']) && !empty($dhcpdv6cfg[$ifname]['range']['to'])) {
                    /* get config entry and marry it to the live prefix */
                    $dhcpdv6cfg[$ifname]['range'] = array(
                        'from' => make_ipv6_64_address($ifcfgipv6, $dhcpdv6cfg[$ifname]['range']['from']),
                        'to' => make_ipv6_64_address($ifcfgipv6, $dhcpdv6cfg[$ifname]['range']['to']),
                    );
                }

                if (!empty($dhcpdv6cfg[$ifname]['prefixrange']['from']) && !empty($dhcpdv6cfg[$ifname]['prefixrange']['to'])) {
                    $pd_prefix_from_array = explode(':', $dhcpdv6cfg[$ifname]['prefixrange']['from']);
                    $pd_prefix_to_array = explode(':', $dhcpdv6cfg[$ifname]['prefixrange']['to']);

                    $pd_prefix_from_array[2] = sprintf("%02s", $pd_prefix_from_array[2]);
                    $pd_prefix_to_array[2] = sprintf("%02s", $pd_prefix_to_array[2]);

                    for ($x = 0; $x < 4; $x++) {
                        /* make the prefx long format otherwise dhcpd complains */
                        $ifcfgipv6arr[$x] = sprintf("%04s", $ifcfgipv6arr[$x]);
                    }

                    $pd_prefix_from_array_out = $ifcfgipv6arr;
                    $pd_prefix_to_array_out = $ifcfgipv6arr;

                    $pdval = intval($dhcpdv6cfg[$ifname]['prefixrange']['prefixlength']);

                    switch ($pdval) {
                        // For PD sizes of /60 through /64, the user must do the math!
                        case 60:
                        case 62:
                        case 63:
                        case 64: // 3&4th bytes on 4th array
                            $pd_prefix_from_array_out[3] = sprintf("%04s", $ifcfgipv6arr[3]); // make it 4 bytes
                            $pd_prefix_from_array_out[3] = substr($pd_prefix_from_array_out[3], 0, 2) . $pd_prefix_from_array[2];
                            $pd_prefix_to_array_out[3] = sprintf("%04s", $ifcfgipv6arr[3]); // make it 4 bytes
                            $pd_prefix_to_array_out[3] = substr($pd_prefix_to_array_out[3], 0, 2) . $pd_prefix_to_array[2];
                            break;
                        case 56: // 1st&2nd bytes on 4th array
                            $pd_prefix_from_array[2] = str_pad($pd_prefix_from_array[2], 4, "0");
                            $pd_prefix_from_array_out[3] = sprintf("%s", $pd_prefix_from_array[2]); // make it 4 bytes
                            $pd_prefix_to_array[2] = str_pad($pd_prefix_to_array[2], 4, "0");
                            $pd_prefix_to_array_out[3] = sprintf("%s", $pd_prefix_to_array[2]); // make it 4 bytes
                            break;
                        case 52: // 1st byte on 4th array only, 0 to f, we only want one byte, but lookout for the user entering more
                            $len = strlen($pd_prefix_from_array[2]);
                            $pd_prefix_from_array[2] = substr($pd_prefix_from_array[2], $len - 1, 1);
                            $pd_prefix_from_array_out[3] = sprintf("%s000", substr($pd_prefix_from_array[2], 0, 1)); // first byte from entered value
                            $len = strlen($pd_prefix_to_array[2]);
                            $pd_prefix_to_array[2] = substr($pd_prefix_to_array[2], $len - 1, 1);
                            $pd_prefix_to_array_out[3] = sprintf("%s000", substr($pd_prefix_to_array[2], 0, 1));
                            break;
                        case 48: // 4th byte on 2nd array
                            $pd_prefix_from_array[2] = substr($pd_prefix_from_array[2], 0, 1);
                            $pd_prefix_from_array_out[1] = substr(sprintf("%03s", $ifcfgipv6arr[1]), 0, 3) . $pd_prefix_from_array[2]; // get 1st 3 byte + nibble
                            $pd_prefix_to_array[2] = substr($pd_prefix_to_array[2], 0, 1);
                            $pd_prefix_to_array_out[1] = substr(sprintf("%03s", $ifcfgipv6arr[1]), 0, 3) . $pd_prefix_to_array[2]; // get 1st 3 byte + nibble
                            break;
                    }

                    $ipv6_from_pd_from = implode(':', $pd_prefix_from_array_out);
                    $ipv6_from_pd_to = implode(':', $pd_prefix_to_array_out);

                    $dhcpdv6cfg[$ifname]['prefixrange']['from'] = Net_IPv6::compress($ipv6_from_pd_from);
                    $dhcpdv6cfg[$ifname]['prefixrange']['to'] = Net_IPv6::compress($ipv6_from_pd_to);
                }
            }
        }
    }

    $custoptionsv6 = "";
    foreach ($dhcpdv6cfg as $dhcpv6if => $dhcpv6ifconf) {
        if (isset($dhcpv6ifconf['numberoptions']['item'])) {
            foreach ($dhcpv6ifconf['numberoptions']['item'] as $itemv6idx => $itemv6) {
                if (!empty($itemv6['type'])) {
                    $itemtype = $itemv6['type'];
                } else {
                    $itemtype = "text";
                }
                $custoptionsv6 .= "option custom-{$dhcpv6if}-{$itemv6idx} code {$itemv6['number']} = {$itemtype};\n";
            }
        }
    }

    if (isset($dhcpv6ifconf['netboot']) && !empty($dhcpv6ifconf['bootfile_url'])) {
        $custoptionsv6 .= "option dhcp6.bootfile-url code 59 = string;\n";
    }

    $dhcpdv6conf = <<<EOD
option dhcp6.domain-search "{$config['system']['domain']}";
{$custoptionsv6}
default-lease-time 7200;
max-lease-time 86400;
log-facility local7;
one-lease-per-client true;
deny duplicates;
ping-check true;
update-conflict-detection false;
authoritative;

EOD;

    $dhcpdv6ifs = array();
    $ddns_zones = array();
    $need_ddns_updates = false;

    foreach ($dhcpdv6cfg as $dhcpv6if => $dhcpv6ifconf) {
        if (!isset($dhcpv6ifconf['enable']) || !isset($iflist[$dhcpv6if])) {
            continue;
        }

        if (isset($blacklist[$dhcpv6if])) {
            continue;
        }

        list ($ifcfgipv6, $networkv6) = interfaces_primary_address6($dhcpv6if, null, $ifconfig_details);
        if (!is_ipaddrv6($ifcfgipv6) || !is_subnetv6($networkv6)) {
            log_error("Warning! dhcpd_dhcp6_configure() found no suitable IPv6 address on {$dhcpv6if}");
            continue;
        }

        $dnscfgv6 = "";

        if (!empty($dhcpv6ifconf['domainsearchlist'])) {
            $dnscfgv6 .= "  option dhcp6.domain-search \"" . join("\",\"", preg_split("/[ ;]+/", $dhcpv6ifconf['domainsearchlist'])) . "\";\n";
        }

        $newzone = array();

        if (isset($dhcpv6ifconf['ddnsupdate'])) {
            $need_ddns_updates = true;
            if (!empty($dhcpv6ifconf['ddnsdomain'])) {
                $dnscfgv6 .= "  ddns-domainname \"{$dhcpv6ifconf['ddnsdomain']}\";\n";
                $newzone['domain-name'] = $dhcpv6ifconf['ddnsdomain'];
            } else {
                $newzone['domain-name'] = $config['system']['domain'];
            }

            $subnetv6 = explode("/", $networkv6)[0];
            $addr = inet_pton($subnetv6);
            $addr_unpack = unpack('H*hex', $addr);
            $addr_hex = $addr_unpack['hex'];
            $revsubnet = array_reverse(str_split($addr_hex));
            foreach ($revsubnet as $octet) {
                if ($octet == "0") {
                    array_shift($revsubnet);
                } else {
                    break;
                }
            }

            $newzone['ptr-domain'] = implode(".", $revsubnet) . ".ip6.arpa";
        }

        if (isset($dhcpv6ifconf['dnsserver'][0])) {
            $dnscfgv6 .= "  option dhcp6.name-servers " . join(",", $dhcpv6ifconf['dnsserver']) . ";";
        } elseif (isset($config['dnsmasq']['enable']) || isset($config['unbound']['enable'])) {
            $dnscfgv6 .= "  option dhcp6.name-servers {$ifcfgipv6};";
        } elseif (!empty($dns_arrv6)) {
            $dnscfgv6 .= "  option dhcp6.name-servers " . join(",", $dns_arrv6) . ";";
        }

        $dhcpdv6conf .= "\nsubnet6 {$networkv6} {\n";

        if (!empty($dhcpv6ifconf['range']['from'])) {
            $dhcpdv6conf .= "  range6 {$dhcpv6ifconf['range']['from']} {$dhcpv6ifconf['range']['to']};\n";
        }

        $dhcpdv6conf .= "{$dnscfgv6}\n";

        if (!empty($dhcpv6ifconf['prefixrange']['from']) && is_ipaddrv6($dhcpv6ifconf['prefixrange']['from']) && is_ipaddrv6($dhcpv6ifconf['prefixrange']['to'])) {
            $dhcpdv6conf .= "  prefix6 {$dhcpv6ifconf['prefixrange']['from']} {$dhcpv6ifconf['prefixrange']['to']}/{$dhcpv6ifconf['prefixrange']['prefixlength']};\n";
        }

        // default-lease-time
        if (!empty($dhcpv6ifconf['defaultleasetime'])) {
            $dhcpdv6conf .= "  default-lease-time {$dhcpv6ifconf['defaultleasetime']};\n";
        }

        // max-lease-time
        if (!empty($dhcpv6ifconf['maxleasetime'])) {
            $dhcpdv6conf .= "  max-lease-time {$dhcpv6ifconf['maxleasetime']};\n";
        }

        // min-secs
        if (!empty($dhcpv6ifconf['minsecs'])) {
            $dhcpdv6conf .= "  min-secs {$dhcpv6ifconf['minsecs']};\n";
        }

        // ntp-servers
        if (isset($dhcpv6ifconf['ntpserver'][0])) {
            $ntpservers = array();
            foreach ($dhcpv6ifconf['ntpserver'] as $ntpserver) {
                if (is_ipaddrv6($ntpserver)) {
                    $ntpservers[] = $ntpserver;
                }
            }
            if (count($ntpservers) > 0) {
                $dhcpdv6conf .= "  option dhcp6.sntp-servers " . join(",", $dhcpv6ifconf['ntpserver']) . ";\n";
            }
        }

        // Handle option, number rowhelper values
        if (isset($dhcpv6ifconf['numberoptions']['item'])) {
            $dhcpdv6conf .= "\n";
            foreach ($dhcpv6ifconf['numberoptions']['item'] as $itemv6idx => $itemv6) {
                if (empty($itemv6['type']) || $itemv6['type'] == "text") {
                    $dhcpdv6conf .= "  option custom-{$dhcpv6if}-{$itemv6idx} \"{$itemv6['value']}\";\n";
                } else {
                    $dhcpdv6conf .= "  option custom-{$dhcpv6if}-{$itemv6idx} {$itemv6['value']};\n";
                }
            }
        }

        // net boot information
        if (isset($dhcpv6ifconf['netboot'])) {
            if (!empty($dhcpv6ifconf['bootfile_url'])) {
                $dhcpdv6conf .= "  option dhcp6.bootfile-url \"{$dhcpv6ifconf['bootfile_url']}\";\n";
            }
        }

        $dhcpdv6conf .= "}\n";

        /* add static mappings */
        /* Needs to use DUID */
        if (isset($dhcpv6ifconf['staticmap'])) {
            $i = 0;
            foreach ($dhcpv6ifconf['staticmap'] as $sm) {
                $dhcpdv6conf .= <<<EOD

host s_{$dhcpv6if}_{$i} {
  host-identifier option dhcp6.client-id {$sm['duid']};

EOD;
                if (!empty($sm['ipaddrv6'])) {
                    if (isset($config['interfaces'][$dhcpv6if]['dhcpd6track6allowoverride'])) {
                        $sm['ipaddrv6'] = make_ipv6_64_address($ifcfgipv6, $sm['ipaddrv6']);
                    }
                    $dhcpdv6conf .= "  fixed-address6 {$sm['ipaddrv6']};\n";
                }

                if (!empty($sm['hostname'])) {
                    $dhhostname = str_replace(" ", "_", $sm['hostname']);
                    $dhhostname = str_replace(".", "_", $dhhostname);
                    $dhcpdv6conf .= "  option host-name {$dhhostname};\n";
                }
                if (!empty($sm['filename'])) {
                    $dhcpdv6conf .= "  filename \"{$sm['filename']}\";\n";
                }

                if (!empty($sm['rootpath'])) {
                    $dhcpdv6conf .= "  option root-path \"{$sm['rootpath']}\";\n";
                }

                if (!empty($sm['domainsearchlist']) && ($sm['domainsearchlist'] != $dhcpv6ifconf['domainsearchlist'])) {
                    $dhcpdv6conf .= "  option dhcp6.domain-search \"" . join("\",\"", preg_split("/[ ;]+/", $sm['domainsearchlist'])) . "\";\n";
                }

                $dhcpdv6conf .= "}\n";
                $i++;
            }
        }

        if (!empty($newzone['domain-name']) && isset($dhcpv6ifconf['ddnsupdate']) && is_ipaddrv6($dhcpv6ifconf['ddnsdomainprimary'])) {
            $newzone['dns-servers'] = array($dhcpv6ifconf['ddnsdomainprimary']);
            $newzone['ddnsdomainkeyname'] = $dhcpv6ifconf['ddnsdomainkeyname'];
            $newzone['ddnsdomainkey'] = $dhcpv6ifconf['ddnsdomainkey'];
            $newzone['ddnsdomainalgorithm'] = !empty($dhcpv6ifconf['ddnsdomainalgorithm']) ? $dhcpv6ifconf['ddnsdomainalgorithm'] : "hmac-md5";
            $ddns_zones[] = $newzone;
        }

        if (preg_match("/poes/si", $dhcpv6if)) {
            /* magic here */
            $dhcpdv6ifs = array_merge($dhcpdv6ifs, dhcpd_get_pppoes_child_interfaces($dhcpv6if));
        } else {
            $realif = get_real_interface($dhcpv6if, "inet6");
            if (stristr("$realif", "bridge")) {
                $mac = get_interface_mac($realif);
                $v6address = dhcpd_generate_ipv6_from_mac($mac);
                /* create link local address for bridges */
                mwexec("/sbin/ifconfig {$realif} inet6 {$v6address}");
            }
            $realif = escapeshellcmd($realif);
            $dhcpdv6ifs[] = $realif;
        }
    }

    if ($need_ddns_updates) {
        $dhcpdv6conf .= "\nddns-update-style interim;\n";
        $dhcpdv6conf .= "update-static-leases on;\n";
        $dhcpdv6conf .= dhcpd_zones($ddns_zones, "inet6");
    } else {
        $dhcpdv6conf .= "\nddns-update-style none;\n";
    }

    @file_put_contents('/var/dhcpd/etc/dhcpdv6.conf', $dhcpdv6conf);
    @touch('/var/dhcpd/var/db/dhcpd6.leases');
    @unlink('/var/dhcpd/var/run/dhcpdv6.pid');

    /* fire up dhcpd in a chroot */
    if (count($dhcpdv6ifs) > 0) {
        mwexec('/usr/local/sbin/dhcpd -6 -user dhcpd -group dhcpd -chroot /var/dhcpd -cf /etc/dhcpdv6.conf -pf /var/run/dhcpdv6.pid ' . join(' ', $dhcpdv6ifs));
        mwexecf('/usr/local/sbin/dhcpleases6 -c %s -l %s', array(
            '/usr/local/sbin/configctl dhcpd update prefixes',
            '/var/dhcpd/var/db/dhcpd6.leases',
        ));
    }

    if ($verbose) {
        echo "done.\n";
    }
}

function dhcpd_dhcrelay_configure($verbose = false, $family = 'all')
{
    if ($family == 'all' || $family == 'inet') {
        dhcpd_dhcrelay4_configure($verbose);
    }

    if ($family == 'all' || $family == 'inet6') {
        dhcpd_dhcrelay6_configure($verbose);
    }
}

function dhcpd_dhcrelay4_configure($verbose = false)
{
    global $config;

    $dhcrelayifs = array();

    killbypid('/var/run/dhcrelay.pid', 'TERM', true);

    $dhcrelaycfg = &config_read_array('dhcrelay');

    if (!isset($dhcrelaycfg['enable'])) {
        return;
    }

    if ($verbose) {
        echo 'Starting DHCPv4 relay...';
        flush();
    }

    $iflist = get_configured_interface_with_descr();
    $ifconfig_details = legacy_interfaces_details();
    $a_gateways = (new \OPNsense\Routing\Gateways($ifconfig_details))->gatewaysIndexedByName(true);
    $dhcifaces = explode(",", $dhcrelaycfg['interface']);
    foreach ($dhcifaces as $dhcrelayif) {
        if (!isset($iflist[$dhcrelayif]) || link_interface_to_bridge($dhcrelayif)) {
            continue;
        }

        if (is_ipaddr(get_interface_ip($dhcrelayif, $ifconfig_details))) {
            $dhcrelayifs[] = get_real_interface($dhcrelayif);
        }
    }

    /*
     * In order for the relay to work, it needs to be active
     * on the interface in which the destination server sits.
     */
    $srvips = explode(",", $dhcrelaycfg['server']);
    foreach ($srvips as $srcidx => $srvip) {
        unset($destif);

        /* XXX runs multiple times because of server address loop :( */
        foreach (array_keys($iflist) as $ifname) {
            $realif = get_real_interface($ifname, $ifconfig_details);
            $subnet = find_interface_network($realif, true, $ifconfig_details);
            if (!is_subnetv4($subnet)) {
                continue;
            }
            if (ip_in_subnet($srvip, $subnet)) {
                $destif = $realif;
                break;
            }
        }
        if (!isset($destif)) {
            foreach (get_staticroutes() as $rtent) {
                if (ip_in_subnet($srvip, $rtent['network'])) {
                    $destif = get_real_interface($a_gateways[$rtent['gateway']]['interface']);
                    break;
                }
            }
        }

        if (!isset($destif)) {
            /* Create an array from the existing route table */
            exec("/usr/bin/netstat -rnWf inet", $route_str);
            array_shift($route_str);
            array_shift($route_str);
            array_shift($route_str);
            array_shift($route_str);
            foreach ($route_str as $routeline) {
                $items = preg_split("/[ ]+/i", $routeline);
                if (is_subnetv4($items[0])) {
                    $subnet = $items[0];
                } elseif (is_ipaddrv4($items[0])) {
                    $subnet = "{$items[0]}/32";
                } else {
                    // Not a subnet or IP address, skip to the next line.
                    continue;
                }
                if (ip_in_subnet($srvip, $subnet)) {
                    $destif = trim($items[6]);
                    break;
                }
            }
        }

        if (!isset($destif)) {
            if (is_array($config['gateways']['gateway_item'])) {
                foreach ($config['gateways']['gateway_item'] as $gateway) {
                    if (isset($gateway['defaultgw'])) {
                        $destif = get_real_interface($gateway['interface']);
                        break;
                    }
                }
            } else {
                $destif = get_real_interface("wan");
            }
        }

        if (!empty($destif)) {
            $dhcrelayifs[] = $destif;
        }
    }
    $dhcrelayifs = array_unique($dhcrelayifs);

    if (!empty($dhcrelayifs)) {
        $cmd = "/usr/local/sbin/dhcrelay -i " .  implode(" -i ", $dhcrelayifs);

        if (isset($dhcrelaycfg['agentoption'])) {
            $cmd .= ' -a -m replace';
        }

        $cmd .= " " . implode(" ", $srvips);
        mwexec($cmd);
    }

    if ($verbose) {
        echo "done.\n";
    }
}

function dhcpd_dhcrelay6_configure($verbose = false)
{
    global $config;

    $dhcrelayifs = array();

    killbypid('/var/run/dhcrelay6.pid', 'TERM', true);

    $dhcrelaycfg = &config_read_array('dhcrelay6');

    if (!isset($dhcrelaycfg['enable'])) {
        return;
    }

    if ($verbose) {
        echo 'Starting DHCPv6 relay...';
        flush();
    }

    $iflist = get_configured_interface_with_descr();
    $ifconfig_details = legacy_interfaces_details();
    $a_gateways = (new \OPNsense\Routing\Gateways($ifconfig_details))->gatewaysIndexedByName(true);
    $dhcifaces = explode(",", $dhcrelaycfg['interface']);
    foreach ($dhcifaces as $dhcrelayif) {
        if (!isset($iflist[$dhcrelayif]) || link_interface_to_bridge($dhcrelayif)) {
            continue;
        }

        if (is_ipaddrv6(get_interface_ipv6($dhcrelayif, $ifconfig_details))) {
            $dhcrelayifs[] = get_real_interface($dhcrelayif);
        }
    }
    $dhcrelayifs = array_unique($dhcrelayifs);

    /*
     * In order for the relay to work, it needs to be active
     * on the interface in which the destination server sits.
     */
    $srvips = explode(",", $dhcrelaycfg['server']);
    $srvifaces = array();
    foreach ($srvips as $srcidx => $srvip) {
        unset($destif);

        /* XXX runs multiple times because of server address loop :( */
        foreach (array_keys($iflist) as $ifname) {
            $realif = get_real_interface($ifname, 'inet6');
            $subnet = find_interface_networkv6($realif, true, $ifconfig_details);
            if (!is_subnetv6($subnet)) {
                continue;
            }
            if (ip_in_subnet($srvip, $subnet)) {
                $destif = $realif;
                break;
            }
        }

        if (!isset($destif)) {
            if (isset($config['staticroutes']['route'])) {
                foreach ($config['staticroutes']['route'] as $rtent) {
                    if (ip_in_subnet($srvip, $rtent['network'])) {
                        $destif = $a_gateways[$rtent['gateway']]['interface'];
                        break;
                    }
                }
            }
        }

        if (!isset($destif)) {
            /* Create an array from the existing route table */
            exec("/usr/bin/netstat -rnWf inet6", $route_str);
            array_shift($route_str);
            array_shift($route_str);
            array_shift($route_str);
            array_shift($route_str);
            foreach ($route_str as $routeline) {
                $items = preg_split("/[ ]+/i", $routeline);
                if (ip_in_subnet($srvip, $items[0])) {
                    $destif = trim($items[6]);
                    break;
                }
            }
        }

        if (!isset($destif)) {
            if (is_array($config['gateways']['gateway_item'])) {
                foreach ($config['gateways']['gateway_item'] as $gateway) {
                    if (isset($gateway['defaultgw'])) {
                        $destif = $gateway['interface'];
                        break;
                    }
                }
            } else {
                $destif = get_real_interface("wan");
            }
        }

        if (!empty($destif)) {
            $srvifaces[] = "{$srvip}%{$destif}";
        }
    }

    if (!empty($dhcrelayifs) && !empty($srvifaces)) {
        $cmd = '/usr/local/sbin/dhcrelay -6 -pf /var/run/dhcrelay6.pid';
        foreach ($dhcrelayifs as $dhcrelayif) {
            $cmd .= " -l {$dhcrelayif}";
        }
        foreach ($srvifaces as $srviface) {
            $cmd .= " -u \"{$srviface}\"";
        }
        mwexec($cmd);
    }

    if ($verbose) {
        echo "done.\n";
    }
}

function dhcpd_staticmap($domain_fallback = 'not.found', $ifconfig_details = null)
{
    $staticmap = [];

    foreach ([4, 6] as $inet) {
        $ipaddr = $inet == 6 ? 'ipaddrv6' : 'ipaddr';

        foreach (config_read_array($inet == 6 ? 'dhcpdv6' : 'dhcpd') as $dhcpif => $dhcpifconf) {
            if (!isset($dhcpifconf['staticmap']) || !isset($dhcpifconf['enable'])) {
                continue;
            }

            $ifconf = config_read_array('interfaces', $dhcpif);
            list ($ipaddrv6) = $inet == 6 && isset($ifconf['dhcpd6track6allowoverride']) ?
                interfaces_primary_address6($dhcpif, null, $ifconfig_details) : [null];

            foreach ($dhcpifconf['staticmap'] as $host) {
                if (empty($host[$ipaddr]) || empty($host['hostname'])) {
                    continue;
                }

                if (!empty($ipaddrv6)) {
                    $host['ipaddrv6'] = make_ipv6_64_address($ipaddrv6, $host['ipaddrv6']);
                }

                // XXX: dhcpdv6 domain entries have been superseded by domainsearchlist,
                //      for backward compatibilty support both here.
                if (!empty($host['domainsearchlist'])) {
                    $domain = $host['domainsearchlist'];
                } elseif (!empty($host['domain'])) {
                    $domain = $host['domain'];
                } elseif (!empty($dhcpifconf['domainsearchlist'])) {
                    $domain = $dhcpifconf['domainsearchlist'];
                } elseif (!empty($dhcpifconf['domain'])) {
                    $domain = $dhcpifconf['domain'];
                } else {
                    $domain = $domain_fallback;
                }

                $domain = explode(";", $domain)[0]; // XXX: first entry of domainsearchlist

                $entry = [
                    'descr' => $host['descr'],
                    'domain' => $domain,
                    'hostname' => $host['hostname'],
                    'interface' => $dhcpif,
                    $ipaddr => $host[$ipaddr],
                ];

                if (isset($host['duid'])) {
                    $entry['duid'] = $host['duid'];
                }

                $staticmap[] = $entry;
            }
        }
    }

    return $staticmap;
}
