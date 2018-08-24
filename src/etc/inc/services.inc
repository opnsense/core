<?php

/*
 * Copyright (C) 2014-2018 Franco Fichtner <franco@opnsense.org>
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

/*
 * Base services are slowly being converted into plugins,
 * hooks are created and expanded to accomodate for their
 * feature set, which allows us to build a better plugin
 * system in general.
 *
 * Ideally this may allow us to remove all service-relevant
 * parts from system.inc and services.inc so that the latter
 * will eventually become obsolete.
 *
 * The goal may not be to remove all of the base system
 * as plugins, but the things listed below are viable
 * targets in the future:
 */
require_once('plugins.inc.d/dnsmasq.inc');
require_once('plugins.inc.d/ipsec.inc');
require_once('plugins.inc.d/openssh.inc');
require_once('plugins.inc.d/openvpn.inc');
require_once('plugins.inc.d/unbound.inc');

function generate_ipv6_from_mac($mac)
{
    $elements = explode(":", $mac);
    if (count($elements) <> 6) {
        return false;
    }

    $i = 0;
    $ipv6 = "fe80::";
    foreach($elements as $byte) {
        if ($i == 0) {
            $hexadecimal =  substr($byte, 1, 2);
            $bitmap = base_convert($hexadecimal, 16, 2);
            $bitmap = str_pad($bitmap, 4, "0", STR_PAD_LEFT);
            $bitmap = substr($bitmap, 0, 2) ."1". substr($bitmap, 3,4);
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

function get_pppoes_child_interfaces($ifpattern)
{
    $if_arr = array();
    if ($ifpattern == "") {
        return;
    }

    exec("ifconfig", $out, $ret);
    foreach($out as $line) {
        if (preg_match("/^({$ifpattern}[0-9]+):/i", $line, $match)) {
            $if_arr[] = $match[1];
        }
    }
    return $if_arr;
}

function services_radvd_configure($blacklist = array())
{
    global $config;

    killbypid('/var/run/radvd.pid', 'TERM', true);

    config_read_array('dhcpdv6');

    $radvdconf = "# Automatically generated, do not edit\n";

    /* Process all links which need the router advertise daemon */
    $radvdifs = array();

    /* handle manually configured DHCP6 server settings first */
    foreach ($config['dhcpdv6'] as $dhcpv6if => $dhcpv6ifconf) {
        if (isset($config['interfaces'][$dhcpv6if]['track6-interface']) && !isset($config['interfaces'][$dhcpv6if]['dhcpd6track6allowoverride'])) {
            continue;
        } elseif (!isset($config['interfaces'][$dhcpv6if]['enable'])) {
            continue;
        } elseif (isset($blacklist[$dhcpv6if])) {
            /* Do not put in the config an interface which is down */
            continue;
        } elseif (!isset($dhcpv6ifconf['ramode']) || $dhcpv6ifconf['ramode'] == 'disabled') {
            continue;
        }

        /* check if we need to listen on a CARP interface */
        $realif = get_real_interface($dhcpv6if, "inet6");
        if (!empty($dhcpv6ifconf['rainterface']) && $dhcpv6ifconf['rainterface'] != 'interface') {
            $dhcpv6if = $dhcpv6ifconf['rainterface'];
        } elseif (!empty($dhcpv6ifconf['rainterface'])) {
            // backward compatibilty mode, unset invalid options
            unset($dhcpv6ifconf['rainterface']);
        }

        $ifcfgipv6 = get_interface_ipv6($dhcpv6if);
        if (!is_ipaddrv6($ifcfgipv6) && !isset($config['interfaces'][$dhcpv6if]['dhcpd6track6allowoverride'])) {
            continue;
        }

        $radvdifs[$realif] = 1;

        $radvdconf .= "# Generated for DHCPv6 Server $dhcpv6if\n";
        $radvdconf .= "interface {$realif} {\n";
        $radvdconf .= sprintf("\tAdvSendAdvert %s;\n", !empty($dhcpv6ifconf['ranosend']) ? 'off' : 'on');
        $radvdconf .= sprintf("\tMinRtrAdvInterval %s;\n", !empty($dhcpv6ifconf['ramininterval']) ? $dhcpv6ifconf['ramininterval'] : '200');
        $radvdconf .= sprintf("\tMaxRtrAdvInterval %s;\n", !empty($dhcpv6ifconf['ramaxinterval']) ? $dhcpv6ifconf['ramaxinterval'] : '600');
        $mtu = legacy_interface_stats($realif)['mtu'];
        $radvdconf .= "\tAdvLinkMTU ". (is_numeric($mtu) ? $mtu : 1280) .";\n";

        switch($dhcpv6ifconf['rapriority']) {
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

        switch($dhcpv6ifconf['ramode']) {
            case "managed":
            case "assist":
                $radvdconf .= "\tAdvManagedFlag on;\n";
                $radvdconf .= "\tAdvOtherConfigFlag on;\n";
                break;
            case "stateless":
                $radvdconf .= "\tAdvManagedFlag off;\n";
                $radvdconf .= "\tAdvOtherConfigFlag on;\n";
                break;
            default:
                break;
        }

        $stanzas = array();
        $ifcfgsnv6 = get_interface_subnetv6($dhcpv6if);
        if (!empty($ifcfgsnv6)) {
            $subnetv6 = gen_subnetv6($ifcfgipv6, $ifcfgsnv6);
            $stanzas[] = "{$subnetv6}/{$ifcfgsnv6}";
        }

        $viparr = &config_read_array('virtualip', 'vip');
        foreach ($viparr as $vip) {
            if ($vip['interface'] == $dhcpv6if && is_ipaddrv6($vip['subnet'])) {
                $ifcfgsnv6 = $vip['subnet_bits'];
                $subnetv6 = gen_subnetv6($vip['subnet'], $ifcfgsnv6);
                $stanzas[] = "{$subnetv6}/{$ifcfgsnv6}";
            }
        }

        /* VIPs may duplicate readings from system */
        $stanzas = array_unique($stanzas);

        foreach ($stanzas as $stanza) {
            $radvdconf .= "\tprefix {$stanza} {\n";
            $radvdconf .= "\t\tDeprecatePrefix ". (!empty($dhcpv6ifconf['rainterface']) ? "off" : "on").";\n";
            switch($dhcpv6ifconf['ramode']) {
                case "managed":
                    $radvdconf .= "\t\tAdvOnLink on;\n";
                    $radvdconf .= "\t\tAdvAutonomous off;\n";
                    $radvdconf .= "\t\tAdvRouterAddr on;\n";
                    break;
                case "router":
                    $radvdconf .= "\t\tAdvOnLink off;\n";
                    $radvdconf .= "\t\tAdvAutonomous off;\n";
                    $radvdconf .= "\t\tAdvRouterAddr on;\n";
                    break;
                case "assist":
                case "unmanaged":
                case "stateless":
                    $radvdconf .= "\t\tAdvOnLink on;\n";
                    $radvdconf .= "\t\tAdvAutonomous on;\n";
                    $radvdconf .= "\t\tAdvRouterAddr on;\n";
                    break;
                default:
                    break;
            }
            $radvdconf .= "\t};\n";
        }

        if (!empty($dhcpv6ifconf['rainterface'])) {
            $radvdconf .= "\troute ::/0 {\n";
            $radvdconf .= "\t\tRemoveRoute off;\n";
            $radvdconf .= "\t};\n";
        } elseif (empty($dhcpv6ifconf['ranodefault'])) {
            $radvdconf .= "\troute ::/0 {\n";
            $radvdconf .= "\t\tRemoveRoute on;\n";
            $radvdconf .= "\t};\n";
        }

        if (!empty($dhcpv6ifconf['raroutes'])) {
            foreach (explode(',', $dhcpv6ifconf['raroutes']) as $raroute) {
                $radvdconf .= "\troute {$raroute} {\n";
                $radvdconf .= "\t\tRemoveRoute on;\n";
                $radvdconf .= "\t};\n";
            }
        }

        /* add DNS servers */
        $dnslist_tmp = array();
        if (isset($dhcpv6ifconf['rasamednsasdhcp6']) && !empty($dhcpv6ifconf['dnsserver'][0])) {
            $dnslist_tmp = $dhcpv6ifconf['dnsserver'];
        } elseif (!isset($dhcpv6ifconf['rasamednsasdhcp6']) && !empty($dhcpv6ifconf['radnsserver'][0])) {
            $dnslist_tmp = $dhcpv6ifconf['radnsserver'];
        } elseif (isset($config['dnsmasq']['enable']) || isset($config['unbound']['enable'])) {
            $dnslist_tmp[] = get_interface_ipv6($realif);
        } elseif (!empty($config['system']['dnsserver'][0])) {
            $dnslist_tmp = $config['system']['dnsserver'];
        }
        $dnslist = array();
        foreach($dnslist_tmp as $server) {
            if (is_ipaddrv6($server)) {
                $dnslist[] = $server;
            }
        }

        if (count($dnslist) > 0) {
            $radvdconf .= "\tRDNSS ".implode(" ", $dnslist)." { };\n";
        }
        if (!empty($dhcpv6ifconf['radomainsearchlist'])) {
            $dnssl = implode(' ', explode(';', $dhcpv6ifconf['radomainsearchlist']));
            $radvdconf .= "\tDNSSL {$dnssl} { };\n";
        } elseif (!empty($config['system']['domain'])) {
            $radvdconf .= "\tDNSSL {$config['system']['domain']} { };\n";
        }
        $radvdconf .= "};\n";
    }

    /* handle DHCP-PD prefixes and 6RD dynamic interfaces */
    foreach (get_configured_interface_list() as $if => $ifdescr) {
        if (!isset($config['interfaces'][$if]['track6-interface'])) {
            continue;
        } elseif (empty($config['interfaces'][$config['interfaces'][$if]['track6-interface']])) {
            continue;
        } elseif (!isset($config['interfaces'][$if]['enable'])) {
            continue;
        } elseif (isset($blacklist[$if])) {
            /* Do not put in the config an interface which is down */
            continue;
        }
        $trackif = $config['interfaces'][$if]['track6-interface'];
        $realif = get_real_interface($if, "inet6");
        /* prevent duplicate entries, manual overrides */
        if (isset($radvdifs[$realif])) {
            continue;
        }

        $radvdifs[$realif] = 1;
        $autotype = 'unknown';

        if (isset($config['interfaces'][$trackif]['ipaddrv6'])) {
            $autotype = $config['interfaces'][$trackif]['ipaddrv6'];
        }

        $mtu = legacy_interface_stats($realif)['mtu'];
        $dnslist = array();
        $subnetv6 = '::';
        $ifcfgsnv6 = '64';

        $ifcfgipv6 = get_interface_ipv6($if);

        if ($autotype == 'slaac') {
            $subnetv6 = '2000::';
        } elseif (is_ipaddrv6($ifcfgipv6)) {
            $ifcfgsnv6 = get_interface_subnetv6($if);
            $subnetv6 = gen_subnetv6($ifcfgipv6, $ifcfgsnv6);
        }

        if ((isset($config['dnsmasq']['enable']) || isset($config['unbound']['enable'])) && is_ipaddrv6($ifcfgipv6)) {
            $dnslist[] = $ifcfgipv6;
        } elseif (!empty($config['system']['dnsserver'])) {
            foreach($config['system']['dnsserver'] as $server) {
                if (is_ipaddrv6($server)) {
                    $dnslist[] = $server;
                }
            }
        }

        $radvdconf .= "# Generated config for {$autotype} delegation from {$trackif} on {$if}\n";
        $radvdconf .= "interface {$realif} {\n";
        $radvdconf .= "\tAdvSendAdvert on;\n";
        $radvdconf .= "\tMinRtrAdvInterval 3;\n";
        $radvdconf .= "\tMaxRtrAdvInterval 10;\n";
        $radvdconf .= "\tAdvLinkMTU ". (is_numeric($mtu) ? $mtu : 1280) .";\n";
        $radvdconf .= "\tAdvOtherConfigFlag on;\n";
        $radvdconf .= "\tprefix {$subnetv6}/{$ifcfgsnv6} {\n";
        if ($autotype == 'slaac') {
            $realtrackif = get_real_interface($trackif, 'inet6');
            $radvdconf .= "\t\tBase6Interface $realtrackif;\n";
            $radvdconf .= "\t\tDeprecatePrefix on;\n";
        }
        $radvdconf .= "\t\tAdvOnLink on;\n";
        $radvdconf .= "\t\tAdvAutonomous on;\n";
        $radvdconf .= "\t\tAdvRouterAddr on;\n";
        $radvdconf .= "\t};\n";

        if (count($dnslist) > 0) {
            $radvdconf .= "\tRDNSS ".implode(" ", $dnslist)." { };\n";
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
}

function services_dhcpd_leasesfile()
{
    return '/var/dhcpd/var/db/dhcpd.leases';
}

function services_dhcpdv6_leasesfile()
{
    return '/var/dhcpd/var/db/dhcpd6.leases';
}

function services_dhcpd_configure($family = 'all', $blacklist = array(), $verbose = false)
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
        services_dhcpdv4_configure($verbose);
    }

    if ($family == 'all' || $family == 'inet6') {
        services_dhcpdv6_configure($blacklist, $verbose);
        services_radvd_configure($blacklist);
    }
}

function is_dhcpv4_server_enabled()
{
    global $config;

    if (empty($config['dhcpd']) || !is_array($config['dhcpd'])) {
        return false;
    }

    foreach ($config['dhcpd'] as $dhcpif => $dhcpifconf) {
        if (isset($dhcpifconf['enable']) && !empty($config['interfaces'][$dhcpif])) {
            return true;
        }
    }

    return false;
}

function services_dhcpdv4_configure($verbose = false)
{
    global $config;

    $need_ddns_updates = false;
    $ddns_zones = array();

    killbypid('/var/dhcpd/var/run/dhcpd.pid', 'TERM', true);

    if (!is_dhcpv4_server_enabled()) {
        return;
    }

    /* Only consider DNS servers with IPv4 addresses for the IPv4 DHCP server. */
    $dns_arrv4 = array();
    if (!empty($config['system']['dnsserver'][0])) {
        foreach($config['system']['dnsserver'] as $dnsserver) {
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
    foreach ($config['dhcpd'] as $dhcpif => $dhcpifconf) {
        if (isset($dhcpifconf['numberoptions']['item'])) {
            foreach($dhcpifconf['numberoptions']['item'] as $itemidx => $item) {
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
    $add_routers = false;
    $gateways_arr = return_gateways_array();
    /* only add a routers line if the system has any IPv4 gateway at all */
    /* a static route has a gateway, manually overriding this field always works */
    foreach($gateways_arr as $gwitem) {
        if ($gwitem['ipprotocol'] == "inet") {
            $add_routers = true;
            break;
        }
    }

    /*    loop through and determine if we need to setup
     *    failover peer "bleh" entries
     */
    foreach ($config['dhcpd'] as $dhcpif => $dhcpifconf) {
        interfaces_staticarp_configure($dhcpif);
        if (!isset($dhcpifconf['enable'])) {
            continue;
        }

        if (!empty($dhcpifconf['failover_peerip'])) {
            $intip = get_interface_ip($dhcpif);
            /*
             *    yep, failover peer is defined.
             *    does it match up to a defined vip?
             */
            $skew = 110;
            if (!empty($config['virtualip']['vip'])) {
                foreach ($config['virtualip']['vip'] as $vipent) {
                    if ($vipent['interface'] == $dhcpif) {
                        $carp_nw = gen_subnet($vipent['subnet'], $vipent['subnet_bits']);
                        if (ip_in_subnet($dhcpifconf['failover_peerip'], "{$carp_nw}/{$vipent['subnet_bits']}")) {
                            /* this is the interface! */
                            if (is_numeric($vipent['advskew']) && (intval($vipent['advskew']) < 20)) {
                                $skew = 0;
                                break;
                            }
                        }
                    }
                }
            } else {
                log_error('Warning! DHCP Failover setup and no CARP virtual IPs defined!');
            }
            $dhcpdconf_pri = "";
            if ($skew > 10) {
                $type = "secondary";
                $my_port = "520";
                $peer_port = "519";
            } else {
                $my_port = "519";
                $peer_port = "520";
                $type = "primary";
                $dhcpdconf_pri  = "split 128;\n";
                $dhcpdconf_pri .= "  mclt 600;\n";
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

    $Iflist = get_configured_interface_list();
    foreach ($config['dhcpd'] as $dhcpif => $dhcpifconf) {
        $ifcfg = $config['interfaces'][$dhcpif];
        $newzone = array();

        if (!isset($dhcpifconf['enable']) || !isset($Iflist[$dhcpif])) {
            continue;
        }
        $ifcfgip = get_interface_ip($dhcpif);
        $ifcfgsn = get_interface_subnet($dhcpif);
        $subnet = gen_subnet($ifcfgip, $ifcfgsn);
        $subnetmask = gen_subnet_mask($ifcfgsn);

        if (!is_ipaddr($subnet)) {
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

        /* Create classes - These all contain comma separated lists. Join them into one
           big comma separated string then split them all up. */
        $all_mac_strings = array();
        foreach($all_pools as $poolconf) {
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
        foreach($all_pools as $poolconf) {
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

            if (!empty($poolconf['gateway']) && $poolconf['gateway'] != "none"
              && (empty($dhcpifconf['gateway']) || $poolconf['gateway'] != $dhcpifconf['gateway'])
              ) {
                $dhcpdconf .= "    option routers {$poolconf['gateway']};\n";
            }

            if (!empty($dhcpifconf['failover_peerip'])) {
                $dhcpdconf .= "    failover peer \"dhcp_{$dhcpif}\";\n";
            }

            $pdnscfg = "";
            if (!empty($poolconf['domain'])
              && (empty($dhcpifconf['domain']) || $poolconf['domain'] != $dhcpifconf['domain'])
              ) {
                $pdnscfg .= "    option domain-name \"{$poolconf['domain']}\";\n";
            }

            if (!empty($poolconf['domainsearchlist'])
              && (empty($dhcpifconf['domainsearchlist']) || $poolconf['domainsearchlist'] != $dhcpifconf['domainsearchlist'])) {
                $pdnscfg .= "    option domain-search \"" . join("\",\"", preg_split("/[ ;]+/", $poolconf['domainsearchlist'])) . "\";\n";
            }

            if (isset($poolconf['ddnsupdate'])) {
                if (!empty($poolconf['ddnsdomain'])
                  && (empty($dhcpifconf['ddnsdomain']) || $poolconf['ddnsdomain'] != $dhcpifconf['ddnsdomain'])) {
                    $pdnscfg .= "    ddns-domainname \"{$poolconf['ddnsdomain']}\";\n";
                }
                $pdnscfg .= "    ddns-update-style interim;\n";
            }

            if (!empty($poolconf['dnsserver'][0])
              && (empty($dhcpifconf['dnsserver'][0]) || $poolconf['dnsserver'][0] != $dhcpifconf['dnsserver'][0])) {
                $pdnscfg .= "    option domain-name-servers " . join(",", $poolconf['dnsserver']) . ";\n";
            }
            $dhcpdconf .= "{$pdnscfg}";

            // default-lease-time
            if (!empty($poolconf['defaultleasetime'])
              && (empty($dhcpifconf['defaultleasetime']) || $poolconf['defaultleasetime'] != $dhcpifconf['defaultleasetime'])) {
                $dhcpdconf .= "    default-lease-time {$poolconf['defaultleasetime']};\n";
            }

            // max-lease-time
            if (!empty($poolconf['maxleasetime'])
              && (empty($dhcpifconf['maxleasetime']) || $poolconf['maxleasetime'] != $dhcpifconf['maxleasetime'])) {
                $dhcpdconf .= "    max-lease-time {$poolconf['maxleasetime']};\n";
            }
            // interface MTU
            if (!empty($poolconf['interface_mtu'])
              && (empty($dhcpifconf['interface_mtu']) || $poolconf['interface_mtu'] != $dhcpifconf['interface_mtu'])) {
                $dhcpdconf .= "    option interface-mtu {$poolconf['interface_mtu']};\n";
            }

            // netbios-name*
            if (!empty($poolconf['winsserver'][0])
              && (empty($dhcpifconf['winsserver'][0]) || $poolconf['winsserver'][0] != $dhcpifconf['winsserver'][0])) {
                $dhcpdconf .= "    option netbios-name-servers " . join(",", $poolconf['winsserver']) . ";\n";
                $dhcpdconf .= "    option netbios-node-type 8;\n";
            }

            // ntp-servers
            if (!empty($poolconf['ntpserver'][0])
              && (empty($dhcpifconf['ntpserver'][0]) || $poolconf['ntpserver'][0] != $dhcpifconf['ntpserver'][0])) {
                $dhcpdconf .= "    option ntp-servers " . join(",", $poolconf['ntpserver']) . ";\n";
            }

            // tftp-server-name
            if (!empty($poolconf['tftp']) && (empty($dhcpifconf['tftp']) || $poolconf['tftp'] != $dhcpifconf['tftp'])) {
                $dhcpdconf .= "    option tftp-server-name \"{$poolconf['tftp']}\";\n";
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

        if (!empty($dhcpifconf['gateway']) && $dhcpifconf['gateway'] != "none") {
            $routers = $dhcpifconf['gateway'];
            $add_routers = true;
        } elseif (!empty($dhcpifconf['gateway']) && $dhcpifconf['gateway'] == "none") {
            $add_routers = false;
        } else {
            $routers = $ifcfgip;
        }
        if ($add_routers) {
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
            foreach($dhcpifconf['numberoptions']['item'] as $itemidx => $item) {
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

                if (!empty($sm['gateway']) && ($sm['gateway'] != $dhcpifconf['gateway'])) {
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
                    if (($sm['ddnsdomain'] <> "") && ($sm['ddnsdomain'] != $dhcpifconf['ddnsdomain'])) {
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
                }

                $dhcpdconf .= "}\n";
            }
        }

        $dhcpdifs[] = get_real_interface($dhcpif);
        if (!empty($newzone['domain-name'])) {
            if (isset($dhcpifconf['ddnsupdate'])) {
                $newzone['dns-servers'] = array($dhcpifconf['ddnsdomainprimary']);
                $newzone['ddnsdomainkeyname'] = $dhcpifconf['ddnsdomainkeyname'];
                $newzone['ddnsdomainkey'] = $dhcpifconf['ddnsdomainkey'];
                $newzone['ddnsdomainalgorithm'] = !empty($dhcpifconf['ddnsdomainalgorithm']) ? $dhcpifconf['ddnsdomainalgorithm'] : "hmac-md5";
                $ddns_zones[] = $newzone;
            }
        }
    }

    if ($need_ddns_updates) {
        $dhcpdconf .= "ddns-update-style interim;\n";
        $dhcpdconf .= "update-static-leases on;\n";
        $dhcpdconf .= services_dhcpd_zones($ddns_zones);
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

function services_dhcpd_zones($ddns_zones)
{
    $dhcpdconf = '';

    if (is_array($ddns_zones)) {
        $added_zones = array();
        $added_keys = array();
        foreach ($ddns_zones as $zone) {
            if (!is_array($zone) || empty($zone) || !is_array($zone['dns-servers'])) {
                continue;
            }

            $primary = $zone['dns-servers'][0];
            $secondary = empty($zone['dns-servers'][1]) ? "" : $zone['dns-servers'][1];

            // Make sure we aren't using any invalid or IPv6 DNS servers.
            if (!is_ipaddrv4($primary)) {
                if (is_ipaddrv4($secondary)) {
                    $primary = $secondary;
                    $secondary = "";
                } else {
                    continue;
                }
            }

            // We don't need to add zones multiple times.
            foreach (array($zone['domain-name'], $zone['ptr-domain']) as $domain) {
                if (!empty($domain) && !in_array($domain, $added_zones)) {
                    /* dhcpdconf2 is injected *after* the key */
                    $dhcpdconf2 = "zone {$domain}. {\n";
                    $dhcpdconf2 .= "  primary {$primary};\n";
                    if (is_ipaddrv4($secondary)) {
                        $dhcpdconf2 .= "  secondary {$secondary};\n";
                    }
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

function is_dhcpv6_server_enabled()
{
    global $config;

    foreach (legacy_config_get_interfaces(array('virtual' => false)) as $ifcfg) {
        if (isset($ifcfg['enable']) && !empty($ifcfg['track6-interface'])) {
            $pdlen = calculate_ipv6_delegation_length($ifcfg['track6-interface']);
            if ($pdlen > 2) {
                return true;
            }
        }
    }

    if (!isset($config['dhcpdv6']) || !is_array($config['dhcpdv6'])) {
        return false;
    }

    foreach ($config['dhcpdv6'] as $dhcpv6if => $dhcpv6ifconf) {
        if (isset($dhcpv6ifconf['enable']) && !empty($config['interfaces'][$dhcpv6if])) {
            return true;
        }
    }

    return false;
}

function services_dhcpdv6_configure($blacklist = array(), $verbose = false)
{
    global $config;

    killbypid('/var/dhcpd/var/run/dhcpdv6.pid', 'TERM', true);
    killbypid('/var/run/dhcpleases6.pid', 'TERM', true);

    if (!is_dhcpv6_server_enabled()) {
        return;
    }

    $dhcpdv6cfg = config_read_array('dhcpdv6');
    $Iflist = get_configured_interface_list();

    /* Only consider DNS servers with IPv6 addresses for the IPv6 DHCP server. */
    $dns_arrv6 = array();
    if (!empty($config['system']['dnsserver'][0])) {
        foreach($config['system']['dnsserver'] as $dnsserver) {
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
    foreach ($Iflist as $ifname) {
        /* Do not put in the config an interface which is down */
        if (isset($blacklist[$ifname])) {
            continue;
        }
        if (!empty($config['interfaces'][$ifname]['track6-interface'])) {
            $realif = get_real_interface($ifname, "inet6");
            $ifcfgipv6 = get_interface_ipv6($ifname);
            if (!is_ipaddrv6($ifcfgipv6)) {
                continue;
            }
            $ifcfgipv6 = Net_IPv6::getNetmask($ifcfgipv6, 64);
            $trackifname = $config['interfaces'][$ifname]['track6-interface'];
            $trackcfg = $config['interfaces'][$trackifname];
            $pdlen = calculate_ipv6_delegation_length($trackifname);
            $dhcpdv6cfg[$ifname] = array();
            $dhcpdv6cfg[$ifname]['enable'] = true;
            if (!isset($config['interfaces'][$ifname]['dhcpd6track6allowoverride']) || !isset($config['dhcpdv6'][$ifname]['enable'])) {
                /* fixed range */
                $ifcfgipv6arr = $ifcfgipv6arr = explode(':', $ifcfgipv6);
                $ifcfgipv6arr[7] = '1000';
                $dhcpdv6cfg[$ifname]['range'] = array();
                $dhcpdv6cfg[$ifname]['range']['from'] = Net_IPv6::compress(implode(':', $ifcfgipv6arr));
                $ifcfgipv6arr[7] = '2000';
                $dhcpdv6cfg[$ifname]['range']['to'] = Net_IPv6::compress(implode(':', $ifcfgipv6arr));

                /* prefix length > 0? We can add dhcp6 prefix delegation server */
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
                /* get config entry and marry it to the live prefix */
                $dhcpdv6cfg[$ifname]['range'] = array();
                $dhcpdv6cfg[$ifname]['range']['from'] = make_ipv6_64_address($ifcfgipv6, $config['dhcpdv6'][$ifname]['range']['from']);
                $dhcpdv6cfg[$ifname]['range']['to'] = make_ipv6_64_address($ifcfgipv6, $config['dhcpdv6'][$ifname]['range']['to']);

                $dhcpdv6cfg[$ifname]['prefixrange'] = array();
                $dhcpdv6cfg[$ifname]['prefixrange']['prefixlength'] = $config['dhcpdv6'][$ifname]['prefixrange']['prefixlength'];
                $dhcpdv6cfg[$ifname]['prefixrange']['from'] = $config['dhcpdv6'][$ifname]['prefixrange']['from'];
                $dhcpdv6cfg[$ifname]['prefixrange']['to'] = $config['dhcpdv6'][$ifname]['prefixrange']['to'];
            }
        }
    }

    $custoptionsv6 = "";
    foreach ($dhcpdv6cfg as $dhcpv6if => $dhcpv6ifconf) {
        if (isset($dhcpv6ifconf['numberoptions']['item'])) {
            foreach($dhcpv6ifconf['numberoptions']['item'] as $itemv6idx => $itemv6) {
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
option domain-name "{$config['system']['domain']}";
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
    $nsupdate = false;
    $dhcpv6num = 0;

    foreach ($dhcpdv6cfg as $dhcpv6if => $dhcpv6ifconf) {
        $ifcfgv6 = $config['interfaces'][$dhcpv6if];
        $newzone = array();

        if (!isset($dhcpv6ifconf['enable']) || !isset($Iflist[$dhcpv6if])) {
            continue;
        }
        $ifcfgipv6 = get_interface_ipv6($dhcpv6if);
        $ifcfgsnv6 = get_interface_subnetv6($dhcpv6if);
        $subnetv6 = gen_subnetv6($ifcfgipv6, $ifcfgsnv6);

        $dnscfgv6 = "";
        if (!empty($dhcpv6ifconf['domain'])) {
            $dnscfgv6 .= "  option domain-name \"{$dhcpv6ifconf['domain']}\";\n";
        }

        if (!empty($dhcpv6ifconf['domainsearchlist'])) {
            $dnscfgv6 .= "  option domain-search \"" . join("\",\"", preg_split("/[ ;]+/", $dhcpv6ifconf['domainsearchlist'])) . "\";\n";
        }

        if (isset($dhcpv6ifconf['ddnsupdate'])) {
            if (!empty($dhcpv6ifconf['ddnsdomain'])) {
                $dnscfgv6 .= "  ddns-domainname \"{$dhcpv6ifconf['ddnsdomain']}\";\n";
                $newzone['domain-name'] = $dhcpv6ifconf['ddnsdomain'];
            } else {
                $newzone['domain-name'] = $config['system']['domain'];
            }

            $nsupdate = true;
        }

        if (isset($dhcpv6ifconf['dnsserver'][0])) {
            $dnscfgv6 .= "  option dhcp6.name-servers " . join(",", $dhcpv6ifconf['dnsserver']) . ";";
        } elseif ((isset($config['dnsmasq']['enable']) || isset($config['unbound']['enable'])) && (is_ipaddrv6($ifcfgipv6))) {
            $dnscfgv6 .= "  option dhcp6.name-servers {$ifcfgipv6};";
        } elseif (!empty($dns_arrv6)) {
            $dnscfgv6 .= "  option dhcp6.name-servers " . join(",", $dns_arrv6) . ";";
        }

        if (is_ipaddrv6($ifcfgipv6)) {
            $dhcpdv6conf .= "\nsubnet6 {$subnetv6}/{$ifcfgsnv6}";
        } elseif (!empty($dhcpv6ifconf['range']['from'])) {
            $subnet6 = gen_subnetv6($dhcpv6ifconf['range']['from'], '64', !isset($dhcpdv6cfg[$ifname]['dhcpd6track6allowoverride']));
            $dhcpdv6conf .= "\nsubnet6 {$subnet6}/64";
        }
        $dhcpdv6conf .= " {\n";

        if (!empty($dhcpv6ifconf['range']['from'])) {
            $dhcpdv6conf .= <<<EOD
  range6 {$dhcpv6ifconf['range']['from']} {$dhcpv6ifconf['range']['to']};
$dnscfgv6

EOD;
        }

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

        // ntp-servers
        if (isset($dhcpv6ifconf['ntpserver'][0])) {
            $ntpservers = array();
            foreach($dhcpv6ifconf['ntpserver'] as $ntpserver) {
                if (is_ipaddrv6($ntpserver)) {
                    $ntpservers[] = $ntpserver;
                }
            }
            if (count($ntpservers) > 0 ) {
                $dhcpdv6conf .= "  option dhcp6.sntp-servers " . join(",", $dhcpv6ifconf['ntpserver']) . ";\n";
            }
        }

        // Handle option, number rowhelper values
        if (isset($dhcpv6ifconf['numberoptions']['item'])) {
            $dhcpdv6conf .= "\n";
            foreach($dhcpv6ifconf['numberoptions']['item'] as $itemv6idx => $itemv6) {
                $dhcpdv6conf .= "  option custom-{$dhcpv6if}-{$itemv6idx} \"{$itemv6['value']}\";\n";
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

                if (!empty($sm['domain']) && ($sm['domain'] != $dhcpv6ifconf['domain'])) {
                    $dhcpdv6conf .= "  option domain-name \"{$sm['domain']}\";\n";
                }

                $dhcpdv6conf .= "}\n";
                $i++;
            }
        }

        if (!empty($newzone['domain-name'])) {
            if (isset($dhcpv6ifconf['ddnsupdate'])) {
                $newzone['dns-servers'] = array($dhcpv6ifconf['ddnsdomainprimary']);
                $newzone['ddnsdomainkeyname'] = $dhcpv6ifconf['ddnsdomainkeyname'];
                $newzone['ddnsdomainkey'] = $dhcpv6ifconf['ddnsdomainkey'];
                $ddns_zones[] = $newzone;
            }
        }

        if (preg_match("/poes/si", $dhcpv6if)) {
            /* magic here */
            $dhcpdv6ifs = array_merge($dhcpdv6ifs, get_pppoes_child_interfaces($dhcpv6if));
        } else {
            $realif = get_real_interface($dhcpv6if, "inet6");
            if (stristr("$realif", "bridge")) {
                $mac = get_interface_mac($realif);
                $v6address = generate_ipv6_from_mac($mac);
                /* create link local address for bridges */
                mwexec("/sbin/ifconfig {$realif} inet6 {$v6address}");
            }
            $realif = escapeshellcmd($realif);
            $dhcpdv6ifs[] = $realif;
        }
    }

    if ($nsupdate) {
        $dhcpdv6conf .= "\nddns-update-style interim;\n";
        $dhcpdv6conf .= services_dhcpd_zones($ddns_zones);
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

function services_dhcrelay_configure($verbose = false)
{
    global $config;

    $dhcrelayifs = array();

    killbypid('/var/run/dhcrelay.pid', 'TERM', true);

    $dhcrelaycfg = &config_read_array('dhcrelay');

    if (!isset($dhcrelaycfg['enable'])) {
        return;
    }

    if ($verbose) {
        echo 'Starting DHCPv4 Relay...';
        flush();
    }

    $iflist = get_configured_interface_list();
    $dhcifaces = explode(",", $dhcrelaycfg['interface']);
    foreach ($dhcifaces as $dhcrelayif) {
        if (!isset($iflist[$dhcrelayif]) || link_interface_to_bridge($dhcrelayif)) {
            continue;
        }

        if (is_ipaddr(get_interface_ip($dhcrelayif))) {
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
        foreach ($iflist as $ifname) {
            $subnet = get_interface_ip($ifname);
            if (!is_ipaddr($subnet)) {
                continue;
            }
            $subnet .=  "/" . get_interface_subnet($ifname);
            if (ip_in_subnet($srvip, $subnet)) {
                $destif = get_real_interface($ifname);
                break;
            }
        }
        if (!isset($destif)) {
            foreach (get_staticroutes() as $rtent) {
                if (ip_in_subnet($srvip, $rtent['network'])) {
                    $a_gateways = return_gateways_array(true);
                    $destif = $a_gateways[$rtent['gateway']]['interface'];
                    break;
                }
            }
        }

        if (!isset($destif)) {
            /* Create a array from the existing route table */
            exec("/usr/bin/netstat -rnWf inet", $route_str);
            array_shift($route_str);
            array_shift($route_str);
            array_shift($route_str);
            array_shift($route_str);
            $route_arr = array();
            foreach($route_str as $routeline) {
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
            $cmd .=  " -a -m replace";
        }

        $cmd .= " " . implode(" ", $srvips);
        mwexec($cmd);
    }

    if ($verbose) {
        echo "done.\n";
    }
}

function services_dhcrelay6_configure($verbose = false)
{
    global $config;

    $dhcrelayifs = array();

    killbypid('/var/run/dhcrelay6.pid', 'TERM', true);

    $dhcrelaycfg = &config_read_array('dhcrelay6');

    if (!isset($dhcrelaycfg['enable'])) {
        return;
    }

    if ($verbose) {
        echo 'Starting DHCPv6 Relay...';
        flush();
    }

    $iflist = get_configured_interface_list();
    $dhcifaces = explode(",", $dhcrelaycfg['interface']);
    foreach ($dhcifaces as $dhcrelayif) {
        if (!isset($iflist[$dhcrelayif]) || link_interface_to_bridge($dhcrelayif))  {
            continue;
        }

        if (is_ipaddrv6(get_interface_ipv6($dhcrelayif))) {
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
        foreach ($iflist as $ifname) {
            $subnet = get_interface_ipv6($ifname);
            if (!is_ipaddrv6($subnet)) {
                continue;
            }
            $subnet .=  "/" . get_interface_subnetv6($ifname);
            if (ip_in_subnet($srvip, $subnet)) {
                $destif = get_real_interface($ifname);
                break;
            }
        }
        if (!isset($destif)) {
            if (isset($config['staticroutes']['route'])) {
                foreach ($config['staticroutes']['route'] as $rtent) {
                    if (ip_in_subnet($srvip, $rtent['network'])) {
                        $a_gateways = return_gateways_array(true);
                        $destif = $a_gateways[$rtent['gateway']]['interface'];
                        break;
                    }
                }
            }
        }

        if (!isset($destif)) {
            /* Create a array from the existing route table */
            exec("/usr/bin/netstat -rnWf inet6", $route_str);
            array_shift($route_str);
            array_shift($route_str);
            array_shift($route_str);
            array_shift($route_str);
            $route_arr = array();
            foreach($route_str as $routeline) {
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

function get_dyndns_ip($int, $ipver = 4)
{
    global $config;

    $ip_address = $ipver == 6 ? get_interface_ipv6($int) : get_interface_ip($int);
    if (empty($ip_address)) {
        log_error("Aborted IPv{$ipver} detection: no address for {$int}");
        return 'down';
    }

    $gateways_status = return_gateways_status(true);

    /*
     * If the gateway for this interface is down, then the external
     * check cannot work. Avoid the long wait for the external check
     * to timeout.
     */
    if (!empty($gateways_status[$config['interfaces'][$int]['gateway']]['status']) &&
        stristr($gateways_status[$config['interfaces'][$int]['gateway']]['status'], 'down')) {
        log_error("Aborted IPv{$ipver} detection: gateway down for {$int}");
        return 'down';
    }

    if ($ipver != 6 && is_private_ip($ip_address)) {
        /* Chinese alternative is http://ip.3322.net/ */
        $hosttocheck = 'http://checkip.dyndns.org';
        $ip_ch = curl_init($hosttocheck);
        curl_setopt($ip_ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ip_ch, CURLOPT_INTERFACE, $ip_address);
        curl_setopt($ip_ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ip_ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ip_ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
        $ip_result = curl_exec($ip_ch);
        if ($ip_result !== false) {
            preg_match('=<body>Current IP Address: (.*)</body>=siU', $ip_result, $matches);
            $ip_address = trim($matches[1]);
        } else {
            log_error('Aborted IPv4 detection: ' . curl_error($ip_ch));
            $ip_address = '';
        }
        curl_close($ip_ch);
    } elseif (ipver == 6 && is_linklocal($ip_address)) {
        log_error('Aborted IPv6 detection: cannot bind to link-local address');
        $ip_address = '';
    }

    if (($ipver == 6 && !is_ipaddrv6($ip_address)) || ($ipver != 6 && !is_ipaddrv4($ip_address))) {
        return 'down';
    }

    return $ip_address;
}

function is_apinger_enabled()
{
    global $config;

    if (isset($config['system']['prefer_dpinger'])) {
        return false;
    }

    $gwcount = 0;

    if (isset($config['gateways']['gateway_item'])) {
        foreach($config['gateways']['gateway_item'] as $gwkey => $gateway) {
            if (!isset($gateway['monitor_disable']) || $gateway['monitor_disable'] == '0') {
                $gwcount += 1;
            }
        }
    }

    if ($gwcount == 0) {
        return false;
    }

    return is_array(return_gateways_array());
}

function is_dpinger_enabled()
{
    global $config;

    if (!isset($config['system']['prefer_dpinger'])) {
        return false;
    }

    $gwcount = 0;

    if (isset($config['gateways']['gateway_item'])) {
        foreach($config['gateways']['gateway_item'] as $gwkey => $gateway) {
            if (!isset($gateway['monitor_disable']) || $gateway['monitor_disable'] == '0') {
                $gwcount += 1;
            }
        }
    }

    if ($gwcount == 0) {
        return false;
    }

    return is_array(return_gateways_array());
}

function services_get()
{
    global $config;

    $services = array();

    foreach (plugins_services() as $service) {
        $services[] = $service;
    }

    uasort($services, function ($a, $b) {
        return strcasecmp($a['name'], $b['name']);
    });

    return $services;
}

function find_service_by_name($name, $filter = array())
{
    $services = services_get();

    foreach ($services as $service) {
        if ($service['name'] != $name) {
            continue;
        }
        if (!count($filter)) {
            /* force match if filter wasn't set (standard behaviour) */
            $filter['name'] = $name;
        }
        foreach ($filter as $key => $value) {
            if (isset($service[$key]) && $service[$key] == $value) {
                return $service;
            }
        }
    }

    return array();
}

function get_service_status($service)
{
    if (!empty($service['nocheck'])) {
        return true;
    }

    if (isset($service['pidfile'])) {
        return isvalidpid($service['pidfile']);
    }

    return is_process_running($service['name']);
}


function get_service_status_icon($service, $xs = false)
{
    $output = '';

    if (get_service_status($service)) {
        $output .= '<span class="label label-opnsense label-opnsense-%s label-success"><i class="fa fa-play fa-fw"></i></span>' . PHP_EOL;
    } else {
        $output .= '<span class="label label-opnsense label-opnsense-%s label-danger"><i class="fa fa-stop fa-fw"></i></span>' . PHP_EOL;
    }

    return sprintf($output, $xs ? 'xs' : 'sm');
}

function get_service_control_links($service, $xs = false)
{
    $service_id = isset($service['id']) ? $service['id'] : '';

    $template  = '<span data-service_id="%s" data-service_action="%s" data-service="%s" ';
    $template .= 'class="btn btn-%s btn-default %s"><i class="%s"></i></span>' . PHP_EOL;

    $output = '';

    if (get_service_status($service)) {
        $output .= sprintf(
            $template,
            $service_id,
            'restart',
            $service['name'],
            $xs ? 'xs' : 'sm',
            'srv_status_act',
            'fa fa-refresh fa-fw'
        );
        if (empty($service['nocheck'])) {
            $output .= sprintf(
                $template,
                $service_id,
                'stop',
                $service['name'],
                $xs ? 'xs' : 'sm',
                'srv_status_act',
                'fa fa-stop fa-fw'
            );
        }
    } else {
        $output .= sprintf(
            $template,
            $service_id,
            'start',
            $service['name'],
            $xs ? 'xs' : 'sm',
            'srv_status_act',
            'fa fa-play fa-fw'
        );
    }

    return $output;
}

function is_radvd_enabled()
{
    global $config;

    $dhcpdv6cfg = config_read_array('dhcpdv6');
    $Iflist = get_configured_interface_list();

    /* handle manually configured DHCP6 server settings first */
    foreach ($dhcpdv6cfg as $dhcpv6if => $dhcpv6ifconf) {
        if (!isset($config['interfaces'][$dhcpv6if]['enable'])) {
            continue;
        } elseif ($dhcpv6ifconf['ramode'] == "disabled") {
            continue;
        }

        return true;
    }

    /* handle DHCP-PD prefixes and 6RD dynamic interfaces */
    foreach ($Iflist as $if => $ifdescr) {
        if (!isset($config['interfaces'][$if]['track6-interface'])) {
            continue;
        } elseif (empty($config['interfaces'][$config['interfaces'][$if]['track6-interface']])) {
            continue;
        } elseif (!isset($config['interfaces'][$if]['enable'])) {
            continue;
        }

        return true;
    }

    return false;
}

function service_control_start($name, $extras)
{
    if (!empty($extras['id'])) {
        $filter['id'] = $extras['id'];
    }

    $service = find_service_by_name($name, $filter);
    if (!isset($service['name'])) {
        return sprintf(gettext("Could not start unknown service `%s'"), htmlspecialchars($name));
    }

    if (isset($service['configd']['start'])) {
        foreach ($service['configd']['start'] as $cmd) {
            configd_run($cmd);
        }
    } elseif (isset($service['php']['start'])) {
        foreach ($service['php']['start'] as $cmd) {
            $params = array();
            if (isset($service['php']['args'])) {
                foreach ($service['php']['args'] as $param) {
                    $params[] = $service[$param];
                }
            }
            call_user_func_array($cmd, $params);
        }
    } elseif (isset($service['mwexec']['start'])) {
        foreach ($service['mwexec']['start'] as $cmd) {
            mwexec($cmd);
        }
    } else {
        return sprintf(gettext("Could not start service `%s'"), htmlspecialchars($name));
    }

    return sprintf(gettext('%s has been started.'), htmlspecialchars($name));
}

function service_control_stop($name, $extras)
{
    $filter = array();

    if (!empty($extras['id'])) {
        $filter['id'] = $extras['id'];
    }

    $service = find_service_by_name($name, $filter);
    if (!isset($service['name'])) {
        return sprintf(gettext("Could not stop unknown service `%s'"), htmlspecialchars($name));
    }

    if (isset($service['configd']['stop'])) {
        foreach ($service['configd']['stop'] as $cmd) {
            configd_run($cmd);
        }
    } elseif (isset($service['php']['stop'])) {
        foreach ($service['php']['stop'] as $cmd) {
            $cmd();
        }
    } elseif (isset($service['mwexec']['stop'])) {
        foreach ($service['mwexec']['stop'] as $cmd) {
            mwexec($cmd);
        }
    } elseif (isset($service['pidfile'])) {
        killbypid($service['pidfile'], 'TERM', true);
    } else {
        /* last resort, but not very elegant */
        killbyname($service['name']);
    }

    return sprintf(gettext("%s has been stopped."), htmlspecialchars($name));
}

function service_control_restart($name, $extras)
{
    if (!empty($extras['id'])) {
        $filter['id'] = $extras['id'];
    }

    $service = find_service_by_name($name, $filter);
    if (!isset($service['name'])) {
        return sprintf(gettext("Could not restart unknown service `%s'"), htmlspecialchars($name));
    }

    if (isset($service['configd']['restart'])) {
        foreach ($service['configd']['restart'] as $cmd) {
            configd_run($cmd);
        }
    } elseif (isset($service['php']['restart'])) {
        foreach ($service['php']['restart'] as $cmd) {
            $params = array();
            if (isset($service['php']['args'])) {
                foreach ($service['php']['args'] as $param) {
                    $params[] = $service[$param];
                }
            }
            call_user_func_array($cmd, $params);
        }
    } elseif (isset($service['mwexec']['restart'])) {
        foreach ($service['mwexec']['restart'] as $cmd) {
            mwexec($cmd);
        }
    } else {
        return sprintf(gettext("Could not restart service `%s'"), htmlspecialchars($name));
    }

    return sprintf(gettext("%s has been restarted."), htmlspecialchars($name));
}
