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

function dhcpd_configure()
{
    return [
        'dhcp' => ['dhcpd_dhcp_configure:3'],
        'local' => ['dhcpd_dhcp_configure'],
    ];
}

function dhcpd_run()
{
    return [
        'static_mapping' => 'dhcpd_staticmap',
    ];
}

function dhcpd_dhcpv6_enabled()
{
    global $config;
    $explicit_off = [];

    /* handle manually configured DHCP6 server settings first */
    foreach (config_read_array('dhcpdv6') as $dhcpv6if => $dhcpv6ifconf) {
        if (isset($config['interfaces'][$dhcpv6if]['enable']) && isset($dhcpv6ifconf['enable'])) {
            if ($dhcpv6ifconf['enable'] == '-1') {
                $explicit_off[] = $dhcpv6if;
            } else {
                return true;
            }
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
    $services = [];

    if (dhcpd_dhcpv4_enabled()) {
        $pconfig = [];
        $pconfig['name'] = 'dhcpd';
        $pconfig['description'] = gettext('ISC DHCPv4 Server');
        $pconfig['php']['restart'] = ['dhcpd_dhcp4_configure'];
        $pconfig['php']['start'] = ['dhcpd_dhcp4_configure'];
        $pconfig['pidfile'] = '/var/dhcpd/var/run/dhcpd.pid';
        $services[] = $pconfig;
    }

    if (dhcpd_dhcpv6_enabled()) {
        $pconfig = [];
        $pconfig['name'] = 'dhcpd6';
        $pconfig['description'] = gettext('ISC DHCPv6 Server');
        $pconfig['php']['restart'] = ['dhcpd_dhcp6_configure'];
        $pconfig['php']['start'] = ['dhcpd_dhcp6_configure'];
        $pconfig['pidfile'] = '/var/dhcpd/var/run/dhcpdv6.pid';
        $services[] = $pconfig;
    }

    return $services;
}

function dhcpd_xmlrpc_sync()
{
    $result = [];

    $result[] = [
        'description' => gettext('ISC DHCPv4'),
        'help' => gettext('Synchronize the DHCP Server settings over to the other HA host.'),
        'section' => 'dhcpd',
        'id' => 'dhcpd',
        'services' => ['dhcpd'],
    ];

    $result[] = [
        'description' => gettext('ISC DHCPv6'),
        'help' => gettext('Synchronize DHCPv6 including router advertisements.'),
        'section' => 'dhcpdv6',
        'id' => 'dhcpdv6',
        'services' => ['dhcpdv6', 'radvd'], /* XXX we need to extract this */
    ];

    return $result;
}

function dhcpd_dhcp_configure($verbose = false, $family = null, $blacklist = [])
{
    $dirs = ['/dev', '/etc', '/lib', '/run', '/usr', '/usr/local/sbin', '/var/db', '/var/run'];

    foreach ($dirs as $dir) {
        mwexecf('/bin/mkdir -p %s', "/var/dhcpd{$dir}");
    }

    if (mwexecfm('/sbin/mount -uw %s', '/var/dhcpd/dev')) {
        mwexecf('/sbin/mount -t devfs devfs %s', '/var/dhcpd/dev');
    }

    mwexecf('/usr/sbin/chown -R dhcpd:dhcpd %s', '/var/dhcpd');

    if ($family == null || $family == 'inet') {
        dhcpd_dhcp4_configure($verbose);
    }

    if ($family == null || $family == 'inet6') {
        dhcpd_dhcp6_configure($verbose, $blacklist);
    }
}

function dhcpd_dhcp4_configure($verbose = false)
{
    global $config;

    $need_ddns_updates = false;
    $ddns_zones = [];

    if (!dhcpd_dhcpv4_enabled()) {
        /*
         * XXX If there is no enabled server interfaces_staticarp_configure()
         * is not executed as documented.  This likely does not matter, but
         * since it came up as odd in testing make a note here for the future.
         */
        killbypid('/var/dhcpd/var/run/dhcpd.pid');
        return;
    }

    service_log('Starting DHCPv4 service...', $verbose);

    killbypid('/var/dhcpd/var/run/dhcpd.pid');

    /* Only consider DNS servers with IPv4 addresses for the IPv4 DHCP server. */
    $dns_arrv4 = [];
    if (!empty($config['system']['dnsserver'][0])) {
        foreach ($config['system']['dnsserver'] as $dnsserver) {
            if (is_ipaddrv4($dnsserver)) {
                $dns_arrv4[] = $dnsserver;
            }
        }
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

    $omapi_added = false;
    $dhcpdifs = [];

    /*
     * loop through and determine if we need
     * to setup failover peer "bleh" entries
     */
    foreach ($config['dhcpd'] as $dhcpif => $dhcpifconf) {
        /* certainly odd but documented and side effect populates ARP table */
        interfaces_staticarp_configure($dhcpif, $ifconfig_details);

        if (!isset($dhcpifconf['enable'])) {
            continue;
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
                log_msg("DHCP failover set up on {$dhcpif} but no CARP virtual IPs defined!", LOG_WARNING);
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

    foreach ($config['dhcpd'] as $dhcpif => $dhcpifconf) {
        if (!isset($dhcpifconf['enable']) || !isset($iflist[$dhcpif])) {
            continue;
        }

        list ($ifcfgip, $ifcfgnet, $ifcfgsn) = interfaces_primary_address($dhcpif, $ifconfig_details);
        if (!is_ipaddrv4($ifcfgip) || !is_subnetv4($ifcfgnet)) {
            $device = get_real_interface($dhcpif);
            log_msg("dhcpd_dhcp4_configure() found no suitable IPv4 address on {$dhcpif}({$device})", LOG_WARNING);
            continue;
        }

        $subnetmask = gen_subnet_mask($ifcfgsn);
        $subnet = explode('/', $ifcfgnet)[0];

        $all_pools = [];
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

        $newzone = [];

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
        } elseif (!empty(service_by_filter(['dns_ports' => '53']))) {
            $dnscfg .= "  option domain-name-servers {$ifcfgip};";
            if (!empty($newzone['domain-name'])) {
                $newzone['dns-servers'] = [$ifcfgip];
            }
        } elseif (!empty($dns_arrv4)) {
            $dnscfg .= "  option domain-name-servers " . join(",", $dns_arrv4) . ";";
            if (!empty($newzone['domain-name'])) {
                $newzone['dns-servers'] = $dns_arrv4;
            }
        }

        /*
         * Create classes - These all contain comma-separated lists.
         * Join them into one big comma-separated string then split
         * them all up.
         */
        $all_mac_strings = [];
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
            if (!empty($poolconf['dnsserver'][0])) {
                $dhcpdconf .= '    option domain-name-servers ' . join(',', $poolconf['dnsserver']) . ";\n";
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

            if (
                !empty($poolconf['domain'])
                && (empty($dhcpifconf['domain']) || $poolconf['domain'] != $dhcpifconf['domain'])
            ) {
                $dhcpdconf .= "    option domain-name \"{$poolconf['domain']}\";\n";
            }

            if (
                !empty($poolconf['domainsearchlist'])
                && (empty($dhcpifconf['domainsearchlist']) || $poolconf['domainsearchlist'] != $dhcpifconf['domainsearchlist'])
            ) {
                $dhcpdconf .= "    option domain-search \"" . join("\",\"", preg_split("/[ ;]+/", $poolconf['domainsearchlist'])) . "\";\n";
            }

            if (isset($poolconf['ddnsupdate'])) {
                if (
                    !empty($poolconf['ddnsdomain'])
                    && (empty($dhcpifconf['ddnsdomain']) || $poolconf['ddnsdomain'] != $dhcpifconf['ddnsdomain'])
                ) {
                    $dhcpdconf .= "    ddns-domainname \"{$poolconf['ddnsdomain']}\";\n";

                    $newddnszone = [];
                    $newddnszone['domain-name'] = $poolconf['ddnsdomain'];
                    $newddnszone['dns-servers'] = array($poolconf['ddnsdomainprimary']);
                    $newddnszone['ddnsdomainkeyname'] = $poolconf['ddnsdomainkeyname'] ?? '';
                    $newddnszone['ddnsdomainkey'] = $poolconf['ddnsdomainkey'] ?? '';
                    $newddnszone['ddnsdomainalgorithm'] = !empty($poolconf['ddnsdomainalgorithm']) ? $poolconf['ddnsdomainalgorithm'] : "hmac-md5";
                    $ddns_zones[] = $newddnszone;
                }
            }

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
        } else {
            // by default, add interface address in "option routers"
            $routers = $ifcfgip;
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

            $conditional = false;
            $filemap = [
                '00:06' => 'filename32',
                '00:07' => 'filename64',
                '00:09' => 'filename64',
                '00:0a' => 'filename32arm',
                '00:0b' => 'filename64arm',
            ];

            if (!empty($dhcpifconf['filenameipxe'])) {
                $dhcpdconf .= "  if exists user-class and option user-class = \"iPXE\" {\n";
                $dhcpdconf .= "    filename \"{$dhcpifconf['filenameipxe']}\";\n";
                $dhcpdconf .= "  }";
                $conditional = true;
            }

            foreach ($filemap as $arch => $file) {
                if (empty($dhcpifconf[$file])) {
                    continue;
                }

                $dhcpdconf .= $conditional ? ' else ' : '  ';
                $dhcpdconf .= "if option arch = {$arch} {\n";
                $dhcpdconf .= "    filename \"{$dhcpifconf[$file]}\";\n";
                $dhcpdconf .= "  }";

                $conditional = true;
            }

            if ($conditional) {
                if (!empty($dhcpifconf['filename'])) {
                    $dhcpdconf .= "  else {\n";
                    $dhcpdconf .= "    filename \"{$dhcpifconf['filename']}\";\n";
                    $dhcpdconf .= "  }";
                }
                $dhcpdconf .= "\n";
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
                    if ($need_ddns_updates) {
                        $dhcpdconf .= "  ddns-hostname \"{$dhhostname}\";\n";
                    }
                    $dhcpdconf .= "  set hostname-override = config-option host-name;\n";
                }
                if (!empty($sm['filename'])) {
                    $dhcpdconf .= "  filename \"{$sm['filename']}\";\n";
                }

                if (!empty($sm['rootpath'])) {
                    $dhcpdconf .= "  option root-path \"{$sm['rootpath']}\";\n";
                }

                if (!empty($sm['gateway']) && $sm['gateway'] != "none" && (empty($dhcpifconf['gateway']) || $sm['gateway'] != $dhcpifconf['gateway'])) {
                    $dhcpdconf .= "  option routers {$sm['gateway']};\n";
                }

                $smdnscfg = "";
                if (!empty($sm['domain']) && ($sm['domain'] != $dhcpifconf['domain'])) {
                    $smdnscfg .= "  option domain-name \"{$sm['domain']}\";\n";
                }

                if (!empty($sm['domainsearchlist']) && ($sm['domainsearchlist'] != $dhcpifconf['domainsearchlist'])) {
                    $smdnscfg .= "  option domain-search \"" . join("\",\"", preg_split("/[ ;]+/", $sm['domainsearchlist'])) . "\";\n";
                }

                if ($need_ddns_updates) {
                    if (!empty($sm['ddnsdomain']) && ($sm['ddnsdomain'] != $dhcpifconf['ddnsdomain'])) {
                        $smdnscfg .= "  ddns-domainname \"{$sm['ddnsdomain']}\";\n";
                    }
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

        if (!empty($newzone['domain-name']) && isset($dhcpifconf['ddnsupdate']) && is_ipaddrv4($dhcpifconf['ddnsdomainprimary'])) {
            $newzone['dns-servers'] = array($dhcpifconf['ddnsdomainprimary']);
            $newzone['ddnsdomainkeyname'] = $dhcpifconf['ddnsdomainkeyname'] ?? '';
            $newzone['ddnsdomainkey'] = $dhcpifconf['ddnsdomainkey'] ?? '';
            $newzone['ddnsdomainalgorithm'] = !empty($dhcpifconf['ddnsdomainalgorithm']) ? $dhcpifconf['ddnsdomainalgorithm'] : "hmac-md5";
            $ddns_zones[] = $newzone;
        }

        if (isset($dhcpifconf['omapi']) && !$omapi_added) {
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

        $dhcpdifs[] = escapeshellarg(get_real_interface($dhcpif));
    }

    if ($need_ddns_updates) {
        $dhcpdconf .= "\nddns-update-style interim;\n";
        $dhcpdconf .= "update-static-leases on;\n";
        $dhcpdconf .= dhcpd_zones($ddns_zones);
    }

    foreach (glob('/usr/local/etc/dhcpd.opnsense.d/*.conf') as $file) {
        $dhcpdconf .= "\n\n# including custom file {$file}\n" . file_get_contents($file);
    }

    @file_put_contents('/var/dhcpd/etc/dhcpd.conf', $dhcpdconf);
    @touch('/var/dhcpd/var/db/dhcpd.leases');

    if (count($dhcpdifs) > 0) {
        mwexecf('/usr/local/opnsense/scripts/dhcp/cleanup_leases4.php -m');
        mwexecf('/usr/local/sbin/dhcpd -user dhcpd -group dhcpd -chroot /var/dhcpd -cf /etc/dhcpd.conf -pf /var/run/dhcpd.pid ' . implode(' ', $dhcpdifs));
    }

    service_log("done.\n", $verbose);
}

function dhcpd_zones($ddns_zones, $ipproto = 'inet')
{
    $dhcpdconf = '';
    if (is_array($ddns_zones)) {
        $added_zones = [];
        $added_keys = [];
        foreach ($ddns_zones as $zone) {
            $versionsuffix = $ipproto == "inet6" ? "6" : "";
            // We don't need to add zones multiple times.
            foreach ([$zone['domain-name'], $zone['ptr-domain']] as $domain) {
                if (!empty($domain) && !in_array($domain, $added_zones)) {
                    /* dhcpdconf2 is injected *after* the key */
                    $dhcpdconf2 = "zone {$domain}. {\n";
                    // XXX: $zone['dns-servers'] only contains one item, ref $newzone['dns-servers']
                    $dhcpdconf2 .= "  primary{$versionsuffix} {$zone['dns-servers'][0]};\n";
                    if (!empty($zone['ddnsdomainkeyname']) && !empty($zone['ddnsdomainkey'])) {
                        if (!in_array($zone['ddnsdomainkeyname'], $added_keys)) {
                            $dhcpdconf .= "\nkey {$zone['ddnsdomainkeyname']} {\n";
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

function dhcpd_dhcp6_configure($verbose = false, $blacklist = [])
{
    global $config;

    if (!dhcpd_dhcpv6_enabled()) {
        killbypid('/var/dhcpd/var/run/dhcpdv6.pid');
        killbypid('/var/run/dhcpleases6.pid');
        return;
    }

    service_log('Starting DHCPv6 service...', $verbose);

    killbypid('/var/dhcpd/var/run/dhcpdv6.pid');
    killbypid('/var/run/dhcpleases6.pid');

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

    /* we add a fake entry for interfaces that are set to track6 another WAN */
    foreach (array_keys($iflist) as $ifname) {
        /* Do not put in the config an interface which is down */
        if (isset($blacklist[$ifname])) {
            continue;
        }
        if (!empty($config['interfaces'][$ifname]['track6-interface'])) {
            list ($ifcfgipv6) = interfaces_primary_address6($ifname, $ifconfig_details);
            if (!is_ipaddrv6($ifcfgipv6)) {
                continue;
            }

            $ifcfgipv6 = Net_IPv6::getNetmask($ifcfgipv6, 64);
            $ifcfgipv6arr = explode(':', $ifcfgipv6);

            if (!isset($config['interfaces'][$ifname]['dhcpd6track6allowoverride'])) {
                /* mock a real server */
                if (!empty($dhcpdv6cfg[$ifname]) && $dhcpdv6cfg[$ifname]['enable'] == '-1') {
                    /* tracking, but dhcpv6 disabled */
                    $dhcpdv6cfg[$ifname] = [];
                } else {
                    $dhcpdv6cfg[$ifname] = ['enable' => true];
                }

                /* fixed range */
                $ifcfgipv6arr[7] = '1000';
                $dhcpdv6cfg[$ifname]['range'] = array();
                $dhcpdv6cfg[$ifname]['range']['from'] = Net_IPv6::compress(implode(':', $ifcfgipv6arr));
                $ifcfgipv6arr[7] = '2000';
                $dhcpdv6cfg[$ifname]['range']['to'] = Net_IPv6::compress(implode(':', $ifcfgipv6arr));

                /* with enough room we can add dhcp6 prefix delegation */
                $pdlen = calculate_ipv6_delegation_length($config['interfaces'][$ifname]['track6-interface']);
                if ($pdlen > 2) {
                    /* XXX calculation is probably out of whack, please fix */

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
                    $dhcpdv6cfg[$ifname]['range']['from'] = merge_ipv6_address($ifcfgipv6, $dhcpdv6cfg[$ifname]['range']['from']);
                    $dhcpdv6cfg[$ifname]['range']['to'] = merge_ipv6_address($ifcfgipv6, $dhcpdv6cfg[$ifname]['range']['to']);
                }

                if (!empty($dhcpdv6cfg[$ifname]['prefixrange']['from']) && !empty($dhcpdv6cfg[$ifname]['prefixrange']['to'])) {
                    /* XXX $pdlen is never validated against prefixlenght setting, but must be smaller or equal */
                    $pdlen = 64 - calculate_ipv6_delegation_length($config['interfaces'][$ifname]['track6-interface']);

                    $range_from = $dhcpdv6cfg[$ifname]['prefixrange']['from'];
                    if (merge_ipv6_address($range_from, '::') == '::') {
                        log_msg("'{$dhcpdv6cfg[$ifname]['prefixrange']['from']}' is not a valid prefix range value", LOG_WARNING);
                        /* XXX previously it was suggested to use suffix but it was actually infix so shift 64 bits if possible */
                        $range_from = $dhcpdv6cfg[$ifname]['prefixrange']['from'] . ':0:0:0:0';
                    }

                    $range_to = $dhcpdv6cfg[$ifname]['prefixrange']['to'];
                    if (merge_ipv6_address($range_to, '::') == '::') {
                        log_msg("'{$dhcpdv6cfg[$ifname]['prefixrange']['to']}' is not a valid prefix range value", LOG_WARNING);
                        /* XXX previously it was suggested to use suffix but it was actually infix so shift 64 bits if possible */
                        $range_to = $dhcpdv6cfg[$ifname]['prefixrange']['to'] . ':0:0:0:0';
                    }

                    $dhcpdv6cfg[$ifname]['prefixrange']['from'] = merge_ipv6_address($ifcfgipv6, $range_from, $pdlen);
                    $dhcpdv6cfg[$ifname]['prefixrange']['to'] = merge_ipv6_address($ifcfgipv6, $range_to, $pdlen);
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
option dhcp6.rapid-commit;
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

    $dhcpdv6ifs = [];
    $ddns_zones = [];
    $need_ddns_updates = false;

    foreach ($dhcpdv6cfg as $dhcpv6if => $dhcpv6ifconf) {
        if (!isset($dhcpv6ifconf['enable']) || !isset($iflist[$dhcpv6if])) {
            continue;
        }

        if (isset($blacklist[$dhcpv6if])) {
            continue;
        }

        list ($ifcfgipv6, $networkv6) = interfaces_primary_address6($dhcpv6if, $ifconfig_details);
        if (!is_ipaddrv6($ifcfgipv6) || !is_subnetv6($networkv6)) {
            $device = get_real_interface($dhcpv6if, 'inet6');
            log_msg("dhcpd_dhcp6_configure() found no suitable IPv6 address on {$dhcpv6if}({$device})", LOG_WARNING);
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
        } elseif (!empty(service_by_filter(['dns_ports' => '53']))) {
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
                        $sm['ipaddrv6'] = merge_ipv6_address($ifcfgipv6, $sm['ipaddrv6']);
                    }
                    $dhcpdv6conf .= "  fixed-address6 {$sm['ipaddrv6']};\n";
                }

                if (!empty($sm['hostname'])) {
                    $dhhostname = str_replace(" ", "_", $sm['hostname']);
                    $dhhostname = str_replace(".", "_", $dhhostname);
                    $dhcpdv6conf .= "  option host-name \"{$dhhostname}\";\n";
                    if ($need_ddns_updates) {
                        $dhcpdv6conf .= "  ddns-hostname \"{$dhhostname}\";\n";
                    }
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

        $dhcpdv6ifs[] = escapeshellarg(get_real_interface($dhcpv6if, 'inet6'));
    }

    if ($need_ddns_updates) {
        $dhcpdv6conf .= "\nddns-update-style interim;\n";
        $dhcpdv6conf .= "update-static-leases on;\n";
        $dhcpdv6conf .= dhcpd_zones($ddns_zones, "inet6");
    } else {
        $dhcpdv6conf .= "\nddns-update-style none;\n";
    }

    foreach (glob('/usr/local/etc/dhcpd6.opnsense.d/*.conf') as $file) {
        $dhcpdv6conf .= "\n\n# including custom file {$file}\n" . file_get_contents($file);
    }

    @file_put_contents('/var/dhcpd/etc/dhcpdv6.conf', $dhcpdv6conf);
    @touch('/var/dhcpd/var/db/dhcpd6.leases');

    if (count($dhcpdv6ifs) > 0) {
        mwexecf('/usr/local/sbin/dhcpd -6 -user dhcpd -group dhcpd -chroot /var/dhcpd -cf /etc/dhcpdv6.conf -pf /var/run/dhcpdv6.pid ' . implode(' ', $dhcpdv6ifs));
        mwexecf('/usr/sbin/daemon -m0 -f -p %s %s', ['/var/run/dhcpleases6.pid', '/usr/local/opnsense/scripts/dhcp/prefixes.sh']);
    }

    service_log("done.\n", $verbose);
}

function dhcpd_staticmap($proto = null, $valid_addresses = true, $ifconfig_details = null, $allow_disabled = false)
{
    $staticmap = [];
    foreach (empty($proto) ? [4, 6] : [$proto] as $inet) {
        $ipaddr = $inet == 6 ? 'ipaddrv6' : 'ipaddr';

        foreach (config_read_array($inet == 6 ? 'dhcpdv6' : 'dhcpd') as $dhcpif => $dhcpifconf) {
            if (!isset($dhcpifconf['staticmap']) || (!isset($dhcpifconf['enable']) && !$allow_disabled)) {
                continue;
            }

            $ifconf = config_read_array('interfaces', $dhcpif);
            list ($ipaddrv6) = $inet == 6 && isset($ifconf['dhcpd6track6allowoverride']) ?
                interfaces_primary_address6($dhcpif, $ifconfig_details) : [null];

            foreach ($dhcpifconf['staticmap'] as $host) {
                if (empty($host[$ipaddr]) && $valid_addresses) {
                    /* we only return proper entries with an IP address */
                    continue;
                }

                if (!empty($ipaddrv6)) {
                    /* expand IPv6 suffix address, but only allow user-given compressed suffix */
                    $host['ipaddrv6'] = merge_ipv6_address($ipaddrv6, $host['ipaddrv6']);
                }

                if (!empty($host['ipaddrv6'])) {
                    /* avoid sloppy input by recompressing the address correctly */
                    $host['ipaddrv6'] = Net_IPv6::compress(Net_IPv6::uncompress($host['ipaddrv6']));
                }

                $domain = null;

                // XXX: dhcpdv6 domain entries have been superseded by domainsearchlist,
                //      for backward compatibility support both here.
                if ($inet == 6 && !empty($host['domainsearchlist'])) {
                    $domain = $host['domainsearchlist'];
                } elseif (!empty($host['domain'])) {
                    $domain = $host['domain'];
                } elseif ($inet == 6 && !empty($dhcpifconf['domainsearchlist'])) {
                    $domain = $dhcpifconf['domainsearchlist'];
                } elseif (!empty($dhcpifconf['domain'])) {
                    $domain = $dhcpifconf['domain'];
                }

                if ($domain !== null) {
                    /* first entry only */
                    $domain = preg_split('/[ ;]+/', $domain)[0];
                }

                $entry = [
                    'descr' => $host['descr'] ?? null,
                    'domain' => $domain,
                    'hostname' => $host['hostname'] ?? null,
                    'interface' => $dhcpif,
                    $ipaddr => $host[$ipaddr],
                ];

                foreach (['mac', 'duid'] as $property) {
                    if (isset($host[$property])) {
                        $entry[$property] = $host[$property];
                    }
                }

                $staticmap[] = $entry;
            }
        }
    }

    return $staticmap;
}

function dhcpd_parse_duid($duid_string)
{
    $parsed_duid = [];

    for ($i = 0; $i < strlen($duid_string); $i++) {
        $s = substr($duid_string, $i, 1);
        if ($s == '\\') {
            $n = substr($duid_string, $i + 1, 1);
            if ($n == '\\' || $n == '"') {
                $parsed_duid[] = sprintf('%02x', ord($n));
                $i += 1;
            } elseif (is_numeric($n)) {
                $parsed_duid[] = sprintf('%02x', octdec(substr($duid_string, $i + 1, 3)));
                $i += 3;
            }
        } else {
            $parsed_duid[] = sprintf('%02x', ord($s));
        }
    }

    $iaid = array_slice($parsed_duid, 0, 4);
    $duid = array_slice($parsed_duid, 4);

    return [$iaid, $duid];
}
