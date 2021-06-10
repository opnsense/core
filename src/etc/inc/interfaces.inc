<?php

/*
 * Copyright (C) 2015-2021 Franco Fichtner <franco@opnsense.org>
 * Copyright (C) 2004-2008 Scott Ullrich <sullrich@gmail.com>
 * Copyright (C) 2008-2009 Ermal Luçi
 * Copyright (C) 2005 Espen Johansen
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

require_once("interfaces.lib.inc");

/*
 * converts a string like "a,b,c,d"
 * into an array like array("a" => "b", "c" => "d")
 */
function explode_assoc($delimiter, $string)
{
    $array = explode($delimiter, $string);
    $result = array();
    $numkeys = floor(count($array) / 2);
    for ($i = 0; $i < $numkeys; $i += 1) {
        $result[$array[$i * 2]] = $array[$i * 2 + 1];
    }
    return $result;
}


function return_hex_ipv4($ipv4)
{
    if (!is_ipaddrv4($ipv4)) {
        return false;
    }

    /* we need the hex form of the interface IPv4 address */
    $ip4arr = explode(".", $ipv4);
    return (sprintf("%02x%02x%02x%02x", $ip4arr[0], $ip4arr[1], $ip4arr[2], $ip4arr[3]));
}

function convert_ipv6_to_128bit($ipv6)
{
    if (!is_ipaddrv6($ipv6)) {
        return false;
    }

    $ip6prefix = Net_IPv6::uncompress($ipv6);
    $ip6arr = explode(":", $ip6prefix);
    /* binary presentation of the prefix for all 128 bits. */
    $ip6prefixbin = "";
    foreach ($ip6arr as $element) {
        $ip6prefixbin .= sprintf("%016b", hexdec($element));
    }
    return $ip6prefixbin;
}

function convert_128bit_to_ipv6($ip6bin)
{
    if (strlen($ip6bin) != 128) {
        return false;
    }

    $ip6arr = array();
    $ip6binarr = str_split($ip6bin, 16);
    foreach ($ip6binarr as $binpart) {
        $ip6arr[] = dechex(bindec($binpart));
    }
    $ip6addr = Net_IPv6::compress(implode(":", $ip6arr));

    return $ip6addr;
}

function match_wireless_interface($int)
{
    $wireless_prefix = [
        'an',
        'ath',
        'bwi',
        'bwn',
        'ipw',
        'iwi',
        'iwm',
        'iwn',
        'malo',
        'mwl',
        'ndis',
        'otus',
        'ral',
        'rsu',
        'rtwn',
        'rum',
        'run',
        'uath',
        'upgt',
        'ural',
        'urtw',
        'wi',
        'wlan',
        'wpi',
        'zyd',
    ];

    return preg_match('/^(' . implode('|', $wireless_prefix) . ')/', $int);
}

function interfaces_bring_up($interface)
{
    if (!$interface) {
        log_error('interfaces_bring_up() was called but no variable defined.');
        log_error('Backtrace: ' . debug_backtrace());
        return;
    }

    legacy_interface_flags($interface, 'up');
}

function does_interface_exist($interface)
{
    $ints = legacy_interface_listget();
    if (empty($interface) || $ints == null || !in_array($interface, $ints)) {
        return false;
    } else {
        return true;
    }
}

function interfaces_loopback_configure($verbose = false)
{
    if ($verbose) {
        echo 'Configuring loopback interface...';
        flush();
    }

    legacy_interface_setaddress('lo0', '127.0.0.1');
    legacy_interface_flags('lo0', 'up');

    if ($verbose) {
        echo "done.\n";
    }
}

function interfaces_vlan_priorities()
{
    $priorities = array();

    $priorities['1'] = gettext('Background (1, lowest)');
    $priorities['0'] = gettext('Best Effort (0, default)');
    $priorities['2'] = gettext('Excellent Effort (2)');
    $priorities['3'] = gettext('Critical Applications (3)');
    $priorities['4'] = gettext('Video (4)');
    $priorities['5'] = gettext('Voice (5)');
    $priorities['6'] = gettext('Internetwork Control (6)');
    $priorities['7'] = gettext('Network Control (7, highest)');

    return $priorities;
}

function interfaces_vlan_configure($verbose = false, $realif = '')
{
    global $config;

    if (!empty($realif) && !strstr($realif, '_vlan')) {
        return;
    }

    if (!isset($config['vlans']['vlan'])) {
        return;
    }

    if ($verbose) {
        echo 'Configuring VLAN interfaces...';
        flush();
    }
    $ints = legacy_interface_listget();
    foreach ($config['vlans']['vlan'] as $vlan) {
        if (empty($vlan['vlanif'])) {
            $vlan['vlanif'] = "{$vlan['if']}_vlan{$vlan['tag']}";
        }

        if (!empty($realif) && $realif != $vlan['vlanif']) {
            continue;
        }

        interface_vlan_configure($vlan, $ints);
    }

    if ($verbose) {
        echo "done.\n";
    }
}

function interface_vlan_configure(&$vlan, $intfs = null)
{
    if (!is_array($vlan)) {
        log_error('VLAN: called with wrong options. Problems with config!');
        return;
    }
    $if = $vlan['if'];
    $vlanif = empty($vlan['vlanif']) ? "{$if}_vlan{$vlan['tag']}" : $vlan['vlanif'];

    if (empty($if)) {
        log_error('interface_vlan_configure called with if undefined.');
        return;
    }

    interfaces_bring_up($if);

    $ints = empty($intfs) ? legacy_interface_listget() : $intfs;
    if (!empty($vlanif) && in_array($vlanif, $ints)) {
        legacy_interface_destroy($vlanif);
    }

    legacy_interface_create('vlan', $vlanif);
    $pcp = isset($vlan['pcp']) ? $vlan['pcp'] : 0;
    legacy_vlan_tag($vlanif, $if, $vlan['tag'], $pcp);

    interfaces_bring_up($vlanif);

    return $vlanif;
}

function interfaces_test_wireless_capability($if, $cap)
{
    $caps = array('hostap' => 'HOSTAP', 'adhoc' => 'IBSS');

    if (!isset($caps[$cap])) {
        return false;
    }

    exec(sprintf('/sbin/ifconfig %s list caps', escapeshellarg($if)), $lines);

    foreach ($lines as $line) {
        if (preg_match("/^drivercaps=.*<.*{$caps[$cap]}.*>$/", $line)) {
            return true;
        }
    }

    return false;
}

function interfaces_create_wireless_clones($verbose = false)
{
    global $config;

    if ($verbose) {
        echo 'Creating wireless clone interfaces...';
        flush();
    }

    foreach (array_keys(get_configured_interface_with_descr()) as $if) {
        $realif = $config['interfaces'][$if]['if'];
        if (is_interface_wireless($realif)) {
            interface_wireless_clone(interface_get_wireless_clone($realif), $config['interfaces'][$if]);
        }
    }

    if (isset($config['wireless']['clone'])) {
        foreach ($config['wireless']['clone'] as $clone) {
            if (empty($clone['cloneif'])) {
                continue;
            }
            if (does_interface_exist($clone['cloneif'])) {
                continue;
            }
            interface_wireless_clone($clone['cloneif'], $clone);
        }
    }

    if ($verbose) {
        echo "done.\n";
    }
}

function interfaces_bridge_configure($verbose = false, $checkmember = 0, $realif = '')
{
    global $config;

    if (!empty($realif) && substr($realif, 0, 6) != 'bridge') {
        return;
    }

    if (!isset($config['bridges']['bridged'])) {
        return;
    }

    if ($verbose) {
        echo "Configuring bridge interfaces ($checkmember)...";
        flush();
    }

    foreach ($config['bridges']['bridged'] as $i => $bridge) {
        if (empty($bridge['bridgeif'])) {
            $bridge['bridgeif'] = "bridge{$i}";
        }

        if (!empty($realif) && $realif != $bridge['bridgeif']) {
            continue;
        }

        interface_bridge_configure($bridge, $checkmember);
    }

    if ($verbose) {
        echo "done.\n";
    }
}

function interface_bridge_configure(&$bridge, $checkmember = 0)
{
    if (!is_array($bridge)) {
        return;
    }

    if (empty($bridge['members'])) {
        log_error("No members found on {$bridge['bridgeif']}");
        return;
    }

    $members = explode(',', $bridge['members']);
    if (!count($members)) {
        return;
    }

    /* Calculate smaller mtu and enforce it */
    $mtu = null;
    $foundgif = false;
    foreach ($members as $member) {
        $realif = get_real_interface($member);
        $opts = legacy_interface_stats($realif);
        if (substr($realif, 0, 3) == "gif") {
            $foundgif = true;
            if ($checkmember == 1) {
                return;
            }
            if ($opts['mtu'] <= 1500) {
                continue;
            }
        }
        if (!empty($opts['mtu']) && ($mtu == null || $opts['mtu'] < $mtu)) {
            $mtu = $opts['mtu'];
        }
    }

    if (!$foundgif && $checkmember == 2) {
        return;
    }

    if (file_exists("/var/run/booting") || !empty($bridge['bridgeif'])) {
        legacy_interface_destroy($bridge['bridgeif']);
        legacy_interface_create($bridge['bridgeif']);
        $bridgeif = $bridge['bridgeif'];
    } else {
        $bridgeif = legacy_interface_create('bridge');
        $bridge['bridgeif'] = $bridgeif;
    }

    mwexecf('/sbin/ifconfig %s inet6 %sauto_linklocal', array($bridgeif, isset($bridge['linklocal']) ? '' : '-'));

    $checklist = get_configured_interface_with_descr();

    /* Add interfaces to bridge */
    foreach ($members as $member) {
        if (empty($checklist[$member])) {
            continue;
        }

        $realif = get_real_interface($member);
        if (!does_interface_exist($realif)) {
            log_error('realif not defined in interfaces bridge - up');
            continue;
        }

        /* make sure the parent interface is up */
        legacy_interface_mtu($realif, $mtu);
        configure_interface_hardware($realif);
        interfaces_bring_up($realif);
        legacy_bridge_member($bridge['bridgeif'], $realif);
    }

    if (isset($bridge['enablestp'])) {
        /* Choose spanning tree proto */
        mwexec("/sbin/ifconfig {$bridgeif} proto " . escapeshellarg($bridge['proto']));

        if (!empty($bridge['stp'])) {
            $stpifs = explode(',', $bridge['stp']);
            foreach ($stpifs as $stpif) {
                $realif = get_real_interface($stpif);
                mwexec("/sbin/ifconfig {$bridgeif} stp {$realif}");
            }
        }
        if (!empty($bridge['maxage'])) {
            mwexec("/sbin/ifconfig {$bridgeif} maxage " . escapeshellarg($bridge['maxage']));
        }
        if (!empty($bridge['fwdelay'])) {
            mwexec("/sbin/ifconfig {$bridgeif} fwddelay " . escapeshellarg($bridge['fwdelay']));
        }
        if (!empty($bridge['hellotime'])) {
            mwexec("/sbin/ifconfig {$bridgeif} hellotime " . escapeshellarg($bridge['hellotime']));
        }
        if (!empty($bridge['priority'])) {
            mwexec("/sbin/ifconfig {$bridgeif} priority " . escapeshellarg($bridge['priority']));
        }
        if (!empty($bridge['holdcnt'])) {
            mwexec("/sbin/ifconfig {$bridgeif} holdcnt " . escapeshellarg($bridge['holdcnt']));
        }
        if (!empty($bridge['ifpriority'])) {
            $pconfig = explode(",", $bridge['ifpriority']);
            $ifpriority = array();
            foreach ($pconfig as $cfg) {
                $embcfg = explode_assoc(":", $cfg);
                foreach ($embcfg as $key => $value) {
                    $ifpriority[$key] = $value;
                }
            }
            foreach ($ifpriority as $key => $value) {
                $realif = get_real_interface($key);
                mwexec("/sbin/ifconfig ${bridgeif} ifpriority {$realif} " . escapeshellarg($value));
            }
        }
        if (!empty($bridge['ifpathcost'])) {
            $pconfig = explode(",", $bridge['ifpathcost']);
            $ifpathcost = array();
            foreach ($pconfig as $cfg) {
                $embcfg = explode_assoc(":", $cfg);
                foreach ($embcfg as $key => $value) {
                    $ifpathcost[$key] = $value;
                }
            }
            foreach ($ifpathcost as $key => $value) {
                $realif = get_real_interface($key);
                mwexec("/sbin/ifconfig ${bridgeif} ifpathcost {$realif} " . escapeshellarg($value));
            }
        }
    }

    if ($bridge['maxaddr'] != '') {
        mwexec("/sbin/ifconfig {$bridgeif} maxaddr " . escapeshellarg($bridge['maxaddr']));
    }
    if ($bridge['timeout'] != '') {
        mwexec("/sbin/ifconfig {$bridgeif} timeout " . escapeshellarg($bridge['timeout']));
    }
    if (!empty($bridge['span'])) {
        $realif = get_real_interface($bridge['span']);
        mwexec("/sbin/ifconfig {$bridgeif} span {$realif}");
    }
    if (!empty($bridge['edge'])) {
        $edgeifs = explode(',', $bridge['edge']);
        foreach ($edgeifs as $edgeif) {
            $realif = get_real_interface($edgeif);
            mwexec("/sbin/ifconfig {$bridgeif} edge {$realif}");
        }
    }
    if (!empty($bridge['autoedge'])) {
        $edgeifs = explode(',', $bridge['autoedge']);
        foreach ($edgeifs as $edgeif) {
            $realif = get_real_interface($edgeif);
            mwexec("/sbin/ifconfig {$bridgeif} -autoedge {$realif}");
        }
    }
    if (!empty($bridge['ptp'])) {
        $ptpifs = explode(',', $bridge['ptp']);
        foreach ($ptpifs as $ptpif) {
            $realif = get_real_interface($ptpif);
            mwexec("/sbin/ifconfig {$bridgeif} ptp {$realif}");
        }
    }
    if (!empty($bridge['autoptp'])) {
        $ptpifs = explode(',', $bridge['autoptp']);
        foreach ($ptpifs as $ptpif) {
            $realif = get_real_interface($ptpif);
            mwexec("/sbin/ifconfig {$bridgeif} -autoptp {$realif}");
        }
    }
    if (!empty($bridge['static'])) {
        $stickyifs = explode(',', $bridge['static']);
        foreach ($stickyifs as $stickyif) {
            $realif = get_real_interface($stickyif);
            mwexec("/sbin/ifconfig {$bridgeif} sticky {$realif}");
        }
    }
    if (!empty($bridge['private'])) {
        $privateifs = explode(',', $bridge['private']);
        foreach ($privateifs as $privateif) {
            $realif = get_real_interface($privateif);
            mwexec("/sbin/ifconfig {$bridgeif} private {$realif}");
        }
    }

    if ($bridge['bridgeif']) {
        interfaces_bring_up($bridge['bridgeif']);
    } else {
        log_error('bridgeif not defined -- could not bring interface up');
    }
}

function interface_bridge_add_member($bridgeif, $interface)
{
    if (!does_interface_exist($bridgeif) || !does_interface_exist($interface)) {
        return;
    }

    $mtu = legacy_interface_stats($bridgeif)['mtu'];
    $mtum = legacy_interface_stats($interface)['mtu'];

    if ($mtu != $mtum && !(substr($interface, 0, 3) == "gif" && $mtu <= 1500)) {
        legacy_interface_mtu($interface, $mtu);
    }

    configure_interface_hardware($interface);
    interfaces_bring_up($interface);
    legacy_bridge_member($bridgeif, $interface);
}

function interfaces_lagg_configure($verbose = false, $realif = '')
{
    global $config;

    if (!empty($realif) && substr($realif, 0, 4) != 'lagg') {
        return;
    }

    if (!isset($config['laggs']['lagg'])) {
        return;
    }

    if ($verbose) {
        echo 'Configuring LAGG interfaces...';
        flush();
    }

    foreach ($config['laggs']['lagg'] as $i => $lagg) {
        if (empty($lagg['laggif'])) {
            $lagg['laggif'] = "lagg{$i}";
        }

        if (!empty($realif) && $realif != $lagg['laggif']) {
            continue;
        }

        interface_lagg_configure($lagg);
    }

    if ($verbose) {
        echo "done.\n";
    }
}

function interface_lagg_configure(&$lagg)
{
    if (!is_array($lagg)) {
        return -1;
    }

    $members = explode(',', $lagg['members']);
    if (!count($members)) {
        return -1;
    }

    $interface_stats = legacy_interfaces_details();

    if (!empty($lagg['laggif'])) {
        $laggif = $lagg['laggif'];
        if (empty($interface_stats[$laggif])) {
            legacy_interface_create($lagg['laggif']);
        } else {
            // Already configured, detach child interfaces before attempting to configure.
            // Prevents vlans to loose parent.
            if (!empty($interface_stats[$laggif]['laggport'])) {
                foreach ($interface_stats[$laggif]['laggport'] as $laggport) {
                    mwexec("/sbin/ifconfig {$laggif} -laggport {$laggport}");
                }
            }
        }
    } else {
        $laggif = legacy_interface_create('lagg');
    }

    // determine mtu value to use, either the provided one for the lagg or smallest of its children
    $mtu = null;
    if (!empty($lagg['mtu'])) {
        // mtu provided for lagg
        $mtu = $lagg['mtu'];
    } else {
        // min() mtu of children
        foreach ($members as $member) {
            if (!empty($interface_stats[$member]['mtu']) && ($mtu == null || $interface_stats[$member]['mtu'] < $mtu)) {
                $mtu = $interface_stats[$member]['mtu'];
            }
        }
    }

    foreach ($members as $member) {
        if (!empty($interface_stats[$member])) {
            legacy_interface_mtu($member, $mtu);
            configure_interface_hardware($member);
            interfaces_bring_up($member);
            mwexec("/sbin/ifconfig {$laggif} laggport {$member}");
        }
    }

    mwexec("/sbin/ifconfig {$laggif} laggproto " . escapeshellarg($lagg['proto']));
    if (!empty($lagg['lacp_fast_timeout'])) {
        mwexec("/sbin/ifconfig {$laggif} lacp_fast_timeout");
    } elseif ($lagg['proto'] == 'lacp') {
        mwexec("/sbin/ifconfig {$laggif} -lacp_fast_timeout");
    }

    interfaces_bring_up($laggif);

    return $laggif;
}

function interfaces_gre_configure($verbose = false, $checkparent = 0, $realif = '')
{
    global $config;

    if (!empty($realif) && substr($realif, 0, 3) != 'gre') {
        return;
    }

    if (!isset($config['gres']['gre'])) {
        return;
    }

    if ($verbose) {
        echo "Configuring GRE interfaces ($checkparent)...";
        flush();
    }

    foreach ($config['gres']['gre'] as $i => $gre) {
        if (empty($gre['greif'])) {
            $gre['greif'] = "gre{$i}";
        }

        if (!empty($realif) && $realif != $gre['greif']) {
            continue;
        }

        if ($checkparent == 1 && strstr($gre['if'], '_vip')) {
            continue;
        } elseif ($checkparent == 2 && !strstr($gre['if'], '_vip')) {
            continue;
        }

        interface_gre_configure($gre);
    }

    if ($verbose) {
        echo "done.\n";
    }
}

/* NOTE: $grekey is not used but useful for passing this function to array_walk. */
function interface_gre_configure(&$gre, $grekey = "")
{
    if (!is_array($gre)) {
        return -1;
    }

    $realif = get_real_interface($gre['if']);

    interfaces_bring_up($realif);

    if (file_exists("/var/run/booting") || !empty($gre['greif'])) {
        legacy_interface_destroy($gre['greif']);
        legacy_interface_create($gre['greif']);
        $greif = $gre['greif'];
    } else {
        $greif = legacy_interface_create('gre');
    }

    /* Do not change the order here for more see gre(4) NOTES section. */
    if (is_ipaddrv6($gre['remote-addr'])) {
        $realifip = is_ipaddr($gre['if']) ? $gre['if'] : get_interface_ipv6($gre['if']);
        mwexecf('/sbin/ifconfig %s inet6 tunnel %s %s', array($greif, $realifip, $gre['remote-addr']));
    } else {
        $realifip = is_ipaddr($gre['if']) ? $gre['if'] : get_interface_ip($gre['if']);
        mwexecf('/sbin/ifconfig %s tunnel %s %s', array($greif, $realifip, $gre['remote-addr']));
    }
    if ((is_ipaddrv6($gre['tunnel-local-addr'])) || (is_ipaddrv6($gre['tunnel-remote-addr']))) {
        mwexec("/sbin/ifconfig {$greif} inet6 " . escapeshellarg($gre['tunnel-local-addr']) . " " . escapeshellarg($gre['tunnel-remote-addr']) . " prefixlen 128");
    } else {
        mwexec("/sbin/ifconfig {$greif} " . escapeshellarg($gre['tunnel-local-addr']) . " " . escapeshellarg($gre['tunnel-remote-addr']) . " netmask " . gen_subnet_mask($gre['tunnel-remote-net']));
    }

    if (isset($gre['link0'])) {
        legacy_interface_flags($greif, 'link0');
    }
    if (isset($gre['link1'])) {
        legacy_interface_flags($greif, 'link1');
    }
    if (isset($gre['link2'])) {
        legacy_interface_flags($greif, 'link2');
    }

    if (isset($gre['link1']) && $gre['link1']) {
        mwexecf(
            '/sbin/route add %s/%s %s',
            array(
                $gre['tunnel-remote-addr'],
                $gre['tunnel-remote-net'],
                $gre['tunnel-local-addr']
            )
        );
    }

    interfaces_bring_up($greif);

    if (is_ipaddrv4($gre['tunnel-remote-addr'])) {
        file_put_contents("/tmp/{$greif}_router", $gre['tunnel-remote-addr']);
    }
    if (is_ipaddrv6($gre['tunnel-remote-addr'])) {
        file_put_contents("/tmp/{$greif}_routerv6", $gre['tunnel-remote-addr']);
    }

    $gateways = new \OPNsense\Routing\Gateways(legacy_interfaces_details());

    foreach (array_keys(get_configured_interface_with_descr()) as $ifname) {
        if ($config['interfaces'][$ifname]['if'] == $greif) {
            if ($gateways->getInterfaceGateway($ifname, 'inet') || $gateways->getInterfaceGateway($ifname, 'inet6')) {
                system_routing_configure(false, $ifname);
                break;
            }
        }
    }

    return $greif;
}

function interfaces_gif_configure($verbose = false, $checkparent = 0, $realif = '')
{
    global $config;

    if (!empty($realif) && substr($realif, 0, 3) != 'gif') {
        return;
    }

    if (!isset($config['gifs']['gif'])) {
        return;
    }

    if ($verbose) {
        echo "Configuring GIF interfaces ($checkparent)...";
        flush();
    }

    foreach ($config['gifs']['gif'] as $i => $gif) {
        if (empty($gif['gifif'])) {
            $gif['gifif'] = "gif{$i}";
        }

        if (!empty($realif) && $realif != $gif['gifif']) {
            continue;
        }

        if ($checkparent == 1 && strstr($gif['if'], '_vip')) {
            continue;
        } elseif ($checkparent == 2 && !strstr($gif['if'], '_vip')) {
            continue;
        }

        interface_gif_configure($gif);
    }

    if ($verbose) {
        echo "done.\n";
    }
}

/* NOTE: $gifkey is not used but useful for passing this function to array_walk. */
function interface_gif_configure(&$gif, $gifkey = "")
{
    global $config;

    if (!is_array($gif)) {
        return -1;
    }

    $realif = get_real_interface($gif['if']);
    $ipaddr = $gif['ipaddr'];

    $gateways = new \OPNsense\Routing\Gateways(legacy_interfaces_details());
    if (is_ipaddrv4($gif['remote-addr'])) {
        if (is_ipaddrv4($ipaddr)) {
            $realifip = $ipaddr;
        } else {
            $realifip = get_interface_ip($gif['if']);
        }
        $realifgw = $gateways->getInterfaceGateway($gif['if'], "inet");
    } elseif (is_ipaddrv6($gif['remote-addr'])) {
        if (is_ipaddrv6($ipaddr)) {
            $realifip = $ipaddr;
        } else {
            $realifip = get_interface_ipv6($gif['if']);
        }
        $realifgw = $gateways->getInterfaceGateway($gif['if'], "inet6");
    }

    interfaces_bring_up($realif);

    if (file_exists("/var/run/booting") || !empty($gif['gifif'])) {
        legacy_interface_destroy($gif['gifif']);
        legacy_interface_create($gif['gifif']);
        $gifif = $gif['gifif'];
    } else {
        $gifif = legacy_interface_create('gif');
    }

    /* Do not change the order here for more see gif(4) NOTES section. */
    if (is_ipaddrv6($gif['remote-addr'])) {
        mwexecf('/sbin/ifconfig %s inet6 tunnel %s %s', array($gifif, $realifip, $gif['remote-addr']));
    } else {
        mwexecf('/sbin/ifconfig %s tunnel %s %s', array($gifif, $realifip, $gif['remote-addr']));
    }
    if ((is_ipaddrv6($gif['tunnel-local-addr'])) || (is_ipaddrv6($gif['tunnel-remote-addr']))) {
        mwexec("/sbin/ifconfig {$gifif} inet6 " . escapeshellarg($gif['tunnel-local-addr']) . " " . escapeshellarg($gif['tunnel-remote-addr']) . " prefixlen 128");
    } else {
        mwexec("/sbin/ifconfig {$gifif} " . escapeshellarg($gif['tunnel-local-addr']) . " " . escapeshellarg($gif['tunnel-remote-addr']) . " netmask " . gen_subnet_mask($gif['tunnel-remote-net']));
    }

    if (isset($gif['link0'])) {
        legacy_interface_flags($gifif, 'link0');
    }
    if (isset($gif['link1'])) {
        legacy_interface_flags($gifif, 'link1');
    }

    system_host_route($gif['remote-addr'], $realifgw);

    interfaces_bring_up($gifif);

    if (is_ipaddrv4($gif['tunnel-remote-addr'])) {
        file_put_contents("/tmp/{$gifif}_router", $gif['tunnel-remote-addr']);
    }
    if (is_ipaddrv6($gif['tunnel-remote-addr'])) {
        file_put_contents("/tmp/{$gifif}_routerv6", $gif['tunnel-remote-addr']);
    }

    foreach (array_keys(get_configured_interface_with_descr()) as $ifname) {
        if ($config['interfaces'][$ifname]['if'] == $gifif) {
            if ($gateways->getInterfaceGateway($ifname, 'inet') || $gateways->getInterfaceGateway($ifname, 'inet6')) {
                system_routing_configure(false, $ifname);
                break;
            }
        }
    }

    return $gifif;
}

function interfaces_configure($verbose = false)
{
    interfaces_loopback_configure($verbose);
    interfaces_create_wireless_clones($verbose);
    interfaces_lagg_configure($verbose);
    interfaces_vlan_configure($verbose);

    /* XXX: temporary plugin spot for device creation (independent of other devices) */
    plugins_configure('loopback_prepare', $verbose);
    plugins_configure('openvpn_prepare', $verbose);

    /*
     * Queues are set up to order interfaces according to their
     * dependencies / requirements of devices or other interfaces.
     * Some queues may overlap, but they are laid out in full to
     * make sure that the configuration flow is as clean as possible.
     * See individual notes in the queued handling below.
     */

    $special = [];
    $track6 = [];
    $bridge = [];
    $dhcp6c = [];

    foreach (legacy_config_get_interfaces(['enable' => true, 'virtual' => false]) as $if => $ifcfg) {
        if (strstr($ifcfg['if'], 'gre') || strstr($ifcfg['if'], 'gif') || strstr($ifcfg['if'], 'ovpn')) {
            /* special interfaces on top of newly generated devices */
            $special[] = $if;
            continue;
        } elseif (strstr($ifcfg['if'], 'vxlan')) {
            // XXX device configuration is responsible for interface setup too when trying to init all.
            continue;
        }

        $is_track6 = !empty($ifcfg['ipaddrv6']) && $ifcfg['ipaddrv6'] == 'track6';
        if (!strstr($ifcfg['if'], 'bridge') && $is_track6) {
            /* trackers without bridges are next */
            $track6[] = $if;
            continue;
        }

        $is_dhcp6c = !empty($ifcfg['ipaddrv6']) && ($ifcfg['ipaddrv6'] == 'dhcp6' || $ifcfg['ipaddrv6'] == 'slaac');
        if (strstr($ifcfg['if'], 'bridge') && !$is_dhcp6c) {
            /* bridges that may be trackers, but not dhcp6c interfaces */
            $bridge[] = $if;
            continue;
        } elseif ($is_dhcp6c) {
            /* dhcp6c interfaces load last */
            $dhcp6c[] = $if;
            continue;
        }

        /* everything else requires no queued handling and is available immediately */
        interface_configure($verbose, $if);
    }

    /*
     * NOTE: The following function parameter consists of
     *  1 - Do not load gre/gif with parent as vip
     *  2 - Do load gre/gif with parent as vip
     *  (bridge can't vip, but needs special gif handling)
     */

    interfaces_gre_configure($verbose, 1);
    interfaces_gif_configure($verbose, 1);
    interfaces_bridge_configure($verbose, 1);

    /*
     * XXX Only unspecified VIP init call can probably be removed now
     * which would also avoid having to use the 0, 1, 2 options in GRE,
     * GIF and bride device setup.
     */
    interfaces_vips_configure($verbose);

    interfaces_gre_configure($verbose, 2);
    interfaces_gif_configure($verbose, 2);
    interfaces_bridge_configure($verbose, 2);

    foreach ([$special, $track6, $bridge, $dhcp6c] as $list) {
        foreach ($list as $if) {
            interface_configure($verbose, $if);
        }
    }

    /* XXX temporary plugin spot for device creation, see plugins_devices() */
    plugins_configure('ipsec_prepare', $verbose);
    plugins_configure('vxlan_prepare', $verbose);
}

function interface_vip_bring_down($vip)
{
    $vipif = get_real_interface($vip['interface']);
    switch ($vip['mode']) {
        case 'proxyarp':
            killbypid("/var/run/choparp_{$vipif}.pid");
            break;
        case 'ipalias':
        case 'carp':
            if (does_interface_exist($vipif)) {
                legacy_interface_deladdress($vipif, $vip['subnet'], is_ipaddrv6($vip['subnet']) ? 6 : 4);
            }
            break;
        default:
            break;
    }
}

function interface_bring_down($interface = "wan", $ifacecfg = false)
{
    global $config;

    if (!isset($config['interfaces'][$interface]) || ($ifacecfg !== false && !is_array($ifacecfg))) {
        return;
    }

    if ($ifacecfg === false) {
        $realif = get_real_interface($interface);
        $realifv6 = get_real_interface($interface, "inet6");
        $ifcfg = $config['interfaces'][$interface];
        $ppps = isset($config['ppps']['ppp']) ? $config['ppps']['ppp'] : array();
    } else {
        $ifcfg = $ifacecfg['ifcfg'];
        $ppps = $ifacecfg['ppps'];
        /* When $ifacecfg is passed, it should contain the original interfaces */
        $realif = $ifacecfg['ifcfg']['realif'];
        $realifv6 = $ifacecfg['ifcfg']['realifv6'];
    }

    $ifcfg['ipaddrv6'] = empty($ifcfg['ipaddrv6']) ? null : $ifcfg['ipaddrv6'];

    if (!in_array($interface, array('openvpn', 'ppp', 'ipsec', 'enc0'))) {
        /**
         * NOTE: The $realifv6 is needed when WANv4 is type PPP and v6 is DHCP and the option v6 from v4 is used.
         * XXX: we kept this in place due to dropping the $realv6iface parameter from get_real_interface(),
        */
        if (!in_array($ifcfg['ipaddrv6'], array('6rd', '6to4', 'pppoe', 'ppp', 'l2tp', 'pptp'))) {
            $ipaddr = empty($ifcfg['ipaddr']) ? null : $ifcfg['ipaddr'];
            if (in_array($ipaddr, array('pppoe', 'ppp', 'l2tp', 'pptp'))) {
                foreach ($ppps as $ppp) {
                    if ($ifcfg['if'] == $ppp['if']) {
                        $ports = explode(',', $ppp['ports']);
                        foreach ($ports as $pid => $parent_if) {
                            $realifv6 = $parent_if;
                            break;
                        }
                    }
                }
            }
        }
    }

    if (isset($config['virtualip']['vip'])) {
        foreach ($config['virtualip']['vip'] as $vip) {
            if ($vip['interface'] == $interface && $vip['mode'] != 'carp') {
                /**
                 * only disable non carp aliases, net.inet.carp.ifdown_demotion_factor should do its work
                 * when a carp interface goes down
                 */
                interface_vip_bring_down($vip);
            }
        }
    }

    /*
     * hostapd and wpa_supplicant do not need to be running when the
     * interface is down.  They will also use 100% CPU if running after
     * the wireless clone gets deleted.
     */
    if (!empty($ifcfg['wireless'])) {
        kill_wpasupplicant($realif);
        kill_hostapd($realif);
    }

    $track6 = link_interface_to_track6($interface);
    if (count($track6)) {
        /* bring down radvd and dhcp6 on these interfaces */
        plugins_configure('dhcp', false, array('inet6', $track6));
    }

    switch ($ifcfg['ipaddrv6']) {
        case 'slaac':
        case 'dhcp6':
            interface_dhcpv6_prepare($interface, $ifcfg, true);
            killbypid('/var/run/dhcp6c.pid', 'HUP');
            break;
        case 'track6':
            interface_track6_configure($interface, $ifcfg, false, true);
            break;
        default:
            break;
    }

    switch ($ifcfg['ipaddr']) {
        case 'ppp':
        case 'pppoe':
        case 'pptp':
        case 'l2tp':
            if (!empty($ppps)) {
                foreach ($ppps as $ppp) {
                    if ($ifcfg['if'] == $ppp['if']) {
                        if (isset($ppp['ondemand']) && $ifacecfg === false) {
                            configdp_run('interface reconfigure', array($interface));
                            break;
                        }
                        killbypid("/var/run/{$ppp['type']}_{$interface}.pid", 'TERM', true);
                        @unlink("/var/etc/mpd_{$interface}.conf");
                        break;
                    }
                }
            }
            break;
        case 'dhcp':
            killbypid("/var/run/dhclient.{$realif}.pid", 'TERM', true);
            break;
        default:
            break;
    }

    $pfctlflush = array();
    $arpflush = array();

    if (does_interface_exist($realif)) {
        $arpflush[$realif] = 1;

        if (!empty(trim(@file_get_contents("/tmp/{$realif}_router")))) {
            $pfctlflush[$realif] = 1;
        }

        $ip4 = find_interface_ip($realif);
        if (is_ipaddrv4($ip4)) {
            mwexecf('/sbin/ifconfig %s delete %s', array($realif, $ip4));
        }
    }

    if (does_interface_exist($realifv6)) {
        /*
         * XXX So we flush ARP on IPv6 here!  But why?
         *
         * Either we want to flush NDP or both ARP
         * and NDP, but then the same goes for IPv4.
         */
        $arpflush[$realifv6] = 1;

        if (!empty(trim(@file_get_contents("/tmp/{$realifv6}_routerv6")))) {
            $pfctlflush[$realifv6] = 1;
        }

        $ip6 = find_interface_ipv6($realifv6);
        if (is_ipaddrv6($ip6)) {
            mwexecf('/sbin/ifconfig %s inet6 %s delete', array($realifv6, $ip6));
        }
    }

    foreach (array_keys($arpflush) as $dev) {
        mwexecf('/usr/sbin/arp -d -i %s -a', $dev);
    }

    foreach (array_keys($pfctlflush) as $dev) {
        log_error("Clearing states for stale {$interface} route on {$dev}");
        mwexecf('/sbin/pfctl -i %s -Fs', $dev);
    }

    /* clear stale state files associated with this interface */
    @unlink("/var/db/{$interface}_ip");
    @unlink("/var/db/{$interface}_ipv6");
    @unlink("/var/db/{$interface}_cacheip");
    @unlink("/var/db/{$interface}_cacheipv6");
    @unlink("/var/etc/nameserver_{$realif}");
    @unlink("/var/etc/nameserver_v6{$realifv6}");
    @unlink("/var/etc/searchdomain_{$realif}");
    @unlink("/var/etc/searchdomain_v6{$realifv6}");
    @unlink("/tmp/{$realif}up");
    @unlink("/tmp/{$realifv6}upv6");
    @unlink("/tmp/{$realif}_router");
    @unlink("/tmp/{$realifv6}_routerv6");
    @unlink("/tmp/{$realifv6}_pdinfo");
}

function interfaces_ptpid_used($ptpid)
{
    global $config;

    if (isset($config['ppps']['ppp'])) {
        foreach ($config['ppps']['ppp'] as & $settings) {
            if ($ptpid == $settings['ptpid']) {
                return true;
            }
        }
    }

    return false;
}

function interfaces_ptpid_next()
{

    $ptpid = 0;
    while (interfaces_ptpid_used($ptpid)) {
        $ptpid++;
    }

    return $ptpid;
}

/*
 * This function can configure PPPoE, MLPPP (PPPoE), PPTP.
 * It writes the mpd config file to /var/etc every time the link is opened.
 */
function interface_ppps_configure($interface)
{
    global $config;

    /* Return for unassigned interfaces. This is a minimum requirement. */
    if (empty($config['interfaces'][$interface])) {
        return 0;
    }
    $ifcfg = $config['interfaces'][$interface];
    if (!isset($ifcfg['enable'])) {
        return 0;
    }

    // mpd5 modem chat script expected in the same directory as the mpd_xxx.conf files
    @copy('/usr/local/opnsense/scripts/interfaces/mpd.script', '/var/etc/mpd.script');

    if (isset($config['ppps']['ppp'])) {
        foreach ($config['ppps']['ppp'] as $pppid => $ppp) {
            if ($ifcfg['if'] == $ppp['if']) {
                break;
            }
        }
    }
    if (!isset($ppp) || $ifcfg['if'] != $ppp['if']) {
        log_error("Can't find PPP config for {$ifcfg['if']} in interface_ppps_configure().");
        return 0;
    }

    if (file_exists("/var/run/booting")) {
        // Do not re-configure the interface if we are booting and it's already been started
        if (isvalidpid("/var/run/{$ppp['type']}_{$interface}.pid")) {
            return 0;
        }
    }

    $ports = explode(',', $ppp['ports']);
    if ($ppp['type'] != "ppp") {
        foreach ($ports as $pid => $port) {
            $ports[$pid] = get_real_interface($port);
            if (empty($ports[$pid])) {
                return 0;
            }
        }
    }
    $localips = isset($ppp['localip']) ? explode(',', $ppp['localip']) : array();
    $gateways = isset($ppp['gateway']) ? explode(',', $ppp['gateway']) : array();
    $subnets = isset($ppp['subnet']) ? explode(',', $ppp['subnet']) : array();

    /*
     * We bring up the parent interface first because if DHCP is configured on the parent we need
     * to obtain an address first so we can write it in the mpd .conf file for PPTP and L2TP configs
     */
    foreach ($ports as $pid => $port) {
        switch ($ppp['type']) {
            case "pppoe":
                /* Bring the parent interface up */
                interfaces_bring_up($port);
                /* Enable setautosrc to automatically change mac address if parent interface's changes */
                mwexecf('/usr/sbin/ngctl msg %s: setautosrc 1', array($port));
                break;
            case "pptp":
            case "l2tp":
                /* configure interface */
                if (is_ipaddr($localips[$pid])) {
                    // Manually configure interface IP/subnet
                    legacy_interface_setaddress($port, "{$localips[$pid]}/{$subnets[$pid]}");
                    interfaces_bring_up($port);
                } elseif (empty($localips[$pid])) {
                    $localips[$pid] = get_interface_ip($port); // try to get the interface IP from the port
                }

                if (!is_ipaddr($localips[$pid])) {
                    log_error("Could not get a Local IP address for PPTP/L2TP link on {$port}. Using 0.0.0.0!");
                    $localips[$pid] = "0.0.0.0";
                }
                if (!is_ipaddr($gateways[$pid])) {
                    log_error("Could not get a Remote IP address for PPTP/L2TP link on {$port}.");
                    return 0;
                }
                break;
            case "ppp":
                if (!file_exists("{$port}")) {
                    log_error("Device {$port} does not exist. PPP link cannot start without the modem device.");
                    return 0;
                }
                break;
            default:
                log_error("Unknown {$ppp['type']} configured as PPP interface.");
                break;
        }
    }

    // Construct the mpd.conf file
    $mpdconf = <<<EOD
startup:
  # configure the console
  set console close
  # configure the web server
  set web close

default:
{$ppp['type']}client:
  create bundle static {$interface}
  set bundle enable ipv6cp
  set iface name {$ifcfg['if']}

EOD;
    $setdefaultgw = false;
    $founddefaultgw = false;
    if (isset($config['gateways']['gateway_item'])) {
        foreach ($config['gateways']['gateway_item'] as $gateway) {
            if ($interface == $gateway['interface'] && isset($gateway['defaultgw'])) {
                $setdefaultgw = true;
                break;
            } elseif (isset($gateway['defaultgw']) && !empty($gateway['interface'])) {
                $founddefaultgw = true;
                break;
            }
        }
    }

    if (!$founddefaultgw || $setdefaultgw) {
        $mpdconf .= "  set iface route default\n";
    }
    if (isset($ppp['ondemand'])) {
        $mpdconf .= "  set iface enable on-demand\n";
    } else {
        $mpdconf .= "  set iface disable on-demand\n";
    }
    if (!isset($ppp['idletimeout'])) {
        $mpdconf .= "  set iface idle 0\n";
    } else {
        $mpdconf .= "  set iface idle {$ppp['idletimeout']}\n";
    }

    if (isset($ppp['ondemand'])) {
        $mpdconf .= "  set iface addrs 10.10.1.1 10.10.1.2\n";
    }

    if (isset($ppp['tcpmssfix'])) {
        $mpdconf .= "  set iface disable tcpmssfix\n";
    } else {
        $mpdconf .= "  set iface enable tcpmssfix\n";
    }

    $mpdconf .= "  set iface up-script /usr/local/opnsense/scripts/interfaces/ppp-linkup.sh\n";
    $mpdconf .= "  set iface down-script /usr/local/opnsense/scripts/interfaces/ppp-linkdown.sh\n";
    if ($ppp['type'] == "ppp") {
        if (is_ipaddr($ppp['localip'])) {
            $localip = $ppp['localip'];
        } else {
            $localip = '0.0.0.0';
        }
        if (is_ipaddr($ppp['gateway'])) {
            $gateway = $ppp['gateway'];
        } else {
            $gateway = "10.64.64.{$pppid}";
        }
        $mpdconf .= "  set ipcp ranges {$localip}/0 {$gateway}/0\n";
    } else {
        $mpdconf .= "  set ipcp ranges 0.0.0.0/0 0.0.0.0/0\n";
    }

    if (isset($ppp['vjcomp'])) {
        $mpdconf .= "  set ipcp no vjcomp\n";
    }

    if (isset($config['system']['dnsallowoverride'])) {
        $mpdconf .= "  set ipcp enable req-pri-dns\n";
        $mpdconf .= "  set ipcp enable req-sec-dns\n";
    }
    foreach ($ports as $pid => $port) {
        $mpdconf_arr = array();
        $port = get_real_interface($port);
        if ($ppp['type'] == "ppp") {
            $mpdconf_arr[] = "create link static {$interface}_link{$pid} modem";
        } else {
            $mpdconf_arr[] = "create link static {$interface}_link{$pid} {$ppp['type']}";
        }
        $mpdconf_arr[] = "set link action bundle {$interface}";
        if (count($ports) > 1) {
            $mpdconf_arr[] = "set link enable multilink";
        } else {
            $mpdconf_arr[] = "set link disable multilink";
        }
        $mpdconf_arr[] = "set link keep-alive 10 60";
        $mpdconf_arr[] = "set link max-redial 0";
        if (isset($ppp['shortseq'])) {
            $mpdconf_arr[] = "set link no shortseq";
        }
        if (isset($ppp['acfcomp'])) {
            $mpdconf_arr[] = "set link no acfcomp";
        }
        if (isset($ppp['protocomp'])) {
            $mpdconf_arr[] = "set link no protocomp";
        }
        $mpdconf_arr[] = "set link disable chap pap";
        $mpdconf_arr[] = "set link accept chap pap eap";
        $mpdconf_arr[] = "set link disable incoming";
        $bandwidths = !empty($ppp['bandwidth']) ? explode(',', $ppp['bandwidth']) : null;
        if (!empty($bandwidths[$pid])) {
            $mpdconf_arr[] = "set link bandwidth {$bandwidths[$pid]}";
        }

        $mtus = !empty($ppp['mtu']) ? explode(',', $ppp['mtu']) : null;
        if (empty($mtus[$pid])) {
            $mtus[$pid] = !empty($ifcfg['mtu']) ? intval($ifcfg['mtu']) : 1500;
        }
        if ($ppp['type'] == "pppoe" && $mtus[$pid] > 1500) {
            // use pppoe max-payload if mtu we're about to set > 1492
            $mpdconf_arr[] = "set pppoe max-payload " . ($mtus[$pid] - 8);
        } else {
            $mpdconf_arr[] = "set link mtu " . ($mtus[$pid] - 8);
        }
        $mrus = !empty($ppp['mtu']) ? explode(',', $ppp['mru']) : null;
        if (!empty($mrus[$pid])) {
            $mpdconf_arr[] = "set link mru {$mrus[$pid]}";
        }
        $mrrus = !empty($ppp['mrru']) ? explode(',', $ppp['mrru']) : null;
        if (!empty($mrrus[$pid])) {
            $mpdconf_arr[] = "set link mrru {$mrrus[$pid]}";
        }

        if (empty($ppp['username']) && $ppp['type'] == "ppp") {
            $mpdconf_arr[] = "set auth authname \"user\"";
        } else {
            $mpdconf_arr[] = "set auth authname \"{$ppp['username']}\"";
        }
        if (empty($ppp['password']) && $ppp['type'] == "ppp") {
            $mpdconf_arr[] = "set auth password " .  base64_decode('none');
        } else {
            $mpdconf_arr[] = "set auth password " . base64_decode($ppp['password']);
        }

        if ($ppp['type'] == "ppp") {
            // ppp, modem connections
            $mpdconf_arr[] = "set modem device {$ppp['ports']}";
            $mpdconf_arr[] = "set modem script DialPeer";
            $mpdconf_arr[] = "set modem idle-script Ringback";
            $mpdconf_arr[] = "set modem watch -cd";
            $mpdconf_arr[] = "set modem var \$DialPrefix \"DT\"";
            $mpdconf_arr[] = "set modem var \$Telephone \"{$ppp['phone']}\"";
            if (isset($ppp['connect-timeout'])) {
                $mpdconf_arr[] = "set modem var \$ConnectTimeout \"{$ppp['connect-timeout']}\"";
            }
            if (isset($ppp['initstr'])) {
                $initstr = base64_decode($ppp['initstr']);
                $mpdconf_arr[] = "set modem var \$InitString \"{$initstr}\"";
            }
            if (isset($ppp['simpin'])) {
                $mpdconf_arr[] = "set modem var \$SimPin \"{$ppp['simpin']}\"";
                if (!empty($ppp['pin-wait'])) {
                    $mpdconf_arr[] = "set modem var \$PinWait \"{$ppp['pin-wait']}\"";
                } else {
                    $mpdconf_arr[] = "set modem var \$PinWait \"0\"";
                }
            }
            if (isset($ppp['apn'])) {
                $mpdconf_arr[] = "set modem var \$APN \"{$ppp['apn']}\"";
                if (empty($ppp['apnum'])) {
                    $mpdconf_arr[] = "set modem var \$APNum \"1\"";
                } else {
                    $mpdconf_arr[] = "set modem var \$APNum \"{$ppp['apnum']}\"";
                }
            }
        } elseif ($ppp['type'] == "pppoe") {
            $provider = isset($ppp['provider']) ? $ppp['provider'] : "";
            $hostuniq = '';
            if (!empty($ppp['hostuniq'])) {
                $hostuniq = '0x' . strtolower(array_shift(unpack('H*', $ppp['hostuniq']))) . '|';
            }
            $mpdconf_arr[] = "set pppoe service \"{$hostuniq}{$provider}\"";
            $mpdconf_arr[] = "set pppoe iface {$port}";
        } elseif ($ppp['type'] == "pptp" || $ppp['type'] == "l2tp") {
            $mpdconf_arr[] = "set {$ppp['type']} self {$localips[$pid]}";
            $mpdconf_arr[] = "set {$ppp['type']} peer {$gateways[$pid]}";
        }

        foreach ($mpdconf_arr as $mpdconf_opt) {
            $mpdconf .= "  " . $mpdconf_opt . "\n";
        }

        $mpdconf .= "\topen\n";
    }

    $fd = fopen("/var/etc/mpd_{$interface}.conf", "w");
    if (!$fd) {
        log_error("Error: cannot open mpd_{$interface}.conf in interface_ppps_configure().");
        return 0;
    }

    // Write out mpd_ppp.conf
    fwrite($fd, $mpdconf);
    fclose($fd);
    unset($mpdconf);

    // Create the uptime log if requested and if it doesn't exist already, or delete it if it is no longer requested.
    if (isset($ppp['uptime'])) {
        @touch("/conf/{$ifcfg['if']}.log");
    } else {
        @unlink("/conf/{$ifcfg['if']}.log");
    }

    /* clean up old lock files */
    foreach ($ports as $port) {
        @unlink("/var/spool/lock/LCK..{$port}");
    }

    /* fire up mpd */
    mwexecf(
        '/usr/local/sbin/mpd5 -b -k -d /var/etc -f %s -p %s -s ppp %s',
        array("mpd_{$interface}.conf", "/var/run/{$ppp['type']}_{$interface}.pid", "{$ppp['type']}client")
    );

    /* wait for up to 10 seconds for the interface to appear (ppp(oe)) */
    $i = 0;
    while ($i < 10) {
        exec("/sbin/ifconfig " . escapeshellarg($ppp['if']) . " 2>&1", $out, $ret);
        if ($ret == 0) {
            break;
        }
        sleep(1);
        $i++;
    }

    /* we only support the 3gstats.php for huawei modems for now. Will add more later. */
    /* We should be able to launch the right version for each modem */
    /* We can also guess the mondev from the manufacturer */
    exec("usbconfig | egrep -ie '(huawei)'", $usbmodemoutput);
    mwexec("/bin/ps auxww|grep \"{$interface}\" |grep \"[3]gstats\" | awk '{print $2}' |xargs kill");
    foreach ($ports as $port) {
        $mondev = '';
        if (preg_match("/huawei/i", implode("\n", $usbmodemoutput))) {
            $mondev  = substr(basename($port), 0, -1);
            $devlist = glob("/dev/{$mondev}?");
            $mondev = basename(end($devlist));
        }
        if (preg_match("/zte/i", implode("\n", $usbmodemoutput))) {
            $mondev  = substr(basename($port), 0, -1) . "1";
        }
        if ($mondev != '') {
            log_error("Starting 3gstats.php on device '{$mondev}' for interface '{$interface}'");
            mwexec_bg("/usr/local/sbin/3gstats.php {$mondev} {$interface}");
        }
    }

    return 1;
}

function interfaces_carp_setup($verbose = false)
{
    global $config;

    if ($verbose) {
        echo 'Configuring CARP settings...';
        flush();
    }

    if (!empty($config['hasync']['pfsyncinterface'])) {
        $carp_sync_int = get_real_interface($config['hasync']['pfsyncinterface']);
    }

    if (!empty($carp_sync_int) && isset($config['hasync']['pfsyncenabled'])) {
        if (!empty($config['hasync']['pfsyncpeerip']) && is_ipaddrv4($config['hasync']['pfsyncpeerip'])) {
            $syncpeer = "syncpeer {$config['hasync']['pfsyncpeerip']}";
        } else {
            $syncpeer = "-syncpeer";
        }

        $intf_stats = legacy_interfaces_details();

        mwexec("/sbin/ifconfig pfsync0 syncdev {$carp_sync_int} {$syncpeer} up");

        if (!empty($intf_stats[$carp_sync_int]['mtu'])) {
            mwexec("/sbin/ifconfig pfsync0 mtu {$intf_stats[$carp_sync_int]['mtu']}");
        }
    } else {
        mwexec('/sbin/ifconfig pfsync0 -syncdev -syncpeer down');
    }

    if ($verbose) {
        echo "done.\n";
    }
}

function interface_proxyarp_configure($interface = '')
{
    global $config;

    /* kill any running choparp, on restart "all" */
    if (empty($interface)) {
        killbyname('choparp');
    }

    $paa = array();
    if (isset($config['virtualip']['vip'])) {
        /* group by interface */
        foreach ($config['virtualip']['vip'] as $vipent) {
            if ($vipent['mode'] === "proxyarp") {
                if (empty($interface) || $interface == $vipent['interface']) {
                    if (empty($paa[$vipent['interface']])) {
                        $paa[$proxyif] = array();
                    }
                    $paa[$vipent['interface']][] = $vipent;
                }
            }
        }
    }

    foreach ($paa as $paif => $paents) {
        $paaifip = get_interface_ip($paif);
        if (!is_ipaddr($paaifip)) {
            continue;
        }
        $vipif = get_real_interface($paif);
        $pid_filename = "/var/run/choparp_{$vipif}.pid";
        $args = "-p {$pid_filename} {$vipif} auto";
        foreach ($paents as $paent) {
            $args .= " " . escapeshellarg("{$paent['subnet']}/{$paent['subnet_bits']}");
        }
        if (!empty($interface)) {
            killbypid($pid_filename);
        }
        mwexec_bg("/usr/local/sbin/choparp " . $args);
    }
}

function interfaces_vips_configure($verbose = false, $interface = '')
{
    global $config;

    if (!isset($config['virtualip']['vip'])) {
        return;
    }

    $carp_setup = false;
    $anyproxyarp = false;

    foreach ($config['virtualip']['vip'] as $vip) {
        if (empty($interface) || $vip['interface'] == $interface) {
            switch ($vip['mode']) {
                case 'proxyarp':
                    $anyproxyarp = true;
                    break;
                case 'ipalias':
                    interface_ipalias_configure($vip);
                    break;
                case 'carp':
                    if (!$carp_setup) {
                        $carp_setup = true;
                    }
                    interface_carp_configure($vip);
                    break;
            }
        }
    }

    if ($carp_setup) {
        interfaces_carp_setup($verbose);
    }

    if ($anyproxyarp) {
        interface_proxyarp_configure();
    }
}

function interface_ipalias_configure(&$vip)
{
    global $config;

    if ($vip['mode'] != 'ipalias') {
        return;
    }

    if ($vip['interface'] != 'lo0' && !isset($config['interfaces'][$vip['interface']]['enable'])) {
        return;
    }

    if (is_ipaddrv6($vip['subnet'])) {
        $if = get_real_interface($vip['interface'], 'inet6');
        $af = 'inet6';
    } else {
        $if = get_real_interface($vip['interface']);
        $af = 'inet';
    }

    $vhid = !empty($vip['vhid']) ? 'vhid ' . escapeshellarg($vip['vhid']) : '';
    $gateway = !empty($vip['gateway']) ? escapeshellarg($vip['gateway']) . ' ' : '';

    /* XXX use legacy_interface_setaddress */
    mwexec("/sbin/ifconfig " . escapeshellarg($if) . " {$af} " . escapeshellarg($vip['subnet']) . "/" . escapeshellarg($vip['subnet_bits']) . " alias " . $gateway . $vhid);
}

function interface_carp_configure(&$vip)
{
    if ($vip['mode'] != 'carp') {
        return;
    }

    /* when CARP is temporary disabled do not try to configure on any interface up events */
    if (get_single_sysctl('net.inet.carp.allow') == '0') {
        return;
    }

    $realif = get_real_interface($vip['interface']);

    $vip_password = $vip['password'];
    $vip_password = escapeshellarg(addslashes(str_replace(" ", "", $vip_password)));
    if ($vip['password'] != "") {
        $password = " pass {$vip_password}";
    }

    $advbase = "";
    if (!empty($vip['advbase'])) {
        $advbase = "advbase " . escapeshellarg($vip['advbase']);
    }

    $advskew = "advskew " . escapeshellarg($vip['advskew']);

    mwexec("/sbin/ifconfig {$realif} vhid " . escapeshellarg($vip['vhid']) . " {$advskew} {$advbase} {$password}");

    /* XXX use legacy_interface_setaddress */
    if (is_ipaddrv4($vip['subnet'])) {
        mwexec("/sbin/ifconfig {$realif} " . escapeshellarg($vip['subnet']) . "/" . escapeshellarg($vip['subnet_bits']) . " alias vhid " . escapeshellarg($vip['vhid']));
    } elseif (is_ipaddrv6($vip['subnet'])) {
        mwexec("/sbin/ifconfig {$realif} inet6 " . escapeshellarg($vip['subnet']) . " prefixlen " . escapeshellarg($vip['subnet_bits']) . " alias vhid " . escapeshellarg($vip['vhid']));
    }

    return $realif;
}

function interface_wireless_clone($realif, $wlcfg)
{
    /*
     * Check to see if interface has been cloned as of yet.
     * If it has not been cloned then go ahead and clone it.
     */
    $needs_clone = false;
    if (isset($wlcfg['wireless']) && is_array($wlcfg['wireless'])) {
        $wlcfg_mode = $wlcfg['wireless']['mode'];
    } else {
        $wlcfg_mode = $wlcfg['mode'];
    }
    switch ($wlcfg_mode) {
        case "hostap":
            $mode = "wlanmode hostap";
            break;
        case "adhoc":
            $mode = "wlanmode adhoc";
            break;
        default:
            $mode = "";
            break;
    }
    $baseif = interface_get_wireless_base($wlcfg['if']);
    if (does_interface_exist($realif)) {
        exec("/sbin/ifconfig " . escapeshellarg($realif), $output, $ret);
        $ifconfig_str = implode($output);
        if (($wlcfg_mode == "hostap") && (! preg_match("/hostap/si", $ifconfig_str))) {
            log_error("Interface {$realif} changed to hostap mode");
            $needs_clone = true;
        }
        if (($wlcfg_mode == "adhoc") && (! preg_match("/adhoc/si", $ifconfig_str))) {
            log_error("Interface {$realif} changed to adhoc mode");
            $needs_clone = true;
        }
        if (($wlcfg_mode == "bss") && (preg_match("/hostap|adhoc/si", $ifconfig_str))) {
            log_error("Interface {$realif} changed to infrastructure mode");
            $needs_clone = true;
        }
    } else {
        $needs_clone = true;
    }

    if ($needs_clone) {
        /* remove previous instance if it exists */
        if (does_interface_exist($realif)) {
            legacy_interface_destroy($realif);
        }

        log_error("Cloning new wireless interface {$realif}");
        exec("/sbin/ifconfig wlan create wlandev {$baseif} {$mode} bssid name {$realif} 2>&1", $out, $ret);
        if ($ret != 0) {
            log_error("Failed to clone interface {$baseif} with error code {$ret}, output {$out[0]}");
            return false;
        }
        file_put_contents("/tmp/{$realif}_oldmac", get_interface_mac($realif));
    }
    return true;
}

function interface_sync_wireless_clones(&$ifcfg, $sync_changes = false)
{
    global $config;

    $shared_settings = array(
        'channel',
        'diversity',
        'protmode',
        'regcountry',
        'regdomain',
        'reglocation',
        'rxantenna',
        'standard',
        'turbo',
        'txantenna',
        'txpower',
    );

    if (!is_interface_wireless($ifcfg['if'])) {
        return;
    }

    $baseif = interface_get_wireless_base($ifcfg['if']);

    foreach (array_keys(legacy_config_get_interfaces(['virtual' => false])) as $if) {
        if ($baseif == interface_get_wireless_base($config['interfaces'][$if]['if']) && $ifcfg['if'] != $config['interfaces'][$if]['if']) {
            if (isset($config['interfaces'][$if]['wireless']['standard']) || $sync_changes) {
                foreach ($shared_settings as $setting) {
                    if ($sync_changes) {
                        if (isset($ifcfg['wireless'][$setting])) {
                            $config['interfaces'][$if]['wireless'][$setting] = $ifcfg['wireless'][$setting];
                        } elseif (isset($config['interfaces'][$if]['wireless'][$setting])) {
                            unset($config['interfaces'][$if]['wireless'][$setting]);
                        }
                    } else {
                        if (isset($config['interfaces'][$if]['wireless'][$setting])) {
                            $ifcfg['wireless'][$setting] = $config['interfaces'][$if]['wireless'][$setting];
                        } elseif (isset($ifcfg['wireless'][$setting])) {
                            unset($ifcfg['wireless'][$setting]);
                        }
                    }
                }
                if (!$sync_changes) {
                    break;
                }
            }
        }
    }

    // Read or write settings at shared area
    if (isset($config['wireless']['interfaces'][$baseif]) && is_array($config['wireless']['interfaces'][$baseif])) {
        foreach ($shared_settings as $setting) {
            if ($sync_changes) {
                if (isset($ifcfg['wireless'][$setting])) {
                    $config['wireless']['interfaces'][$baseif][$setting] = $ifcfg['wireless'][$setting];
                } elseif (isset($config['wireless']['interfaces'][$baseif][$setting])) {
                    unset($config['wireless']['interfaces'][$baseif][$setting]);
                }
            } elseif (isset($config['wireless']['interfaces'][$baseif][$setting])) {
                if (isset($config['wireless']['interfaces'][$baseif][$setting])) {
                    $ifcfg['wireless'][$setting] = $config['wireless']['interfaces'][$baseif][$setting];
                } elseif (isset($ifcfg['wireless'][$setting])) {
                    unset($ifcfg['wireless'][$setting]);
                }
            }
        }
    }

    // Sync the mode on the clone creation page with the configured mode on the interface
    if (stristr($ifcfg['if'], "_wlan") && isset($config['wireless']['clone']) && is_array($config['wireless']['clone'])) {
        foreach ($config['wireless']['clone'] as &$clone) {
            if ($clone['cloneif'] == $ifcfg['if']) {
                if ($sync_changes) {
                    $clone['mode'] = $ifcfg['wireless']['mode'];
                } else {
                    $ifcfg['wireless']['mode'] = $clone['mode'];
                }
                break;
            }
        }
        unset($clone);
    }
}

function interface_wireless_configure($if, &$wl, &$wlcfg)
{
    global $config;

   /*    open up a shell script that will be used to output the commands.
    *    since wireless is changing a lot, these series of commands are fragile
    *    and will sometimes need to be verified by a operator by executing the command
    *    and returning the output of the command to the developers for inspection.  please
    *    do not change this routine from a shell script to individual exec commands.  -sullrich
    */

    // Remove script file
    @unlink("/tmp/{$if}_setup.sh");

    // Clone wireless nic if needed.
    interface_wireless_clone($if, $wl);

    // Reject inadvertent changes to shared settings in case the interface hasn't been configured.
    interface_sync_wireless_clones($wl, false);

    $fd_set = fopen("/tmp/{$if}_setup.sh", "w");
    fwrite($fd_set, "#!/bin/sh\n");
    fwrite($fd_set, "# wireless configuration script.\n\n");

    /* set values for /path/program */
    $wpa_supplicant = '/usr/local/sbin/wpa_supplicant';
    $hostapd = '/usr/local/sbin/hostapd';
    $ifconfig = '/sbin/ifconfig';
    $sysctl = '/sbin/sysctl';

    /* Set all wireless ifconfig variables (split up to get rid of needed checking) */

    $wlcmd = array();
    $wl_sysctl = array();
    /* Make sure it's up */
    $wlcmd[] = "up";
    /* Set a/b/g standard */
    $standard = str_replace(" Turbo", "", $wlcfg['standard']);
    $wlcmd[] = "mode " . escapeshellarg($standard);

    /* XXX: Disable ampdu for now on mwl when running in 11n mode
     * to prevent massive packet loss under certain conditions. */
    if (preg_match("/^mwl/i", $if) && ($standard == "11ng" || $standard == "11na")) {
        $wlcmd[] = "-ampdu";
    }

    /* Set ssid */
    if ($wlcfg['ssid']) {
        $wlcmd[] = "ssid " . escapeshellarg($wlcfg['ssid']);
    }

    /* Set 802.11g protection mode */
    $wlcmd[] = "protmode " . escapeshellarg($wlcfg['protmode']);

    /* set wireless channel value */
    if (isset($wlcfg['channel'])) {
        if ($wlcfg['channel'] == "0") {
            $wlcmd[] = "channel any";
        } else {
            $wlcmd[] = "channel " . escapeshellarg($wlcfg['channel']);
        }
    }

    /* Set antenna diversity value */
    if (isset($wlcfg['diversity'])) {
        $wl_sysctl[] = "diversity=" . escapeshellarg($wlcfg['diversity']);
    }

    /* Set txantenna value */
    if (isset($wlcfg['txantenna'])) {
        $wl_sysctl[] = "txantenna=" . escapeshellarg($wlcfg['txantenna']);
    }

    /* Set rxantenna value */
    if (isset($wlcfg['rxantenna'])) {
        $wl_sysctl[] = "rxantenna=" . escapeshellarg($wlcfg['rxantenna']);
    }

    /* Set wireless hostap mode */
    if ($wlcfg['mode'] == "hostap") {
        $wlcmd[] = "mediaopt hostap";
    } else {
        $wlcmd[] = "-mediaopt hostap";
    }

    /* Set wireless adhoc mode */
    if ($wlcfg['mode'] == "adhoc") {
        $wlcmd[] = "mediaopt adhoc";
    } else {
        $wlcmd[] = "-mediaopt adhoc";
    }

    /* Not neccesary to set BSS mode as this is default if adhoc and/or hostap is NOT set */

    /* handle hide ssid option */
    if (isset($wlcfg['hidessid']['enable'])) {
        $wlcmd[] = "hidessid";
    } else {
        $wlcmd[] = "-hidessid";
    }

    /* handle pureg (802.11g) only option */
    if (isset($wlcfg['pureg']['enable'])) {
        $wlcmd[] = "mode 11g pureg";
    } else {
        $wlcmd[] = "-pureg";
    }

    /* handle puren (802.11n) only option */
    if (isset($wlcfg['puren']['enable'])) {
        $wlcmd[] = "puren";
    } else {
        $wlcmd[] = "-puren";
    }

    /* enable apbridge option */
    if (isset($wlcfg['apbridge']['enable'])) {
        $wlcmd[] = "apbridge";
    } else {
        $wlcmd[] = "-apbridge";
    }

    /* handle turbo option */
    if (isset($wlcfg['turbo']['enable'])) {
        $wlcmd[] = "mediaopt turbo";
    } else {
        $wlcmd[] = "-mediaopt turbo";
    }

    /* handle wme option */
    if (isset($wlcfg['wme']['enable'])) {
        $wlcmd[] = "wme";
    } else {
        $wlcmd[] = "-wme";
    }

    /* set up wep if enabled */
    $wepset = "";
    if (isset($wlcfg['wep']['enable']) && is_array($wlcfg['wep']['key'])) {
        switch ($wlcfg['wpa']['auth_algs']) {
            case "1":
                $wepset .= "authmode open wepmode on ";
                break;
            case "2":
                $wepset .= "authmode shared wepmode on ";
                break;
            case "3":
                $wepset .= "authmode mixed wepmode on ";
        }
        $i = 1;
        foreach ($wlcfg['wep']['key'] as $wepkey) {
            $wepset .= "wepkey " . escapeshellarg("{$i}:{$wepkey['value']}") . " ";
            if (isset($wepkey['txkey'])) {
                $wlcmd[] = "weptxkey {$i} ";
            }
            $i++;
        }
        $wlcmd[] = $wepset;
    } else {
        $wlcmd[] = "authmode open wepmode off ";
    }

    kill_wpasupplicant($if);
    kill_hostapd($if);

    /* generate wpa_supplicant/hostap config if wpa is enabled */
    switch ($wlcfg['mode']) {
        case 'bss':
            if (isset($wlcfg['wpa']['enable'])) {
                $wpa = <<<EOD
ctrl_interface=/var/run/wpa_supplicant
ctrl_interface_group=0
ap_scan=1
#fast_reauth=1
network={
ssid="{$wlcfg['ssid']}"
scan_ssid=1
priority=5
key_mgmt={$wlcfg['wpa']['wpa_key_mgmt']}
psk="{$wlcfg['wpa']['passphrase']}"
pairwise={$wlcfg['wpa']['wpa_pairwise']}
group={$wlcfg['wpa']['wpa_pairwise']}
}

EOD;

                @file_put_contents("/var/etc/wpa_supplicant_{$if}.conf", $wpa);
                unset($wpa);
            }
            break;
        case 'hostap':
            if (!empty($wlcfg['wpa']['passphrase'])) {
                $wpa_passphrase = "wpa_passphrase={$wlcfg['wpa']['passphrase']}\n";
            } else {
                $wpa_passphrase = "";
            }
            if (isset($wlcfg['wpa']['enable'])) {
                $wpa = <<<EOD
interface={$if}
driver=bsd
logger_syslog=-1
logger_syslog_level=0
logger_stdout=-1
logger_stdout_level=0
dump_file=/tmp/hostapd_{$if}.dump
ctrl_interface=/var/run/hostapd
ctrl_interface_group=wheel
#accept_mac_file=/tmp/hostapd_{$if}.accept
#deny_mac_file=/tmp/hostapd_{$if}.deny
#macaddr_acl={$wlcfg['wpa']['macaddr_acl']}
ssid={$wlcfg['ssid']}
debug={$wlcfg['wpa']['debug_mode']}
auth_algs={$wlcfg['wpa']['auth_algs']}
wpa={$wlcfg['wpa']['wpa_mode']}
wpa_key_mgmt={$wlcfg['wpa']['wpa_key_mgmt']}
wpa_pairwise={$wlcfg['wpa']['wpa_pairwise']}
wpa_group_rekey={$wlcfg['wpa']['wpa_group_rekey']}
wpa_gmk_rekey={$wlcfg['wpa']['wpa_gmk_rekey']}
wpa_strict_rekey={$wlcfg['wpa']['wpa_strict_rekey']}
{$wpa_passphrase}

EOD;

                if (isset($wlcfg['wpa']['rsn_preauth'])) {
                    $wpa .= <<<EOD
# Enable the next lines for preauth when roaming. Interface = wired or wireless interface talking to the AP you want to roam from/to
rsn_preauth=1
rsn_preauth_interfaces={$if}

EOD;
                }
                if (is_array($wlcfg['wpa']['ieee8021x']) && isset($wlcfg['wpa']['ieee8021x']['enable'])) {
                    $wpa .= "ieee8021x=1\n";

                    if (!empty($wlcfg['auth_server_addr']) && !empty($wlcfg['auth_server_shared_secret'])) {
                        $auth_server_port = "1812";
                        if (!empty($wlcfg['auth_server_port']) && is_numeric($wlcfg['auth_server_port'])) {
                            $auth_server_port = intval($wlcfg['auth_server_port']);
                        }
                        $wpa .= <<<EOD

auth_server_addr={$wlcfg['auth_server_addr']}
auth_server_port={$auth_server_port}
auth_server_shared_secret={$wlcfg['auth_server_shared_secret']}

EOD;
                        if (!empty($wlcfg['auth_server_addr2']) && !empty($wlcfg['auth_server_shared_secret2'])) {
                            $auth_server_port2 = "1812";
                            if (!empty($wlcfg['auth_server_port2']) && is_numeric($wlcfg['auth_server_port2'])) {
                                $auth_server_port2 = intval($wlcfg['auth_server_port2']);
                            }

                            $wpa .= <<<EOD
auth_server_addr={$wlcfg['auth_server_addr2']}
auth_server_port={$auth_server_port2}
auth_server_shared_secret={$wlcfg['auth_server_shared_secret2']}

EOD;
                        }
                    }
                }

                @file_put_contents("/var/etc/hostapd_{$if}.conf", $wpa);
                unset($wpa);
            }
            break;
    }

    /*
     *    all variables are set, lets start up everything
     */

    $baseif = interface_get_wireless_base($if);
    preg_match("/^(.*?)([0-9]*)$/", $baseif, $baseif_split);
    $wl_sysctl_prefix = 'dev.' . $baseif_split[1] . '.' . $baseif_split[2];

    /* set sysctls for the wireless interface */
    if (!empty($wl_sysctl)) {
        fwrite($fd_set, "# sysctls for {$baseif}\n");
        foreach ($wl_sysctl as $wl_sysctl_line) {
            fwrite($fd_set, "{$sysctl} {$wl_sysctl_prefix}.{$wl_sysctl_line}\n");
        }
    }

    if (isset($wlcfg['wpa']['enable'])) {
        if ($wlcfg['mode'] == "bss") {
            fwrite($fd_set, "{$wpa_supplicant} -B -i {$if} -c /var/etc/wpa_supplicant_{$if}.conf\n");
        }
        if ($wlcfg['mode'] == "hostap") {
            /* add line to script to restore old mac to make hostapd happy */
            if (file_exists("/tmp/{$if}_oldmac")) {
                $if_oldmac = file_get_contents("/tmp/{$if}_oldmac");
                if (is_macaddr($if_oldmac)) {
                    fwrite($fd_set, "{$ifconfig} " . escapeshellarg($if) .
                        " link " . escapeshellarg($if_oldmac) . "\n");
                }
            }

            fwrite($fd_set, "{$hostapd} -B -P /var/run/hostapd_{$if}.pid /var/etc/hostapd_{$if}.conf\n");

            /* add line to script to restore spoofed mac after running hostapd */
            if (file_exists("/tmp/{$if}_oldmac")) {
                if ($wl['spoofmac']) {
                    $if_curmac = $wl['spoofmac'];
                } else {
                    $if_curmac = get_interface_mac($if);
                }
                if (is_macaddr($if_curmac)) {
                    fwrite($fd_set, "{$ifconfig} " . escapeshellarg($if) .
                        " link " . escapeshellarg($if_curmac) . "\n");
                }
            }
        }
    }

    fclose($fd_set);

    /*
     * Making sure regulatory settings have actually changed
     * before applying, because changing them requires bringing
     * down all wireless networks on the interface.
     */
    exec("{$ifconfig} " . escapeshellarg($if), $output);
    $ifconfig_str = implode($output);
    unset($output);
    $reg_changing = false;

    /* special case for the debug country code */
    if ($wlcfg['regcountry'] == 'DEBUG' && !preg_match("/\sregdomain\s+DEBUG\s/si", $ifconfig_str)) {
        $reg_changing = true;
    } elseif ($wlcfg['regdomain'] && !preg_match("/\sregdomain\s+{$wlcfg['regdomain']}\s/si", $ifconfig_str)) {
        $reg_changing = true;
    } elseif ($wlcfg['regcountry'] && !preg_match("/\scountry\s+{$wlcfg['regcountry']}\s/si", $ifconfig_str)) {
        $reg_changing = true;
    } elseif ($wlcfg['reglocation'] == 'anywhere' && preg_match("/\s(indoor|outdoor)\s/si", $ifconfig_str)) {
        $reg_changing = true;
    } elseif ($wlcfg['reglocation'] && $wlcfg['reglocation'] != 'anywhere' && !preg_match("/\s{$wlcfg['reglocation']}\s/si", $ifconfig_str)) {
        $reg_changing = true;
    }

    if ($reg_changing) {
        /* set regulatory domain */
        if ($wlcfg['regdomain']) {
            $wlregcmd[] = "regdomain " . escapeshellarg($wlcfg['regdomain']);
        }

        /* set country */
        if ($wlcfg['regcountry']) {
            $wlregcmd[] = "country " . escapeshellarg($wlcfg['regcountry']);
        }

        /* set location */
        if ($wlcfg['reglocation']) {
            $wlregcmd[] = escapeshellarg($wlcfg['reglocation']);
        }

        $wlregcmd_args = implode(" ", $wlregcmd);

        /* build a complete list of the wireless clones for this interface */
        $clone_list = array();
        if (does_interface_exist(interface_get_wireless_clone($baseif))) {
            $clone_list[] = interface_get_wireless_clone($baseif);
        }
        if (isset($config['wireless']['clone'])) {
            foreach ($config['wireless']['clone'] as $clone) {
                if ($clone['if'] == $baseif) {
                    $clone_list[] = $clone['cloneif'];
                }
            }
        }

        /* find which clones are up and bring them down */
        $ifup = legacy_interface_listget('up');
        $clones_up = array();
        foreach ($clone_list as $clone_if) {
            if (in_array($clone_if, $ifup)) {
                $clones_up[] = $clone_if;
                mwexec("{$ifconfig} " . escapeshellarg($clone_if) . " down");
            }
        }

        /* apply the regulatory settings */
        mwexec("{$ifconfig} " . escapeshellarg($if) . " {$wlregcmd_args}");

        /* bring the clones back up that were previously up */
        foreach ($clones_up as $clone_if) {
            mwexec("{$ifconfig} " . escapeshellarg($clone_if) . " up");

            /*
             * Rerun the setup script for the interface if it isn't this interface, the interface
             * is in infrastructure mode, and WPA is enabled.
             * This can be removed if wpa_supplicant stops dying when you bring the interface down.
             */
            if ($clone_if != $if) {
                $friendly_if = convert_real_interface_to_friendly_interface_name($clone_if);
                if (
                    !empty($friendly_if)
                    && $config['interfaces'][$friendly_if]['wireless']['mode'] == "bss"
                    && isset($config['interfaces'][$friendly_if]['wireless']['wpa']['enable'])
                ) {
                    mwexec('/bin/sh /tmp/' . escapeshellarg($clone_if) . '_setup.sh');
                }
            }
        }
    }

    /* The mode must be specified in a separate command before ifconfig
     * will allow the mode and channel at the same time in the next. */
    mwexec("/sbin/ifconfig " . escapeshellarg($if) . " mode " . escapeshellarg($standard));

    /* configure wireless */
    $wlcmd_args = implode(" ", $wlcmd);
    mwexec("/sbin/ifconfig " . escapeshellarg($if) . " " . $wlcmd_args, false);
    unset($wlcmd_args, $wlcmd);

    /* configure txpower setting (it has been known to fail so run it separately) */
    if (!empty($wlcfg['txpower'])) {
        mwexecf('/sbin/ifconfig %s txpower %s', array($if, $wlcfg['txpower']));
    }

    sleep(1);
    /* execute hostapd and wpa_supplicant if required in shell */
    mwexec('/bin/sh /tmp/' . escapeshellarg($if) . '_setup.sh');

    return 0;
}

function kill_hostapd($interface)
{
    killbypid("/var/run/hostapd_{$interface}.pid");
}

function kill_wpasupplicant($interface)
{
    mwexec("/bin/pkill -f \"wpa_supplicant .*{$interface}\\.conf\"\n");
}

function interface_static_configure($interface, $wancfg)
{
    if (empty($wancfg['ipaddr']) || !is_ipaddrv4($wancfg['ipaddr']) || $wancfg['subnet'] == '') {
        return;
    }

    $realif = get_real_interface($interface);

    mwexecf('/sbin/ifconfig %s inet %s/%s', array($realif, $wancfg['ipaddr'], $wancfg['subnet']));
}

function interface_static6_configure($interface, $wancfg)
{
    if (empty($wancfg['ipaddrv6']) || !is_ipaddrv6($wancfg['ipaddrv6']) || $wancfg['subnetv6'] == '') {
        return;
    }

    $realif = get_real_interface($interface, 'inet6');

    mwexecf('/sbin/ifconfig %s inet6 %s prefixlen %s', array($realif, $wancfg['ipaddrv6'], $wancfg['subnetv6']));
}

function interface_configure($verbose = false, $interface = 'wan', $reload = false, $linkupevent = false)
{
    global $config;

    $wancfg = $config['interfaces'][$interface];

    if (!isset($wancfg['enable'])) {
        return;
    }

    $wandescr = !empty($wancfg['descr']) ? $wancfg['descr'] : strtoupper($interface);

    if ($verbose) {
        echo sprintf('Configuring %s interface...', $wandescr);
        flush();
    }

    $realif = get_real_interface($interface);
    $realhwif_array = get_parent_interface($interface);
    // Need code to handle MLPPP if we ever use $realhwif for MLPPP handling
    $realhwif = $realhwif_array[0];

    if (!file_exists("/var/run/booting") && !(substr($realif, 0, 4) == "ovpn")) {
        /* remove all IPv4 and IPv6 addresses */
        foreach (array_keys(interfaces_addresses($realif, true)) as $tmpiface) {
            $tmpip = $tmpiface;
            $family = 4;

            if (is_linklocal($tmpip)) {
                continue;
            } elseif (is_ipaddrv6($tmpip) || is_subnetv6($tmpip)) {
                $family = 6;
            } elseif (is_subnetv4($tmpiface)) {
                $tmpip = explode('/', $tmpiface)[0];
            }

            legacy_interface_deladdress($realif, $tmpip, $family);
        }

        /* only bring down the interface when both v4 and v6 are set to NONE */
        if (empty($wancfg['ipaddr']) && empty($wancfg['ipaddrv6'])) {
            interface_bring_down($interface);
        }
    }

    $interface_to_check = $realif;
    switch ($wancfg['ipaddr']) {
        case 'pppoe':
        case 'l2tp':
        case 'pptp':
        case 'ppp':
            $interface_to_check = $realhwif;
            break;
    }

    /* XXX we come up empty here in edge cases, which is silly AND dangerous */
    if (empty($interface_to_check)) {
        log_error("Interface {$interface} device lookup came up empty");
    } else {
        /*
         * Interface code must figure out if the call is for them so
         * we start all but when we pass an interface the name will
         * be matched so most will be a NOP.
         *
         * Verbose printing ($verbose) is off in the block because
         * we are reconfiguring a specific interface and the verbose
         * print was already started above.
         */

        /* make sure tunnel inside IP is set when interface changes #3702 */
        interfaces_gre_configure(false, 0, $interface_to_check);
        interfaces_gif_configure(false, 0, $interface_to_check);

        /* need to check that the interface exists #3270 */
        if (!does_interface_exist($interface_to_check)) {
            interfaces_vlan_configure(false, $interface_to_check);
            interfaces_lagg_configure(false, $interface_to_check);
            interfaces_bridge_configure(false, 0, $interface_to_check);
            plugins_configure('openvpn_prepare', false, array($interface_to_check));
        }
    }

    /* wireless configuration? */
    if (isset($wancfg['wireless']) && is_array($wancfg['wireless']) && !$linkupevent) {
        interface_wireless_configure($realif, $wancfg, $wancfg['wireless']);
    }

    /*
     * Don't try to reapply the spoofed MAC if it's already applied.
     * When ifconfig link is used, it cycles the interface down/up,
     * which triggers the interface config again, which attempts to
     * spoof the MAC again which cycles the link again...
     */
    if (!empty($wancfg['spoofmac']) && strcasecmp($wancfg['spoofmac'], get_interface_mac($realhwif))) {
        mwexecf('/sbin/ifconfig %s link %s', array($realhwif, $wancfg['spoofmac']));

        /* All vlans need to spoof their parent mac address, too. */
        if (isset($config['vlans']['vlan'])) {
            foreach ($config['vlans']['vlan'] as $vlan) {
                if ($vlan['if'] == $realhwif) {
                    mwexecf('/sbin/ifconfig %s link %s', array($vlan['vlanif'], $wancfg['spoofmac']));
                }
            }
        }
    }

    /* only try to set media properties on (parent) device when requested */
    if (!empty($wancfg['media']) || !empty($wancfg['mediaopt'])) {
        $intf_details = legacy_interface_details($realhwif);
        $media_changed = stripos($intf_details['media_raw'], $wancfg['media']) == false;
        if (!empty($wancfg['mediaopt'])) {
            $media_changed |= stripos($intf_details['media_raw'], $wancfg['mediaopt']) == false;
        }
        if ($media_changed) {
            $cmd = "/sbin/ifconfig " . escapeshellarg($realhwif);
            if (!empty($wancfg['media'])) {
                $cmd .= " media " . escapeshellarg($wancfg['media']);
            }
            if (!empty($wancfg['mediaopt'])) {
                $cmd .= " mediaopt " . escapeshellarg($wancfg['mediaopt']);
            }
            mwexec($cmd);
        }
    }

    /* apply interface hardware settings (tso, lro, ..) */
    configure_interface_hardware($realhwif);

    switch ($wancfg['ipaddr']) {
        case 'dhcp':
            interface_dhcp_configure($interface);
            break;
        case 'pppoe':
        case 'l2tp':
        case 'pptp':
        case 'ppp':
            interface_ppps_configure($interface);
            break;
        default:
            interface_static_configure($interface, $wancfg);
            break;
    }

    /* disable accepting router advertisements unless specifically requested */
    mwexecf('/sbin/ifconfig %s inet6 -accept_rtadv', array($realif));

    if (isset($wancfg['ipaddrv6'])) {
        switch ($wancfg['ipaddrv6']) {
            case 'slaac':
            case 'dhcp6':
                $realifv6 = get_real_interface($interface, 'inet6');

                /* accept router advertisements for this interface */
                set_single_sysctl('net.inet6.ip6.accept_rtadv', '1');
                log_error("Accept router advertisements on interface {$realifv6}");
                mwexecf('/sbin/ifconfig %s inet6 accept_rtadv -ifdisabled', $realifv6);

                /* Enable RFC6204w support for IPv6 Customer Edge (CE) router */
                set_single_sysctl('net.inet6.ip6.rfc6204w3', '1');

                if (!isset($wancfg['dhcp6usev4iface'])) {
                    interface_dhcpv6_prepare($interface, $wancfg);
                    interface_dhcpv6_configure($interface, $wancfg);
                }
                break;
            case '6rd':
                interface_6rd_configure($interface, $wancfg);
                break;
            case '6to4':
                interface_6to4_configure($interface, $wancfg);
                break;
            case 'track6':
                interface_track6_configure($interface, $wancfg, $reload || $linkupevent);
                break;
            default:
                if (!isset($wancfg['dhcp6usev4iface'])) {
                    interface_static6_configure($interface, $wancfg);
                }
                break;
        }

        /* XXX we do not disable accept_rtadv on $realifv6 != $realif where unused */
    }

    if (!empty($wancfg['mtu'])) {
        $intf_stats = legacy_interface_stats($realhwif);
        if (stristr($realif, "_vlan")) {
            // max mtu to parent
            $mtu = $wancfg['mtu'] < $intf_stats['mtu'] ? $wancfg['mtu'] : $intf_stats['mtu'];
            if ($mtu != $intf_stats['mtu']) {
                legacy_interface_mtu($realif, $mtu);
            }
        } elseif ($wancfg['mtu'] != $intf_stats['mtu']) {
            legacy_interface_mtu($realhwif, $wancfg['mtu']);
        }
    }

    // in case this interface has vlan's configured, make sure none of them has an mtu set higher than its parent
    if (!empty($config['vlans']['vlan'])) {
        $intf_stats = legacy_interface_stats();
        foreach ($config['vlans']['vlan'] as $vlan) {
            if ($realhwif == $vlan['if'] && $intf_stats[$realhwif]['mtu'] < $intf_stats[$vlan['vlanif']]['mtu']) {
                legacy_interface_mtu($vlan['vlanif'], $intf_stats[$realhwif]['mtu']);
            }
        }
    }

    if (does_interface_exist($wancfg['if'])) {
        interfaces_bring_up($wancfg['if']);
    }

    /* always reconfigure VIPs since late boot configuration may need it too */
    interfaces_vips_configure(false, $interface);

    if (!file_exists("/var/run/booting")) {
        unset($gre);
        $gre = link_interface_to_gre($interface);
        if (!empty($gre)) {
            array_walk($gre, 'interface_gre_configure');
        }

        unset($gif);
        $gif = link_interface_to_gif($interface);
        if (!empty($gif)) {
            array_walk($gif, 'interface_gif_configure');
        }

        if ($linkupevent == false || substr($realif, 0, 4) == "ovpn") {
            unset($bridgetmp);
            $bridgetmp = link_interface_to_bridge($interface);
            if (!empty($bridgetmp)) {
                interface_bridge_add_member($bridgetmp, $realif);
            }
        }
    }

    if ($verbose) {
        echo "done.\n";
    }

    if (!file_exists("/var/run/booting")) {
        if ($reload) {
            system_routing_configure($verbose, $interface);
            plugins_configure('ipsec', $verbose, array($interface));
            plugins_configure('dhcp', $verbose);
            plugins_configure('dns', $verbose);
            /* XXX move these up the call stack */
            configdp_run('dyndns reload', array($interface));
            configdp_run('rfc2136 reload', array($interface));
        }
    }

    interfaces_staticarp_configure($interface);
}

function interface_track6_configure($interface = 'lan', $lancfg, $linkupevent = false, $linkdownevent = false)
{
    global $config;

    if (!is_array($lancfg) || empty($lancfg['track6-interface'])) {
        return;
    }

    $trackcfg = $config['interfaces'][$lancfg['track6-interface']];
    if (!isset($trackcfg['enable'])) {
        log_error("Interface {$interface} tracking non-existent interface {$lancfg['track6-interface']}");
        return;
    }

    if ($linkdownevent || !isset($lancfg['enable'])) {
        switch ($trackcfg['ipaddrv6']) {
            /* deconfiguring 6to4 and 6rd is done elsewhere as it simply removes addresses */
            case 'slaac':
            case 'dhcp6':
                interface_dhcpv6_prepare($lancfg['track6-interface'], $trackcfg);
                killbypid('/var/run/dhcp6c.pid', 'HUP');
                break;
            default:
                break;
        }

        /* exit now as there are only linkup-related bits following */
        return;
    }

    switch ($trackcfg['ipaddrv6']) {
        case '6to4':
            interface_track6_6to4_configure($interface, $lancfg);
            break;
        case '6rd':
            interface_track6_6rd_configure($interface, $lancfg);
            break;
        case 'slaac':
            interface_track6_slaac_configure($interface, $lancfg);
            /* FALLTHROUGH */
        case 'dhcp6':
            if ($linkupevent) {
                interface_dhcpv6_prepare($lancfg['track6-interface'], $trackcfg);
                killbypid('/var/run/dhcp6c.pid', 'HUP');
            }
            break;
    }
}

function interface_track6_slaac_configure($interface = 'lan', $lancfg)
{
    $realifv6 = get_real_interface($interface, 'inet6');
    log_error("Accept router advertisements on interface {$realifv6}");
    mwexecf('/sbin/ifconfig %s inet6 accept_rtadv -ifdisabled', $realifv6);
}

function interface_track6_6rd_configure($interface = 'lan', $lancfg)
{
    global $config;

    $wancfg = $config['interfaces'][$lancfg['track6-interface']];
    if (empty($wancfg)) {
        log_error("Interface {$interface} tracking non-existent interface {$lancfg['track6-interface']}");
        return;
    }

    $ip4address = get_interface_ip($lancfg['track6-interface']);
    if (!is_ipaddrv4($ip4address)) {
        log_error("The interface IPv4 address '{$ip4address}' on interface '{$lancfg['track6-interface']}' is invalid, not configuring 6RD tracking");
        return;
    }
    $hexwanv4 = return_hex_ipv4($ip4address);

    /* create the long prefix notation for math, save the prefix length */
    $rd6prefix = explode("/", $wancfg['prefix-6rd']);
    $rd6prefixlen = $rd6prefix[1];
    $rd6prefix = Net_IPv6::uncompress($rd6prefix[0]);

    /* binary presentation of the prefix for all 128 bits. */
    $rd6lanbin = convert_ipv6_to_128bit($rd6prefix);

    /* just save the left prefix length bits */
    $rd6lanbin = substr($rd6lanbin, 0, $rd6prefixlen);
    /* add the v4 address, offset n bits from the left */
    $rd6lanbin .= substr(sprintf("%032b", hexdec($hexwanv4)), (0 + $wancfg['prefix-6rd-v4plen']), 32);

    /* add the custom prefix id, max 32bits long? (64 bits - (prefixlen + (32 - v4plen)) */
    /* 64 - (37 + (32 - 17)) = 8 == /52 */
    $restbits = 64 - ($rd6prefixlen + (32 - $wancfg['prefix-6rd-v4plen']));
    // echo "64 - (prefixlen {$rd6prefixlen} + v4len (32 - {$wancfg['prefix-6rd-v4plen']})) = {$restbits} \n";
    $rd6lanbin .= substr(sprintf("%032b", str_pad($lancfg['track6-prefix-id'], 32, "0", STR_PAD_LEFT)), (32 - $restbits), 32);
    /* fill the rest out with zeros */
    $rd6lanbin = str_pad($rd6lanbin, 128, "0", STR_PAD_RIGHT);

    /* convert the 128 bits for the lan address back into a valid IPv6 address */
    $rd6lan = convert_128bit_to_ipv6($rd6lanbin) . (1 + get_interface_number_track6($lancfg['track6-interface'], $interface));

    $lanif = get_real_interface($interface, 'inet6');
    $oip = find_interface_ipv6($lanif);
    if (is_ipaddrv6($oip)) {
        mwexec("/sbin/ifconfig {$lanif} inet6 {$oip} delete");
    }

    log_error("rd6 {$interface} with ipv6 address {$rd6lan} based on {$lancfg['track6-interface']} ipv4 {$ip4address}");
    mwexec("/sbin/ifconfig {$lanif} inet6 {$rd6lan} prefixlen 64");
}

function interface_track6_6to4_configure($interface = 'lan', $lancfg)
{
    $ip4address = get_interface_ip($lancfg['track6-interface']);
    if (!is_ipaddrv4($ip4address) || is_private_ip($ip4address)) {
        log_error("The interface IPv4 address '{$ip4address}' on interface '{$lancfg['track6-interface']}' is not public, not configuring 6to4 tracking");
        return;
    }
    $hexwanv4 = return_hex_ipv4($ip4address);

    /* create the long prefix notation for math, save the prefix length */
    $sixto4prefix = "2002::";
    $sixto4prefixlen = 16;
    $sixto4prefix = Net_IPv6::uncompress($sixto4prefix);

    /* binary presentation of the prefix for all 128 bits. */
    $sixto4lanbin = convert_ipv6_to_128bit($sixto4prefix);

    /* just save the left prefix length bits */
    $sixto4lanbin = substr($sixto4lanbin, 0, $sixto4prefixlen);
    /* add the v4 address */
    $sixto4lanbin .= sprintf("%032b", hexdec($hexwanv4));
    /* add the custom prefix id */
    $sixto4lanbin .= sprintf("%016b", $lancfg['track6-prefix-id']);
    /* fill the rest out with zeros */
    $sixto4lanbin = str_pad($sixto4lanbin, 128, "0", STR_PAD_RIGHT);

    /* convert the 128 bits for the lan address back into a valid IPv6 address */
    $sixto4lan = convert_128bit_to_ipv6($sixto4lanbin) . (1 + get_interface_number_track6($lancfg['track6-interface'], $interface));

    $lanif = get_real_interface($interface, 'inet6');
    $oip = find_interface_ipv6($lanif);
    if (is_ipaddrv6($oip)) {
        mwexec("/sbin/ifconfig {$lanif} inet6 {$oip} delete");
    }

    log_error("sixto4 {$interface} with ipv6 address {$sixto4lan} based on {$lancfg['track6-interface']} ipv4 {$ip4address}");
    mwexec("/sbin/ifconfig {$lanif} inet6 {$sixto4lan} prefixlen 64");
}

function interface_6rd_configure($interface = 'wan', $wancfg)
{
    if (!is_array($wancfg)) {
        return;
    }

    $wanif = get_real_interface($interface);
    $ip4address = find_interface_ip($wanif);

    if (!is_ipaddrv4($ip4address)) {
        log_error("The interface IPv4 address '{$ip4address}' on interface '{$wanif}' is invalid, not configuring 6RD tunnel");
        return false;
    }

    $hexwanv4 = return_hex_ipv4(!empty($wancfg['prefix-6rd-v4addr']) ? $wancfg['prefix-6rd-v4addr'] : $ip4address);

    if (!is_numeric($wancfg['prefix-6rd-v4plen']) || $wancfg['prefix-6rd-v4plen'] < 0 || $wancfg['prefix-6rd-v4plen'] > 32) {
        log_error("The interface IPv4 prefix '{$wancfg['prefix-6rd-v4plen']}' on interface '{$wanif}' is invalid, assuming zero");
        $wancfg['prefix-6rd-v4plen'] = 0;
    }

    /* create the long prefix notation for math, save the prefix length */
    $rd6prefix = explode("/", $wancfg['prefix-6rd']);
    $rd6prefix_isp = $rd6prefix[0];
    $rd6prefixlen = $rd6prefix[1];
    $rd6prefix = Net_IPv6::uncompress($rd6prefix[0]);

    /* binary presentation of the prefix for all 128 bits. */
    $rd6prefixbin = convert_ipv6_to_128bit($rd6prefix);

    /* just save the left prefix length bits */
    $rd6prefixbin = substr($rd6prefixbin, 0, $rd6prefixlen);
    /* if the prefix length is not 32 bits we need to shave bits off from the left of the v4 address. */
    $rd6prefixbin .= substr(sprintf("%032b", hexdec($hexwanv4)), $wancfg['prefix-6rd-v4plen'], 32);
    /* fill out the rest with 0's */
    $rd6prefixbin = str_pad($rd6prefixbin, 128, "0", STR_PAD_RIGHT);

    /* convert the 128 bits for the broker address back into a valid IPv6 address */
    $rd6prefix = convert_128bit_to_ipv6($rd6prefixbin);

    /* use gateway inside original prefix to avoid routing issues */
    $rd6brgw = "{$rd6prefix_isp}{$wancfg['gateway-6rd']}";

    $stfiface = "{$interface}_stf";
    if (does_interface_exist($stfiface)) {
        legacy_interface_destroy($stfiface);
    }
    legacy_interface_create('stf', $stfiface);
    legacy_interface_flags($stfiface, 'link2');

    $mtu = !empty($wancfg['mtu']) ? $wancfg['mtu'] - 20 : 1280;
    if ($mtu > 1480) {
        $mtu = 1480;
    }
    legacy_interface_mtu($stfiface, $mtu);

    # use original prefix length for network address to avoid setting the same subnet as on the LAN side (/64 prefix)
    mwexecf('/sbin/ifconfig %s inet6 %s/%s', array($stfiface, $rd6prefix, $rd6prefixlen));
    mwexecf('/sbin/ifconfig %s stfv4br %s', array($stfiface, $wancfg['gateway-6rd']));
    mwexecf('/sbin/ifconfig %s stfv4net %s/%s', array($stfiface, $ip4address, $wancfg['prefix-6rd-v4plen']));

    file_put_contents("/tmp/{$stfiface}_routerv6", "{$rd6brgw}\n");
    file_put_contents("/tmp/{$stfiface}_defaultgwv6", "{$rd6brgw}\n");

    $gateways = new \OPNsense\Routing\Gateways(legacy_interfaces_details());
    $ip4gateway = $gateways->getInterfaceGateway($interface, "inet");
    if (is_ipaddrv4($ip4gateway)) {
        system_host_route($wancfg['gateway-6rd'], $ip4gateway);
    }

    /* configure dependent interfaces */
    if (!file_exists("/var/run/booting")) {
        link_interface_to_track6($interface, true);
    }
}

function interface_6to4_configure($interface = 'wan', $wancfg)
{
    if (!is_array($wancfg)) {
        return;
    }

    $wanif = get_real_interface($interface);
    $ip4address = find_interface_ip($wanif);
    if (!is_ipaddrv4($ip4address) || is_private_ip($ip4address)) {
        log_error("The interface IPv4 address '{$ip4address}' on interface '{$wanif}' is not public, not configuring 6to4 tunnel");
        return false;
    }

    /* create the long prefix notation for math, save the prefix length */
    $stfprefixlen = 16;
    $stfprefix = Net_IPv6::uncompress("2002::");
    $stfarr = explode(":", $stfprefix);
    $v4prefixlen = "0";

    /* we need the hex form of the interface IPv4 address */
    $ip4arr = explode(".", $ip4address);
    $hexwanv4 = "";
    foreach ($ip4arr as $octet) {
        $hexwanv4 .= sprintf("%02x", $octet);
    }

    /* we need the hex form of the broker IPv4 address */
    $ip4arr = explode(".", "192.88.99.1");
    $hexbrv4 = "";
    foreach ($ip4arr as $octet) {
        $hexbrv4 .= sprintf("%02x", $octet);
    }

    /* binary presentation of the prefix for all 128 bits. */
    $stfprefixbin = "";
    foreach ($stfarr as $element) {
        $stfprefixbin .= sprintf("%016b", hexdec($element));
    }
    /* just save the left prefix length bits */
    $stfprefixstartbin = substr($stfprefixbin, 0, $stfprefixlen);

    /* if the prefix length is not 32 bits we need to shave bits off from the left of the v4 address. */
    $stfbrokerbin = substr(sprintf("%032b", hexdec($hexbrv4)), $v4prefixlen, 32);
    $stfbrokerbin = str_pad($stfprefixstartbin . $stfbrokerbin, 128, "0", STR_PAD_RIGHT);

    /* for the local subnet too. */
    $stflanbin = substr(sprintf("%032b", hexdec($hexwanv4)), $v4prefixlen, 32);
    $stflanbin = str_pad($stfprefixstartbin . $stflanbin, 128, "0", STR_PAD_RIGHT);

    /* convert the 128 bits for the broker address back into a valid IPv6 address */
    $stfbrarr = array();
    $stfbrbinarr = str_split($stfbrokerbin, 16);
    foreach ($stfbrbinarr as $bin) {
        $stfbrarr[] = dechex(bindec($bin));
    }
    $stfbrgw = Net_IPv6::compress(implode(":", $stfbrarr));

    /* convert the 128 bits for the broker address back into a valid IPv6 address */
    $stflanarr = array();
    $stflanbinarr = str_split($stflanbin, 16);
    foreach ($stflanbinarr as $bin) {
        $stflanarr[] = dechex(bindec($bin));
    }
    $stflan = Net_IPv6::compress(implode(":", $stflanarr));

    $stfiface = "{$interface}_stf";
    if (does_interface_exist($stfiface)) {
        legacy_interface_destroy($stfiface);
    }
    legacy_interface_create('stf', $stfiface);
    legacy_interface_flags($stfiface, 'link2');

    $mtu = !empty($wancfg['mtu']) ? $wancfg['mtu'] - 20 : 1280;
    if ($mtu > 1480) {
        $mtu = 1480;
    }
    legacy_interface_mtu($stfiface, $mtu);

    mwexecf('/sbin/ifconfig %s inet6 %s prefixlen 16', array($stfiface, $stflan));

    file_put_contents("/tmp/{$stfiface}_routerv6", "{$stfbrgw}");
    file_put_contents("/tmp/{$stfiface}_defaultgwv6", "{$stfbrgw}");

    $gateways = new \OPNsense\Routing\Gateways(legacy_interfaces_details());
    $ip4gateway = $gateways->getInterfaceGateway($interface, "inet");
    if (is_ipaddrv4($ip4gateway)) {
        system_host_route('192.88.99.1', $ip4gateway);
    }

    if (!file_exists("/var/run/booting")) {
        link_interface_to_track6($interface, true);
    }
}

function interface_dhcpv6_configure($interface = 'wan', $wancfg)
{
    $syscfg = config_read_array('system');

    /* write DUID if override was set */
    if (!empty($syscfg['ipv6duid'])) {
        dhcp6c_duid_write($syscfg['ipv6duid']);
    /* clear DUID if it is faulty */
    } elseif (empty(dhcp6c_duid_read())) {
        dhcp6c_duid_clear();
    }

    if (!is_array($wancfg)) {
        return;
    }

    $realifv6 = get_real_interface($interface, 'inet6');

    /* always kill rtsold in case of reconfigure */
    killbypid('/var/run/rtsold.pid', 'TERM', true);

    $rtsoldcommand = exec_safe(
        '/usr/sbin/rtsold -p %s -O %s -R %s -a',
        array(
            '/var/run/rtsold.pid',
            '/var/etc/rtsold_script.sh',
            '/usr/bin/true', /* XXX missing proper script to refresh resolv.conf */
        )
    );

    if (!empty($syscfg['dhcp6_debug'])) {
        $mode = [ '1' => ' -d', '2' => ' -D' ];
        $rtsoldcommand .= $mode[$syscfg['dhcp6_debug']];
    }

    /* fire up rtsold for IPv6 RAs first */
    mwexec($rtsoldcommand);

    /* unconditional trigger for hybrid approach, reloads without advertisements */
    mwexecf('/var/etc/rtsold_script.sh %s', array($realifv6));
}

function interface_dhcpv6_id($interface)
{
    global $config;

    /* configuration default */
    $id = 0;

    if (empty($config['interfaces'])) {
        return $id;
    }

    /* detect unique index */
    foreach (array_keys($config['interfaces']) as $key) {
        if ($key == $interface) {
            break;
        }
        $id += 1;
    }

    return $id;
}

function interface_dhcpv6_prepare($interface = 'wan', $wancfg, $linkdownevent = false)
{
    if (!is_array($wancfg)) {
        return;
    }

    $wanif = get_real_interface($interface, 'inet6');
    $id = interface_dhcpv6_id($interface);
    $syscfg = config_read_array('system');

    $dhcp6cconf = DHCP6_Config_File_Basic($interface, $wancfg, $wanif, $id);

    // DHCP6 Config File Advanced
    if ($wancfg['adv_dhcp6_config_advanced']) {
        $dhcp6cconf = DHCP6_Config_File_Advanced($interface, $wancfg, $wanif, $id);
    }

    // DHCP6 Config File Override
    if (!empty($wancfg['adv_dhcp6_config_file_override'])) {
        $dhcp6cfile = $wancfg['adv_dhcp6_config_file_override_path'];
        if (file_exists($dhcp6cfile)) {
            $dhcp6cconf = file_get_contents($dhcp6cfile);
            $dhcp6cconf = DHCP6_Config_File_Substitutions($wancfg, $wanif, $dhcp6cconf);
        } else {
            log_error("DHCP6 config file override does not exist: '{$dhcp6cfile}'");
        }
    }

    if (!$linkdownevent) {
        @file_put_contents("/var/etc/dhcp6c_{$interface}.conf", $dhcp6cconf);
    } else {
        @unlink("/var/etc/dhcp6c_{$interface}.conf");
    }

    $dhcp6cscript = "#!/bin/sh\n";
    $dhcp6cscript .= "if [ -n '" . (!empty($syscfg['dhcp6_debug']) ? 'debug' : '') . "' ]; then\n";
    $dhcp6cscript .= "\t/usr/bin/logger -t dhcp6c \"dhcp6c \$REASON on {$wanif}\"\n";
    $dhcp6cscript .= "fi\n";
    $dhcp6cscript .= "case \$REASON in\n";
    $dhcp6cscript .= "REQUEST)\n";
    $dhcp6cscript .= "\tif [ -n \"\${PDINFO}\" ]; then\n";
    $dhcp6cscript .= "\t\techo \${PDINFO} > /tmp/{$wanif}_pdinfo\n";
    $dhcp6cscript .= "\tfi\n";
    $dhcp6cscript .= "\t/usr/bin/logger -t dhcp6c \"dhcp6c \$REASON on {$wanif} - running newipv6\"\n";
    $dhcp6cscript .= "\t/usr/local/opnsense/service/configd_ctl.py interface newipv6 {$wanif}\n";
    $dhcp6cscript .= "\t;;\n";
    $dhcp6cscript .= "EXIT|RELEASE)\n";
    $dhcp6cscript .= "\t/usr/bin/logger -t dhcp6c \"dhcp6c \$REASON on {$wanif} - running newipv6\"\n";
    $dhcp6cscript .= "\trm -f /tmp/{$wanif}_pdinfo\n";
    $dhcp6cscript .= "\t/usr/local/opnsense/service/configd_ctl.py interface newipv6 {$wanif}\n";
    $dhcp6cscript .= "\t;;\n";
    $dhcp6cscript .= "*)\n";
    $dhcp6cscript .= "\t;;\n";
    $dhcp6cscript .= "esac\n";

    @file_put_contents("/var/etc/dhcp6c_{$interface}_script.sh", $dhcp6cscript);
    @chmod("/var/etc/dhcp6c_{$interface}_script.sh", 0755);

    $dhcp6ccommand = exec_safe(
        '/usr/local/sbin/dhcp6c -c %s -p %s',
        array(
            '/var/etc/dhcp6c.conf',
            '/var/run/dhcp6c.pid',
        )
    );

    if (!empty($syscfg['dhcp6_debug'])) {
        $mode = [ '1' => ' -d', '2' => ' -D' ];
        $dhcp6ccommand .= $mode[$syscfg['dhcp6_debug']];
    }

    if (!empty($syscfg['dhcp6_norelease'])) {
        $dhcp6ccommand .= ' -n';
    }

    $dhcp6cconf = '';

    /* merge configs and prepare single instance of dhcp6c for startup */
    foreach (legacy_config_get_interfaces(['enable' => true, 'virtual' => false]) as $_interface => $_wancfg) {
        if (empty($_wancfg['ipaddrv6']) || $_wancfg['ipaddrv6'] != 'dhcp6') {
            continue;
        }

        if (!file_exists("/var/etc/dhcp6c_{$_interface}.conf")) {
            /* config file not yet rendered, defer for later */
            continue;
        }

        $dhcp6cconf .= file_get_contents("/var/etc/dhcp6c_{$_interface}.conf");
    }

    @file_put_contents('/var/etc/dhcp6c.conf', $dhcp6cconf);

    $rtsold_script = <<<EOD
#!/bin/sh
# this file was auto-generated, do not edit
if [ -z "\${1}" ]; then
    echo "Nothing to do."
    exit 0
fi
if [ -n "\${2}" ]; then
    echo \${2} > /tmp/\${1}_routerv6
    echo \${2} > /tmp/\${1}_defaultgwv6
fi
if [ -f /var/run/dhcp6c.pid ]; then
    if ! /bin/pkill -0 -F /var/run/dhcp6c.pid; then
        rm -f /var/run/dhcp6c.pid
    fi
fi
if [ -f /var/run/dhcp6c.pid ]; then
    /usr/bin/logger -t dhcp6c "RTSOLD script - Sending SIGHUP to dhcp6c"
    /bin/pkill -HUP -F /var/run/dhcp6c.pid
else
    /usr/bin/logger -t dhcp6c "RTSOLD script - Starting dhcp6 client"
    {$dhcp6ccommand}
fi

EOD;

    @file_put_contents('/var/etc/rtsold_script.sh', $rtsold_script);
    @chmod('/var/etc/rtsold_script.sh', 0755);
}

function DHCP6_Config_File_Basic($interface, $wancfg, $wanif, $id = 0)
{
    $dhcp6cconf = "interface {$wanif} {\n";

    /* for SLAAC interfaces we do fire off a dhcp6 client for just our name servers */
    if ($wancfg['ipaddrv6'] == "slaac") {
        $dhcp6cconf .= "  information-only;\n";
        $dhcp6cconf .= "  request domain-name-servers;\n";
        $dhcp6cconf .= "  request domain-name;\n";
        $dhcp6cconf .= "  script \"/var/etc/dhcp6c_{$interface}_script.sh\"; # we'd like some nameservers please\n";
        $dhcp6cconf .= "};\n";
    } else {
        /* skip address request if this is set */
        if (!isset($wancfg['dhcp6prefixonly'])) {
            $dhcp6cconf .= "  send ia-na {$id}; # request stateful address\n";
        }
        if (is_numeric($wancfg['dhcp6-ia-pd-len'])) {
            $dhcp6cconf .= "  send ia-pd {$id}; # request prefix delegation\n";
        }

        $dhcp6cconf .= "  request domain-name-servers;\n";
        $dhcp6cconf .= "  request domain-name;\n";
        $dhcp6cconf .= "  script \"/var/etc/dhcp6c_{$interface}_script.sh\"; # we'd like some nameservers please\n";

        $dhcp6cconf .= "};\n";

        if (!isset($wancfg['dhcp6prefixonly'])) {
            $dhcp6cconf .= "id-assoc na {$id} { };\n";
        }

        if (is_numeric($wancfg['dhcp6-ia-pd-len'])) {
            /* Setup the prefix delegation */
            $dhcp6cconf .= "id-assoc pd {$id} {\n";
            if (isset($wancfg['dhcp6-ia-pd-send-hint'])) {
                $preflen = 64 - $wancfg['dhcp6-ia-pd-len'];
                $dhcp6cconf .= "  prefix ::/{$preflen} infinity;\n";
            }
            foreach (link_interface_to_track6($interface) as $friendly => $lancfg) {
                if (is_numeric($lancfg['track6-prefix-id'])) {
                    $trackifv6 = get_real_interface($friendly, 'inet6');
                    $dhcp6cconf .= "  prefix-interface {$trackifv6} {\n";
                    $dhcp6cconf .= "    sla-id {$lancfg['track6-prefix-id']};\n";
                    $dhcp6cconf .= "    sla-len {$wancfg['dhcp6-ia-pd-len']};\n";
                    $dhcp6cconf .= "  };\n";
                }
            }
            $dhcp6cconf .= "};\n";
        }
    }

    return $dhcp6cconf;
}

function DHCP6_Config_File_Advanced($interface, $wancfg, $wanif, $id = 0)
{
    $send_options = "";

    if ($wancfg['adv_dhcp6_interface_statement_send_options'] != '') {
        $options = preg_split('/\s*,\s*(?=(?:[^"]*"[^"]*")*[^"]*$)/', $wancfg['adv_dhcp6_interface_statement_send_options']);
        foreach ($options as $option) {
            $send_options .= "  send {$option};\n";
        }
    }

    $request_options = "";
    if ($wancfg['adv_dhcp6_interface_statement_request_options'] != '') {
        $options = preg_split('/\s*,\s*(?=(?:[^"]*"[^"]*")*[^"]*$)/', $wancfg['adv_dhcp6_interface_statement_request_options']);
        foreach ($options as $option) {
            $request_options .= "  request {$option};\n";
        }
    }

    $information_only = "";
    if ($wancfg['adv_dhcp6_interface_statement_information_only_enable'] != '') {
        $information_only = "  information-only;\n";
    }

    $script = "  script \"/var/etc/dhcp6c_{$interface}_script.sh\";\n";
    if ($wancfg['adv_dhcp6_interface_statement_script'] != '') {
        $script = "  script \"{$wancfg['adv_dhcp6_interface_statement_script']}\";\n";
    }

    $interface_statement  = "interface {$wanif} {\n";
    $interface_statement .= $send_options;
    $interface_statement .= $request_options;
    $interface_statement .= $information_only;
    $interface_statement .= $script;
    $interface_statement .= "};\n";

    $id_assoc_statement_address = "";
    if ($wancfg['adv_dhcp6_id_assoc_statement_address_enable'] != '') {
        $id_assoc_statement_address .= "id-assoc na ";
        if (is_numeric($wancfg['adv_dhcp6_id_assoc_statement_address_id'])) {
            $id_assoc_statement_address .= "{$wancfg['adv_dhcp6_id_assoc_statement_address_id']}";
        } else {
            $id_assoc_statement_address .= $id;
        }
        $id_assoc_statement_address .= " {\n";

        if (
            ($wancfg['adv_dhcp6_id_assoc_statement_address'] != '') &&
            (is_numeric($wancfg['adv_dhcp6_id_assoc_statement_address_pltime']) ||
                $wancfg['adv_dhcp6_id_assoc_statement_address_pltime'] == 'infinity')
        ) {
            $id_assoc_statement_address .= "  address";
            $id_assoc_statement_address .= " {$wancfg['adv_dhcp6_id_assoc_statement_address']}";
            $id_assoc_statement_address .= " {$wancfg['adv_dhcp6_id_assoc_statement_address_pltime']}";
            if (
                is_numeric($wancfg['adv_dhcp6_id_assoc_statement_address_vltime']) ||
                $wancfg['adv_dhcp6_id_assoc_statement_address_vltime'] == 'infinity'
            ) {
                $id_assoc_statement_address .= " {$wancfg['adv_dhcp6_id_assoc_statement_address_vltime']}";
            }
            $id_assoc_statement_address .= ";\n";
        }

        $id_assoc_statement_address  .= "};\n";
    }

    $id_assoc_statement_prefix = "";
    if ($wancfg['adv_dhcp6_id_assoc_statement_prefix_enable'] != '') {
        $id_assoc_statement_prefix .= "id-assoc pd ";
        if (is_numeric($wancfg['adv_dhcp6_id_assoc_statement_prefix_id'])) {
            $id_assoc_statement_prefix .= "{$wancfg['adv_dhcp6_id_assoc_statement_prefix_id']}";
        } else {
            $id_assoc_statement_prefix .= $id;
        }
        $id_assoc_statement_prefix .= " {\n";

        if (
            ($wancfg['adv_dhcp6_id_assoc_statement_prefix'] != '') &&
            (is_numeric($wancfg['adv_dhcp6_id_assoc_statement_prefix_pltime']) ||
            $wancfg['adv_dhcp6_id_assoc_statement_prefix_pltime'] == 'infinity')
        ) {
             $id_assoc_statement_prefix .= "  prefix";
             $id_assoc_statement_prefix .= " {$wancfg['adv_dhcp6_id_assoc_statement_prefix']}";
            $id_assoc_statement_prefix .= " {$wancfg['adv_dhcp6_id_assoc_statement_prefix_pltime']}";
            if (
                (is_numeric($wancfg['adv_dhcp6_id_assoc_statement_prefix_vltime'])) ||
                ($wancfg['adv_dhcp6_id_assoc_statement_prefix_vltime'] == 'infinity')
            ) {
                $id_assoc_statement_prefix .= " {$wancfg['adv_dhcp6_id_assoc_statement_prefix_vltime']}";
            }
            $id_assoc_statement_prefix .= ";\n";
        }

        foreach (link_interface_to_track6($interface) as $friendly => $lancfg) {
            if (is_numeric($lancfg['track6-prefix-id'])) {
                $trackifv6 = get_real_interface($friendly, 'inet6');
                $id_assoc_statement_prefix .= "  prefix-interface {$trackifv6} {\n";
                $id_assoc_statement_prefix .= "    sla-id {$lancfg['track6-prefix-id']};\n";
                if (
                    ($wancfg['adv_dhcp6_prefix_interface_statement_sla_len'] >= 0) &&
                    ($wancfg['adv_dhcp6_prefix_interface_statement_sla_len'] <= 128)
                ) {
                    $id_assoc_statement_prefix .= "    sla-len {$wancfg['adv_dhcp6_prefix_interface_statement_sla_len']};\n";
                }
                $id_assoc_statement_prefix .= "  };\n";
            }
        }

        $id_assoc_statement_prefix  .= "};\n";
    }

    $authentication_statement = "";
    if (
        ($wancfg['adv_dhcp6_authentication_statement_authname'] != '') &&
        ($wancfg['adv_dhcp6_authentication_statement_protocol'] == 'delayed')
    ) {
        $authentication_statement .= "authentication {$wancfg['adv_dhcp6_authentication_statement_authname']} {\n";
        $authentication_statement .= "  protocol {$wancfg['adv_dhcp6_authentication_statement_protocol']};\n";
        if (preg_match("/(hmac(-)?md5)||(HMAC(-)?MD5)/", $wancfg['adv_dhcp6_authentication_statement_algorithm'])) {
            $authentication_statement .= "  algorithm {$wancfg['adv_dhcp6_authentication_statement_algorithm']};\n";
        }
        if ($wancfg['adv_dhcp6_authentication_statement_rdm'] == 'monocounter') {
            $authentication_statement .= "  rdm {$wancfg['adv_dhcp6_authentication_statement_rdm']};\n";
        }
        $authentication_statement .= "};\n";
    }

    $key_info_statement = "";
    if (
        ($wancfg['adv_dhcp6_key_info_statement_keyname'] != '') &&
        ($wancfg['adv_dhcp6_key_info_statement_realm'] != '') &&
        (is_numeric($wancfg['adv_dhcp6_key_info_statement_keyid'])) &&
        ($wancfg['adv_dhcp6_key_info_statement_secret'] != '')
    ) {
        $key_info_statement .= "keyinfo {$wancfg['adv_dhcp6_key_info_statement_keyname']} {\n";
        $key_info_statement .= "  realm \"{$wancfg['adv_dhcp6_key_info_statement_realm']}\";\n";
        $key_info_statement .= "  keyid {$wancfg['adv_dhcp6_key_info_statement_keyid']};\n";
        $key_info_statement .= "  secret \"{$wancfg['adv_dhcp6_key_info_statement_secret']}\";\n";
        if (preg_match("/((([0-9]{4}-)?[0-9]{2}[0-9]{2} )?[0-9]{2}:[0-9]{2})||(foreever)/", $wancfg['adv_dhcp6_key_info_statement_expire'])) {
            $key_info_statement .= "  expire \"{$wancfg['adv_dhcp6_key_info_statement_expire']}\";\n";
        }
        $key_info_statement .= "};\n";
    }

    $dhcp6cconf  = $interface_statement;
    $dhcp6cconf .= $id_assoc_statement_address;
    $dhcp6cconf .= $id_assoc_statement_prefix;
    $dhcp6cconf .= $authentication_statement;
    $dhcp6cconf .= $key_info_statement;

    $dhcp6cconf = DHCP6_Config_File_Substitutions($wancfg, $wanif, $dhcp6cconf);

    return $dhcp6cconf;
}

function DHCP6_Config_File_Substitutions($wancfg, $wanif, $dhcp6cconf)
{
    return DHCP_Config_File_Substitutions($wancfg, $wanif, $dhcp6cconf);
}

function interface_dhcp_configure($interface = 'wan')
{
    global $config;

    $wancfg = $config['interfaces'][$interface];
    if (empty($wancfg)) {
        log_error("Interface '{$interface}' does not have a DHCP configuration.");
        return;
    }

    $wanif = get_real_interface($interface);
    if (empty($wanif)) {
        log_error("Invalid interface '{$interface}' in interface_dhcp_configure()");
        return;
    }

    killbypid("/var/run/dhclient.{$wanif}.pid", 'TERM', true);

    $fd = fopen("/var/etc/dhclient_{$interface}.conf", "w");
    if (!$fd) {
        log_error("Error: cannot open dhclient_{$interface}.conf in interface_dhcp_configure() for writing.");
        return;
    }

    if ($wancfg['dhcphostname']) {
        $dhclientconf_hostname = "send dhcp-client-identifier \"{$wancfg['dhcphostname']}\";\n";
        $dhclientconf_hostname .= "\tsend host-name \"{$wancfg['dhcphostname']}\";\n";
    } else {
        $dhclientconf_hostname = "";
    }

    $dhclientconf = <<<EOD
interface "{$wanif}" {
  timeout 60;
  retry 15;
  select-timeout 0;
  initial-interval 1;
  {$dhclientconf_hostname}
  script "/usr/local/opnsense/scripts/interfaces/dhclient-script";

EOD;

    if (empty($wancfg['dhcphonourmtu'])) {
        $dhclientconf .= "  supersede interface-mtu 0;\n";
    }

    if (is_ipaddrv4($wancfg['dhcprejectfrom'])) {
        $dhclientconf .= "  reject {$wancfg['dhcprejectfrom']};\n";
    }

    $dhclientconf .= "}\n";

    // DHCP Config File Advanced
    if ($wancfg['adv_dhcp_config_advanced']) {
        $dhclientconf = DHCP_Config_File_Advanced($interface, $wancfg, $wanif);
    }

    if (is_ipaddr($wancfg['alias-address'])) {
        $subnetmask = gen_subnet_mask($wancfg['alias-subnet']);
        $dhclientconf .= <<<EOD
alias {
  interface  "{$wanif}";
  fixed-address {$wancfg['alias-address']};
  option subnet-mask {$subnetmask};
}

EOD;
    }

    // DHCP Config File Override
    if (!empty($wancfg['adv_dhcp_config_file_override'])) {
        $dhclientfile = $wancfg['adv_dhcp_config_file_override_path'];
        if (file_exists($dhclientfile)) {
            $dhclientconf = file_get_contents($dhclientfile);
            $dhclientconf = DHCP_Config_File_Substitutions($wancfg, $wanif, $dhclientconf);
        } else {
            log_error("DHCP config file override does not exist: '{$dhclientfile}'");
        }
    }

    fwrite($fd, $dhclientconf);
    fclose($fd);

    interfaces_bring_up($wanif);

    mwexecf('/sbin/dhclient -c %s -p %s %s', array(
        "/var/etc/dhclient_{$interface}.conf",
        "/var/run/dhclient.{$wanif}.pid",
        $wanif
    ));
}

function DHCP_Config_File_Advanced($interface, $wancfg, $wanif)
{
    $hostname = "";
    if ($wancfg['dhcphostname'] != '') {
        $hostname = "\tsend host-name \"{$wancfg['dhcphostname']}\";\n";
    }

    /* DHCP Protocol Timings */
    $protocol_timings = array ('adv_dhcp_pt_timeout' => "timeout", 'adv_dhcp_pt_retry' => "retry", 'adv_dhcp_pt_select_timeout' => "select-timeout", 'adv_dhcp_pt_reboot' => "reboot", 'adv_dhcp_pt_backoff_cutoff' => "backoff-cutoff", 'adv_dhcp_pt_initial_interval' => "initial-interval");
    foreach ($protocol_timings as $Protocol_Timing => $PT_Name) {
        $pt_variable = "{$Protocol_Timing}";
        ${$pt_variable} = "";
        if ($wancfg[$Protocol_Timing] != "") {
            ${$pt_variable} = "{$PT_Name} {$wancfg[$Protocol_Timing]};\n";
        }
    }

    $send_options = "";
    if ($wancfg['adv_dhcp_send_options'] != '') {
        $options = preg_split('/\s*,\s*(?=(?:[^"]*"[^"]*")*[^"]*$)/', $wancfg['adv_dhcp_send_options']);
        foreach ($options as $option) {
            $send_options .= "\tsend " . $option . ";\n";
        }
    }

    $request_options = "";
    if ($wancfg['adv_dhcp_request_options'] != '') {
        $request_options = "\trequest {$wancfg['adv_dhcp_request_options']};\n";
    }

    $required_options = "";
    if ($wancfg['adv_dhcp_required_options'] != '') {
        $required_options = "\trequire {$wancfg['adv_dhcp_required_options']};\n";
    }

    $option_modifiers = "";
    if ($wancfg['adv_dhcp_option_modifiers'] != '') {
        $modifiers = preg_split('/\s*,\s*(?=(?:[^"]*"[^"]*")*[^"]*$)/', $wancfg['adv_dhcp_option_modifiers']);
        foreach ($modifiers as $modifier) {
            $option_modifiers .= "\t" . $modifier . ";\n";
        }
    }

    $dhclientconf = "interface \"{$wanif}\" {\n";
    $dhclientconf .= "\t# DHCP Protocol Timing Values\n";
    $dhclientconf .= "{$adv_dhcp_pt_timeout}";
    $dhclientconf .= "{$adv_dhcp_pt_retry}";
    $dhclientconf .= "{$adv_dhcp_pt_select_timeout}";
    $dhclientconf .= "{$adv_dhcp_pt_reboot}";
    $dhclientconf .= "{$adv_dhcp_pt_backoff_cutoff}";
    $dhclientconf .= "{$adv_dhcp_pt_initial_interval}";
    $dhclientconf .= "\n\t# DHCP Protocol Options\n";
    $dhclientconf .= "{$hostname}";
    $dhclientconf .= "{$send_options}";
    $dhclientconf .= "{$request_options}";
    $dhclientconf .= "{$required_options}";
    $dhclientconf .= "{$option_modifiers}";
    $dhclientconf .= "\n\tscript \"/usr/local/opnsense/scripts/interfaces/dhclient-script\";\n";
    if (empty($wancfg['dhcphonourmtu'])) {
        $dhclientconf .= "\tsupersede interface-mtu 0;\n";
    }
    if (is_ipaddrv4($wancfg['dhcprejectfrom'])) {
        $dhclientconf .= "\treject {$wancfg['dhcprejectfrom']};\n";
    }
    $dhclientconf .= "}\n";

    $dhclientconf = DHCP_Config_File_Substitutions($wancfg, $wanif, $dhclientconf);

    return $dhclientconf;
}

function DHCP_Config_File_Substitutions($wancfg, $wanif, $dhclientconf)
{

    /* Apply Interface Substitutions */
    $dhclientconf = str_replace("{interface}", "{$wanif}", $dhclientconf);

    /* Apply Hostname Substitutions */
    $dhclientconf = str_replace("{hostname}", $wancfg['dhcphostname'], $dhclientconf);

    /* Arrays of MAC Address Types, Cases, Delimiters */
    /* ASCII or HEX, Upper or Lower Case, Various Delimiters (none, space, colon, hyphen, period) */
    $various_mac_types      = array("mac_addr_ascii", "mac_addr_hex");
    $various_mac_cases      = array("U", "L");
    $various_mac_delimiters = array("", " ", ":", "-", ".");

    /* Apply MAC Address Substitutions */
    foreach ($various_mac_types as $various_mac_type) {
        foreach ($various_mac_cases as $various_mac_case) {
            foreach ($various_mac_delimiters as $various_mac_delimiter) {
                $res = stripos($dhclientconf, $various_mac_type . $various_mac_case . $various_mac_delimiter);
                if ($res !== false) {
                    /* Get MAC Address as ASCII String With Colon (:) Celimiters */
                    if ("$various_mac_case" == "U") {
                        $dhcpclientconf_mac = strtoupper(get_interface_mac($wanif));
                    }
                    if ("$various_mac_case" == "L") {
                        $dhcpclientconf_mac = strtolower(get_interface_mac($wanif));
                    }

                    if ("$various_mac_type" == "mac_addr_hex") {
                        /* Convert MAC ascii string to HEX with colon (:) delimiters. */
                        $dhcpclientconf_mac = str_replace(":", "", $dhcpclientconf_mac);
                        $dhcpclientconf_mac_hex = "";
                        $delimiter = "";
                        for ($i = 0; $i < strlen($dhcpclientconf_mac); $i++) {
                            $dhcpclientconf_mac_hex .= $delimiter . bin2hex($dhcpclientconf_mac[$i]);
                            $delimiter = ":";
                        }
                        $dhcpclientconf_mac = $dhcpclientconf_mac_hex;
                    }

                    /* MAC Address Delimiter Substitutions */
                    $dhcpclientconf_mac = str_replace(":", $various_mac_delimiter, $dhcpclientconf_mac);

                    /* Apply MAC Address Substitutions */
                    $dhclientconf = str_replace("{" . $various_mac_type . $various_mac_case . $various_mac_delimiter . "}", $dhcpclientconf_mac, $dhclientconf);
                }
            }
        }
    }

    return $dhclientconf;
}


/* convert fxp0 -> wan, etc. */
function convert_real_interface_to_friendly_interface_name($interface = 'wan')
{
    global $config;

    if (!isset($config['interfaces'])) {
        /* some people do trigger this, I don't know why :) */
        return null;
    }

    // search direct
    $all_interfaces = legacy_config_get_interfaces();
    foreach ($all_interfaces as $if => $ifname) {
        if ($if == $interface || $ifname['if'] == $interface) {
            return $if;
        }
    }

    // search related
    foreach ($all_interfaces as $if => $ifname) {
        if (get_real_interface($if) == $interface) {
            return $if;
        }

        $int = get_parent_interface($if, true);
        if (is_array($int)) {
            foreach ($int as $iface) {
                if ($iface == $interface) {
                    return $if;
                }
            }
        }
    }

    if ($interface == 'enc0') {
        return 'IPsec';
    }

    return null;
}

function convert_friendly_interface_to_friendly_descr($interface)
{
    global $config;

    $ifdesc = $interface;

    switch ($interface) {
        case 'l2tp':
            $ifdesc = 'L2TP';
            break;
        case 'pptp':
            $ifdesc = 'PPTP';
            break;
        case 'pppoe':
            $ifdesc = 'PPPoE';
            break;
        case 'openvpn':
            /* XXX practically unneeded as we are rendering virtual interfaces to the config */
            $ifdesc = 'OpenVPN';
            break;
        case 'enc0':
        case 'ipsec':
        case 'IPsec':
            /* XXX practically unneeded as we are rendering virtual interfaces to the config */
            /* XXX it should also be noted that 'enc0' is the only proper way for this lookup */
            $ifdesc = 'IPsec';
            break;
        default:
            if (isset($config['interfaces'][$interface])) {
                return !empty($config['interfaces'][$interface]['descr']) ?
                    $config['interfaces'][$interface]['descr'] : strtoupper($interface);
            } elseif (stristr($interface, '_vip')) {
                if (isset($config['virtualip']['vip'])) {
                    foreach ($config['virtualip']['vip'] as $counter => $vip) {
                        if ($vip['mode'] == 'carp') {
                            if ($interface == "{$vip['interface']}_vip{$vip['vhid']}") {
                                return "{$vip['subnet']} - {$vip['descr']}";
                            }
                        }
                    }
                }
            } else {
                foreach (legacy_config_get_interfaces(array('virtual' => false)) as $if => $ifcfg) {
                    if ($if == $interface || $ifcfg['descr'] == $interface) {
                        return $ifcfg['ifdescr'];
                    }
                }
            }
            break;
    }

    return $ifdesc;
}

/*
 *  get_parent_interface($interface):
 *      --returns the (real or virtual) parent interface(s) array for a given interface friendly name (i.e. wan)
 *        or virtual interface (i.e. vlan)
 *        (We need array because MLPPP and bridge interfaces have more than one parent.)
 *      -- returns $interface passed in if $interface parent is not found
 *      -- returns empty array if an invalid interface is passed
 *  (Only handles ppps and vlans now.)
 */
function get_parent_interface($interface, $avoidrecurse = false)
{
    global $config;

    $parents = array();

    $realif = get_real_interface($interface);

    // If we got a real interface, find it's friendly assigned name
    if ($interface == $realif && $avoidrecurse == false) {
        $interface = convert_real_interface_to_friendly_interface_name($interface);
    }

    if (!empty($interface) && isset($config['interfaces'][$interface])) {
        $ifcfg = $config['interfaces'][$interface];
        switch ($ifcfg['ipaddr']) {
            case "ppp":
            case "pppoe":
            case "pptp":
            case "l2tp":
                if (empty($parents)) {
                    if (isset($config['ppps']['ppp'])) {
                        foreach ($config['ppps']['ppp'] as $pppidx => $ppp) {
                            if ($ifcfg['if'] == $ppp['if']) {
                                $ports = explode(',', $ppp['ports']);
                                foreach ($ports as $pid => $parent_if) {
                                    $parents[$pid] = get_real_interface($parent_if);
                                }
                                break;
                            }
                        }
                    }
                }
                break;
            case "dhcp":
            case "static":
            default:
                // Handle _vlans
                if (stristr($realif, "_vlan")) {
                    if (isset($config['vlans']['vlan'])) {
                        foreach ($config['vlans']['vlan'] as $vlanidx => $vlan) {
                            if ($ifcfg['if'] == $vlan['vlanif']) {
                                $parents[0] = $vlan['if'];
                                break;
                            }
                        }
                    }
                }
                break;
        }
    }

    if (empty($parents)) {
        $parents[0] = $realif;
    }

    return $parents;
}


function interface_get_wireless_base($wlif)
{
    if (!stristr($wlif, "_wlan")) {
        return $wlif;
    } else {
        return substr($wlif, 0, stripos($wlif, "_wlan"));
    }
}

function interface_get_wireless_clone($wlif)
{
    if (!stristr($wlif, "_wlan")) {
        return $wlif . "_wlan0";
    } else {
        return $wlif;
    }
}

function get_real_interface($interface = 'wan', $family = 'all')
{
    global $config;

    $realif = $interface;

    switch ($interface) {
        case 'openvpn':
        case 'ppp':
            $realif = $interface;
            break;
        case 'ipsec':
        case 'enc0':
            $realif = 'enc0';
            break;
        default:
            if (empty($config['interfaces'][$interface])) {
                /* assume the interface exists, most code does not verify our return value anyway */
                break;
            }

            $cfg = &config_read_array('interfaces', $interface);

            if ($family == 'inet6') {
                switch (isset($cfg['ipaddrv6']) ? $cfg['ipaddrv6'] : '') {
                    case '6rd':
                    case '6to4':
                        $realif = "{$interface}_stf";
                        break;
                    case 'pppoe':
                    case 'ppp':
                    case 'l2tp':
                    case 'pptp':
                        if (isset($cfg['wireless']) || match_wireless_interface($cfg['if'])) {
                            $realif = interface_get_wireless_clone($cfg['if']);
                        } else {
                            $realif = $cfg['if'];
                        }
                        break;
                    default:
                        switch ($cfg['ipaddr']) {
                            case 'pppoe':
                            case 'ppp':
                            case 'l2tp':
                            case 'pptp':
                                if (isset($cfg['dhcp6usev4iface'])) {
                                    $realif = $cfg['if'];
                                } else {
                                    $parents = get_parent_interface($interface);
                                    if (!empty($parents[0])) {
                                        $realif = $parents[0];
                                    } else {
                                        $realif = $cfg['if'];
                                    }
                                }
                                break;
                            default:
                                if (isset($cfg['wireless']) || match_wireless_interface($cfg['if'])) {
                                    $realif = interface_get_wireless_clone($cfg['if']);
                                } else {
                                    $realif = $cfg['if'];
                                }
                                break;
                        }
                        break;
                }
            } else {
                // Wireless cloned NIC support (FreeBSD 8+)
                // interface name format: $parentnic_wlanparentnic#
                // example: ath0_wlan0
                if (isset($cfg['wireless']) || match_wireless_interface($cfg['if'])) {
                    $realif = interface_get_wireless_clone($cfg['if']);
                } else {
                    $realif = $cfg['if'];
                }
            }
            break;
    }

    return $realif;
}

function guess_interface_from_ip($ipaddress)
{
    if (is_ipaddrv4($ipaddress)) {
        $family = "inet";
    } elseif (is_ipaddrv6($ipaddress)) {
        $family = "inet6";
    } else {
        return false;
    }

    /* create a route table we can search */
    exec("/usr/bin/netstat -rnWf " . $family, $output, $ret);
    foreach ($output as $line) {
        $fields = preg_split("/\s+/", $line);
        if (is_subnet($fields[0])) {
            if (ip_in_subnet($ipaddress, $fields[0])) {
                return $fields[5];
            }
        }
    }

    $ret = exec_command("/sbin/route -n get {$ipaddress} | /usr/bin/awk '/interface/ { print \$2; };'");
    if (empty($ret)) {
        return false;
    }
    return $ret;
}

function get_interface_number_track6($wanif, $targetif)
{
    $list = link_interface_to_track6($wanif);
    $number = 0;

    foreach (array_keys($list) as $lanif) {
        if ($lanif == $targetif) {
            return $number;
        }
        $number += 1;
    }

    /* if we fail give backwards-compat */
    return 0;
}

function link_interface_to_track6($wanif, $update = false)
{
    $list = array();

    if (empty($wanif)) {
        return $list;
    }

    $wancfg = config_read_array('interfaces', $wanif);

    if (!isset($wancfg['enable']) || empty($wancfg['ipaddrv6'])) {
        return $list;
    }

    foreach (legacy_config_get_interfaces(array('virtual' => false)) as $lanif => $lancfg) {
        if (!isset($lancfg['enable']) || empty($lancfg['ipaddrv6'])) {
            continue;
        }

        if ($lancfg['ipaddrv6'] == 'track6' && $lancfg['track6-interface'] == $wanif) {
            $list[$lanif] = $lancfg;
        }
    }

    if ($update) {
        if ($wancfg['ipaddrv6'] == 'slaac') {
            plugins_configure('dhcp', false, array('inet6'));
        }

        foreach ($list as $lanif => $lancfg) {
            interface_track6_configure($lanif, $lancfg);
        }
    }

    return $list;
}

/****f* interfaces/link_interface_to_bridge
 * NAME
 *   link_interface_to_bridge - Finds out a bridge group for an interface
 * INPUTS
 *   $ip
 * RESULT
 *   bridge[0-99]
 ******/
function link_interface_to_bridge($int)
{
    global $config;

    if (isset($config['bridges']['bridged'])) {
        foreach ($config['bridges']['bridged'] as $bridge) {
            if (in_array($int, explode(',', $bridge['members']))) {
                return "{$bridge['bridgeif']}";
            }
        }
    }
}

function link_interface_to_group($int)
{
    global $config;

    $result = array();

    if (isset($config['ifgroups']['ifgroupentry'])) {
        foreach ($config['ifgroups']['ifgroupentry'] as $group) {
            if (in_array($int, explode(" ", $group['members']))) {
                $result[$group['ifname']] = $int;
            }
        }
    }

    return $result;
}

function link_interface_to_gre($interface)
{
    global $config;

    $result = array();

    if (isset($config['gres']['gre'])) {
        foreach ($config['gres']['gre'] as $gre) {
            if ($gre['if'] == $interface) {
                $result[] = $gre;
            }
        }
    }

    return $result;
}

function link_interface_to_gif($interface)
{
    global $config;

    $result = array();

    if (isset($config['gifs']['gif'])) {
        foreach ($config['gifs']['gif'] as $gif) {
            if ($gif['if'] == $interface) {
                $result[] = $gif;
            }
        }
    }

    return $result;
}

function find_interface_ip($realif, $ifconfig_details = null)
{
    $ifinfo = legacy_get_interface_addresses($realif, $ifconfig_details);

    if (!isset($ifinfo['ipaddr'])) {
        return null;
    }

    return $ifinfo['ipaddr'];
}

function find_interface_network($realif, $subnet = true, $ifconfig_details = null)
{
    $ifinfo = legacy_get_interface_addresses($realif, $ifconfig_details);

    if (!isset($ifinfo['ipaddr']) || !isset($ifinfo['subnetbits'])) {
        return null;
    }

    $net = $ifinfo['subnetbits'];
    $addr = $ifinfo['ipaddr'];

    if ($subnet) {
        $addr = gen_subnet($addr, $net);
    }

    return "{$addr}/${net}";
}

function find_interface_ipv6($realif, $ifconfig_details = null)
{
    $ifinfo = legacy_get_interface_addresses($realif, $ifconfig_details);

    if (!isset($ifinfo['ipaddr6'])) {
        return null;
    }

    return $ifinfo['ipaddr6'];
}

function find_interface_networkv6($realif, $subnet = true, $ifconfig_details = null)
{
    $ifinfo = legacy_get_interface_addresses($realif, $ifconfig_details);

    if (!isset($ifinfo['ipaddr6']) || !isset($ifinfo['subnetbits6'])) {
        return null;
    }

    $net = $ifinfo['subnetbits6'];
    $addr = $ifinfo['ipaddr6'];

    if ($subnet) {
        $addr = gen_subnetv6($addr, $net);
    }

    return "{$addr}/${net}";
}

function find_interface_ipv6_ll($realif, $ifconfig_details = null)
{
    $ifinfo = legacy_get_interface_addresses($realif, $ifconfig_details);

    if (!isset($ifinfo['ipaddr6_ll'])) {
        return null;
    }

    return $ifinfo['ipaddr6_ll'];
}

function ip_in_interface_alias_subnet($interface, $ipalias)
{
    global $config;

    if (empty($interface) || !is_ipaddr($ipalias)) {
        return false;
    }
    if (isset($config['virtualip']['vip'])) {
        foreach ($config['virtualip']['vip'] as $vip) {
            switch ($vip['mode']) {
                case "ipalias":
                    if ($vip['interface'] != $interface) {
                        break;
                    }
                    $subnet = is_ipaddrv6($ipalias) ? gen_subnetv6($vip['subnet'], $vip['subnet_bits']) : gen_subnet($vip['subnet'], $vip['subnet_bits']);
                    if (ip_in_subnet($ipalias, $subnet . "/" . $vip['subnet_bits'])) {
                        return true;
                    }
                    break;
            }
        }
    }

    return false;
}

function get_interface_ip($interface = 'wan', $ifconfig_details = null)
{
    /* XXX maybe we should make a proper lookup */
    if (strstr($interface, '_vip')) {
        return get_configured_carp_interface_list($interface);
    }

    $realif = get_real_interface($interface, $ifconfig_details);

    return find_interface_ip($realif);
}

function get_interface_ipv6($interface = 'wan', $ifconfig_details = null)
{
    /* XXX maybe we should make a proper lookup */
    if (strstr($interface, '_vip')) {
        return get_configured_carp_interface_list($interface, 'inet6');
    }

    list ($ipv6) = interfaces_primary_address6($interface, null, $ifconfig_details);

    return $ipv6;
}

/****f* interfaces/is_interface_wireless
 * NAME
 *   is_interface_wireless - Returns if an interface is wireless
 * RESULT
 *   $tmp       - Returns if an interface is wireless
 ******/
function is_interface_wireless($interface)
{
    global $config;

    $friendly = convert_real_interface_to_friendly_interface_name($interface);
    if (!isset($config['interfaces'][$friendly]['wireless'])) {
        if (match_wireless_interface($interface)) {
            config_read_array('interfaces', $friendly, 'wireless');
            return true;
        }
        return false;
    }

    return true;
}

function get_interface_mac($interface, $ifconfig_details = null)
{
    $macinfo = legacy_get_interface_addresses($interface, $ifconfig_details);

    return $macinfo["macaddr"];
}

function get_vip_descr($ipaddress)
{
    global $config;

    foreach ($config['virtualip']['vip'] as $vip) {
        if ($vip['subnet'] == $ipaddress) {
            return ($vip['descr']);
        }
    }

    return '';
}

function interfaces_staticarp_configure($if)
{
    global $config;

    $ifcfg = $config['interfaces'][$if];
    if (empty($ifcfg['if']) || !isset($ifcfg['enable'])) {
        return;
    }

    if (isset($config['dhcpd'][$if]['staticarp'])) {
        mwexecf('/sbin/ifconfig %s staticarp', array($ifcfg['if']));
        if (!file_exists("/var/run/booting")) {
            // XXX: arp is really slow when there are many interfaces, while booting -d -a shouldn't be needed anyway
            mwexecf('/usr/sbin/arp -d -i %s  -a > /dev/null 2>&1', array($ifcfg['if']));
        }
        if (isset($config['dhcpd'][$if]['staticmap'])) {
            foreach ($config['dhcpd'][$if]['staticmap'] as $arpent) {
                if (isset($arpent['ipaddr'])) {
                    mwexecf(
                        '/usr/sbin/arp -s %s %s',
                        array($arpent['ipaddr'], $arpent['mac'])
                    );
                }
            }
        }
    } else {
        mwexecf('/sbin/ifconfig %s -staticarp', array($ifcfg['if']));
        if (!file_exists("/var/run/booting")) {
            // XXX: arp is really slow when there are many interfaces, while booting -d -a shouldn't be needed anyway
            mwexecf('/usr/sbin/arp -d -i %s  -a > /dev/null 2>&1', array($ifcfg['if']));
        }
        if (isset($config['dhcpd'][$if]['staticmap'])) {
            foreach ($config['dhcpd'][$if]['staticmap'] as $arpent) {
                if (isset($arpent['arp_table_static_entry'])) {
                    if (isset($arpent['ipaddr'])) {
                        mwexecf(
                            '/usr/sbin/arp -s %s %s',
                            array($arpent['ipaddr'], $arpent['mac'])
                        );
                    }
                }
            }
        }
    }
}

function get_interfaces_info()
{
    global $config;

    $all_intf_details = legacy_interfaces_details();
    $all_intf_stats = legacy_interface_stats();
    $gateways = new \OPNsense\Routing\Gateways($all_intf_details);
    $ifup = legacy_interface_listget('up');
    $result = array();

    foreach (legacy_config_get_interfaces(array('virtual' => false)) as $ifdescr => $ifcnf) {
        $ifinfo = array();

        $ifinfo['if'] = get_real_interface($ifdescr);
        $ifinfo['ifv6'] = get_real_interface($ifdescr, 'inet6');

        $ifinfo['status'] = (is_array($ifup) && in_array($ifinfo['if'], $ifup)) ? 'up' : 'down';
        $ifinfo['statusv6'] = (is_array($ifup) && in_array($ifinfo['ifv6'], $ifup)) ? 'up' : 'down';

        if (!empty($all_intf_details[$ifinfo['if']])) {
            if (
                isset($all_intf_details[$ifinfo['if']]['status']) &&
                    in_array($all_intf_details[$ifinfo['if']]['status'], array('active', 'running'))
            ) {
                $all_intf_details[$ifinfo['if']]['status'] = $ifinfo['status'];
            }
            $ifinfo = array_merge($ifinfo, $all_intf_details[$ifinfo['if']]);
        }

        if (!empty($ifinfo['ipv4'])) {
            $ifinfo['ipaddr'] = $ifinfo['ipv4'][0]['ipaddr'];
            $ifinfo['subnet'] = $ifinfo['ipv4'][0]['subnetbits'];
        }

        if (!empty($all_intf_details[$ifinfo['ifv6']]['ipv6'])) {
            /* rewrite always as it can be a different interface */
            $ifinfo['ipv6'] = $all_intf_details[$ifinfo['ifv6']]['ipv6'];
        } elseif ($ifinfo['if'] != !$ifinfo['ifv6']) {
            /* clear on a mismatch to avoid wrong data here */
            $ifinfo['ipv6'] = [];
        }

        if (!empty($ifinfo['ipv6'])) {
            list ($primary6, $unused, $subnet6) = interfaces_primary_address6($ifdescr, $ifinfo['ifv6'], $all_intf_details);
            if (!empty($primary6)) {
                $ifinfo['ipaddrv6'] = $primary6;
                $ifinfo['subnetv6'] = $subnet6;
            }
            foreach ($ifinfo['ipv6'] as $ipv6addr) {
                if (!empty($ipv6addr['link-local'])) {
                    $ifinfo['linklocal'] = $ipv6addr['ipaddr'];
                } elseif (empty($ifinfo['ipaddrv6'])) {
                    $ifinfo['ipaddrv6'] = $ipv6addr['ipaddr'];
                    $ifinfo['subnetv6'] = $ipv6addr['subnetbits'];
                }
            }
        }

        /* XXX there are more magic files */
        $aux = @file_get_contents("/tmp/{$ifinfo['ifv6']}_pdinfo");
        if (!empty($aux)) {
            $ifinfo['pdinfo'] = $aux;
        }

        $ifinfotmp = $all_intf_stats[$ifinfo['if']];
        $ifinfo['inerrs'] = $ifinfotmp['input errors'];
        $ifinfo['outerrs'] = $ifinfotmp['output errors'];
        $ifinfo['collisions'] = $ifinfotmp['collisions'];

        $link_type = $config['interfaces'][$ifdescr]['ipaddr'];
        switch ($link_type) {
            case 'dhcp':
                $ifinfo['dhcplink'] = isvalidpid("/var/run/dhclient.{$ifinfo['if']}.pid") ? 'up' : 'down';
                break;
            /* PPPoE/PPTP/L2TP interface? -> get status from virtual interface */
            case "pppoe":
            case "pptp":
            case "l2tp":
                if ($ifinfo['status'] == "up") {
                    /* XXX get PPPoE link status for dial on demand */
                    $ifinfo["{$link_type}link"] = "up";
                } else {
                    $ifinfo["{$link_type}link"] = "down";
                }
                break;
            /* PPP interface? -> get uptime for this session and cumulative uptime from the persistent log file in conf */
            case "ppp":
                if ($ifinfo['status'] == "up") {
                    $ifinfo['ppplink'] = "up";
                } else {
                    $ifinfo['ppplink'] = "down";
                }

                if (empty($ifinfo['status'])) {
                    $ifinfo['status'] = "down";
                }

                if (isset($config['ppps']['ppp'])) {
                    foreach ($config['ppps']['ppp'] as $pppid => $ppp) {
                        if ($config['interfaces'][$ifdescr]['if'] == $ppp['if']) {
                            break;
                        }
                    }
                }
                $dev = $ppp['ports'];
                if ($config['interfaces'][$ifdescr]['if'] != $ppp['if'] || empty($dev)) {
                    break;
                }
                if (!file_exists($dev)) {
                    $ifinfo['nodevice'] = 1;
                    $ifinfo['pppinfo'] = $dev . " " . gettext("device not present! Is the modem attached to the system?");
                }

                $usbmodemoutput = array();
                exec("usbconfig", $usbmodemoutput);
                $mondev = "/tmp/3gstats.{$ifdescr}";
                if (file_exists($mondev)) {
                    $cellstats = file($mondev);
                    /* skip header */
                    $a_cellstats = explode(",", $cellstats[1]);
                    if (preg_match("/huawei/i", implode("\n", $usbmodemoutput))) {
                        $ifinfo['cell_rssi'] = huawei_rssi_to_string($a_cellstats[1]);
                        $ifinfo['cell_mode'] = huawei_mode_to_string($a_cellstats[2], $a_cellstats[3]);
                        $ifinfo['cell_simstate'] = huawei_simstate_to_string($a_cellstats[10]);
                        $ifinfo['cell_service'] = huawei_service_to_string(trim($a_cellstats[11]));
                    }
                    if (preg_match("/zte/i", implode("\n", $usbmodemoutput))) {
                        $ifinfo['cell_rssi'] = zte_rssi_to_string($a_cellstats[1]);
                        $ifinfo['cell_mode'] = zte_mode_to_string($a_cellstats[2], $a_cellstats[3]);
                        $ifinfo['cell_simstate'] = zte_simstate_to_string($a_cellstats[10]);
                        $ifinfo['cell_service'] = zte_service_to_string(trim($a_cellstats[11]));
                    }
                    $ifinfo['cell_upstream'] = $a_cellstats[4];
                    $ifinfo['cell_downstream'] = trim($a_cellstats[5]);
                    $ifinfo['cell_sent'] = $a_cellstats[6];
                    $ifinfo['cell_received'] = trim($a_cellstats[7]);
                    $ifinfo['cell_bwupstream'] = $a_cellstats[8];
                    $ifinfo['cell_bwdownstream'] = trim($a_cellstats[9]);
                }
                // Calculate cumulative uptime for PPP link. Useful for connections that have per minute/hour contracts so you don't go over!
                if (isset($ppp['uptime'])) {
                    $ifinfo['ppp_uptime_accumulated'] = "(" . get_ppp_uptime($ifinfo['if']) . ")";
                }
                break;
            default:
                break;
        }

        if (file_exists("/var/run/{$link_type}_{$ifdescr}.pid")) {
            $sec = trim(`/usr/local/opnsense/scripts/interfaces/ppp-uptime.sh {$ifinfo['if']}`);
            $ifinfo['ppp_uptime'] = convert_seconds_to_hms($sec);
        }

        if ($ifinfo['status'] == "up") {
            $wifconfiginfo = array();
            if (is_interface_wireless($ifdescr)) {
                exec("/sbin/ifconfig {$ifinfo['if']} list sta", $wifconfiginfo);
                array_shift($wifconfiginfo);
            }
            foreach ($wifconfiginfo as $ici) {
                $elements = preg_split("/[ ]+/i", $ici);
                if ($elements[0] != "") {
                    $ifinfo['bssid'] = $elements[0];
                }
                if ($elements[3] != "") {
                    $ifinfo['rate'] = $elements[3];
                }
                if ($elements[4] != "") {
                    $ifinfo['rssi'] = $elements[4];
                }
            }
            $gateway = $gateways->getInterfaceGateway($ifdescr, 'inet');
            if (!empty($gateway)) {
                $ifinfo['gateway'] = $gateway;
            }
            $gatewayv6 = $gateways->getInterfaceGateway($ifdescr, 'inet6');
            if (!empty($gatewayv6)) {
                $ifinfo['gatewayv6'] = $gatewayv6;
            }
        }

        $bridge = link_interface_to_bridge($ifdescr);
        if ($bridge) {
            $bridge_text = `/sbin/ifconfig {$bridge}`;
            if (stristr($bridge_text, 'blocking') != false) {
                $ifinfo['bridge'] = "<b><span class='text-danger'>" . gettext("blocking") . "</span></b> - " . gettext("check for ethernet loops");
                $ifinfo['bridgeint'] = $bridge;
            } elseif (stristr($bridge_text, 'learning') != false) {
                $ifinfo['bridge'] = gettext("learning");
                $ifinfo['bridgeint'] = $bridge;
            } elseif (stristr($bridge_text, 'forwarding') != false) {
                $ifinfo['bridge'] = gettext("forwarding");
                $ifinfo['bridgeint'] = $bridge;
            }
        }
        $result[$ifdescr] = $ifinfo;
    }

    return $result;
}

function convert_seconds_to_hms($sec)
{
    $min = $hrs = 0;
    if ($sec != 0) {
        $min = floor($sec / 60);
        $sec %= 60;
    }
    if ($min != 0) {
        $hrs = floor($min / 60);
        $min %= 60;
    }
    if ($sec < 10) {
        $sec = "0" . $sec;
    }
    if ($min < 10) {
        $min = "0" . $min;
    }
    if ($hrs < 10) {
        $hrs = "0" . $hrs;
    }
    $result = $hrs . ":" . $min . ":" . $sec;
    return $result;
}

function huawei_rssi_to_string($rssi)
{
    $dbm = array();
    $i = 0;
    $dbstart = -113;
    while ($i < 32) {
        $dbm[$i] = $dbstart + ($i * 2);
        $i++;
    }
    $percent = round(($rssi / 31) * 100);
    $string = "rssi:{$rssi} level:{$dbm[$rssi]}dBm percent:{$percent}%";
    return $string;
}

function huawei_mode_to_string($mode, $submode)
{
    $modes[0] = "None";
    $modes[1] = "AMPS";
    $modes[2] = "CDMA";
    $modes[3] = "GSM/GPRS";
    $modes[4] = "HDR";
    $modes[5] = "WCDMA";
    $modes[6] = "GPS";

    $submodes[0] = "No Service";
    $submodes[1] = "GSM";
    $submodes[2] = "GPRS";
    $submodes[3] = "EDGE";
    $submodes[4] = "WCDMA";
    $submodes[5] = "HSDPA";
    $submodes[6] = "HSUPA";
    $submodes[7] = "HSDPA+HSUPA";
    $submodes[8] = "TD-SCDMA";
    $submodes[9] = "HSPA+";
    $string = "{$modes[$mode]}, {$submodes[$submode]} Mode";
    return $string;
}

function huawei_service_to_string($state)
{
    $modes[0] = "No";
    $modes[1] = "Restricted";
    $modes[2] = "Valid";
    $modes[3] = "Restricted Regional";
    $modes[4] = "Powersaving";
    $string = "{$modes[$state]} Service";
    return $string;
}

function huawei_simstate_to_string($state)
{
    $modes[0] = "Invalid SIM/locked";
    $modes[1] = "Valid SIM";
    $modes[2] = "Invalid SIM CS";
    $modes[3] = "Invalid SIM PS";
    $modes[4] = "Invalid SIM CS/PS";
    $modes[255] = "Missing SIM";
    $string = "{$modes[$state]} State";
    return $string;
}

function zte_rssi_to_string($rssi)
{
    return huawei_rssi_to_string($rssi);
}

function zte_mode_to_string($mode, $submode)
{
    $modes[0] = "No Service";
    $modes[1] = "Limited Service";
    $modes[2] = "GPRS";
    $modes[3] = "GSM";
    $modes[4] = "UMTS";
    $modes[5] = "EDGE";
    $modes[6] = "HSDPA";

    $submodes[0] = "CS_ONLY";
    $submodes[1] = "PS_ONLY";
    $submodes[2] = "CS_PS";
    $submodes[3] = "CAMPED";
    $string = "{$modes[$mode]}, {$submodes[$submode]} Mode";
    return $string;
}

function zte_service_to_string($state)
{
    $modes[0] = "Initializing";
    $modes[1] = "Network Lock error";
    $modes[2] = "Network Locked";
    $modes[3] = "Unlocked or correct MCC/MNC";
    $string = "{$modes[$state]} Service";
    return $string;
}

function zte_simstate_to_string($state)
{
    $modes[0] = "No action";
    $modes[1] = "Network lock";
    $modes[2] = "(U)SIM card lock";
    $modes[3] = "Network Lock and (U)SIM card Lock";
    $string = "{$modes[$state]} State";
    return $string;
}

/* Compute the total uptime from the ppp uptime log file in the conf directory */
function get_ppp_uptime($port)
{
    if (file_exists("/conf/{$port}.log")) {
        $saved_time = file_get_contents("/conf/{$port}.log");
        $uptime_data = explode("\n", $saved_time);
        $sec = 0;
        foreach ($uptime_data as $upt) {
            $sec += substr($upt, 1 + strpos($upt, " "));
        }
        return convert_seconds_to_hms($sec);
    } else {
        $total_time = gettext("No history data found!");
        return $total_time;
    }
}

/****f* legacy/is_ipaddr_configured
 * NAME
 *   is_ipaddr_configured
 * INPUTS
 *   IP Address to check.
 * RESULT
 *   returns true if the IP Address is
 *   configured and present on this device.
*/
function is_ipaddr_configured($ipaddr, $ignore_if = '')
{
    $if = get_real_interface($ignore_if);
    $interface_list_ips = get_configured_ip_addresses();

    if (empty($interface_list_ips[$ipaddr]) || $interface_list_ips[$ipaddr] == $if) {
        return false;
    } else {
        return true;
    }
}

/*
 * get_carp_interface_status($carpinterface): returns the status of a carp ip
 */
function get_carp_interface_status($carpinterface)
{
    $carp_query = [];

    /* XXX: Need to find a better way for this! */
    list ($interface, $vhid) = explode("_vip", $carpinterface);
    $interface = get_real_interface($interface);
    exec("/sbin/ifconfig $interface | /usr/bin/grep -v grep | /usr/bin/grep carp: | /usr/bin/grep 'vhid {$vhid}'", $carp_query);
    foreach ($carp_query as $int) {
        if (stristr($int, "MASTER")) {
            return gettext("MASTER");
        } elseif (stristr($int, "BACKUP")) {
            return gettext("BACKUP");
        } elseif (stristr($int, "INIT")) {
            return gettext("INIT");
        }
    }
    return gettext('DISABLED');
}

function make_ipv6_64_address($prefix, $suffix)
{
    $prefix_array = explode(':', Net_IPv6::uncompress($prefix));
    $suffix_array = explode(':', Net_IPv6::uncompress($suffix));

    /* unconditionally merge right side of /64 */
    foreach (array(4, 5, 6, 7) as $index) {
        $prefix_array[$index] = $suffix_array[$index];
    }

    /* XXX correctly merging the left side of /64 requires user-specified prefix length */
    foreach (array(0, 1, 2, 3) as $index) {
        if ((int)$suffix_array[$index] === 0) {
            continue;
        }
        $prefix_array[$index] = $suffix_array[$index];
    }

    return implode(':', $prefix_array);
}

function interfaces_addresses($interfaces, $as_subnet = false, $ifconfig_details = null)
{
    global $config;

    $realifs = array();
    $result = array();

    if (!is_array($interfaces)) {
        $interfaces = array($interfaces);
    }

    foreach ($interfaces as $interface) {
        if (isset($config['interfaces'][$interface])) {
            foreach (array('all', 'inet6') as $family) {
                $realifs[] = get_real_interface($interface, $family);
            }
        } else {
            /* take interfaces as is */
            $realifs[] = $interface;
        }
    }

    if (!count($realifs)) {
        return $result;
    }


    $realifs = array_unique($realifs);
    if (!empty($ifconfig_details)) {
        $intf_details = $ifconfig_details;
    } else {
        $intf_details = count($realifs) > 1 ? legacy_interfaces_details() : legacy_interfaces_details($realifs[0]);
    }

    foreach ($realifs as $realif) {
        foreach (array('ipv4', 'ipv6') as $proto) {
            if (empty($intf_details[$realif][$proto])) {
                continue;
            }
            foreach ($intf_details[$realif][$proto] as $address) {
                if (!empty($address['tunnel']) || empty($address['ipaddr'])) {
                    continue;
                }
                $scope = '';
                if (!empty($address['link-local'])) {
                    $scope = "%{$realif}";
                }
                $suffix = '';
                if ($as_subnet) {
                    if (empty($address['subnetbits'])) {
                        continue;
                    }
                    $suffix = "/{$address['subnetbits']}";
                    $scope = '';
                }
                $result["{$address['ipaddr']}{$scope}{$suffix}"] = array(
                    'family' => $proto == 'ipv4' ? 'inet' : 'inet6',
                    'scope' => !empty($address['link-local']),
                    'name' => $realif,
                );
            }
        }
    }

    return $result;
}

function interfaces_primary_address6($interface, $realif = null, $ifconfig_details = null)
{
    $ifcfgipv6 = $networkv6 = $subnetbitsv6 = null;

    if ($realif === null) {
        $realif = get_real_interface($interface, 'inet6');
    }

    $interfaces_a = config_read_array('interfaces');

    foreach (interfaces_addresses($realif, true, $ifconfig_details) as $tmpaddr => $info) {
        if ($info['family'] != 'inet6' || $info['deprecated']) {
            continue;
        }

        if (!isset($interfaces_a[$interface]['dhcp6prefixonly'])) {
            if ($info['scope']) {
                continue;
            }
        } else {
            if (!$info['scope']) {
                continue;
            }
        }

        $addrparts = explode('/', $tmpaddr);

        foreach (config_read_array('virtualip', 'vip') as $vip) {
            if ($vip['interface'] == $interface && $vip['mode'] == 'ipalias' && strpos($vip['subnet'], ':') !== false) {
                /*
                 * Since we do not know what subnet value was given by user
                 * uncompress/compress to match correctly compressed system
                 * value.  Matching alias cannot be primary address.
                 */
                if (Net_IPv6::compress(Net_IPv6::uncompress($vip['subnet'])) == $addrparts[0]) {
                    continue 2;
                }
            }
        }

        $networkv6 = gen_subnetv6($addrparts[0], $addrparts[1]) . "/{$addrparts[1]}";
        $subnetbitsv6 = $addrparts[1];
        $ifcfgipv6 = $addrparts[0];
        break; /* all done */
    }

    return [ $ifcfgipv6, $networkv6, $subnetbitsv6 ];
}
