#!/usr/local/bin/php
<?php

/*
 * Copyright (C) 2017-2021 Franco Fichtner <franco@opnsense.org>
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

require_once("config.inc");
require_once("interfaces.inc");
require_once("util.inc");
require_once("filter.inc");
require_once("util.inc");
require_once("system.inc");

function console_prompt_for_yn($prompt_text, $default = '')
{
    global $fp;

    $prompt_yn = sprintf('[%s/%s] ', $default === 'y' ? 'Y' : 'y', $default === 'n' ? 'N' : 'n');

    while (true) {
        echo "{$prompt_text} {$prompt_yn}";
        switch (strtolower(chop(fgets($fp)))) {
            case 'y':
                return true;
            case 'n':
                return false;
            case '':
                if ($default !== '') {
                    return $default === 'y';
                }
                /* FALLTHROUGH */
            default:
                break;
        }
    }
}

function console_get_interface_from_ppp($realif)
{
    global $config;

    if (isset($config['ppps']['ppp'])) {
        foreach ($config['ppps']['ppp'] as $pppid => $ppp) {
            if ($realif == $ppp['if']) {
                $ifaces = explode(',', $ppp['ports']);
                return $ifaces[0];
            }
        }
    }

    return '';
}

function prompt_for_enable_dhcp_server($version = 4)
{
    global $config, $interface;
    if ($interface == "wan") {
        if ($config['interfaces']['lan']) {
            return false;
        }
    }
    /* only allow DHCP server to be enabled when static IP is configured on this interface */
    if ($version === 6) {
        $is_ipaddr = is_ipaddrv6($config['interfaces'][$interface]['ipaddrv6']);
    } else {
        $is_ipaddr = is_ipaddrv4($config['interfaces'][$interface]['ipaddr']);
    }
    if (!($is_ipaddr)) {
        return false;
    }

    $label_DHCP = ($version === 6) ? 'DHCP6' : 'DHCP';
    $upperifname = strtoupper($interface);

    $ret = console_prompt_for_yn(sprintf('Do you want to enable the %s server on %s?', $label_DHCP, $upperifname), 'n');
    echo "\n";
    return $ret;
}

function get_interface_config_description($iface)
{
    global $config;
    $c = $config['interfaces'][$iface];
    if (!$c) {
        return null;
    }
    $if = $c['if'];
    $result = $if;
    $result2 = array();
    $ipaddr = $c['ipaddr'];
    $ipaddrv6 = $c['ipaddrv6'];
    if (is_ipaddr($ipaddr)) {
        $result2[] = 'static';
    } elseif (!empty($ipaddr)) {
        $result2[] = $ipaddr;
    }
    if (is_ipaddr($ipaddrv6)) {
        $result2[] = 'staticv6';
    } elseif (!empty($ipaddrv6)) {
        $result2[] = $ipaddrv6;
    }
    if (count($result2)) {
        $result .= ' - ' . implode(', ', $result2);
    }
    return $result;
}

$fp = fopen('php://stdin', 'r');

/* build an interface collection */
$ifdescrs = legacy_config_get_interfaces(array('virtual' => false));
$count = count($ifdescrs);

/* grab interface that we will operate on, unless there is only one
   interface */
if ($count > 1) {
    echo "Available interfaces:\n\n";
    $x = 1;
    foreach ($ifdescrs as $iface => $ifcfg) {
        $config_descr = get_interface_config_description($iface);
        echo "{$x} - {$ifcfg['descr']} ({$config_descr})\n";
        $x++;
    }
    echo "\nEnter the number of the interface to configure: ";
    $intnum = chop(fgets($fp));
    echo "\n";
} else {
    $intnum = $count;
}

if ($intnum < 1) {
    exit;
}
if ($intnum > $count) {
    exit;
}

$index = 1;
foreach (array_keys($ifdescrs) as $ifname) {
    if ($intnum == $index) {
        $interface = $ifname;
        break;
    } else {
        $index++;
    }
}
if (!$interface) {
    echo "Invalid interface!\n";
    exit;
}

$ifaceassigned = "";

function next_unused_gateway_name($interface)
{
    global $config;

    $new_name = strtoupper($interface) . '_GW';

    if (!isset($config['gateways']['gateway_item'])) {
        return $new_name;
    }
    $count = 1;
    do {
        $existing = false;
        foreach ($config['gateways']['gateway_item'] as $item) {
            if ($item['name'] === $new_name) {
                $existing = true;
                break;
            }
        }
        if ($existing) {
            $count += 1;
            $new_name = strtoupper($interface) . '_GW_' . $count;
        }
    } while ($existing);
    return $new_name;
}

function add_gateway_to_config($interface, $gatewayip, $inet_type, $is_in_subnet)
{
    global $fp;

    $label_IPvX = $inet_type == 'inet6' ? 'IPv6' : 'IPv4';

    $a_gateways = &config_read_array('gateways', 'gateway_item');
    $is_default = true;
    $new_name = '';

    foreach ($a_gateways as &$item) {
        if ($item['ipprotocol'] === $inet_type) {
            if ($item['interface'] === $interface && $item['gateway'] === $gatewayip) {
                $new_name = $item['name'];
                unset($item);
                continue;
            }
            if (isset($item['defaultgw'])) {
                $is_default = false;
            }
        }
    }

    if (!$is_default) {
        if (console_prompt_for_yn(sprintf('Do you want to use it as the default %s gateway?', $label_IPvX), $interface == 'wan' ? 'y' : 'n')) {
            foreach ($a_gateways as &$item) {
                if ($item['ipprotocol'] === $inet_type) {
                    if (isset($item['defaultgw'])) {
                        unset($item['defaultgw']);
                    }
                }
            }

            $is_default = true;
        }

        echo "\n";
    }

    if ($is_default) {
        if (console_prompt_for_yn(sprintf('Do you want to use the gateway as the %s name server, too?', $label_IPvX), 'y')) {
            $nameserver = $gatewayip;
        } else {
            do {
                echo sprintf("Enter the %s name server or press <ENTER> for none:\n> ", $label_IPvX);
                $nameserver = chop(fgets($fp));
                $is_ipaddr = $inet_type == 'inet6' ? is_ipaddrv6($nameserver) : is_ipaddrv4($nameserver);
                if ($nameserver != '') {
                    if (!$is_ipaddr) {
                        echo sprintf('Not an %s address!', $label_IPvX) . "\n\n";
                    }
                }
            } while (!($nameserver == '' || $is_ipaddr));
        }

        echo "\n";
    }

    if ($new_name == '') {
        $new_name = next_unused_gateway_name($interface);
    }

    $item = array(
        'descr' => sprintf('Interface %s Gateway', strtoupper($interface)),
        'defaultgw' => $is_default,
        'ipprotocol' => $inet_type,
        'interface' => $interface,
        'gateway' => $gatewayip,
        'monitor_disable' => 1,
        'name' => $new_name,
        'interval' => true,
        'weight' => 1,
    );
    if (!$is_in_subnet) {
        $item['fargw'] = 1;
    }

    $a_gateways[] = $item;

    return array($new_name, $nameserver);
}

function console_configure_ip_address($version)
{
    global $config, $interface, $restart_dhcpd, $ifaceassigned, $fp;

    $label_IPvX = ($version === 6) ? 'IPv6' : 'IPv4';
    $maxbits = ($version === 6) ? 128 : 32;
    $label_DHCP = ($version === 6) ? 'DHCP6' : 'DHCP';

    $upperifname = strtoupper($interface);

    if (
        $interface != 'wan'
        && $version === 6
        && !empty($config['interfaces']['wan']['ipaddrv6'])
        && $config['interfaces']['wan']['ipaddrv6'] == 'dhcp6'
        && console_prompt_for_yn(sprintf(
            'Configure %s address %s interface via WAN tracking?',
            $label_IPvX,
            $upperifname
        ), 'y')
    ) {
        $intip = 'track6';
        $intbits = '64';
        $isintdhcp = true;
        $restart_dhcpd = true;
        echo "\n";
    } elseif (console_prompt_for_yn(sprintf('Configure %s address %s interface via %s?', $label_IPvX, $upperifname, $label_DHCP), $interface == 'wan' ? 'y' : 'n')) {
        $ifppp = console_get_interface_from_ppp(get_real_interface($interface));
        if (!empty($ifppp)) {
            $ifaceassigned = $ifppp;
        }
        $intip = ($version === 6) ? 'dhcp6' : 'dhcp';
        $intbits = '';
        $isintdhcp = true;
        $restart_dhcpd = true;
        echo "\n";
    }

    if (!$isintdhcp) {
        while (true) {
            do {
                echo "\n" . sprintf('Enter the new %s %s address. Press <ENTER> for none:', $upperifname, $label_IPvX) . "\n> ";
                $intip = chop(fgets($fp));
                $is_ipaddr = ($version === 6) ? is_ipaddrv6($intip) : is_ipaddrv4($intip);
                if ($is_ipaddr && is_ipaddr_configured($intip, $interface)) {
                    $ip_conflict = true;
                    echo "This IP address conflicts with another interface or a VIP\n";
                } else {
                    $ip_conflict = false;
                }
            } while (($ip_conflict === true) || !($is_ipaddr || $intip == ''));
            echo "\n";
            if ($intip != '') {
                echo "\nSubnet masks are entered as bit counts (like CIDR notation).\n";
                if ($version === 6) {
                    echo "e.g. ffff:ffff:ffff:ffff:ffff:ffff:ffff:ff00 = 120\n";
                    echo "     ffff:ffff:ffff:ffff:ffff:ffff:ffff:0    = 112\n";
                    echo "     ffff:ffff:ffff:ffff:ffff:ffff:0:0       =  96\n";
                    echo "     ffff:ffff:ffff:ffff:ffff:0:0:0          =  80\n";
                    echo "     ffff:ffff:ffff:ffff:0:0:0:0             =  64\n";
                } else {
                    echo "e.g. 255.255.255.0 = 24\n";
                    echo "     255.255.0.0   = 16\n";
                    echo "     255.0.0.0     = 8\n";
                }
                do {
                    $upperifname = strtoupper($interface);
                    echo "\n" . sprintf(
                        'Enter the new %s %s subnet bit count (1 to %s):',
                        $upperifname,
                        $label_IPvX,
                        $maxbits
                    ) . "\n> ";
                    $intbits = chop(fgets($fp));
                    $intbits_ok = is_numeric($intbits) && (($intbits >= 1) && ($intbits <= $maxbits));
                    $restart_dhcpd = true;

                    if ($version === 4 && $intbits < $maxbits) {
                        if ($intip == gen_subnet($intip, $intbits)) {
                            echo 'You cannot set network address to an interface';
                            continue 2;
                        } elseif ($intip == gen_subnet_max($intip, $intbits)) {
                            echo 'You cannot set broadcast address to an interface';
                            continue 2;
                        }
                    }
                } while (!$intbits_ok);
                echo "\n";

                if ($version === 6) {
                    $subnet = gen_subnetv6($intip, $intbits);
                } else {
                    $subnet = gen_subnet($intip, $intbits);
                }

                $is_in_subnet = true;

                do {
                    echo sprintf('For a WAN, enter the new %s %s upstream gateway address.', $upperifname, $label_IPvX) . "\n" .
                                'For a LAN, press <ENTER> for none:' . "\n> ";
                    $gwip = chop(fgets($fp));
                    $is_ipaddr = ($version === 6) ? is_ipaddrv6($gwip) : is_ipaddrv4($gwip);
                    $is_in_subnet = $is_ipaddr && ip_in_subnet($gwip, $subnet . "/" . $intbits);
                    if ($gwip != '') {
                        if (!$is_ipaddr) {
                            echo sprintf('Not an %s address!', $label_IPvX) . "\n\n";
                        }
                    }
                } while (!($gwip == '' || $is_ipaddr));
                echo "\n";

                if ($gwip != '') {
                    $inet_type = ($version === 6) ? "inet6" : "inet";
                    list($gwname, $nameserver) = add_gateway_to_config($interface, $gwip, $inet_type, $is_in_subnet);
                }
            }
            $ifppp = console_get_interface_from_ppp(get_real_interface($interface));
            if (!empty($ifppp)) {
                $ifaceassigned = $ifppp;
            }
            break;
        }
    }

    return array($intip, $intbits, $gwname, $nameserver);
}

list($intip,  $intbits,  $gwname, $nameserver) = console_configure_ip_address(4);
list($intip6, $intbits6, $gwname6, $nameserver6) = console_configure_ip_address(6);

if (!empty($ifaceassigned)) {
    $config['interfaces'][$interface]['if'] = $ifaceassigned;
}

$config['interfaces'][$interface]['ipaddr'] = $intip;
$config['interfaces'][$interface]['subnet'] = $intbits;
$config['interfaces'][$interface]['gateway'] = $gwname;
$config['interfaces'][$interface]['ipaddrv6'] = $intip6;
$config['interfaces'][$interface]['subnetv6'] = $intbits6;
$config['interfaces'][$interface]['gatewayv6'] = $gwname6;
$config['interfaces'][$interface]['enable'] = true;

if ($intip6 == 'track6') {
    $config['interfaces'][$interface]['track6-interface'] = 'wan';
    $config['interfaces'][$interface]['track6-prefix-id'] = '0';
} else {
    if (isset($config['interfaces'][$interface]['track6-interface'])) {
        unset($config['interfaces'][$interface]['track6-interface']);
    }
    if (isset($config['interfaces'][$interface]['track6-prefix-id'])) {
        unset($config['interfaces'][$interface]['track6-prefix-id']);
    }
}

$nameservers = array();
if (!empty($nameserver)) {
    $nameservers[] = $nameserver;
}
if (!empty($nameserver6)) {
    $nameservers[] = $nameserverv6;
}
if (count($nameservers)) {
    $config['system']['dnsserver'] = $nameservers;
    for ($dnscounter = 1; $dnscounter < 9; $dnscounter++) {
        $dnsgwname = "dns{$dnscounter}gw";
        if (isset($config['system'][$dnsgwname])) {
            unset($config['system'][$dnsgwname]);
        }
    }
}

function console_configure_dhcpd($version = 4)
{
    global $config, $restart_dhcpd, $fp, $interface, $intip, $intbits, $intip6, $intbits6;

    $label_IPvX = ($version === 6) ? "IPv6"    : "IPv4";
    $dhcpd      = ($version === 6) ? "dhcpdv6" : "dhcpd";

    if (prompt_for_enable_dhcp_server($version)) {
        $subnet_start = ($version === 6) ? gen_subnetv6($intip6, $intbits6) : gen_subnet($intip, $intbits);
        $subnet_end = ($version === 6) ? gen_subnetv6_max($intip6, $intbits6) : gen_subnet_max($intip, $intbits);
        do {
            do {
                echo sprintf('Enter the start address of the %s client address range:', $label_IPvX) . " ";
                $dhcpstartip = chop(fgets($fp));
                if ($dhcpstartip === "") {
                    fclose($fp);
                    exit(0);
                }
                $is_ipaddr = ($version === 6) ? is_ipaddrv6($dhcpstartip) : is_ipaddrv4($dhcpstartip);
                $is_inrange = is_inrange($dhcpstartip, $subnet_start, $subnet_end);
                if (!$is_inrange) {
                    echo "This IP address must be in the interface's subnet\n";
                }
            } while (!$is_ipaddr || !$is_inrange);

            do {
                echo sprintf('Enter the end address of the %s client address range:', $label_IPvX) . " ";
                $dhcpendip = chop(fgets($fp));
                if ($dhcpendip === "") {
                    fclose($fp);
                    exit(0);
                }
                $is_ipaddr = ($version === 6) ? is_ipaddrv6($dhcpendip) : is_ipaddrv4($dhcpendip);
                $is_inrange = is_inrange($dhcpendip, $subnet_start, $subnet_end);
                if (!$is_inrange) {
                    echo "This IP address must be in the interface's subnet\n";
                }
                $not_inorder = ($version === 6) ? (inet_pton($dhcpendip) < inet_pton($dhcpstartip)) : ip_less_than($dhcpendip, $dhcpstartip);
                if ($not_inorder) {
                    echo "The end address of the DHCP range must be >= the start address\n";
                }
            } while (!$is_ipaddr || !$is_inrange);
        } while ($not_inorder);
        $restart_dhcpd = true;
        $config[$dhcpd][$interface]['enable'] = true;
        $config[$dhcpd][$interface]['range']['from'] = $dhcpstartip;
        $config[$dhcpd][$interface]['range']['to'] = $dhcpendip;
        echo "\n";
    } else {
        if (isset($config[$dhcpd][$interface]['enable'])) {
            unset($config[$dhcpd][$interface]['enable']);
            $restart_dhcpd = true;
        }
    }
}

console_configure_dhcpd(4);
console_configure_dhcpd(6);

if ($config['system']['webgui']['protocol'] == 'https') {
    if (console_prompt_for_yn('Do you want to revert to HTTP as the web GUI protocol?', 'n')) {
        $config['system']['webgui']['protocol'] = 'http';
        $restart_webgui = true;
    } elseif (console_prompt_for_yn('Do you want to generate a new self-signed web GUI certificate?', 'n')) {
        unset($config['system']['webgui']['ssl-certref']);
        $restart_webgui = true;
    }
}

if (console_prompt_for_yn('Restore web GUI access defaults?', 'n')) {
    if (isset($config['system']['webgui']['noantilockout'])) {
        unset($config['system']['webgui']['noantilockout']);
        $restart_webgui = true;
    }
    if (isset($config['system']['webgui']['interfaces'])) {
        unset($config['system']['webgui']['interfaces']);
        $restart_webgui = true;
    }
    if (isset($config['system']['webgui']['ssl-ciphers'])) {
        unset($config['system']['webgui']['ssl-ciphers']);
        $restart_webgui = true;
    }
}

if ($config['interfaces']['lan']) {
    if ($config['dhcpd']) {
        if ($config['dhcpd']['wan']) {
            unset($config['dhcpd']['wan']);
        }
    }
    if ($config['dhcpdv6']) {
        if ($config['dhcpdv6']['wan']) {
            unset($config['dhcpdv6']['wan']);
        }
    }
}

if (empty($config['interfaces']['lan'])) {
    unset($config['interfaces']['lan']);
    if (isset($config['dhcpd']['lan'])) {
        unset($config['dhcpd']['lan']);
    }
    if (isset($config['dhcpdv6']['lan'])) {
        unset($config['dhcpdv6']['lan']);
    }
    unset($config['nat']);
    system("rm /var/dhcpd/var/db/* >/dev/null 2>/dev/null");
    $restart_dhcpd = true;
}

echo "\nWriting configuration...";
flush();
write_config(sprintf('%s configuration from console menu', $interface));
echo "done.\n";

system_hosts_generate(true);
system_resolvconf_generate(true);
interface_bring_down($interface);
interface_configure(true, $interface, true);
plugins_configure('monitor', true);
filter_configure_sync(true);

if ($restart_dhcpd) {
    plugins_configure('dhcp', true);
}

if ($restart_webgui) {
    plugins_configure('webgui', true);
}

echo "\n";

if ($intip != '' || $intip6 != '') {
    if (count($ifdescrs) == '1' or $interface == 'lan') {
        $intip = get_interface_ip($interface);
        $intip6 = get_interface_ipv6($interface);
        echo "You can now access the web GUI by opening\nthe following URL in your web browser:\n\n";
        $webuiport = !empty($config['system']['webgui']['port']) ? ":{$config['system']['webgui']['port']}" : '';
        if (is_ipaddr($intip)) {
            echo "    {$config['system']['webgui']['protocol']}://{$intip}{$webuiport}\n";
        }
        if (is_ipaddr($intip6)) {
            echo "    {$config['system']['webgui']['protocol']}://[{$intip6}]{$webuiport}\n";
        }
    }
}

/* rest now or hit CTRL-C */
sleep(3);
