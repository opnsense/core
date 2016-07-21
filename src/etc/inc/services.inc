<?php

/*
    Copyright (C) 2014-2016 Franco Fichtner <franco@opnsense.org>
    Copyright (C) 2010 Ermal Luci
    Copyright (C) 2005-2006 Colin Smith <ethethlay@gmail.com>
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

require_once('dyndns.class');
require_once('unbound.inc');
require_once('miniupnpd.inc');
require_once('pfsense-utils.inc');

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

function get_configured_pppoe_server_interfaces()
{
    global $config;

    $iflist = array();
    if (isset($config['pppoes']['pppoe'])) {
        foreach($config['pppoes']['pppoe'] as $pppoe) {
            if ($pppoe['mode'] == 'server') {
                $int = 'poes'. $pppoe['pppoeid'];
                $iflist[$int] = strtoupper($int);
            }
        }
    }
    return $iflist;
}

/* implement ipv6 route advertising deamon */
function services_radvd_configure($blacklist = array())
{
    global $config;

    if (!isset($config['dhcpdv6']) || !is_array($config['dhcpdv6'])) {
        $config['dhcpdv6'] = array();
    }

    $Iflist = get_configured_interface_list();
    $Iflist = array_merge($Iflist, get_configured_pppoe_server_interfaces());
    $carplist = get_configured_carp_interface_list();

    $radvdconf = "# Automatically Generated, do not edit\n";

    /* Process all links which need the router advertise daemon */
    $radvdifs = array();

    /* handle manually configured DHCP6 server settings first */
    foreach ($config['dhcpdv6'] as $dhcpv6if => $dhcpv6ifconf) {
        if (!is_array($config['interfaces'][$dhcpv6if])) {
            continue;
        } elseif (!isset($config['interfaces'][$dhcpv6if]['enable'])) {
            continue;
        } elseif (isset($blacklist[$dhcpv6if])) {
            /* Do not put in the config an interface which is down */
            continue;
        }
        if (!isset($dhcpv6ifconf['ramode'])) {
            $dhcpv6ifconf['ramode'] = $dhcpv6ifconf['mode'];
        }

        /* are router advertisements enabled? */
        if ($dhcpv6ifconf['ramode'] == "disabled") {
            continue;
        }

        if (!isset($dhcpv6ifconf['rapriority'])) {
            $dhcpv6ifconf['rapriority'] = "medium";
        }

        /* always start with the real parent, we override with the carp if later */
        $carpif = false;
        /* check if we need to listen on a CARP interface */
        if (!empty($dhcpv6ifconf['rainterface'])) {
            if (!empty($carplist[$dhcpv6ifconf['rainterface']])) {
                $dhcpv6if = $dhcpv6ifconf['rainterface'];
                $carpif = true;
            }
        }

        $realif = get_real_interface($dhcpv6if, "inet6");
        if (isset($radvdifs[$realif])) {
            continue;
        }

        $ifcfgipv6 = get_interface_ipv6($dhcpv6if);
        if (!is_ipaddrv6($ifcfgipv6)) {
            continue;
        }

        $ifcfgsnv6 = get_interface_subnetv6($dhcpv6if);
        $subnetv6 = gen_subnetv6($ifcfgipv6, $ifcfgsnv6);
        $radvdifs[$realif] = $realif;

        $radvdconf .= "# Generated for DHCPv6 Server $dhcpv6if\n";
        $radvdconf .= "interface {$realif} {\n";
        $radvdconf .= sprintf("\tAdvSendAdvert %s;\n", !empty($dhcpv6ifconf['ranosend']) ? 'off' : 'on');
        $radvdconf .= sprintf("\tMinRtrAdvInterval %s;\n", !empty($dhcpv6ifconf['ramininterval']) ? $dhcpv6ifconf['ramininterval'] : '200');
        $radvdconf .= sprintf("\tMaxRtrAdvInterval %s;\n", !empty($dhcpv6ifconf['ramaxinterval']) ? $dhcpv6ifconf['ramaxinterval'] : '600');
        $mtu = legacy_interface_stats($realif)['mtu'];
        if (is_numeric($mtu)) {
            $radvdconf .= "\tAdvLinkMTU {$mtu};\n";
        } else {
            $radvdconf .= "\tAdvLinkMTU 1280;\n";
        }

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
        }
        $radvdconf .= "\tprefix {$subnetv6}/{$ifcfgsnv6} {\n";
        if ($carpif == true) {
            $radvdconf .= "\t\tDeprecatePrefix off;\n";
        } else {
            $radvdconf .= "\t\tDeprecatePrefix on;\n";
        }
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
                $radvdconf .= "\t\tAdvOnLink on;\n";
                $radvdconf .= "\t\tAdvAutonomous on;\n";
                $radvdconf .= "\t\tAdvRouterAddr on;\n";
                break;
            case "unmanaged":
                $radvdconf .= "\t\tAdvOnLink on;\n";
                $radvdconf .= "\t\tAdvAutonomous on;\n";
                $radvdconf .= "\t\tAdvRouterAddr on;\n";
                break;
        }
        $radvdconf .= "\t};\n";

        if ($carpif === true) {
            $radvdconf .= "\troute ::/0 {\n";
            $radvdconf .= "\t\tRemoveRoute off;\n";
            $radvdconf .= "\t};\n";
        } else {
            $radvdconf .= "\troute ::/0 {\n";
            $radvdconf .= "\t\tRemoveRoute on;\n";
            $radvdconf .= "\t};\n";
        }

        /* add DNS servers */
        $dnslist = array();
        if (isset($dhcpv6ifconf['rasamednsasdhcp6']) && is_array($dhcpv6ifconf['dnsserver']) && !empty($dhcpv6ifconf['dnsserver'])) {
            foreach($dhcpv6ifconf['dnsserver'] as $server) {
                if (is_ipaddrv6($server)) {
                    $dnslist[] = $server;
                }
            }
        } elseif (!isset($dhcpv6ifconf['rasamednsasdhcp6']) && isset($dhcpv6ifconf['radnsserver']) && is_array($dhcpv6ifconf['radnsserver'])) {
            foreach($dhcpv6ifconf['radnsserver'] as $server) {
                if (is_ipaddrv6($server)) {
                    $dnslist[] = $server;
                }
            }
        } elseif (isset($config['dnsmasq']['enable']) || isset($config['unbound']['enable'])) {
            $dnslist[] = get_interface_ipv6($realif);
        } elseif (!empty($config['system']['dnsserver'])) {
            foreach($config['system']['dnsserver'] as $server) {
                if (is_ipaddrv6($server)) {
                    $dnslist[] = $server;
                }
            }
        }
        if (count($dnslist) > 0) {
            $dnsstring = implode(" ", $dnslist);
            if ($dnsstring <> "") {
                $radvdconf .= "\tRDNSS {$dnsstring} { };\n";
            }
        }
        if (!empty($dhcpv6ifconf['domain'])) {
            $radvdconf .= "\tDNSSL {$dhcpv6ifconf['domain']} { };\n";
        } elseif (!empty($config['system']['domain'])) {
            $radvdconf .= "\tDNSSL {$config['system']['domain']} { };\n";
        }
        $radvdconf .= "};\n";
    }

    /* handle DHCP-PD prefixes and 6RD dynamic interfaces */
    foreach ($Iflist as $if => $ifdescr) {
        if (!isset($config['interfaces'][$if]['track6-interface'])) {
            continue;
        } elseif (!isset($config['interfaces'][$if]['enable'])) {
            continue;
        } elseif (isset($blacklist[$if])) {
            /* Do not put in the config an interface which is down */
            continue;
        }
        $trackif = $config['interfaces'][$if]['track6-interface'];
        if (empty($config['interfaces'][$trackif])) {
            continue;
        }

        $realif = get_real_interface($if, "inet6");
        /* prevent duplicate entries, manual overrides */
        if (isset($radvdifs[$realif])) {
            continue;
        }

        $ifcfgipv6 = get_interface_ipv6($if);
        if (!is_ipaddrv6($ifcfgipv6)) {
            $subnetv6 = "::";
            $ifcfgsnv6 = "64";
        } else {
            $ifcfgsnv6 = get_interface_subnetv6($if);
            $subnetv6 = gen_subnetv6($ifcfgipv6, $ifcfgsnv6);
        }
        $radvdifs[$realif] = $realif;

        if (isset($config['interfaces'][$trackif]['ipaddrv6'])) {
            $autotype = $config['interfaces'][$trackif]['ipaddrv6'];
            $radvdconf .= "# Generated config for {$autotype} delegation from {$trackif} on {$if}\n";
        }

        $radvdconf .= "interface {$realif} {\n";
        $radvdconf .= "\tAdvSendAdvert on;\n";
        $radvdconf .= "\tMinRtrAdvInterval 3;\n";
        $radvdconf .= "\tMaxRtrAdvInterval 10;\n";
        $mtu = legacy_interface_stats($realif)['mtu'];
        if (is_numeric($mtu)) {
            $radvdconf .= "\tAdvLinkMTU {$mtu};\n";
        } else {
            $radvdconf .= "\tAdvLinkMTU 1280;\n";
        }
        $radvdconf .= "\tAdvOtherConfigFlag on;\n";
        $radvdconf .= "\t\tprefix {$subnetv6}/{$ifcfgsnv6} {\n";
        $radvdconf .= "\t\tAdvOnLink on;\n";
        $radvdconf .= "\t\tAdvAutonomous on;\n";
        $radvdconf .= "\t\tAdvRouterAddr on;\n";
        $radvdconf .= "\t};\n";

        /* add DNS servers */
        $dnslist = array();
        if (isset($config['dnsmasq']['enable']) || isset($config['unbound']['enable'])) {
            $dnslist[] = $ifcfgipv6;
        } elseif (!empty($config['system']['dnsserver'])) {
            foreach($config['system']['dnsserver'] as $server) {
                if (is_ipaddrv6($server)) {
                    $dnslist[] = $server;
                }
            }
        }
        if (count($dnslist) > 0) {
            $dnsstring = implode(" ", $dnslist);
            if (!empty($dnsstring)) {
                $radvdconf .= "\tRDNSS {$dnsstring} { };\n";
            }
        }
        if (!empty($config['system']['domain'])) {
            $radvdconf .= "\tDNSSL {$config['system']['domain']} { };\n";
        }
        $radvdconf .= "};\n";
    }

    /* write radvd.conf */
    if (!@file_put_contents("/var/etc/radvd.conf", $radvdconf)) {
        log_error("Error: cannot open radvd.conf in services_radvd_configure().\n");
        if (file_exists("/var/run/booting")) {
            printf("Error: cannot open radvd.conf in services_radvd_configure().\n");
        }
    }
    unset($radvdconf);

    if (count($radvdifs) > 0) {
        if (isvalidpid('/var/run/radvd.pid')) {
            killbypid('/var/run/radvd.pid', 'HUP');
        } else {
            mwexec('/usr/local/sbin/radvd -p /var/run/radvd.pid -C /var/etc/radvd.conf -m syslog');
        }
    } else {
        /* we need to shut down the radvd cleanly, it will send out the prefix
         * information with a lifetime of 0 to notify clients of a (possible) new prefix */
        if (isvalidpid('/var/run/radvd.pid')) {
            log_error("Shutting down Router Advertisment daemon cleanly");
            killbypid('/var/run/radvd.pid');
        }
    }
    return 0;
}

function services_dhcpd_leasesfile()
{
    global $g;

    return "{$g['dhcpd_chroot_path']}/var/db/dhcpd.leases";
}

function services_dhcpdv6_leasesfile()
{
    global $g;

    return "{$g['dhcpd_chroot_path']}/var/db/dhcpd6.leases";
}

function services_dhcpd_configure($family = 'all', $blacklist = array())
{
    global $g;

    $dirs = array('/dev', '/etc', '/lib', '/run', '/usr', '/usr/local/sbin', '/var/db', '/var/run');

    foreach ($dirs as $dir) {
        mwexecf('/bin/mkdir -p %s', "{$g['dhcpd_chroot_path']}{$dir}");
    }

    if (mwexecf('/sbin/mount -uw %s', "{$g['dhcpd_chroot_path']}/dev", true)) {
        mwexecf('/sbin/mount -t devfs devfs %s', "{$g['dhcpd_chroot_path']}/dev");
    }

    mwexecf('/usr/sbin/chown -R dhcpd:dhcpd %s', $g['dhcpd_chroot_path']);

    if ($family == 'all' || $family == 'inet') {
        services_dhcpdv4_configure();
    }

    if ($family == 'all' || $family == 'inet6') {
        services_dhcpdv6_configure($blacklist);
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

function services_dhcpdv4_configure()
{
    global $config, $g;

    $need_ddns_updates = false;
    $ddns_zones = array();

    /* kill any running dhcpd */
    killbypid("{$g['dhcpd_chroot_path']}/var/run/dhcpd.pid");

    /* DHCP enabled on any interfaces? */
    if (!is_dhcpv4_server_enabled()) {
        return 0;
    }

    /* Only consider DNS servers with IPv4 addresses for the IPv4 DHCP server. */
    $dns_arrv4 = array();
    if (!empty($config['system']['dnsserver'])) {
        foreach($config['system']['dnsserver'] as $dnsserver) {
            if (is_ipaddrv4($dnsserver)) {
                $dns_arrv4[] = $dnsserver;
            }
        }
    }

    if (file_exists("/var/run/booting")) {
        echo gettext("Starting DHCP service...");
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
option domain-search-list code 119 = text;
option arch code 93 = unsigned integer 16; # RFC4578
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

        $dhcpdconf .= "subnet {$subnet} netmask {$subnetmask} {\n";

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

        // Handle option, number rowhelper values
        $dhcpdconf .= "\n";
        if (isset($dhcpifconf['numberoptions']['item'])) {
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
                $dhcpdconf .= "host s_{$dhcpif}_{$i} {\n";
                if (!empty($sm['mac'])) {
                    $dhcpdconf .= "        hardware ethernet {$sm['mac']};\n";
                }

                if (!empty($sm['cid'])) {
                    $dhcpdconf .= "        option dhcp-client-identifier \"{$sm['cid']}\";\n";
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
                $ddns_zones[] = $newzone;
            }
        }
    }

    if ($need_ddns_updates) {
        $dhcpdconf .= "ddns-update-style interim;\n";
        $dhcpdconf .= "update-static-leases on;\n";
        $dhcpdconf .= services_dhcpd_zones($ddns_zones);
    }

    /* write dhcpd.conf */
    if (!@file_put_contents("{$g['dhcpd_chroot_path']}/etc/dhcpd.conf", $dhcpdconf)) {
        printf(gettext("Error: cannot open dhcpd.conf in services_dhcpdv4_configure().%s"), "\n");
        unset($dhcpdconf);
        return 1;
    }
    unset($dhcpdconf);

    /* create an empty leases database */
    @touch("{$g['dhcpd_chroot_path']}/var/db/dhcpd.leases");

    /* make sure there isn't a stale dhcpd.pid file, which can make dhcpd fail to start.   */
    /* if we get here, dhcpd has been killed and is not started yet                        */
    @unlink("{$g['dhcpd_chroot_path']}/var/run/dhcpd.pid");

    /* fire up dhcpd in a chroot */
    if (count($dhcpdifs) > 0) {
        mwexec("/usr/local/sbin/dhcpd -user dhcpd -group dhcpd -chroot {$g['dhcpd_chroot_path']} -cf /etc/dhcpd.conf -pf /var/run/dhcpd.pid " . join(" ", $dhcpdifs));
    }

    if (file_exists("/var/run/booting")) {
        print "done.\n";
    }

    return 0;
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
                            $dhcpdconf .= "  algorithm hmac-md5;\n";
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

function services_dhcpdv6_configure($blacklist = array())
{
    global $config, $g;

    /* kill any running dhcpd */
    killbypid("{$g['dhcpd_chroot_path']}/var/run/dhcpdv6.pid");
    killbypid('/var/run/dhcpleases6.pid');

    /* DHCP enabled on any interfaces? */
    if (!is_dhcpv6_server_enabled()) {
        return 0;
    }

    $syscfg = $config['system'];
    if (!isset($config['dhcpdv6']) || !is_array($config['dhcpdv6'])) {
        $config['dhcpdv6'] = array();
    }
    $dhcpdv6cfg = $config['dhcpdv6'];
    $Iflist = get_configured_interface_list();
    $Iflist = array_merge($Iflist, get_configured_pppoe_server_interfaces());

    if (file_exists("/var/run/booting")) {
        echo "Starting DHCPv6 service...";
    } else {
        sleep(1);
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
            $ifcfgipv6arr =explode(":", $ifcfgipv6);
            $dhcpdv6cfg[$ifname] = array();
            $dhcpdv6cfg[$ifname]['enable'] = true;
            /* range */
            $ifcfgipv6arr[7] = "1000";
            $dhcpdv6cfg[$ifname]['range'] = array();
            $dhcpdv6cfg[$ifname]['range']['from'] = Net_IPv6::compress(implode(":", $ifcfgipv6arr));
            $ifcfgipv6arr[7] = "2000";
            $dhcpdv6cfg[$ifname]['range']['to'] = Net_IPv6::compress(implode(":", $ifcfgipv6arr));
            /* prefix length > 0? We can add dhcp6 prefix delegation server */
            if ($pdlen > 2) {
                $pdlenmax = $pdlen;
                $pdlenhalf = $pdlenmax -1;
                $pdlenmin = (64 - ceil($pdlenhalf / 4));
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
                $dhcpdv6cfg[$ifname]['dns6ip'] = get_interface_ipv6($ifname);
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

option domain-name "{$syscfg['domain']}";
option ldap-server code 95 = text;
option domain-search-list code 119 = text;
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
        } elseif (isset($syscfg['dnsserver']) && ($syscfg['dnsserver'][0])) {
            $dns_arrv6 = array();
            foreach($syscfg['dnsserver'] as $dnsserver) {
                if (is_ipaddrv6($dnsserver)) {
                    $dns_arrv6[] = $dnsserver;
                }
            }
            if (!empty($dns_arrv6)) {
                $dnscfgv6 .= "  option dhcp6.name-servers " . join(",", $dns_arrv6) . ";";
            }
        }

        if (is_ipaddrv6($ifcfgipv6)) {
            $dhcpdv6conf .= "subnet6 {$subnetv6}/{$ifcfgsnv6}";
        } else {
            $subnet6 = gen_subnetv6($dhcpv6ifconf['range']['from'], "64");
            $dhcpdv6conf .= "subnet6 {$subnet6}/64";
        }
        $dhcpdv6conf .= " {\n";

        $dhcpdv6conf .= <<<EOD
  range6 {$dhcpv6ifconf['range']['from']} {$dhcpv6ifconf['range']['to']};
$dnscfgv6

EOD;

        if (!empty($dhcpv6ifconf['prefixrange']['from']) && is_ipaddrv6($dhcpv6ifconf['prefixrange']['from']) && is_ipaddrv6($dhcpv6ifconf['prefixrange']['to'])) {
            $dhcpdv6conf .= "  prefix6 {$dhcpv6ifconf['prefixrange']['from']} {$dhcpv6ifconf['prefixrange']['to']}/{$dhcpv6ifconf['prefixrange']['prefixlength']};\n";
        }
        if (isset($dhcpv6ifconf['dns6ip']) && is_ipaddrv6($dhcpv6ifconf['dns6ip'])) {
            $dhcpdv6conf .= "       option dhcp6.name-servers {$dhcpv6ifconf['dns6ip']};\n";
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
                $dhcpdv6conf .= "       option dhcp6.sntp-servers " . join(",", $dhcpv6ifconf['ntpserver']) . ";\n";
            }
        }

        // Handle option, number rowhelper values
        $dhcpdv6conf .= "\n";
        if (isset($dhcpv6ifconf['numberoptions']['item'])) {
            foreach($dhcpv6ifconf['numberoptions']['item'] as $itemv6idx => $itemv6) {
                $dhcpdv6conf .= "  option custom-{$dhcpv6if}-{$itemv6idx} \"{$itemv6['value']}\";\n";
            }
        }

        // ldap-server
        if (!empty($dhcpv6ifconf['ldap'])) {
            $dhcpdv6conf .= "  option ldap-server \"{$dhcpv6ifconf['ldap']}\";\n";
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

        if (isset($config['dhcpdv6'][$dhcpv6if]['ramode']) && $config['dhcpdv6'][$dhcpv6if]['ramode'] <> "unmanaged") {
            if (preg_match("/poes/si", $dhcpv6if)) {
                /* magic here */
                $dhcpdv6ifs = array_merge($dhcpdv6ifs, get_pppoes_child_interfaces($dhcpv6if));
            } else {
                $realif = get_real_interface($dhcpv6if, "inet6");
                if (stristr("$realif", "bridge")) {
                    $mac = get_interface_mac($realif);
                    $v6address = generate_ipv6_from_mac($mac);
                    /* Create link local address for bridges */
                    mwexec("/sbin/ifconfig {$realif} inet6 {$v6address}");
                }
                $realif = escapeshellcmd($realif);
                $dhcpdv6ifs[] = $realif;
            }
        }
    }

    if ($nsupdate) {
        $dhcpdv6conf .= "ddns-update-style interim;\n";
        $dhcpdv6conf .= services_dhcpd_zones($ddns_zones);
    } else {
        $dhcpdv6conf .= "ddns-update-style none;\n";
    }

    /* write dhcpdv6.conf */
    if (!@file_put_contents("{$g['dhcpd_chroot_path']}/etc/dhcpdv6.conf", $dhcpdv6conf)) {
        log_error("Error: cannot open {$g['dhcpd_chroot_path']}/etc/dhcpdv6.conf in services_dhcpdv6_configure().\n");
        if (file_exists("/var/run/booting")) {
            printf("Error: cannot open {$g['dhcpd_chroot_path']}/etc/dhcpdv6.conf in services_dhcpdv6_configure().\n");
        }
        unset($dhcpdv6conf);
        return 1;
    }
    unset($dhcpdv6conf);

    /* create an empty leases v6 database */
    if (!file_exists("{$g['dhcpd_chroot_path']}/var/db/dhcpd6.leases")) {
        @touch("{$g['dhcpd_chroot_path']}/var/db/dhcpd6.leases");
    }

    /* make sure there isn't a stale dhcpdv6.pid file, which may make dhcpdv6 fail to start.  */
    /* if we get here, dhcpdv6 has been killed and is not started yet                         */
    @unlink("{$g['dhcpd_chroot_path']}/var/run/dhcpdv6.pid");

    /* fire up dhcpd in a chroot */
    if (count($dhcpdv6ifs) > 0) {
        mwexec("/usr/local/sbin/dhcpd -6 -user dhcpd -group dhcpd -chroot {$g['dhcpd_chroot_path']} -cf /etc/dhcpdv6.conf -pf /var/run/dhcpdv6.pid " .
          join(" ", $dhcpdv6ifs));
        mwexec("/usr/local/sbin/dhcpleases6 -c \"/usr/local/bin/php -f /usr/local/sbin/prefixes.php|/bin/sh\" -l {$g['dhcpd_chroot_path']}/var/db/dhcpd6.leases");
    }
    if (file_exists("/var/run/booting")) {
        print gettext("done.") . "\n";
    }

    return 0;
}

function services_igmpproxy_configure()
{
    global $config;

    /* kill any running igmpproxy */
    killbyname("igmpproxy");

    if (!isset($config['igmpproxy']['igmpentry']) || !is_array($config['igmpproxy']['igmpentry']) || (count($config['igmpproxy']['igmpentry']) == 0)) {
        return 1;
    }

    $iflist = get_configured_interface_list();

    $igmpconf = <<<EOD

##------------------------------------------------------
## Enable Quickleave mode (Sends Leave instantly)
##------------------------------------------------------
quickleave

EOD;

    foreach ($config['igmpproxy']['igmpentry'] as $igmpcf) {
        unset($iflist[$igmpcf['ifname']]);
        $realif = get_real_interface($igmpcf['ifname']);
        if (empty($igmpcf['threshold'])) {
            $threshld = 1;
        } else {
            $threshld = $igmpcf['threshold'];
        }
        $igmpconf .= "phyint {$realif} {$igmpcf['type']} ratelimit 0 threshold {$threshld}\n";

        if ($igmpcf['address'] <> "") {
            $item = explode(" ", $igmpcf['address']);
            foreach($item as $iww) {
                $igmpconf .= "altnet {$iww}\n";
            }
        }
        $igmpconf .= "\n";
    }
    foreach ($iflist as $ifn) {
        $realif = get_real_interface($ifn);
        $igmpconf .= "phyint {$realif} disabled\n";
    }
    $igmpconf .= "\n";

    $igmpfl = fopen('/usr/local/etc/igmpproxy.conf', 'w');
    if (!$igmpfl) {
        log_error('Could not write Igmpproxy configuration file!');
        return;
    }
    fwrite($igmpfl, $igmpconf);
    fclose($igmpfl);
    unset($igmpconf);

    mwexec('/usr/local/etc/rc.d/igmpproxy onestart');
    log_error('Started IGMP proxy service.');

    return 0;
}

function services_dhcrelay_configure()
{
    global $config;
    $dhcrelayifs = array();

    /* kill any running dhcrelay */
    killbypid('/var/run/dhcrelay.pid');

    $dhcrelaycfg =& $config['dhcrelay'];

    /* DHCPRelay enabled on any interfaces? */
    if (!isset($dhcrelaycfg['enable']))
      return 0;

    if (file_exists("/var/run/booting")) {
        echo gettext("Starting DHCP relay service...");
    } else {
        sleep(1);
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
            } else
            $destif = get_real_interface("wan");
        }

        if (!empty($destif)) {
            $dhcrelayifs[] = $destif;
        }
    }
    $dhcrelayifs = array_unique($dhcrelayifs);

    /* fire up dhcrelay */
    if (empty($dhcrelayifs)) {
        log_error("No suitable interface found for running dhcrelay!");
        return; /* XXX */
    }

    $cmd = "/usr/local/sbin/dhcrelay -i " .  implode(" -i ", $dhcrelayifs);

    if (isset($dhcrelaycfg['agentoption'])) {
        $cmd .=  " -a -m replace";
    }

    $cmd .= " " . implode(" ", $srvips);
    mwexec($cmd);
    unset($cmd);

    return 0;
}

function services_dhcrelay6_configure()
{
    global $config;
    $dhcrelayifs = array();

    /* kill any running dhcrelay */
    killbypid('/var/run/dhcrelay6.pid');

    $dhcrelaycfg =& $config['dhcrelay6'];

    /* DHCPv6 Relay enabled on any interfaces? */
    if (!isset($dhcrelaycfg['enable'])) {
        return 0;
    }

    if (file_exists("/var/run/booting")) {
        echo gettext("Starting DHCPv6 relay service...");
    } else {
        sleep(1);
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

    /* fire up dhcrelay */
    if (empty($dhcrelayifs) || empty($srvifaces) ) {
        log_error("No suitable interface found for running dhcrelay -6!");
        return; /* XXX */
    }

    $cmd = '/usr/local/sbin/dhcrelay -6 -pf /var/run/dhcrelay6.pid';
    foreach ($dhcrelayifs as $dhcrelayif) {
        $cmd .= " -l {$dhcrelayif}";
    }
    foreach ($srvifaces as $srviface) {
        $cmd .= " -u \"{$srviface}\"";
    }
    mwexec($cmd);
    unset($cmd);

    return 0;
}

function services_dyndns_list()
{
    return array(
      'citynetwork' => 'City Network',
      'cloudflare' => 'CloudFlare',
      'custom' => 'Custom',
      'custom-v6' => 'Custom (v6)',
      'dhs' => 'DHS',
      'dnsexit' => 'DNSexit',
      'dnsomatic' => 'DNS-O-Matic',
      'duckdns' => 'Duck DNS',
      'dyndns' => 'DynDNS (dynamic)',
      'dyndns-static' => 'DynDNS (static)',
      'dyndns-custom' => 'DynDNS (custom)',
      'dyns' => 'DyNS',
      'easydns' => 'easyDNS',
      'eurodns' => 'EuroDNS',
      'freedns' => 'freeDNS',
      'gratisdns' => 'GratisDNS',
      'he-net' => 'HE.net',
      'he-net-v6' => 'HE.net (v6)',
      'he-net-tunnelbroker' => 'HE.net Tunnelbroker',
      'loopia' => 'Loopia',
      'namecheap' => 'Namecheap',
      'noip' => 'No-IP',
      'noip-free' => 'No-IP (free)',
      'ods' => 'ODS.org',
      'opendns' => 'OpenDNS',
      'ovh-dynhost' => 'OVH DynHOST',
      'route53' => 'Route 53',
      'selfhost' => 'SelfHost',
      'zoneedit' => 'ZoneEdit',
    );
}

function services_dyndns_configure_client($conf) {

    if (!isset($conf['enable'])) {
        return;
    }

    $dns = new updatedns($dnsService = $conf['type'],
        $dnsHost = $conf['host'],
        $dnsUser = $conf['username'],
        $dnsPass = $conf['password'],
        $dnsWilcard = $conf['wildcard'],
        $dnsMX = $conf['mx'],
        $dnsIf = "{$conf['interface']}",
        $dnsBackMX = NULL,
        $dnsServer = NULL,
        $dnsPort = NULL,
        $dnsUpdateURL = "{$conf['updateurl']}",
        $forceUpdate = $conf['force'],
        $dnsZoneID=$conf['zoneid'],
        $dnsTTL=$conf['ttl'],
        $dnsResultMatch = "{$conf['resultmatch']}",
        $dnsRequestIf = "{$conf['requestif']}",
        $dnsID = "{$conf['id']}",
        $dnsVerboseLog = $conf['verboselog'],
        $curlIpresolveV4 = $conf['curl_ipresolve_v4'],
        $curlSslVerifypeer = $conf['curl_ssl_verifypeer']
    );
}

function services_dyndns_configure($int = '')
{
    global $config;

    if (isset($config['dyndnses']['dyndns'])) {
        $dyndnscfg = $config['dyndnses']['dyndns'];
        $gwgroups = return_gateway_groups_array();
        if (is_array($dyndnscfg)) {
            if (file_exists("/var/run/booting")) {
                echo gettext("Starting dynamic DNS clients...");
            }

            foreach ($dyndnscfg as $dyndns) {
                if ((empty($int)) || ($int == $dyndns['interface']) || (is_array($gwgroups[$dyndns['interface']]))) {
                    $dyndns['verboselog'] = isset($dyndns['verboselog']);
                    $dyndns['curl_ipresolve_v4'] = isset($dyndns['curl_ipresolve_v4']);
                    $dyndns['curl_ssl_verifypeer'] = isset($dyndns['curl_ssl_verifypeer']);
                    services_dyndns_configure_client($dyndns);
                    sleep(1);
                }
            }

            if (file_exists("/var/run/booting")) {
                echo gettext("done.") . "\n";
            }
        }
    }

    return 0;
}

function dyndnsCheckIP($int)
{
    global $config;

    $ip_address = get_interface_ip($int);

    if (is_private_ip($ip_address)) {
        $gateways_status = return_gateways_status(true);
        // If the gateway for this interface is down, then the external check cannot work.
        // Avoid the long wait for the external check to timeout.
        if (stristr($gateways_status[$config['interfaces'][$int]['gateway']]['status'],"down")) {
            return "down";
        }
        $hosttocheck = "http://checkip.dyndns.org";
        $ip_ch = curl_init($hosttocheck);
        curl_setopt($ip_ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ip_ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ip_ch, CURLOPT_INTERFACE, $ip_address);
        curl_setopt($ip_ch, CURLOPT_CONNECTTIMEOUT, '30');
        curl_setopt($ip_ch, CURLOPT_TIMEOUT, 120);
        curl_setopt($ip_ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
        $ip_result_page = curl_exec($ip_ch);
        curl_close($ip_ch);
        $ip_result_decoded = urldecode($ip_result_page);
        preg_match('=Current IP Address: (.*)</body>=siU', $ip_result_decoded, $matches);
        $ip_address = trim($matches[1]);
    }
    return $ip_address;
}

function services_dnsmasq_configure($dhcp_reload = true)
{
    global $config;

    // hard coded args: will be removed to avoid duplication if specified in custom_options
    $standard_args = array(
        "dns-forward-max" => "--dns-forward-max=5000",
        "cache-size" => "--cache-size=10000",
        "local-ttl" => "--local-ttl=1"
    );

    killbypid('/var/run/dnsmasq.pid', 'TERM', true);

    if (isset($config['dnsmasq']['enable'])) {
        if (file_exists("/var/run/booting")) {
            echo gettext("Starting DNS forwarder...");
        }

        $args = "";

        if (isset($config['dnsmasq']['regdhcp'])) {
            $args .= " --dhcp-hostsfile=/etc/hosts ";
        }

        /* Setup listen port, if non-default */
        if (isset($config['dnsmasq']['port']) && is_port($config['dnsmasq']['port'])) {
            $args .= " --port={$config['dnsmasq']['port']} ";
        }

        $listen_addresses = "";
        if (isset($config['dnsmasq']['interface'])) {
            $interfaces = explode(",", $config['dnsmasq']['interface']);
            foreach ($interfaces as $interface) {
                if (is_ipaddrv4($interface)) {
                    $listen_addresses .= " --listen-address={$interface} ";
                } elseif (is_ipaddrv6($interface)) {
                    /*
                     * XXX: Since dnsmasq does not support link-local address
                     * with scope specified. These checks are being done.
                     */
                    if (is_linklocal($interface) && strstr($interface, "%")) {
                        $tmpaddrll6 = explode("%", $interface);
                        $listen_addresses .= " --listen-address={$tmpaddrll6[0]} ";
                    } else {
                        $listen_addresses .= " --listen-address={$interface} ";
                    }
                } else {
                    $if = get_real_interface($interface);
                    if (does_interface_exist($if)) {
                        $laddr = find_interface_ip($if);
                        if (is_ipaddrv4($laddr)) {
                            $listen_addresses .= " --listen-address={$laddr} ";
                        }
                        $laddr6 = find_interface_ipv6($if);
                        if (is_ipaddrv6($laddr6) && !isset($config['dnsmasq']['strictbind'])) {
                            /*
                             * XXX: Since dnsmasq does not support link-local address
                             * with scope specified. These checks are being done.
                             */
                            if (is_linklocal($laddr6) && strstr($laddr6, "%")) {
                                $tmpaddrll6 = explode("%", $laddr6);
                                $listen_addresses .= " --listen-address={$tmpaddrll6[0]} ";
                            } else {
                                $listen_addresses .= " --listen-address={$laddr6} ";
                            }
                        }
                    }
                }
            }
            if (!empty($listen_addresses)) {
                $args .= " {$listen_addresses} ";
                if (isset($config['dnsmasq']['strictbind'])) {
                    $args .= " --bind-interfaces ";
                }
            }
        }

        /* If selected, then first forward reverse lookups for private IPv4 addresses to nowhere. */
        /* If any of these are duplicated by a user-specified domain override (e.g. 10.in-addr.arpa) then */
        /* the user-specified entry made later on the command line below will be the one that is effective. */
        if (isset($config['dnsmasq']['no_private_reverse'])) {
            /* Note: Carrier Grade NAT (CGN) addresses 100.64.0.0/10 are intentionally not here. */
            /* End-users should not be aware of CGN addresses, so reverse lookups for these should not happen. */
            /* Just the OPNsense WAN might get a CGN address from an ISP. */
            $args .= " --server=/10.in-addr.arpa/ ";
            $args .= " --server=/168.192.in-addr.arpa/ ";
            /* Unfortunately the 172.16.0.0/12 range does not map nicely to the in-addr.arpa scheme. */
            for ($subnet_num = 16; $subnet_num < 32; $subnet_num++) {
                $args .= " --server=/" . $subnet_num . ".172.in-addr.arpa/ ";
            }
        }

        /* Setup forwarded domains */
        if (isset($config['dnsmasq']['domainoverrides']) && is_array($config['dnsmasq']['domainoverrides'])) {
          foreach($config['dnsmasq']['domainoverrides'] as $override) {
            if ($override['ip'] == "!") {
                $override['ip'] = "";
            }
            $args .= ' --server='. escapeshellarg('/' . $override['domain'] . '/' . $override['ip']);
          }
        }

        /* Allow DNS Rebind for forwarded domains */
        if (isset($config['dnsmasq']['domainoverrides']) && is_array($config['dnsmasq']['domainoverrides'])) {
            if (!isset($config['system']['webgui']['nodnsrebindcheck'])) {
                foreach($config['dnsmasq']['domainoverrides'] as $override) {
                    $args .= ' --rebind-domain-ok=' . escapeshellarg('/'.$override['domain'].'/') . ' ' ;
                }
            }
        }

        if (!isset($config['system']['webgui']['nodnsrebindcheck'])) {
            $dns_rebind = "--rebind-localhost-ok --stop-dns-rebind";
        }

        if (isset($config['dnsmasq']['strict_order'])) {
            $args .= " --strict-order ";
        }

        if (isset($config['dnsmasq']['domain_needed'])) {
            $args .= " --domain-needed ";
        }

        if (isset($config['dnsmasq']['custom_options']) && !empty($config['dnsmasq']['custom_options'])) {
            foreach (preg_split('/\s+/', $config['dnsmasq']['custom_options']) as $c) {
                $args .= " " . escapeshellarg("--{$c}");
                $p = explode('=', $c);
                if (array_key_exists($p[0], $standard_args)) {
                    unset($standard_args[$p[0]]);
                }
            }
        }
        $args .= ' ' . implode(' ', array_values($standard_args));

        /* run dnsmasq */
        $cmd = "/usr/local/sbin/dnsmasq --all-servers {$dns_rebind} {$args}";
        mwexec_bg($cmd);
        system_dhcpleases_configure();
        unset($args);

        if (file_exists("/var/run/booting")) {
            echo gettext("done.") . "\n";
        }
    }

    /*
     * XXX this is overly convoluted, potentiall
     * restarting all of dhcp up to three times ;)
     */
    if (!file_exists("/var/run/booting") && $dhcp_reload) {
        services_dhcpd_configure();
    }
}

function services_unbound_configure($dhcp_reload = true)
{
    global $config;

    killbypid('/var/run/unbound.pid', 'TERM', true);

    if (isset($config['unbound']['enable'])) {
        if (file_exists("/var/run/booting")) {
            echo gettext("Starting DNS Resolver...");
        }

        sync_unbound_service();
        system_dhcpleases_configure();

        if (file_exists("/var/run/booting")) {
            echo gettext("done.") . "\n";
        }
    }

    /*
     * XXX this is overly convoluted, potentially
     * restarting all of dhcp up to three times ;)
     */
    if (!file_exists("/var/run/booting") && $dhcp_reload) {
        services_dhcpd_configure();
    }
}

function services_snmpd_configure()
{
    global $config, $g;

    /* kill any running snmpd */
    killbypid('/var/run/snmpd.pid');
    sleep(2);

    if (is_process_running('bsnmpd')) {
        mwexec('/usr/bin/killall bsnmpd', true);
    }

    if (isset($config['snmpd']['enable'])) {
        if (file_exists("/var/run/booting")) {
            echo gettext("Starting SNMP daemon... ");
        }

        /* generate snmpd.conf */
        $fd = fopen("/var/etc/snmpd.conf", "w");
        if (!$fd) {
            printf(gettext("Error: cannot open snmpd.conf in services_snmpd_configure().%s"),"\n");
            return 1;
        }


        $snmpdconf = <<<EOD
location := "{$config['snmpd']['syslocation']}"
contact := "{$config['snmpd']['syscontact']}"
read := "{$config['snmpd']['rocommunity']}"

EOD;


        if (isset($config['snmpd']['trapenable']) && preg_match('/^\S+$/', $config['snmpd']['trapserver'])){
            $snmpdconf .= <<<EOD
# SNMP Trap support.
traphost := {$config['snmpd']['trapserver']}
trapport := {$config['snmpd']['trapserverport']}
trap := "{$config['snmpd']['trapstring']}"


EOD;
        }

        $version = trim(file_get_contents('/usr/local/opnsense/version/opnsense'));
        $platform = $g['product_name'];
        $sysDescr = "{$g['product_name']} " . php_uname("n") .
                    " {$version} {$platform} " . php_uname("s") .
                    " " . php_uname("r") . " " . trim(shell_exec('uname -p'));

        $snmpdconf .= <<<EOD
system := 1
%snmpd
sysDescr      = "{$sysDescr}"
begemotSnmpdDebugDumpPdus       = 2
begemotSnmpdDebugSyslogPri      = 7
begemotSnmpdCommunityString.0.1 = $(read)

EOD;

        if (isset($config['snmpd']['trapenable']) && preg_match('/^\S+$/', $config['snmpd']['trapserver'])){
            $snmpdconf .= <<<EOD
begemotTrapSinkStatus.[$(traphost)].$(trapport) = 4
begemotTrapSinkVersion.[$(traphost)].$(trapport) = 2
begemotTrapSinkComm.[$(traphost)].$(trapport) = $(trap)

EOD;
        }


        $snmpdconf .= <<<EOD
begemotSnmpdCommunityDisable    = 1

EOD;

        $bind_to_ip = "0.0.0.0";
        if (isset($config['snmpd']['bindip'])) {
            if (is_ipaddr($config['snmpd']['bindip'])) {
                $bind_to_ip = $config['snmpd']['bindip'];
            } else {
                $if = get_real_interface($config['snmpd']['bindip']);
                if (does_interface_exist($if)) {
                    $bind_to_ip = find_interface_ip($if);
                }
            }
        }

        if (is_port( $config['snmpd']['pollport'] )) {
            $snmpdconf .= <<<EOD
begemotSnmpdPortStatus.{$bind_to_ip}.{$config['snmpd']['pollport']} = 1

EOD;

        }

        $snmpdconf .= <<<EOD
begemotSnmpdLocalPortStatus."/var/run/snmpd.sock" = 1
begemotSnmpdLocalPortType."/var/run/snmpd.sock" = 4

# These are bsnmp macros not php vars.
sysContact      = $(contact)
sysLocation     = $(location)
sysObjectId     = 1.3.6.1.4.1.12325.1.1.2.1.$(system)

snmpEnableAuthenTraps = 2

EOD;

        if (is_array( $config['snmpd']['modules'] )) {
            if (isset($config['snmpd']['modules']['mibii'])) {
                $snmpdconf .= <<<EOD
begemotSnmpdModulePath."mibII"  = "/usr/lib/snmp_mibII.so"

EOD;
            }

            if (isset($config['snmpd']['modules']['netgraph'])) {
                $snmpdconf .= <<<EOD
begemotSnmpdModulePath."netgraph" = "/usr/lib/snmp_netgraph.so"
%netgraph
begemotNgControlNodeName = "snmpd"

EOD;
            }

            if (isset($config['snmpd']['modules']['pf'])) {
                $snmpdconf .= <<<EOD
begemotSnmpdModulePath."pf"     = "/usr/lib/snmp_pf.so"

EOD;
            }

            if (isset($config['snmpd']['modules']['hostres'])) {
                $snmpdconf .= <<<EOD
begemotSnmpdModulePath."hostres"     = "/usr/lib/snmp_hostres.so"

EOD;
            }
            if (isset($config['snmpd']['modules']['bridge'])) {
                $snmpdconf .= <<<EOD
begemotSnmpdModulePath."bridge"     = "/usr/lib/snmp_bridge.so"
# config must end with blank line

EOD;
            }
            if (isset($config['snmpd']['modules']['ucd'])) {
                $snmpdconf .= <<<EOD
begemotSnmpdModulePath."ucd"     = "/usr/local/lib/snmp_ucd.so"

EOD;
            }
            if (isset($config['snmpd']['modules']['regex'])) {
                $snmpdconf .= <<<EOD
begemotSnmpdModulePath."regex"     = "/usr/local/lib/snmp_regex.so"

EOD;
            }
        }

        fwrite($fd, $snmpdconf);
        fclose($fd);
        unset($snmpdconf);


        /* run bsnmpd */
        mwexec("/usr/sbin/bsnmpd -c /var/etc/snmpd.conf -p /var/run/snmpd.pid");

        if (file_exists("/var/run/booting")) {
            echo gettext("done.") . "\n";
        }
    }

    return 0;
}

function services_dnsupdate_process($int = '', $updatehost = '', $forced = false)
{
    global $config;

    if (isset($config['dnsupdates']['dnsupdate'])) {
        $notify_text = "";
        foreach ($config['dnsupdates']['dnsupdate'] as $i => $dnsupdate) {
            if (!isset($dnsupdate['enable'])) {
                continue;
            } elseif (!empty($int) && $int != $dnsupdate['interface']) {
                continue;
            } elseif (!empty($updatehost) && ($updatehost != $dnsupdate['host'])) {
                continue;
            }

            $if = get_real_interface($dnsupdate['interface']);

            if (isset($dnsupdate['usepublicip'])) {
                $wanip = dyndnsCheckIP($dnsupdate['interface']);
            } else {
                $wanip = get_interface_ip($dnsupdate['interface']);
            }

            $wanipv6 = get_interface_ipv6($dnsupdate['interface']);
            $cacheFile = "/conf/dyndns_{$dnsupdate['interface']}_rfc2136_" . escapeshellarg($dnsupdate['host']) . "_{$dnsupdate['server']}.cache";
            $currentTime = time();

            if ($wanip || $wanipv6) {
                $keyname = $dnsupdate['keyname'];
                /* trailing dot */
                if (substr($keyname, -1) != ".") {
                    $keyname .= ".";
                }

                $hostname = $dnsupdate['host'];
                /* trailing dot */
                if (substr($hostname, -1) != ".") {
                    $hostname .= ".";
                }

                /* write private key file
                   this is dumb - public and private keys are the same for HMAC-MD5,
                   but nsupdate insists on having both */
                $fd = fopen("/var/etc/K{$i}{$keyname}+157+00000.private", "w");
                $privkey = <<<EOD
Private-key-format: v1.2
Algorithm: 157 (HMAC)
Key: {$dnsupdate['keydata']}

EOD;
                fwrite($fd, $privkey);
                fclose($fd);

                /* write public key file */
                if ($dnsupdate['keytype'] == "zone") {
                    $flags = 257;
                    $proto = 3;
                } elseif ($dnsupdate['keytype'] == "host") {
                    $flags = 513;
                    $proto = 3;
                } elseif ($dnsupdate['keytype'] == "user") {
                    $flags = 0;
                    $proto = 2;
                }

                $fd = fopen("/var/etc/K{$i}{$keyname}+157+00000.key", "w");
                fwrite($fd, "{$keyname} IN KEY {$flags} {$proto} 157 {$dnsupdate['keydata']}\n");
                fclose($fd);

                /* generate update instructions */
                $upinst = "";
                if (!empty($dnsupdate['server'])) {
                    $upinst .= "server {$dnsupdate['server']}\n";
                }

                if (file_exists($cacheFile)) {
                    list($cachedipv4, $cacheTimev4) = explode("|", file_get_contents($cacheFile));
                }
                if (file_exists("{$cacheFile}.ipv6")) {
                    list($cachedipv6, $cacheTimev6) = explode("|", file_get_contents("{$cacheFile}.ipv6"));
                }

                // 25 Days
                $maxCacheAgeSecs = 25 * 24 * 60 * 60;
                $need_update = false;

                /* Update IPv4 if we have it. */
                if (is_ipaddrv4($wanip)) {
                    if (($wanip != $cachedipv4) || (($currentTime - $cacheTimev4) > $maxCacheAgeSecs) || $forced) {
                        $upinst .= "update delete {$dnsupdate['host']}. A\n";
                        $upinst .= "update add {$dnsupdate['host']}. {$dnsupdate['ttl']} A {$wanip}\n";
                        $notify_text .= sprintf(gettext('Dynamic DNS updated IP Address (A) for %s on %s (%s) to %s'), $dnsupdate['host'], convert_real_interface_to_friendly_descr($if), $if, $wanip) . "\n";
                        @file_put_contents($cacheFile, "{$wanip}|{$currentTime}");
                        log_error("Dynamic DNS: updating cache file {$cacheFile}: {$wanip}");
                        $need_update = true;
                    } else {
                        log_error("Dynamic DNS: Not updating {$dnsupdate['host']} A record because the IP address has not changed.");
                    }
                } else {
                    @unlink($cacheFile);
                }

                /* Update IPv6 if we have it. */
                if (is_ipaddrv6($wanipv6)) {
                    if (($wanipv6 != $cachedipv6) || (($currentTime - $cacheTimev6) > $maxCacheAgeSecs) || $forced) {
                        $upinst .= "update delete {$dnsupdate['host']}. AAAA\n";
                        $upinst .= "update add {$dnsupdate['host']}. {$dnsupdate['ttl']} AAAA {$wanipv6}\n";
                        $notify_text .= sprintf(gettext('Dynamic DNS updated IPv6 Address (AAAA) for %s on %s (%s) to %s'), $dnsupdate['host'], convert_real_interface_to_friendly_descr($if), $if, $wanipv6) . "\n";
                        @file_put_contents("{$cacheFile}.ipv6", "{$wanipv6}|{$currentTime}");
                        log_error("Dynamic DNS: updating cache file {$cacheFile}.ipv6: {$wanipv6}");
                        $need_update = true;
                    } else {
                        log_error("Dynamic DNS: Not updating {$dnsupdate['host']} AAAA record because the IPv6 address has not changed.");
                    }
                } else {
                    @unlink("{$cacheFile}.ipv6");
                }

                $upinst .= "\n";  /* mind that trailing newline! */

                if ($need_update) {
                    @file_put_contents("/var/etc/nsupdatecmds{$i}", $upinst);
                    unset($upinst);
                    /* invoke nsupdate */
                    $cmd = "/usr/local/bin/nsupdate -k /var/etc/K{$i}{$keyname}+157+00000.key";
                    if (isset($dnsupdate['usetcp']))
                      $cmd .= " -v";
                    $cmd .= " /var/etc/nsupdatecmds{$i}";
                    mwexec_bg($cmd);
                    unset($cmd);
                }
            }
        }
        if (!empty($notify_text)) {
            notify_all_remote($notify_text);
        }
    }

    return 0;
}

function configure_cron()
{
    global $config;

    /*
     * Strip the cron jobs that we no longer save explicitly.
     * This can be safely removed on our final road to 17.1.
     */
    if (isset($config['cron']['item'])) {
        $flush = false;
        $index = 0;

        /* PHP retains the index, can iterate all and unset */
        foreach ($config['cron']['item'] as $item) {
            unset($config['cron']['item'][$index]);
            $flush = true;
            $index++;
        }

        if (!count($config['cron']['item'])) {
            unset($config['cron']);
            $flush = true;
        }

        if ($flush) {
            write_config(gettext('Removed obsolete cron jobs'));
        }
    }

    $autocron = array();

    /* hourly */
    $autocron[] = generate_cron_job('/usr/local/sbin/expiretable -v -t 3600 webConfiguratorlockout', '1');
    $autocron[] = generate_cron_job('/usr/local/sbin/expiretable -v -t 3600 sshlockout', '2');
    $autocron[] = generate_cron_job('/usr/local/sbin/expiretable -v -t 3600 virusprot', '3');
    $autocron[] = generate_cron_job('/usr/local/etc/rc.expireaccounts', '5');

    /* daily */
    $autocron[] = generate_cron_job('/usr/local/etc/rc.update_alias_url_data', '1', '1');
    $autocron[] = generate_cron_job('/usr/local/etc/rc.update_urltables', '5', '1');
    $autocron[] = generate_cron_job('/usr/local/etc/rc.dyndns.update', '11', '1');

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

    if (!empty($config['system']['remotebackup']['GDriveEnabled'])) {
        $autocron[] = generate_cron_job('/usr/local/opnsense/scripts/remote_backup.php', 0, 1);
    }

    if (isset($config['filter']['rule'])) {
        foreach ($config['filter']['rule'] as $rule) {
            if (!empty($rule['sched'])) {
                $autocron[] = generate_cron_job('/usr/local/etc/rc.filter_configure_sync', '0,15,30,45');
                break;
            }
        }
    }

    /* bogons fetch always set in default config.xml */
    switch ($config['system']['bogons']['interval']) {
        case 'daily':
            $autocron[] = generate_cron_job('/usr/local/etc/rc.update_bogons', '1', '3', '*', '*', '*');
            break;
        case 'weekly':
            $autocron[] = generate_cron_job('/usr/local/etc/rc.update_bogons', '1', '3', '*', '*', '0');
            break;
        case 'monthly':
        default:
            $autocron[] = generate_cron_job('/usr/local/etc/rc.update_bogons', '1', '3', '1', '*', '*');
            break;
    }

    $crontab_contents = "# DO NOT EDIT THIS FILE -- OPNsense auto-generated file\n";
    $crontab_contents .= "# Please use /etc/crontab for your overrides instead.\n";
    $crontab_contents .= "SHELL=/bin/sh\n";
    $crontab_contents .= "PATH=/etc:/bin:/sbin:/usr/bin:/usr/sbin:/usr/local/bin:/usr/local/sbin\n";
    $crontab_contents .= "#minute\thour\tmday\tmonth\twday\tcommand\n";

    foreach ($autocron as $item) {
        $crontab_contents .= "{$item['minute']}\t";
        $crontab_contents .= "{$item['hour']}\t";
        $crontab_contents .= "{$item['mday']}\t";
        $crontab_contents .= "{$item['month']}\t";
        $crontab_contents .= "{$item['wday']}\t";
        $crontab_contents .= "{$item['command']}\n";
    }

    file_put_contents('/var/cron/tabs/root', $crontab_contents);
    killbypid('/var/run/cron.pid', 'HUP');
}

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

function is_apinger_enabled()
{
    global $config;

    $gwcount=0;

    if (isset($config['gateways']['gateway_item'])) {
        foreach($config['gateways']['gateway_item'] as $gwkey => $gateway) {
            if (!isset($gateway['monitor_disable']) || $gateway['monitor_disable'] == "0") {
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
    global $config, $g;

    $services = array();

    if (is_radvd_enabled()) {
        $pconfig = array();
        $pconfig['name'] = "radvd";
        $pconfig['description'] = gettext("Router Advertisement Daemon");
        $pconfig['php']['restart'] = array('services_radvd_configure');
        $pconfig['php']['start'] = array('services_radvd_configure');
        $pconfig['pidfile'] = '/var/run/radvd.pid';
        $services[] = $pconfig;
    }

    if (isset($config['dnsmasq']['enable'])) {
        $pconfig = array();
        $pconfig['name'] = "dnsmasq";
        $pconfig['description'] = gettext("DNS Forwarder");
        $pconfig['php']['restart'] = array('services_dnsmasq_configure');
        $pconfig['php']['start'] = array('services_dnsmasq_configure');
        $pconfig['pidfile'] = '/var/run/dnsmasq.pid';
        $services[] = $pconfig;
    }

    if (isset($config['unbound']['enable'])) {
        $pconfig = array();
        $pconfig['name'] = "unbound";
        $pconfig['description'] = gettext("Unbound DNS Resolver");
        $pconfig['php']['restart'] = array('services_unbound_configure');
        $pconfig['php']['start'] = array('services_unbound_configure');
        $pconfig['pidfile'] = '/var/run/unbound.pid';
        $services[] = $pconfig;
    }

    if (isset($config['system']['timeservers'])) {
        $pconfig = array();
        $pconfig['name'] = "ntpd";
        $pconfig['description'] = gettext("NTP clock sync");
        $pconfig['php']['restart'] = array('system_ntp_configure');
        $pconfig['php']['start'] = array('system_ntp_configure');
        $pconfig['pidfile'] = '/var/run/ntpd.pid';
        $services[] = $pconfig;
    }

    $iflist = array();
    $ifdescrs = get_configured_interface_list();
    foreach ($ifdescrs as $if) {
        $oc = $config['interfaces'][$if];
        if ($oc['if'] && (!link_interface_to_bridge($if))) {
            $iflist[$if] = $if;
        }
    }

    if (isset($config['dhcrelay']['enable'])) {
        $pconfig = array();
        $pconfig['name'] = "dhcrelay";
        $pconfig['description'] = gettext("DHCP Relay");
        $pconfig['php']['restart'] = array('services_dhcrelay_configure');
        $pconfig['php']['start'] = array('services_dhcrelay_configure');
        $pconfig['pidfile'] = '/var/run/dhcrelay.pid';
        $services[] = $pconfig;
    }

    if (isset($config['dhcrelay6']['enable'])) {
        $pconfig = array();
        $pconfig['name'] = "dhcrelay6";
        $pconfig['description'] = gettext("DHCPv6 Relay");
        $pconfig['php']['restart'] = array('services_dhcrelay6_configure');
        $pconfig['php']['start'] = array('services_dhcrelay6_configure');
        $pconfig['pidfile'] = '/var/run/dhcrelay6.pid';
        $services[] = $pconfig;
    }

    if (is_dhcpv4_server_enabled()) {
        $pconfig = array();
        $pconfig['name'] = 'dhcpd';
        $pconfig['description'] = gettext("DHCP Server");
        $pconfig['php']['restart'] = array('services_dhcpdv4_configure');
        $pconfig['php']['start'] = array('services_dhcpdv4_configure');
        $pconfig['pidfile'] = "{$g['dhcpd_chroot_path']}/var/run/dhcpd.pid";
        $services[] = $pconfig;
    }

    if (is_dhcpv6_server_enabled()) {
        $pconfig = array();
        $pconfig['name'] = 'dhcpd6';
        $pconfig['description'] = gettext("DHCPv6 Server");
        $pconfig['php']['restart'] = array('services_dhcpdv6_configure');
        $pconfig['php']['start'] = array('services_dhcpdv6_configure');
        $pconfig['pidfile'] = "{$g['dhcpd_chroot_path']}/var/run/dhcpdv6.pid";
        $services[] = $pconfig;
    }

    if (is_apinger_enabled()) {
        $pconfig = array();
        $pconfig['name'] = "apinger";
        $pconfig['description'] = gettext("Gateway Monitoring Daemon");
        $pconfig['php']['restart'] = array('setup_gateways_monitor_full');
        $pconfig['php']['start'] = array('setup_gateways_monitor');
        $pconfig['pidfile'] = '/var/run/apinger.pid';
        $services[] = $pconfig;
    }

    if (isset($config['snmpd']['enable'])) {
        $pconfig = array();
        $pconfig['name'] = "bsnmpd";
        $pconfig['description'] = gettext("SNMP Service");
        $pconfig['php']['restart'] = array('services_snmpd_configure');
        $pconfig['php']['start'] = array('services_snmpd_configure');
        $pconfig['pidfile'] = '/var/run/snmpd.pid';
        $services[] = $pconfig;
    }

    if (isset($config['igmpproxy']['igmpentry']) && is_array($config['igmpproxy']['igmpentry']) && (count($config['igmpproxy']['igmpentry']) > 0)) {
        $pconfig = array();
        $pconfig['name'] = "igmpproxy";
        $pconfig['description'] = gettext("IGMP proxy");
        $pconfig['php']['restart'] = array('services_igmpproxy_configure');
        $pconfig['php']['start'] = array('services_igmpproxy_configure');
        $services[] = $pconfig;
    }

    if (isset($config['installedpackages']['miniupnpd']) && $config['installedpackages']['miniupnpd']['config'][0]['enable']) {
        $pconfig = array();
        $pconfig['name'] = "miniupnpd";
        $pconfig['description'] = gettext("UPnP Service");
        $pconfig['description'] = gettext("UPnP Service");
        $pconfig['php']['restart'] = array('upnp_stop', 'upnp_start');
        $pconfig['php']['start'] = array('upnp_start');
        $pconfig['php']['stop'] = array('upnp_stop');
        $pconfig['pidfile'] = '/var/run/miniupnpd.pid';
        $services[] = $pconfig;
    }

    if (isset($config['system']['ssh']['enabled'])) {
        $pconfig = array();
        $pconfig['name'] = 'sshd';
        $pconfig['description'] = gettext('Secure Shell Daemon');
        $pconfig['configd']['restart'] = array('sshd restart');
        $pconfig['configd']['start'] = array('sshd restart');
        $services[] = $pconfig;
    }

    if (isset($config['load_balancer']['virtual_server']) && isset($config['load_balancer']['lbpool']) && count($config['load_balancer']['virtual_server']) && count($config['load_balancer']['lbpool'])) {
          $pconfig = array();
          $pconfig['name'] = "relayd";
          $pconfig['description'] = gettext("Server load balancing daemon");
          $pconfig['php']['restart'] = array('relayd_configure', 'filter_configure');
          $pconfig['php']['start'] = array('relayd_configure', 'filter_configure');
          $services[] = $pconfig;
    }

    if (isset($config['OPNsense']['proxy']['general']['enabled']) && $config['OPNsense']['proxy']['general']['enabled'] == 1) {
        $services[] = array(
            'description' => gettext('Proxy server'),
            'configd' => array(
                'restart' => array('proxy restart'),
                'start' => array('proxy start'),
                'stop' => array('proxy stop'),
            ),
            'name' => 'squid',
        );
    }

    if (isset($config['OPNsense']['IDS']['general']['enabled']) && $config['OPNsense']['IDS']['general']['enabled'] == 1) {
        $services[] = array(
            'description' => gettext('Intrusion Detection'),
            'configd' => array(
                'restart' => array('ids restart'),
                'start' => array('ids start'),
                'stop' => array('ids stop'),
            ),
            'name' => 'suricata',
        );
    }

    if (isset($config['OPNsense']['captiveportal']['zones']['zone'])) {
        $enabled = false;
        if (!empty($config['OPNsense']['captiveportal']['zones']['zone']['enabled'])) {
            // single zone and enabled
            $enabled = true;
        } else {
            // possible more zones, traverse items
            foreach ($config['OPNsense']['captiveportal']['zones']['zone'] as $zone) {
                if (!empty($zone['enabled'])) {
                    $enabled = true;
                }
            }
        }
        if ($enabled) {
            $services[] = array(
                'pidfile' => '/var/run/lighttpd-api-dispatcher.pid',
                'description' => gettext('Captive Portal'),
                'configd' => array(
                    'restart' => array('captiveportal restart'),
                    'start' => array('captiveportal start'),
                    'stop' => array('captiveportal stop'),
                ),
                'name' => 'captiveportal',
            );
        }
    }

    $services[] = array(
        'description' => gettext('System Configuration Daemon'),
        'pidfile' => '/var/run/configd.pid',
        'mwexec' => array(
            'restart' => array('/usr/local/etc/rc.d/configd restart'),
            'start' => array('/usr/local/etc/rc.d/configd start'),
            'stop' => array('/usr/local/etc/rc.d/configd stop'),
        ),
        'name' => 'configd',
    );

    if (function_exists('plugins_services')) {
        /* only pull plugins if plugins.inc was included before */
        foreach (plugins_services() as $service) {
            $services[] = $service;
        }
    }

    return $services;
}

function find_service_by_name($names, $filter = array())
{
    if (!is_array($names)) {
        $names = array($names);
    }

    $services = services_get();

    foreach ($services as $service) {
        foreach ($names as $name) {
            if ($service['name'] != $name) {
                continue;
            }
            if (!count($filter)) {
                /* force match if filter wasn't set (standard behaviour) */
                $filter['name'] = $name;
            }
            foreach ($filter as $key => $value) {
                if (isset($service[$key]) && $service[$key] == $value) {
                    /*
                     * First match wins, $names is only used
                     * to probe similar services that exclude
                     * each other.
                     */
                    return $service;
                }
            }
        }
    }

    return array();
}

function service_name_compare($a, $b) {
    if (strtolower($a['name']) == strtolower($b['name'])) {
        return 0;
    }
    return (strtolower($a['name']) < strtolower($b['name'])) ? -1 : 1;
}


function get_service_status($service)
{
    if (isset($service['pidfile'])) {
        return isvalidpid($service['pidfile']);
    }

    return is_process_running($service['name']);
}


function get_service_status_icon($service, $withtext = true, $smallicon = false)
{
    $output = "";
    if (get_service_status($service)) {
        $statustext = gettext("Running");
        $output .= '<span class="btn btn-success glyphicon glyphicon-play" title="'.sprintf(gettext('%s Service is'),$service['name']).' '.$statustext.'" data-toggle="tooltip"></span>' . PHP_EOL;
    } else {
        $statustext = gettext("Stopped") ;
        $output .= '<span class="btn btn-danger glyphicon glyphicon-stop" title="'.sprintf(gettext('%s Service is'),$service['name']).' '.$statustext.'" data-toggle="tooltip"></span>' . PHP_EOL;
    }
    return $output;
}

function get_service_control_links($service, $addname = false)
{
    $service_id = isset($service['id']) ? $service['id'] : '';
    $service_title = $addname ? $service['name'] . ' ' : '';

    $template  = '<span data-service_id="%s" data-service_action="%s" data-service="%s" ';
    $template .= 'data-toggle="tooltip" title="%s" class="btn btn-default %s %s"></span>' . PHP_EOL;

    $output = '';

    if (get_service_status($service)) {
        $output .= sprintf(
            $template,
            $service_id,
            'restart',
            $service['name'],
            sprintf(gettext('Restart %sService'), $service_title),
            'srv_status_act',
            'glyphicon glyphicon-refresh'
        );

        $output .= sprintf(
            $template,
            $service_id,
            'stop',
            $service['name'],
            sprintf(gettext('Stop %sService'), $service_title),
            'srv_status_act',
            'glyphicon glyphicon-stop'
        );
    } else {
        $output .= sprintf(
            $template,
            $service_id,
            'start',
            $service['name'],
            sprintf(gettext('Start %sService'), $service_title),
            'srv_status_act',
            'glyphicon glyphicon-play'
        );
    }

    return $output;
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
        if (!isset($config['interfaces'][$dhcpv6if]['enable'])) {
            continue;
        }

        if (!isset($dhcpv6ifconf['ramode'])) {
            $dhcpv6ifconf['ramode'] = $dhcpv6ifconf['mode'];
        }

        if ($dhcpv6ifconf['ramode'] == "disabled") {
            continue;
        }

        $ifcfgipv6 = get_interface_ipv6($dhcpv6if);
        if (!is_ipaddrv6($ifcfgipv6)) {
            continue;
        }

        return true;
    }

    /* handle DHCP-PD prefixes and 6RD dynamic interfaces */
    foreach ($Iflist as $if => $ifdescr) {
        if (!isset($config['interfaces'][$if]['track6-interface'])) {
            continue;
        }
        if (!isset($config['interfaces'][$if]['enable'])) {
            continue;
        }

        $ifcfgipv6 = get_interface_ipv6($if);
        if (!is_ipaddrv6($ifcfgipv6)) {
            continue;
        }

        $ifcfgsnv6 = get_interface_subnetv6($if);
        $subnetv6 = gen_subnetv6($ifcfgipv6, $ifcfgsnv6);

        if (!is_ipaddrv6($subnetv6)) {
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
