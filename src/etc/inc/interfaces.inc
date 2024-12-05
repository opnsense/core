<?php

/*
 * Copyright (C) 2015-2024 Franco Fichtner <franco@opnsense.org>
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

function does_interface_exist($interface, $flag = 'all')
{
    return !empty($interface) && in_array($interface, legacy_interface_listget($flag));
}

function interfaces_loopback_configure($verbose = false)
{
    service_log('Configuring loopback interface...', $verbose);

    legacy_interface_setaddress('lo0', '127.0.0.1');
    interfaces_vips_configure('lo0');
    legacy_interface_flags('lo0', 'up');

    service_log("done.\n", $verbose);
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

function interfaces_vlan_configure($verbose = false)
{
    global $config;

    if (!isset($config['vlans']['vlan'])) {
        return;
    }

    service_log('Configuring VLAN interfaces...', $verbose);

    /* XXX sorting vlans here on top of $config seems prone to further side effects */
    // Handle QinQ dependencies by sorting list of vlans to create (first all vlans so we can stack QinQ on top)
    usort($config['vlans']['vlan'], function ($a, $b) {
        $aqinq = strpos($a['vlanif'], 'vlan') !== false ? 0 : 1;
        $bqinq = strpos($b['vlanif'], 'vlan') !== false ? 0 : 1;
        if ($aqinq === $bqinq) {
            return $a['vlanif'] <=> $b['vlanif'];
        } else {
            return $aqinq <=> $bqinq;
        }
    });

    /* requested vlan protocol, when the vlan has vlans as children, the 802.1ad (QinQ) proto should be used */
    $all_parents = [];
    foreach ($config['vlans']['vlan'] as $vlan) {
        if (!in_array($vlan['vlanif'], $all_parents)) {
            if (!isset($all_parents[$vlan['if']])) {
                $all_parents[$vlan['if']] = 0;
            }
            $all_parents[$vlan['if']]++;
        }
    }
    foreach ($config['vlans']['vlan'] as $vlan) {
        if (empty($vlan['proto'])) {
            $vlan['proto'] = empty($all_parents[$vlan['vlanif']]) ? '802.1q' : '802.1ad';
        }
        _interfaces_vlan_configure($vlan);
    }

    service_log("done.\n", $verbose);
}

function _interfaces_vlan_configure($vlan)
{
    legacy_interface_flags($vlan['if'], 'up'); /* XXX overreach? */

    /* destroy is a safety precaution, when configuring via api or gui this function should only be called on new vlans */
    legacy_interface_destroy($vlan['vlanif']);
    legacy_interface_create('vlan', $vlan['vlanif']);

    legacy_vlan_tag($vlan['vlanif'], $vlan['if'], $vlan['tag'], $vlan['pcp'], $vlan['proto']);

    legacy_interface_flags($vlan['vlanif'], 'up');
}

function interfaces_wlan_clone($device)
{
    global $config;

    foreach (array_keys(get_configured_interface_with_descr()) as $if) {
        if (!isset($config['interfaces'][$if]['wireless'])) {
            continue;
        }
        $realif = get_real_interface($if);
        /* XXX 'if' check only required if parent is still embedded */
        if ($device == $realif || $device == $config['interfaces'][$if]['if']) {
            return _interfaces_wlan_clone($realif, $config['interfaces'][$if]);
        }
    }

    if (isset($config['wireless']['clone'])) {
        foreach ($config['wireless']['clone'] as $clone) {
            if ($device == $clone['cloneif']) {
                return _interfaces_wlan_clone($clone['cloneif'], $clone);
            }
        }
    }

    return null;
}

function interfaces_bridge_configure($device)
{
    global $config;

    if (!isset($config['bridges']['bridged'])) {
        return null;
    }

    foreach ($config['bridges']['bridged'] as $bridge) {
        if ($bridge['bridgeif'] == $device) {
            return _interfaces_bridge_configure($bridge);
        }
    }

    return null;
}

function _interfaces_bridge_configure($bridge)
{
    $ret = $bridge['bridgeif'];

    /* XXX avoid destroy/create */
    legacy_interface_destroy($bridge['bridgeif']);
    legacy_interface_create($bridge['bridgeif']);

    $checklist = get_configured_interface_with_descr();
    $members = [];

    /* find all required members */
    foreach (explode(',', $bridge['members'] ?? '') as $member) {
        if (empty($checklist[$member])) {
            /* ignores disabled ones */
            continue;
        }

        $device = get_real_interface($member);
        if (!does_interface_exist($device)) {
            log_msg("Device {$bridge['bridgeif']} cannot attach non-existent member {$device}, skipping now.");
            /* continue but mark this as failed for caller so they could retry */
            $ret = null;
            continue;
        }

        $members[$member] = $device;
    }

    /* calculate smaller mtu and enforce it */
    $mtu = null;
    foreach ($members as $member => $device) {
        $opts = legacy_interface_stats($device);
        if (!empty($opts['mtu']) && ($mtu == null || $opts['mtu'] < $mtu)) {
            $mtu = $opts['mtu'];
        }
    }

    mwexecf('/sbin/ifconfig %s inet6 %sauto_linklocal', [$bridge['bridgeif'], isset($bridge['linklocal']) ? '' : '-']);

    /* add member interfaces to bridge */
    foreach ($members as $member => $device) {
        configure_interface_hardware($device);
        legacy_interface_mtu($device, $mtu);
        legacy_interface_flags($device, 'up');
        legacy_bridge_member($bridge['bridgeif'], $device);
    }

    if (isset($bridge['enablestp'])) {
        mwexecf('/sbin/ifconfig %s proto %s', [$bridge['bridgeif'], $bridge['proto']]);
        if (!empty($bridge['stp'])) {
            $stpifs = explode(',', $bridge['stp']);
            foreach ($stpifs as $stpif) {
                mwexecf('/sbin/ifconfig %s stp %s', [$bridge['bridgeif'], get_real_interface($stpif)]);
            }
        }
        if (!empty($bridge['maxage'])) {
            mwexec("/sbin/ifconfig {$bridge['bridgeif']} maxage " . escapeshellarg($bridge['maxage']));
        }
        if (!empty($bridge['fwdelay'])) {
            mwexec("/sbin/ifconfig {$bridge['bridgeif']} fwddelay " . escapeshellarg($bridge['fwdelay']));
        }
        if (!empty($bridge['hellotime'])) {
            mwexec("/sbin/ifconfig {$bridge['bridgeif']} hellotime " . escapeshellarg($bridge['hellotime']));
        }
        if (!empty($bridge['priority'])) {
            mwexec("/sbin/ifconfig {$bridge['bridgeif']} priority " . escapeshellarg($bridge['priority']));
        }
        if (!empty($bridge['holdcnt'])) {
            mwexec("/sbin/ifconfig {$bridge['bridgeif']} holdcnt " . escapeshellarg($bridge['holdcnt']));
        }
        if (!empty($bridge['ifpriority'])) {
            $pconfig = explode(",", $bridge['ifpriority']);
            $ifpriority = [];
            foreach ($pconfig as $cfg) {
                $embcfg = explode_assoc(":", $cfg);
                foreach ($embcfg as $key => $value) {
                    $ifpriority[$key] = $value;
                }
            }
            foreach ($ifpriority as $key => $value) {
                $realif = get_real_interface($key);
                mwexec("/sbin/ifconfig {$bridgeif} ifpriority {$realif} " . escapeshellarg($value));
            }
        }
        if (!empty($bridge['ifpathcost'])) {
            $pconfig = explode(",", $bridge['ifpathcost']);
            $ifpathcost = [];
            foreach ($pconfig as $cfg) {
                $embcfg = explode_assoc(":", $cfg);
                foreach ($embcfg as $key => $value) {
                    $ifpathcost[$key] = $value;
                }
            }
            foreach ($ifpathcost as $key => $value) {
                $realif = get_real_interface($key);
                mwexec("/sbin/ifconfig {$bridgeif} ifpathcost {$realif} " . escapeshellarg($value));
            }
        }
    }

    if ($bridge['maxaddr'] != '') {
        mwexec("/sbin/ifconfig {$bridge['bridgeif']} maxaddr " . escapeshellarg($bridge['maxaddr']));
    }
    if ($bridge['timeout'] != '') {
        mwexec("/sbin/ifconfig {$bridge['bridgeif']} timeout " . escapeshellarg($bridge['timeout']));
    }
    if (!empty($bridge['span'])) {
        $realif = get_real_interface($bridge['span']);
        mwexec("/sbin/ifconfig {$bridge['bridgeif']} span {$realif}");
    }
    if (!empty($bridge['edge'])) {
        $edgeifs = explode(',', $bridge['edge']);
        foreach ($edgeifs as $edgeif) {
            $realif = get_real_interface($edgeif);
            mwexec("/sbin/ifconfig {$bridge['bridgeif']} edge {$realif}");
        }
    }
    if (!empty($bridge['autoedge'])) {
        $edgeifs = explode(',', $bridge['autoedge']);
        foreach ($edgeifs as $edgeif) {
            $realif = get_real_interface($edgeif);
            mwexec("/sbin/ifconfig {$bridge['bridgeif']} -autoedge {$realif}");
        }
    }
    if (!empty($bridge['ptp'])) {
        $ptpifs = explode(',', $bridge['ptp']);
        foreach ($ptpifs as $ptpif) {
            $realif = get_real_interface($ptpif);
            mwexec("/sbin/ifconfig {$bridge['bridgeif']} ptp {$realif}");
        }
    }
    if (!empty($bridge['autoptp'])) {
        $ptpifs = explode(',', $bridge['autoptp']);
        foreach ($ptpifs as $ptpif) {
            $realif = get_real_interface($ptpif);
            mwexec("/sbin/ifconfig {$bridge['bridgeif']} -autoptp {$realif}");
        }
    }
    if (!empty($bridge['static'])) {
        $stickyifs = explode(',', $bridge['static']);
        foreach ($stickyifs as $stickyif) {
            $realif = get_real_interface($stickyif);
            mwexec("/sbin/ifconfig {$bridge['bridgeif']} sticky {$realif}");
        }
    }
    if (!empty($bridge['private'])) {
        $privateifs = explode(',', $bridge['private']);
        foreach ($privateifs as $privateif) {
            $realif = get_real_interface($privateif);
            mwexec("/sbin/ifconfig {$bridge['bridgeif']} private {$realif}");
        }
    }

    legacy_interface_flags($bridge['bridgeif'], 'up');

    return $ret;
}

function interfaces_lagg_configure($verbose = false)
{
    global $config;

    if (!isset($config['laggs']['lagg'])) {
        return;
    }

    service_log('Configuring LAGG interfaces...', $verbose);

    foreach ($config['laggs']['lagg'] as $lagg) {
        _interfaces_lagg_configure($lagg);
    }

    service_log("done.\n", $verbose);
}

function _interfaces_lagg_configure($lagg)
{
    if (empty($lagg['members'])) {
        /* XXX really necessary? we would like our LAGG anyway */
        log_msg("No members found on {$lagg['laggif']}", LOG_WARNING);
        return;
    }

    $interface_stats = legacy_interfaces_details();
    $members = explode(',', $lagg['members']);
    if (!empty($lagg['primary_member'])) {
        /* place primary member as first member */
        $members = array_unique(array_merge([$lagg['primary_member']], $members));
    }

    if (empty($interface_stats[$lagg['laggif']])) {
        legacy_interface_create($lagg['laggif']);
    } else {
        // Already configured, detach child interfaces before attempting to configure.
        // Prevents vlans to loose parent.
        if (!empty($interface_stats[$lagg['laggif']]['laggport'])) {
            foreach (array_keys($interface_stats[$lagg['laggif']]['laggport']) as $laggport) {
                mwexecf('/sbin/ifconfig %s -laggport %s', [$lagg['laggif'], $laggport]);
            }
        }
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
            /*
             * XXX A LAGG does not allow to set the MTU on its ports individually
             * so we would rather set the MTU on the LAGG after adding all ports.
             * Thus we should avoid runtime entanglement of MTU of ports to find
             * the smallest one since they already follow the LAGG setting despite
             * showing something else in ifconfig and defaulting to 1500 from the
             * LAGG side anyway.
             */
            legacy_interface_mtu($member, $mtu);
            configure_interface_hardware($member);
            legacy_interface_flags($member, 'up');
            mwexecf('/sbin/ifconfig %s laggport %s', [$lagg['laggif'], $member]);
        }
    }

    mwexecf('/sbin/ifconfig %s laggproto %s', [$lagg['laggif'], $lagg['proto']]);
    if (in_array($lagg['proto'], ['lacp', 'loadbalance'])) {
        foreach (['lacp_fast_timeout', 'use_flowid', 'lacp_strict'] as $attr) {
            $attr_proto = strpos($attr, 'lacp_') !== false ? 'lacp' : $lagg['proto'];
            if (isset($lagg[$attr]) && $attr_proto == $lagg['proto']) {
                mwexecf('/sbin/ifconfig %s %s%s', [$lagg['laggif'], empty($lagg[$attr]) ? '-' : '', $attr]);
            }
        }
        $configured_hash = !empty($lagg['lagghash']) ? $lagg['lagghash'] : 'l2,l3,l4';
        mwexecf('/sbin/ifconfig %s lagghash %s', [$lagg['laggif'], $configured_hash]);
    }

    legacy_interface_flags($lagg['laggif'], 'up');
}

function interfaces_gre_configure($device)
{
    global $config;

    if (!isset($config['gres']['gre'])) {
        return null;
    }

    foreach ($config['gres']['gre'] as $gre) {
        if ($gre['greif'] == $device) {
            return _interfaces_gre_configure($gre);
        }
    }

    return null;
}

function _interfaces_gre_configure($gre)
{
    if (!does_interface_exist($gre['greif'])) {
        /* Only create when not already there */
        legacy_interface_create($gre['greif']);
    }

    $family = is_ipaddrv4($gre['remote-addr']) ? 'inet' : 'inet6';
    if (!empty($gre['ipaddr'])) {
        /* MVC splits content into if and ipaddr fields, legacy input stores the address in "if",
           in which case get_interface_* will return an address when offered */
        $local_addr = $gre['ipaddr'];
    } elseif ($family == 'inet') {
        $local_addr = get_interface_ip($gre['if']);
    } else {
        $local_addr = get_interface_ipv6($gre['if'], null, is_linklocal($gre['remote-addr']) ? 'scoped' : 'routed');
    }

    /* ensured device is there, but do not configure unless system is ready */
    if (empty($local_addr)) {
        log_msg("Device {$gre['greif']} missing required local address, skipping now.");
        return null;
    }

    $remote_addr = $gre['remote-addr'] . (strpos($local_addr, '%') === false ? '' : '%' . explode('%', $local_addr)[1]);

    mwexecf('/sbin/ifconfig %s %s tunnel %s %s', [$gre['greif'], $family, $local_addr, $remote_addr]);

    if (is_ipaddrv6($gre['tunnel-local-addr']) || is_ipaddrv6($gre['tunnel-remote-addr'])) {
        /* check if destination is local to source and if not we need the traditional point-to-point setup */
        if ($gre['tunnel-remote-net'] != '128' && ip_in_subnet($gre['tunnel-remote-addr'], "{$gre['tunnel-local-addr']}/{$gre['tunnel-remote-net']}")) {
            mwexecf('/sbin/ifconfig %s inet6 %s prefixlen %s', [
                $gre['greif'],
                $gre['tunnel-local-addr'],
                $gre['tunnel-remote-net'],
            ]);
        } else {
            mwexecf('/sbin/ifconfig %s inet6 %s %s prefixlen 128', [
                $gre['greif'],
                $gre['tunnel-local-addr'],
                $gre['tunnel-remote-addr'],
            ]);
        }
    } else {
        mwexecf('/sbin/ifconfig %s inet6 ifdisabled', [$gre['greif']]);
        mwexecf('/sbin/ifconfig %s %s %s netmask %s', [
            $gre['greif'],
            $gre['tunnel-local-addr'],
            $gre['tunnel-remote-addr'],
            gen_subnet_mask($gre['tunnel-remote-net']),
        ]);
    }

    legacy_interface_flags($gre['greif'], 'up');

    /* XXX: write XXX_router file */
    mwexecf('/usr/local/sbin/ifctl -i %s -%s -rd -a %s', [
        $gre['greif'],
        is_ipaddrv4($gre['tunnel-remote-addr']) ? '4' : '6',
        $gre['tunnel-remote-addr'],
    ]);

    return $gre['greif'];
}

function interfaces_gif_configure($device)
{
    global $config;

    if (!isset($config['gifs']['gif'])) {
        return null;
    }

    foreach ($config['gifs']['gif'] as $gif) {
        if ($gif['gifif'] == $device) {
            return _interfaces_gif_configure($gif);
        }
    }

    return null;
}

function _interfaces_gif_configure($gif)
{
    if (!does_interface_exist($gif['gifif'])) {
        /* Only create when not already there */
        legacy_interface_create($gif['gifif']);
    }

    $interface = !empty($gif['if']) ? explode('_vip', $gif['if'])[0] : null;
    $family = is_ipaddrv4($gif['remote-addr']) ? 'inet' : 'inet6';
    /* XXX: remove routing part in 24.7 */
    $remote_gw = null;
    if (!empty($interface)) {
        $remote_gw = (new \OPNsense\Routing\Gateways())->getInterfaceGateway($interface, $family);
        if ($family == 'inet6' && is_linklocal($remote_gw) && strpos($remote_gw, '%') === false) {
            $remote_gw .= '%' . get_real_interface($interface, 'inet6');
        }
    }

    if ($family == 'inet') {
        $local_addr = get_interface_ip(!empty($gif['ipaddr']) ? $gif['ipaddr'] : $gif['if']);
    } else {
        if (is_linklocal($gif['remote-addr'])) {
            $local_addr = get_interface_ipv6(!empty($gif['ipaddr']) ? $gif['ipaddr'] : $gif['if'], null, 'scoped');
        } else {
            $local_addr = get_interface_ipv6(!empty($gif['ipaddr']) ? $gif['ipaddr'] : $gif['if'], null, 'routed');
        }
    }

    /* ensured device is there, but do not configure unless system is ready */
    if (empty($local_addr)) {
        log_msg("Device {$gif['gifif']} missing required local address, skipping now.");
        return null;
    }

    $remote_addr = $gif['remote-addr'] . (strpos($local_addr, '%') === false ? '' : '%' . explode('%', $local_addr)[1]);

    mwexecf('/sbin/ifconfig %s %s tunnel %s %s', [$gif['gifif'], $family, $local_addr, $remote_addr]);

    if (is_ipaddrv6($gif['tunnel-local-addr']) || is_ipaddrv6($gif['tunnel-remote-addr'])) {
        /* check if destination is local to source and if not we need the traditional point-to-point setup */
        if ($gif['tunnel-remote-net'] != '128' && ip_in_subnet($gif['tunnel-remote-addr'], "{$gif['tunnel-local-addr']}/{$gif['tunnel-remote-net']}")) {
            mwexecf('/sbin/ifconfig %s inet6 %s prefixlen %s', [
                $gif['gifif'],
                $gif['tunnel-local-addr'],
                $gif['tunnel-remote-net'],
            ]);
        } else {
            mwexecf('/sbin/ifconfig %s inet6 %s %s prefixlen 128', [
                $gif['gifif'],
                $gif['tunnel-local-addr'],
                $gif['tunnel-remote-addr'],
            ]);
        }
    } else {
        mwexecf('/sbin/ifconfig %s inet6 ifdisabled', [$gif['gifif']]);
        mwexecf('/sbin/ifconfig %s %s %s netmask %s', [
            $gif['gifif'],
            $gif['tunnel-local-addr'],
            $gif['tunnel-remote-addr'],
            gen_subnet_mask($gif['tunnel-remote-net']),
        ]);
    }

    $flags = (empty($gif['link1']) ? "-" : "") . "link1 " . (empty($gif['link2']) ? "-" : "") . "link2";
    legacy_interface_flags($gif['gifif'], $flags);

    legacy_interface_flags($gif['gifif'], 'up');

    /* XXX: remove below routing block in 24.7 */
    if (!empty($remote_gw)) {
        system_host_route($remote_addr, $remote_gw);
    }

    /* XXX: write XXX_router file */
    mwexecf('/usr/local/sbin/ifctl -i %s -%s -rd -a %s', [
        $gif['gifif'],
        is_ipaddrv4($gif['tunnel-remote-addr']) ? '4' : '6',
        $gif['tunnel-remote-addr'],
    ]);

    return $gif['gifif'];
}

function interfaces_hardware($verbose = false)
{
    service_log('Configuring hardware interfaces...', $verbose);

    $intf_details = legacy_interfaces_details();

    foreach (array_keys(get_interface_list()) as $device) {
        configure_interface_hardware($device, $intf_details);
    }

    service_log("done.\n", $verbose);
}

function interfaces_configure($verbose = false)
{
    $interfaces = [];
    $devices = [];

    foreach (plugins_devices() as $device) {
        if (empty($device['function']) || empty($device['names'])) {
            continue;
        }
        foreach (array_keys($device['names']) as $name) {
            $devices[$name] = $device['function'];
        }
    }

    /*
     * Queues are set up to order interfaces according to their
     * dependencies / requirements of devices or other interfaces.
     * Some queues may overlap, but they are laid out in full to
     * make sure that the configuration flow is as clean as possible.
     * See individual notes in the queued handling below.
     */

    $hardware = []; /* hardware devices */
    $virtual = []; /* software devices */
    $track6 = []; /* trackers without bridges */
    $bridge = []; /* bridges that may be trackers, but not dhcp6c interfaces */
    $dhcp6c = []; /* dhcp6c interfaces load last */

    foreach (legacy_config_get_interfaces(['enable' => true, 'virtual' => false]) as $if => $ifcfg) {
        /* XXX use this to figure out when bridges can be configured */
        $interfaces[$if] = $ifcfg['if'];

        if (!empty($devices[$ifcfg['if']]) && !strstr($ifcfg['if'], 'bridge')) {
            $virtual[$ifcfg['if']] = $if;
            continue;
        }

        $is_track6 = !empty($ifcfg['ipaddrv6']) && $ifcfg['ipaddrv6'] == 'track6';
        if (!strstr($ifcfg['if'], 'bridge') && $is_track6) {
            $track6[$ifcfg['if']] = $if;
            continue;
        }

        $is_dhcp6c = !empty($ifcfg['ipaddrv6']) && ($ifcfg['ipaddrv6'] == 'dhcp6' || $ifcfg['ipaddrv6'] == 'slaac');
        if (strstr($ifcfg['if'], 'bridge') && !$is_dhcp6c) {
            $bridge[$ifcfg['if']] = $if;
            continue;
        } elseif ($is_dhcp6c) {
            $dhcp6c[$ifcfg['if']] = $if;
            continue;
        }

        $hardware[$ifcfg['if']] = $if;
    }

    interfaces_loopback_configure($verbose);
    interfaces_lagg_configure($verbose);
    interfaces_vlan_configure($verbose);

    /* run through priority lists */
    foreach ([$hardware, $virtual, $track6, $bridge, $dhcp6c] as $list) {
        foreach ($list as $device => $if) {
            /* pre-op: configuring the underlying device */
            if (!empty($devices[$device])) {
                /* XXX devices could depend on other devices */
                log_msg("Device $device required for $if, configuring now");
                call_user_func_array($devices[$device], [$device]);
                unset($devices[$device]);
            }

            /* post-op: removing associated devices and current interface */
            foreach (interface_configure($verbose, $if) as $loaded) {
                if (!empty($devices[$loaded])) {
                    log_msg("Device $loaded loaded by $if, skipping now", LOG_INFO);
                    unset($devices[$loaded]);
                } else {
                    log_msg("Device $loaded reloaded by $if, already skipped", LOG_INFO);
                }
            }
            unset($interfaces[$if]);
        }
    }

    /* last but not least start all unconfigured devices */
    foreach ($devices as $name => $function) {
        /* XXX devices could depend on other devices */
        log_msg("Device $name is not assigned, configuring late");
        call_user_func_array($function, [$name]);
    }
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

function interface_reset($interface, $ifacecfg = false, $suspend = false)
{
    global $config;

    if (!isset($config['interfaces'][$interface]) || ($ifacecfg !== false && !is_array($ifacecfg))) {
        return;
    }

    /*
     * The function formerly known as interface_bring_down() is largely intact,
     * but its purpose was split between different use cases that it could not
     * handle correctly not being aware of the caller's requirements.  Now we
     * split between a suspend and reset mode in order to accomodate the need.
     */
    if ($ifacecfg === false) {
        $realif = get_real_interface($interface);
        $realifv6 = get_real_interface($interface, "inet6");
        $ifcfg = $config['interfaces'][$interface];
        $ppps = isset($config['ppps']['ppp']) ? $config['ppps']['ppp'] : [];
    } else {
        $ifcfg = $ifacecfg['ifcfg'];
        $ppps = $ifacecfg['ppps'];
        /* When $ifacecfg is passed, it should contain the original interfaces */
        $realif = $ifacecfg['ifcfg']['realif'];
        $realifv6 = $ifacecfg['ifcfg']['realifv6'];
    }

    if (!$suspend) {
        foreach (config_read_array('virtualip', 'vip') as $vip) {
            if ($vip['interface'] == $interface) {
                interface_vip_bring_down($vip);
            }
        }
    }

    /* cache ifconfig now that VIPs are handled */
    $ifconfig_details = legacy_interfaces_details();

    /*
     * hostapd and wpa_supplicant do not need to be running when the
     * interface is down.  They will also use 100% CPU if running after
     * the wireless clone gets deleted.
     */
    if (isset($ifcfg['wireless'])) {
        kill_wpasupplicant($realif);
        kill_hostapd($realif);
    }

    $track6 = array_keys(link_interface_to_track6($interface));
    if (count($track6)) {
        /* bring down radvd and dhcp6 on these interfaces */
        plugins_configure('dhcp', false, ['inet6', $track6]);
    }

    switch ($ifcfg['ipaddrv6'] ?? 'none') {
        case 'slaac':
        case 'dhcp6':
            interface_dhcpv6_prepare($interface, $ifcfg, true);
            killbypid('/var/run/dhcp6c.pid', 'HUP');
            break;
        case 'track6':
            interface_track6_configure($interface, $ifcfg);
            break;
        default:
            break;
    }

    if (!empty($ifcfg['ipaddrv6'])) {
        if (!$suspend) {
            interfaces_addresses_flush($realifv6, 6, $ifconfig_details);
        } elseif (!is_ipaddrv6($ifcfg['ipaddrv6'])) {
            /* edge case: bring down a primary GUA, but not a link-local */
            list ($ip6) = _interfaces_primary_address6($interface, $ifconfig_details, false, false);
            if (!empty($ip6)) {
                mwexecf('/sbin/ifconfig %s inet6 %s delete', [$realifv6, $ip6]);
            }
        }
    }

    switch ($ifcfg['ipaddr'] ?? 'none') {
        case 'dhcp':
            killbypid("/var/run/dhclient.{$realif}.pid");
            break;
        default:
            break;
    }

    if (!empty($ifcfg['ipaddr'])) {
        if (!$suspend) {
            interfaces_addresses_flush($realif, 4, $ifconfig_details);
        } elseif (!is_ipaddrv4($ifcfg['ipaddr'])) {
            /* dhclient will not flush its address ever so do it here */
            list ($ip4) = interfaces_primary_address($interface, $ifconfig_details);
            if (!empty($ip4)) {
                mwexecf('/sbin/ifconfig %s delete %s', [$realif, $ip4]);
            }
        }
    }

    /* check reset of running PPP configuration inside function */
    interface_ppps_reset($interface, $suspend, $ifcfg, $ppps);

    /* clear stale state associated with this interface */
    mwexecf('/usr/local/sbin/ifctl -4c -i %s', $realif);
    mwexecf('/usr/local/sbin/ifctl -6c -i %s', $realifv6);
}

function interface_suspend($interface)
{
    /*
     * Suspend uses a subset of interface_reset() to avoid
     * stripping all the static addresses already in use.
     * This helps to retain routes and gateway information
     * also relevant when reloading the packet filter for
     * e.g. NAT rules generation.
     */
    interface_reset($interface, false, true);
}

function interface_ppps_bound($interface, $family = null)
{
    global $config;

    $ifcfg = $config['interfaces'][$interface] ?? null;
    $ppps = $config['ppps']['ppp'] ?? null;
    $bound = false;

    if (!interface_ppps_capable($ifcfg, $ppps)) {
        return $bound;
    }

    $ipv4_mode = false;
    $ipv6_mode = false;

    switch ($ifcfg['ipaddr'] ?? 'none') {
        case 'ppp':
        case 'pppoe':
        case 'pptp':
        case 'l2tp':
            $ipv4_mode = true;
            break;
        default:
            break;
    }

    switch ($ifcfg['ipaddrv6'] ?? 'none') {
        case 'dhcp6':
        case 'pppoev6':
        case 'slaac':
            $ipv6_mode = true;
            break;
        default:
            break;
    }

    if ($family == 4) {
        /* use this to steer configuration based on newwanip event */
        $bound = $ipv4_mode;
    } elseif ($family == 6) {
        /* use this to steer configuration based on newwanipv6 event */
        $bound = !$ipv4_mode && $ipv6_mode;
    } else {
        /* use this to prevent doing any early setup in general */
        $bound = $ipv4_mode || $ipv6_mode;
    }

    return $bound;
}

function interface_ppps_capable($ifcfg, $ppps)
{
    if (empty($ifcfg) || empty($ppps)) {
        return false;
    }

    foreach ($ppps as $ppp) {
        if ($ifcfg['if'] == $ppp['if']) {
            /* we only test for PPP capability that needs mpd5 */
            return true;
        }
    }

    return false;
}

function interface_ppps_hardware($device)
{
    $devices = [$device];

    foreach (config_read_array('ppps', 'ppp') as $ppp) {
        if ($device == $ppp['if']) {
            $devices = [];
            foreach (explode(',', $ppp['ports']) as $port) {
                /*
                 * XXX We only have get_real_interface() here because
                 * PPP may be assigned to an assigned interface!  :(
                 */
                $devices[] = get_real_interface($port);
            }
            break;
        }
    }

    return $devices;
}

function interface_ppps_reset($interface, $suspend, $ifcfg, $ppps)
{
    if (!interface_ppps_capable($ifcfg, $ppps)) {
        return;
    }

    foreach ($ppps as $ppp) {
        if ($ifcfg['if'] == $ppp['if']) {
            if (isset($ppp['ondemand']) && $suspend) {
                configdp_run('interface reconfigure', [$interface], true);
            } else {
                killbypid("/var/run/{$ppp['type']}_{$interface}.pid");
            }
            break;
        }
    }
}

function interface_ppps_configure($interface)
{
    global $config;

    $ifcfg = $config['interfaces'][$interface] ?? null;
    $ppps = $config['ppps']['ppp'] ?? null;

    if (!interface_ppps_capable($ifcfg, $ppps)) {
        return;
    }

    if (!isset($ifcfg['enable'])) {
        return;
    }

    $ipv4_mode = $ipv6_mode = 'disable';

    switch ($ifcfg['ipaddr'] ?? 'none') {
        case 'ppp':
        case 'pppoe':
        case 'pptp':
        case 'l2tp':
            $ipv4_mode = 'enable';
            break;
        default:
            break;
    }

    switch ($ifcfg['ipaddrv6'] ?? 'none') {
        case 'dhcp6':
        case 'pppoev6':
        case 'slaac':
            $ipv6_mode = 'enable';
            break;
        default:
            break;
    }

    if ($ipv4_mode != 'enable' && $ipv6_mode != 'enable') {
        return;
    }

    $ppp = null;
    $idx = 0;

    foreach ($ppps as $i => $tmp) {
        if ($ifcfg['if'] == $tmp['if']) {
            $ppp = $tmp;
            $idx = $i;
            break;
        }
    }

    $ports = explode(',', $ppp['ports']);
    if ($ppp['type'] != "ppp") {
        foreach ($ports as $pid => $port) {
            $ports[$pid] = get_real_interface($port);
            if (empty($ports[$pid])) {
                return;
            }
        }
    }

    $localips = isset($ppp['localip']) ? explode(',', $ppp['localip']) : [];
    $gateways = isset($ppp['gateway']) ? explode(',', $ppp['gateway']) : [];
    $subnets = isset($ppp['subnet']) ? explode(',', $ppp['subnet']) : [];
    $mtus = !empty($ppp['mtu']) ? explode(',', $ppp['mtu']) : [];

    /*
     * We bring up the parent interface first because if DHCP is configured on the parent we need
     * to obtain an address first so we can write it in the mpd .conf file for PPTP and L2TP configs
     */
    foreach ($ports as $pid => $port) {
        switch ($ppp['type']) {
            case 'pppoe':
                legacy_interface_flags($port, 'up');
                break;
            case 'pptp':
            case 'l2tp':
                /* configure interface */
                if (is_ipaddr($localips[$pid])) {
                    // Manually configure interface IP/subnet
                    legacy_interface_setaddress($port, "{$localips[$pid]}/{$subnets[$pid]}");
                    legacy_interface_flags($port, 'up');
                } elseif (empty($localips[$pid])) {
                    $localips[$pid] = get_interface_ip($port); // try to get the interface IP from the port
                }

                if (!is_ipaddr($localips[$pid])) {
                    log_msg("Could not get a Local IP address for PPTP/L2TP link on {$port}. Using 0.0.0.0!", LOG_WARNING);
                    $localips[$pid] = '0.0.0.0';
                }
                if (!is_ipaddr($gateways[$pid])) {
                    log_msg("Could not get a Remote IP address for PPTP/L2TP link on {$port}.", LOG_WARNING);
                    return;
                }
                break;
            case 'ppp':
                if (!file_exists($port)) {
                    log_msg("Device {$port} does not exist. PPP link cannot start without the modem device.", LOG_ERR);
                    return;
                }
                break;
            default:
                log_msg("Unknown {$ppp['type']} configured as PPP interface.", LOG_ERR);
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
  set bundle {$ipv4_mode} ipcp
  set bundle {$ipv6_mode} ipv6cp
  set iface name {$ifcfg['if']}

EOD;

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

    if ($ipv4_mode == 'enable') {
        if ($ppp['type'] == 'ppp') {
            $localip = is_ipaddr($ppp['localip']) ? $ppp['localip'] : '0.0.0.0';
            $gateway = is_ipaddr($ppp['gateway']) ? $ppp['gateway'] : "10.64.64.{$idx}";
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
    }

    foreach ($ports as $pid => $port) {
        $mpdconf_arr = [];
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

        if (empty($mtus[$pid])) {
            /* subtract default header when deriving from interface config (as shown there) */
            $mtus[$pid] = !empty($ifcfg['mtu']) ? intval($ifcfg['mtu']) - 8 : 1492;
        }
        if ($ppp['type'] == 'pppoe' && $mtus[$pid] > 1492) {
            $mpdconf_arr[] = "set pppoe max-payload " . $mtus[$pid];
        } else {
            $mpdconf_arr[] = "set link mtu " . $mtus[$pid];
        }
        $mrus = !empty($ppp['mru']) ? explode(',', $ppp['mru']) : null;
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
            $provider = $ppp['provider'] ?? '';
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

    /* stop the service as a precaution */
    killbypid("/var/run/{$ppp['type']}_{$interface}.pid");

    /* mpd5 modem chat script expected in the same directory as the mpd_xxx.conf files */
    @copy('/usr/local/opnsense/scripts/interfaces/mpd.script', '/var/etc/mpd.script');

    /* write the configuration */
    @file_put_contents("/var/etc/mpd_{$interface}.conf", $mpdconf);

    /* create the uptime log if requested */
    if (isset($ppp['uptime'])) {
        @touch("/conf/{$ifcfg['if']}.log");
    } else {
        @unlink("/conf/{$ifcfg['if']}.log");
    }

    /* clean up old lock files */
    foreach ($ports as $port) {
        @unlink('/var/spool/lock/LCK..' . basename($port));
    }

    /* precaution for post-start 'up' check */
    legacy_interface_flags($ifcfg['if'], 'down', false);

    /* fire up mpd */
    mwexecf(
        '/usr/local/sbin/mpd5 -b -d /var/etc -f %s -p %s -s ppp %s',
        ["mpd_{$interface}.conf", "/var/run/{$ppp['type']}_{$interface}.pid", "{$ppp['type']}client"]
    );

    /* appease netgraph by setting the correct node name */
    shell_safe('/usr/sbin/daemon -f /usr/local/opnsense/scripts/interfaces/ppp-rename.sh %s %s %s', [$interface, $ifcfg['if'], $ppp['type']]);

    /* wait for functional device */
    $max = 20;
    $i = 0;
    while ($i < $max) {
        sleep(1);
        if (does_interface_exist($ifcfg['if'], 'up')) {
            break;
        }
        $i++;
    }

    if ($i >= $max) {
        log_msg("interface_ppps_configure() waiting threshold exceeded - device {$ifcfg['if']} is still not up", LOG_WARNING);
    }

    switch ($ppp['type']) {
        case 'pppoe':
            /* automatically change MAC address if parent interface changes */
            $ng_name = preg_replace('/[.:]/', '_', $ports[0]) . ':';
            mwexecf('/usr/sbin/ngctl msg %s setautosrc 1', [$ng_name]);
            /* FALLTHROUGH */
        default:
            legacy_interface_mtu($ifcfg['if'], $mtus[0]);
            break;
    }
}

function interfaces_pfsync_configure()
{
    global $config;

    if (!empty($config['hasync']['pfsyncinterface'])) {
        /*
         * We are just checking the actual attached interface here as get_real_interface()
         * was not dependable when the selected interface does not exist for any reason.
         *
         * What the current method tells us is that we are going to ignore whether this
         * interface is currently enabled or not.  To avoid breakage we will keep it so
         * although in reality disabling your pfsync interface should cause it to stop
         * syncing.
         */
        if (!empty($config['interfaces'][$config['hasync']['pfsyncinterface']]['if'])) {
            $syncdev = $config['interfaces'][$config['hasync']['pfsyncinterface']]['if'];
        }
    }

    if (!empty($syncdev)) {
        if (!empty($config['hasync']['pfsyncpeerip']) && is_ipaddrv4($config['hasync']['pfsyncpeerip'])) {
            $syncpeer = "syncpeer " . escapeshellarg($config['hasync']['pfsyncpeerip']);
        } else {
            $syncpeer = "-syncpeer";
        }

        $version = '';
        if (!empty($config['hasync']['pfsyncversion'])) {
            $version = 'version ' . escapeshellarg($config['hasync']['pfsyncversion']);
        }

        $intf_stats = legacy_interfaces_details(); /* XXX could require passing this down */

        mwexec("/sbin/ifconfig pfsync0 syncdev {$syncdev} {$syncpeer} {$version} up");

        if (!empty($intf_stats[$syncdev]['mtu'])) {
            mwexecf('/sbin/ifconfig pfsync0 mtu %s', [$intf_stats[$syncdev]['mtu']]);
        }
    } else {
        mwexec('/sbin/ifconfig pfsync0 -syncdev -syncpeer down');
    }
}

function interface_proxyarp_configure($interface = '')
{
    global $config;

    /* kill any running choparp, on restart "all" */
    if (empty($interface)) {
        foreach (glob('/var/run/choparp_*.pid') as $pidfile) {
            killbypid($pidfile);
        }
    }

    $paa = array();
    if (isset($config['virtualip']['vip'])) {
        /* group by interface */
        foreach ($config['virtualip']['vip'] as $vipent) {
            if ($vipent['mode'] === "proxyarp") {
                if (empty($interface) || $interface == $vipent['interface']) {
                    if (empty($paa[$vipent['interface']])) {
                        $paa[$vipent['interface']] = [];
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

function interfaces_vips_configure($interface, $family = null)
{
    global $config;

    if (!isset($config['virtualip']['vip'])) {
        return;
    }

    $proxyarp = false;
    $pfsync = false;

    foreach ($config['virtualip']['vip'] as $vip) {
        if ($vip['interface'] != $interface) {
            continue;
        }

        if ($family === 4 && strpos($vip['subnet'], ':') !== false) {
            continue;
        } elseif ($family === 6 && strpos($vip['subnet'], ':') === false) {
            continue;
        }

        switch ($vip['mode']) {
            case 'proxyarp':
                $proxyarp = true;
                break;
            case 'ipalias':
                interface_ipalias_configure($vip);
                break;
            case 'carp':
                interface_carp_configure($vip);
                $pfsync = true;
                break;
        }
    }

    if ($pfsync) {
        interfaces_pfsync_configure();
    }

    if ($proxyarp) {
        interface_proxyarp_configure();
    }
}

function interface_ipalias_configure($vip)
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

function interface_carp_configure($vip)
{
    if ($vip['mode'] != 'carp') {
        return;
    }

    /* when CARP is temporary disabled do not try to configure on any interface up events */
    if (get_single_sysctl('net.inet.carp.allow') == '0') {
        return;
    }

    $realif = get_real_interface($vip['interface']);

    $vip_password = escapeshellarg(addslashes(str_replace(" ", "", $vip['password'])));
    $password = '';
    if ($vip['password'] != "") {
        $password = " pass {$vip_password}";
    }

    $advbase = "";
    if (!empty($vip['advbase'])) {
        $advbase = "advbase " . escapeshellarg($vip['advbase']);
    }

    $advskew = "advskew " . escapeshellarg($vip['advskew']);
    if (empty($vip['peer']) || $vip['peer'] == '224.0.0.18') {
        $peercfg = ' mcast ';
    } else {
        $peercfg = ' peer ' . escapeshellarg($vip['peer']) . ' ';
    }
    if (empty($vip['peer6']) || $vip['peer6'] == 'ff02::12') {
        $peercfg .= ' mcast6 ';
    } else {
        $peercfg .= ' peer6 ' . escapeshellarg($vip['peer6']) . ' ';
    }

    mwexec("/sbin/ifconfig {$realif} vhid " . escapeshellarg($vip['vhid']) . " {$advskew} {$advbase} {$password}");

    /* XXX use legacy_interface_setaddress */
    if (is_ipaddrv4($vip['subnet'])) {
        mwexec("/sbin/ifconfig {$realif} " . escapeshellarg($vip['subnet']) . "/" . escapeshellarg($vip['subnet_bits']) . " alias vhid " . escapeshellarg($vip['vhid']));
    } elseif (is_ipaddrv6($vip['subnet'])) {
        mwexec("/sbin/ifconfig {$realif} inet6 " . escapeshellarg($vip['subnet']) . " prefixlen " . escapeshellarg($vip['subnet_bits']) . " alias vhid " . escapeshellarg($vip['vhid']));
    }
    /**
     *  XXX this is pretty flaky.
     *      If we configure peer[6] during setup, values won't stick, they appear to be flushed when
     *      the initial address is set.
     */
    mwexec("/sbin/ifconfig {$realif} vhid " . escapeshellarg($vip['vhid']) . " {$peercfg}");
}

function _interfaces_wlan_clone($realif, $wlcfg)
{
    /*
     * Check to see if interface has been cloned as of yet.
     * If it has not been cloned then go ahead and clone it.
     */
    $needs_clone = false;

    if (!empty($wlcfg['wireless']['mode'])) {
        /* XXX this is interfaces wireless config */
        $wlcfg_mode = $wlcfg['wireless']['mode'];
    } else {
        /* XXX this is wireless clone config or fallback */
        $wlcfg_mode = $wlcfg['mode'] ?? 'bss';
    }

    switch ($wlcfg_mode) {
        case "hostap":
            $mode = 'wlanmode hostap';
            break;
        case "adhoc":
            $mode = 'wlanmode adhoc';
            break;
        default:
            $mode = '';
            break;
    }

    if (does_interface_exist($realif)) {
        exec("/sbin/ifconfig " . escapeshellarg($realif), $output, $ret);
        $ifconfig_str = implode(PHP_EOL, $output);
        if (($wlcfg_mode == 'hostap') && !preg_match('/hostap/si', $ifconfig_str)) {
            log_msg("Interface {$realif} changed to hostap mode");
            $needs_clone = true;
        }
        if (($wlcfg_mode == 'adhoc') && !preg_match('/adhoc/si', $ifconfig_str)) {
            log_msg("Interface {$realif} changed to adhoc mode");
            $needs_clone = true;
        }
        if (($wlcfg_mode == 'bss') && preg_match('/hostap|adhoc/si', $ifconfig_str)) {
            log_msg("Interface {$realif} changed to infrastructure mode");
            $needs_clone = true;
        }
    } else {
        $needs_clone = true;
    }

    if ($needs_clone) {
        $baseif = interface_get_wireless_base($wlcfg['if']);

        legacy_interface_destroy($realif);

        exec("/sbin/ifconfig wlan create wlandev {$baseif} {$mode} bssid name {$realif} 2>&1", $out, $ret);
        if ($ret != 0) {
            log_msg("Failed to clone interface {$baseif} with error code {$ret}, output {$out[0]}", LOG_ERR);
            return null;
        }

        file_put_contents("/tmp/{$realif}_oldmac", get_interface_mac($realif));
    }

    return $realif;
}

function interface_sync_wireless_clones(&$ifcfg, $sync_changes = false) /* XXX kill side effect */
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
    if (!empty($config['wireless']['interfaces'][$baseif])) {
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
    if (strstr($ifcfg['if'], '_wlan') && !empty($config['wireless']['clone'])) {
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

function interface_wireless_configure($if, &$wancfg)
{
    global $config;

    if (!isset($wancfg['wireless'])) {
        return;
    }

    if (empty($wancfg['wireless'])) {
        /* if an empty node reset to empty array to avoid PHP 8+ issues */
        $wancfg['wireless'] = [];
    }

    /* XXX _interfaces_wlan_clone() and interface_sync_wireless_clones() need work */

    // Clone wireless nic if needed.
    _interfaces_wlan_clone($if, $wancfg);

    // Reject inadvertent changes to shared settings in case the interface hasn't been configured.
    interface_sync_wireless_clones($wancfg, false);

    $wlcfg = &$wancfg['wireless'];

   /*
    * Open up a shell script that will be used to output the commands.
    * since wireless is changing a lot, these series of commands are fragile
    * and will sometimes need to be verified by a operator by executing the command
    * and returning the output of the command to the developers for inspection.  Please
    * do not change this routine from a shell script to individual exec commands.  -sullrich
    */

    $fd_set = fopen("/tmp/{$if}_setup.sh", "w");
    fwrite($fd_set, "#!/bin/sh\n");
    fwrite($fd_set, "# wireless configuration script.\n\n");

    /* set values for /path/program */
    $wpa_supplicant = '/usr/local/sbin/wpa_supplicant';
    $hostapd = '/usr/local/sbin/hostapd';
    $ifconfig = '/sbin/ifconfig';
    $sysctl = '/sbin/sysctl';

    /* Set all wireless ifconfig variables (split up to get rid of needed checking) */

    $wlcmd = [];
    $wl_sysctl = [];

    /* Make sure it's up */
    $wlcmd[] = "up";

    if (isset($wlcfg['standard'])) {
        /* Set a/b/g standard */
        $standard = str_replace(" Turbo", "", $wlcfg['standard']);
        $wlcmd[] = "mode " . escapeshellarg($standard);

        /*
         * XXX: Disable ampdu for now on mwl when running in 11n mode
         * to prevent massive packet loss under certain conditions.
         */
        if (preg_match("/^mwl/i", $if) && ($standard == "11ng" || $standard == "11na")) {
            $wlcmd[] = "-ampdu";
        }
    }

    if (isset($wlcfg['ssid'])) {
        /* Set ssid */
        $wlcmd[] = "ssid " . escapeshellarg($wlcfg['ssid']);
    }

    if (isset($wlcfg['protmode'])) {
        /* Set 802.11g protection mode */
        $wlcmd[] = "protmode " . escapeshellarg($wlcfg['protmode']);
    }

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
    if (isset($wlcfg['mode']) && $wlcfg['mode'] == 'hostap') {
        $wlcmd[] = "mediaopt hostap";
    } else {
        $wlcmd[] = "-mediaopt hostap";
    }

    /* Set wireless adhoc mode */
    if (isset($wlcfg['mode']) && $wlcfg['mode'] == 'adhoc') {
        $wlcmd[] = "mediaopt adhoc";
    } else {
        $wlcmd[] = "-mediaopt adhoc";
    }

    /* Not necessary to set BSS mode as this is default if adhoc and/or hostap is NOT set */

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
    if (isset($wlcfg['wep']['enable']) && is_array($wlcfg['wep']['key'])) {
        $wepset = '';
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
        $wlcmd[] = 'authmode open wepmode off';
    }

    kill_wpasupplicant($if);
    kill_hostapd($if);

    /* generate wpa_supplicant/hostap config if wpa is enabled */
    switch ($wlcfg['mode'] ?? 'bss') {
        case 'bss':
            $authentication = array();
            if (isset($wlcfg['wpa']['enable'])) {
                if ($wlcfg['wpa']['wpa_key_mgmt'] == 'WPA-EAP' && isset($wlcfg['wpa']['wpa_eap_method'])) {
                    switch ($wlcfg['wpa']['wpa_eap_method']) {
                        case 'PEAP':
                        case 'TTLS':
                            $authentication[] = "password=\"{$wlcfg['wpa']['passphrase']}\"";
                            switch ($wlcfg['wpa']['wpa_eap_p2_auth']) {
                                case 'MD5':
                                    $authentication[] = "phase2=\"auth=MD5\"";
                                    break;
                                case 'MSCHAPv2':
                                    $authentication[] = "phase1=\"peaplabel=0\"";
                                    $authentication[] = "phase2=\"auth=MSCHAPV2\"";
                                    break;
                            }
                            /* fallthrough */
                        case 'TLS':
                            $authentication[] = "identity=\"{$wlcfg['wpa']['identity']}\"";
                            $authentication[] = "eap={$wlcfg['wpa']['wpa_eap_method']}";

                            $all_certs = [];
                            // Generate CA cert and client cert to file
                            foreach ($config['ca'] as $ca) {
                                if ($wlcfg['wpa']['wpa_eap_cacertref'] == $ca['refid']) {
                                    $all_certs["/var/etc/wpa_supplicant_{$if}_ca.crt"] =
                                        base64_decode($ca['crt']);
                                    $authentication[] = "ca_cert=\"/var/etc/wpa_supplicant_{$if}_ca.crt\"";
                                }
                            }
                            foreach ($config['cert'] as $cert) {
                                if ($wlcfg['wpa']['wpa_eap_cltcertref'] == $cert['refid']) {
                                    $all_certs["/var/etc/wpa_supplicant_{$if}_clt.crt"] =
                                        base64_decode($cert['crt']);
                                    $all_certs["/var/etc/wpa_supplicant_{$if}_clt.key"] =
                                        base64_decode($cert['prv']);
                                    $authentication[] = "client_cert=\"/var/etc/wpa_supplicant_{$if}_clt.crt\"";
                                    $authentication[] = "private_key=\"/var/etc/wpa_supplicant_{$if}_clt.key\"";
                                }
                            }
                            foreach ($all_certs as $filename => $content) {
                                @touch($filename);
                                @chmod($filename, 0600);
                                @file_put_contents($filename, $content);
                            }
                            break;
                    }
                } else {
                    $authentication[] = "psk=\"{$wlcfg['wpa']['passphrase']}\"";
                }
                $authentication = implode("\n", $authentication);
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
$authentication
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
            fwrite($fd_set, "{$hostapd} -B -P /var/run/hostapd_{$if}.pid /var/etc/hostapd_{$if}.conf\n");
        }
    }

    fclose($fd_set);

    /*
     * Making sure regulatory settings have actually changed
     * before applying, because changing them requires bringing
     * down all wireless networks on the interface.
     */
    exec("{$ifconfig} " . escapeshellarg($if), $output);
    $ifconfig_str = implode(PHP_EOL, $output);
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
        $clone_list = [];
        if (does_interface_exist("{$baseif}_wlan0")) {
            $clone_list[] = "{$baseif}_wlan0";
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
        $clones_up = [];
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
                    && isset($config['interfaces'][$friendly_if]['wireless']['wpa']['enable'])
                    && $config['interfaces'][$friendly_if]['wireless']['mode'] == 'bss'
                ) {
                    mwexec('/bin/sh /tmp/' . escapeshellarg($clone_if) . '_setup.sh');
                }
            }
        }
    }

    if (!empty($standard)) {
        /*
         * The mode must be specified in a separate command before ifconfig
         * will allow the mode and channel at the same time in the next.
         *
         * XXX but we do not have the standard when wireless was not configured...
         */
        mwexec("/sbin/ifconfig " . escapeshellarg($if) . " mode " . escapeshellarg($standard));
    }

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

    mwexecf('/sbin/ifconfig %s inet6 %s prefixlen %s no_dad', [$realif, $wancfg['ipaddrv6'], $wancfg['subnetv6']]);
}

function interfaces_addresses_flush($realif, $family = 4, $ifconfig_details = null)
{
    $family = $family === 6 ? 6 : 4;

    foreach (array_keys(interfaces_addresses($realif, true, $ifconfig_details)) as $tmpiface) {
        $tmpip = $tmpiface;

        if (is_linklocal($tmpip)) {
            /* never delete link-local */
            continue;
        } elseif (is_ipaddrv6($tmpip) || is_subnetv6($tmpip)) {
            if ($family != 6) {
                continue;
            }
        } elseif (is_subnetv4($tmpiface)) {
            if ($family != 4) {
                continue;
            }
            $tmpip = explode('/', $tmpiface)[0];
        }

        legacy_interface_deladdress($realif, $tmpip, $family);
    }
}

function interface_configure_mtu($device, $mtu, $ifconfig_details)
{
    global $config;

    if (strstr($device, 'vlan') || strstr($device, 'qinq')) {
        $parent_device = interface_parent_devices($device)[0];
        $parent_mtu = $mtu + 4;
        $force = false;

        $parent_cfg = $config['interfaces'][convert_real_interface_to_friendly_interface_name($parent_device)] ?? [];
        if (isset($parent_cfg['enable']) && !empty($parent_cfg['mtu'])) {
            $parent_mtu = $parent_cfg['mtu'];
            $force = true;
        }

        if ($force || $mtu > $ifconfig_details[$parent_device]['mtu']) {
            /* configure parent MTU recursively to avoid a fail for current device MTU requirement */
            interface_configure_mtu($parent_device, $parent_mtu, $ifconfig_details);
        }
    }

    if ($mtu != $ifconfig_details[$device]['mtu']) {
        legacy_interface_mtu($device, $mtu);
    }
}

function interface_configure($verbose = false, $interface = 'wan', $reload = false, $linkup = false)
{
    global $config;

    $wancfg = $config['interfaces'][$interface];
    $loaded = [];

    if (!isset($wancfg['enable'])) {
        return $loaded;
    }

    $wandescr = !empty($wancfg['descr']) ? $wancfg['descr'] : strtoupper($interface);

    $realif = get_real_interface($interface);
    $realifv6 = get_real_interface($interface, 'inet6');
    $devices = plugins_devices();

    /* get the correct hardware interface if PPP is involved or return the one we have */
    $realhwif = interface_ppps_hardware($realif)[0]; /* XXX support all MLPPP devices */

    if ($reload) {
        foreach ($devices as $device) {
            if (empty($device['function']) || empty($device['names'])) {
                continue;
            }

            if (in_array($realhwif, array_keys($device['names']))) {
                log_msg("Device $realhwif requires reload for $interface, configuring now");
                call_user_func_array($device['function'], [$realhwif]);
            }
        }
    }

    $ifconfig_details = legacy_interfaces_details();
    if (
        (strpos($realhwif, '/') === false && empty($ifconfig_details[$realhwif])) ||
        (strpos($realhwif, '/') === 0 && !file_exists($realhwif))
    ) {
        log_msg(sprintf('Unable to configure nonexistent interface %s (%s)', $interface, $realhwif), LOG_ERR);
        return $loaded;
    }

    /* XXX mpd5 $realhwif may be a device node path */

    service_log(sprintf('Configuring %s interface...', $wandescr), $verbose);

    if (!empty($wancfg['ipaddr'])) {
        interfaces_addresses_flush($realif, 4, $ifconfig_details);
    }

    if (!empty($wancfg['ipaddrv6'])) {
        interfaces_addresses_flush($realifv6, 6, $ifconfig_details);
    }

    if (!$linkup) {
        /* XXX wireless configuration: shouldn't live in interface config */
        interface_wireless_configure($realif, $wancfg);
    }

    /* get expected and current MAC address from the configuration and system */
    $mac_prev = $ifconfig_details[$realhwif]['macaddr'] ?? '';
    $mac_next = !empty($wancfg['spoofmac']) ? $wancfg['spoofmac'] : ($ifconfig_details[$realhwif]['macaddr_hw'] ?? '');

    /*
     * Disable MAC spoofing if the device type does not support it,
     * its use is prohibited or when it is otherwise reserved.
     */
    foreach ($devices as $device) {
        if (preg_match('/' . $device['pattern'] . '/', $realhwif)) {
            if ($device['spoofmac'] == false) {
                $mac_next = '';
            }
            break;
        }
    }

    /* in case of LAGG the empty MAC may be used which we need to ignore */
    if ($mac_next == '00:00:00:00:00:00') {
        $mac_next = '';
    }

    /*
     * Do not try to reapply the spoofed MAC if it's already applied.
     * When ifconfig link is used, it cycles the interface down/up,
     * which triggers the interface config again, which attempts to
     * spoof the MAC again which cycles the link again.
     */
    if (!empty($mac_prev) && !empty($mac_next) && strcasecmp($mac_prev, $mac_next)) {
        /*
         * When the hardware interface matches the IPv6 interface where we set
         * the MAC address we can assist in providing the accompanying link-local
         * address (EUI-64 embedded MAC address) by nudging the kernel to reapply
         * this address due to the auto_linklocal field which we do not operate
         * unless for bridges, but that one is a reversed use case.
         *
         * Delete all link-local addresses and send the "down" event to bring
         * the kernel into the transition where it will auto-add the right
         * address on the following "up" event.
         */
        $mac_cmd[] = exec_safe('/sbin/ifconfig %s link %s', [$realhwif, $mac_next]);
        if ($realhwif == $realifv6) {
            $ll_found = false;
            foreach (array_keys(interfaces_addresses($realifv6, true, $ifconfig_details)) as $tmpiface) {
                if (is_linklocal($tmpiface)) {
                    $tmpip = explode('%', $tmpiface)[0];
                    legacy_interface_deladdress($realifv6, $tmpip, 6);
                    $ll_found = true;
                }
            }
            if ($ll_found) {
                $mac_cmd[] = 'down';
            }
        }
        mwexec(join(' ', $mac_cmd));
    }

    /* only try to set media properties when requested */
    if (!empty($wancfg['media']) || !empty($wancfg['mediaopt'])) {
        $intf_details = $ifconfig_details[$realhwif];
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

    /* set p(ermanent)-promiscuous mode required for e.g. VLAN MAC spoofing */
    if (in_array('ppromisc', $ifconfig_details[$realhwif]['flags'] ?? []) !== !empty($wancfg['promisc'])) {
        mwexecf('/sbin/ifconfig %s %spromisc', [$realhwif, empty($wancfg['promisc']) ? '-' : '']);
    }

    /* apply interface hardware settings (tso, lro, ..) */
    configure_interface_hardware($realhwif, $ifconfig_details);

    if (!empty($wancfg['mtu']) && strpos($realhwif, '/') === false) {
        interface_configure_mtu($realhwif, $wancfg['mtu'], $ifconfig_details);
    }

    /* since the connectivity can include IPv4 and/or IPv6 let the function decide */
    interface_ppps_configure($interface);

    switch ($wancfg['ipaddr'] ?? 'none') {
        case 'dhcp':
            interface_dhcp_configure($interface);
            break;
        default:
            interface_static_configure($interface, $wancfg);
            break;
    }

    /*
     * Unconditional actions on interface include:
     *
     * 1. Disable accepting router advertisements (SLAAC) on main device
     * 2. Enable duplicate address detection (DAD) on main device
     * 3. Set interface description to get more useful ifconfig output
     * 4. Set "up" flag for UP/RUNNING requirement, adding an address
     *    already does that so at this point try to be more consistent.
     */
    $interface_descr = sprintf('%s (%s)', !empty($wancfg['descr']) ? $wancfg['descr'] : strtoupper($interface), $interface);
    /* XXX we should maybe set "ifdisabled" but it could be dangerous for assigned tunnel devices */
    mwexecf('/sbin/ifconfig %s inet6 -accept_rtadv -no_dad description %s up', [$realif, $interface_descr]);

    switch (isset($config['system']['ipv6allow']) ? ($wancfg['ipaddrv6'] ?? 'none') : 'none') {
        case 'slaac':
        case 'dhcp6':
            mwexecf('/sbin/ifconfig %s inet6 accept_rtadv -ifdisabled up', $realifv6);

            if (!interface_ppps_bound($interface)) {
                interface_dhcpv6_prepare($interface, $wancfg);
                interface_dhcpv6_configure($interface, $wancfg);
            }
            break;
        case '6rd':
            interface_6rd_configure($interface, $wancfg, $reload || $linkup);
            break;
        case '6to4':
            interface_6to4_configure($interface, $wancfg, $reload || $linkup);
            break;
        case 'track6':
            interface_track6_configure($interface, $wancfg, $reload || $linkup);
            break;
        default:
            if (!interface_ppps_bound($interface)) {
                interface_static6_configure($interface, $wancfg);
            }
            break;
    }

    /*
     * In most cases the IPv6 device is the same as IPv4.
     * If not the safe spot to add a description/up is below
     * when the interface was most likely created.
     */
    if ($realif != $realifv6) {
        mwexecf('/sbin/ifconfig %s inet6 description %s up', [$realifv6, $interface_descr]);
    }

    interfaces_vips_configure($interface);

    /* XXX device member plugin hook */
    link_interface_to_bridge($interface, $realif, $ifconfig_details);

    /* XXX may be called twice since also tied to dhcp configure */
    interfaces_staticarp_configure($interface, $ifconfig_details);

    service_log("done.\n", $verbose);

    if ($reload) {
        system_routing_configure($verbose, $interface);
        plugins_configure('ipsec', $verbose, [$interface]);
        plugins_configure('dhcp', $verbose);
        plugins_configure('dns', $verbose);
        /* XXX not ideal but avoids "errors" in the log */
        plugins_configure('newwanip:rfc2136', $verbose, [[$interface]]);
    }

    /* XXX device dependency plugin hook */
    $loaded = array_merge($loaded, link_interface_to_gre($interface, true));
    $loaded = array_merge($loaded, link_interface_to_gif($interface, true));

    /* XXX reload routes for linked devices -- not great but also not avoidable at the moment */
    interfaces_restart_by_device($verbose, $loaded, false);

    return $loaded;
}

function interface_track6_configure($interface, $lancfg, $reload = false)
{
    global $config;

    if (!is_array($lancfg) || empty($lancfg['track6-interface'])) {
        return;
    }

    $trackcfg = $config['interfaces'][$lancfg['track6-interface']];
    if (!isset($trackcfg['enable'])) {
        log_msg("Interface {$interface} tracking nonexistent interface {$lancfg['track6-interface']}", LOG_ERR);
        return;
    }

    $realifv6 = get_real_interface($interface, 'inet6');
    mwexecf('/sbin/ifconfig %s inet6 %sifdisabled', [$realifv6, isset($lancfg['enable']) ? '-' : '']);

    switch ($trackcfg['ipaddrv6']) {
        case '6to4':
            interface_track6_6to4_configure($interface, $lancfg);
            break;
        case '6rd':
            interface_track6_6rd_configure($interface, $lancfg);
            break;
        case 'slaac':
            mwexecf('/sbin/ifconfig %s inet6 accept_rtadv', [$realifv6]);
            /* FALLTHROUGH */
        case 'dhcp6':
            if ($reload || !isset($lancfg['enable'])) {
                interface_dhcpv6_prepare($lancfg['track6-interface'], $trackcfg);
                killbypid('/var/run/dhcp6c.pid', 'HUP');
            }
            break;
    }
}

function interface_track6_6rd_configure($interface, $lancfg)
{
    global $config;

    if (!isset($lancfg['enable'])) {
        /* deconfiguring is done elsewhere as it simply removes addresses */
        return;
    }

    $wancfg = $config['interfaces'][$lancfg['track6-interface']];

    if (empty($wancfg)) {
        log_msg("Interface {$interface} tracking nonexistent interface {$lancfg['track6-interface']}", LOG_ERR);
        return;
    }

    $ip4address = get_interface_ip($lancfg['track6-interface']);

    if (!is_ipaddrv4($ip4address)) {
        log_msg("The interface IPv4 address '{$ip4address}' on interface '{$lancfg['track6-interface']}' is invalid, not configuring 6RD tracking", LOG_ERR);
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
    list ($oip) = interfaces_primary_address6($interface);
    if (!empty($oip)) {
        mwexec("/sbin/ifconfig {$lanif} inet6 {$oip} delete");
    }

    log_msg("rd6 {$interface} with ipv6 address {$rd6lan} based on {$lancfg['track6-interface']} ipv4 {$ip4address}");
    mwexec("/sbin/ifconfig {$lanif} inet6 {$rd6lan} prefixlen 64");
}

function interface_track6_6to4_configure($interface, $lancfg)
{
    if (!isset($lancfg['enable'])) {
        /* deconfiguring is done elsewhere as it simply removes addresses */
        return;
    }

    $ip4address = get_interface_ip($lancfg['track6-interface']);

    if (!is_ipaddrv4($ip4address) || is_private_ip($ip4address)) {
        log_msg("The interface IPv4 address '{$ip4address}' on interface '{$lancfg['track6-interface']}' is not public, not configuring 6to4 tracking", LOG_ERR);
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
    list ($oip) = interfaces_primary_address6($interface);
    if (!empty($oip)) {
        mwexec("/sbin/ifconfig {$lanif} inet6 {$oip} delete");
    }

    log_msg("6to4 {$interface} with ipv6 address {$sixto4lan} based on {$lancfg['track6-interface']} ipv4 {$ip4address}");
    mwexec("/sbin/ifconfig {$lanif} inet6 {$sixto4lan} prefixlen 64");
}

function interface_6rd_configure($interface, $wancfg, $update = false)
{
    if (!is_array($wancfg)) {
        return;
    }

    $ip4address = get_interface_ip($interface);

    if (!is_ipaddrv4($ip4address)) {
        log_msg("The interface IPv4 address '{$ip4address}' on interface '{$interface}' is invalid, not configuring 6RD tunnel", LOG_ERR);
        return false;
    }

    $hexwanv4 = return_hex_ipv4(!empty($wancfg['prefix-6rd-v4addr']) ? $wancfg['prefix-6rd-v4addr'] : $ip4address);

    if (!is_numeric($wancfg['prefix-6rd-v4plen']) || $wancfg['prefix-6rd-v4plen'] < 0 || $wancfg['prefix-6rd-v4plen'] > 32) {
        log_msg("The interface IPv4 prefix '{$wancfg['prefix-6rd-v4plen']}' on interface '{$interface}' is invalid, assuming zero", LOG_WARNING);
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
    legacy_interface_destroy($stfiface);
    legacy_interface_create('stf', $stfiface);
    legacy_interface_flags($stfiface, 'link2');

    $mtu = !empty($wancfg['mtu']) ? $wancfg['mtu'] - 20 : 1280;
    if ($mtu > 1480) {
        $mtu = 1480;
    }
    legacy_interface_mtu($stfiface, $mtu);

    /* use original prefix length for network address to avoid setting the same subnet as on the LAN side (/64 prefix) */
    mwexecf('/sbin/ifconfig %s inet6 %s/%s', array($stfiface, $rd6prefix, $rd6prefixlen));
    mwexecf('/sbin/ifconfig %s stfv4br %s', array($stfiface, $wancfg['gateway-6rd']));
    mwexecf('/sbin/ifconfig %s stfv4net %s/%s', array($stfiface, $ip4address, $wancfg['prefix-6rd-v4plen']));
    mwexecf('/usr/local/sbin/ifctl -i %s -6rd -a %s', [$stfiface, $rd6brgw]);

    $gateways = new \OPNsense\Routing\Gateways();
    $ip4gateway = $gateways->getInterfaceGateway($interface, 'inet');
    system_host_route($wancfg['gateway-6rd'], $ip4gateway);

    link_interface_to_track6($interface, $update);
}

function interface_6to4_configure($interface, $wancfg, $update = false)
{
    if (!is_array($wancfg)) {
        return;
    }

    $ip4address = get_interface_ip($interface);

    if (!is_ipaddrv4($ip4address) || is_private_ip($ip4address)) {
        log_msg("The interface IPv4 address '{$ip4address}' on interface '{$interface}' is not public, not configuring 6to4 tunnel", LOG_ERR);
        return;
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
    legacy_interface_destroy($stfiface);
    legacy_interface_create('stf', $stfiface);
    legacy_interface_flags($stfiface, 'link2');

    $mtu = !empty($wancfg['mtu']) ? $wancfg['mtu'] - 20 : 1280;
    if ($mtu > 1480) {
        $mtu = 1480;
    }
    legacy_interface_mtu($stfiface, $mtu);

    mwexecf('/sbin/ifconfig %s inet6 %s prefixlen 16', array($stfiface, $stflan));
    mwexecf('/usr/local/sbin/ifctl -i %s -6rd -a %s', [$stfiface, $stfbrgw]);

    $gateways = new \OPNsense\Routing\Gateways();
    $ip4gateway = $gateways->getInterfaceGateway($interface, 'inet');
    system_host_route('192.88.99.1', $ip4gateway);

    link_interface_to_track6($interface, $update);
}

function interface_dhcpv6_configure($interface, $wancfg)
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
    killbypid('/var/run/rtsold.pid');

    $rtsoldcommand = exec_safe('/usr/sbin/rtsold -aiu -p %s -A %s -R %s', [
        '/var/run/rtsold.pid',
        '/var/etc/rtsold_script.sh',
        '/usr/local/opnsense/scripts/interfaces/rtsold_resolvconf.sh',
    ]);

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

function interface_dhcpv6_prepare($interface, $wancfg, $cleanup = false)
{
    if (!is_array($wancfg)) {
        return;
    }

    $wanif = get_real_interface($interface, 'inet6');
    $id = interface_dhcpv6_id($interface);
    $syscfg = config_read_array('system');

    if (!empty($wancfg['adv_dhcp6_config_file_override'])) {
        $dhcp6cfile = $wancfg['adv_dhcp6_config_file_override_path'];
        if (file_exists($dhcp6cfile)) {
            $dhcp6cconf = file_get_contents($dhcp6cfile);
            $dhcp6cconf = DHCP6_Config_File_Substitutions($wancfg, $wanif, $dhcp6cconf);
        } else {
            log_msg("DHCP6 config file override does not exist: '{$dhcp6cfile}'", LOG_ERR);
        }
    } elseif (!empty($wancfg['adv_dhcp6_config_advanced'])) {
        $dhcp6cconf = DHCP6_Config_File_Advanced($interface, $wancfg, $wanif, $id);
    } else {
        $dhcp6cconf = DHCP6_Config_File_Basic($interface, $wancfg, $wanif, $id);
    }

    if (!$cleanup) {
        @file_put_contents("/var/etc/dhcp6c_{$interface}.conf", $dhcp6cconf);
    } else {
        @unlink("/var/etc/dhcp6c_{$interface}.conf");
    }

    $dhcp6cscript = <<<EOF
#!/bin/sh

case \$REASON in
INFOREQ|REBIND|RENEW|REQUEST)
    /usr/bin/logger -t dhcp6c "dhcp6c_script: \$REASON on {$wanif} executing"

    ARGS=
    for NAMESERVER in \${new_domain_name_servers}; do
        ARGS="\${ARGS} -a \${NAMESERVER}"
    done
    /usr/local/sbin/ifctl -i {$wanif} -6nd \${ARGS}

    ARGS=
    for DOMAIN in \${new_domain_name}; do
        ARGS="\${ARGS} -a \${DOMAIN}"
    done
    /usr/local/sbin/ifctl -i {$wanif} -6sd \${ARGS}

    ARGS=
    for PD in \${PDINFO}; do
        ARGS="\${ARGS} -a \${PD}"
    done
    if [ \${REASON} != "RENEW" -a \${REASON} != "REBIND" ]; then
        # cannot update since PDINFO may be incomplete in these cases
        # as each PD is being handled separately via the client side
        /usr/local/sbin/ifctl -i {$wanif} -6pd \${ARGS}
    fi

    FORCE=
    if [ \${REASON} = "REQUEST" ]; then
        /usr/bin/logger -t dhcp6c "dhcp6c_script: \$REASON on {$wanif} renewal"
        FORCE=force
    fi

    /usr/local/sbin/configctl -d interface newipv6 {$wanif} \${FORCE}
    ;;
EXIT|RELEASE)
    /usr/bin/logger -t dhcp6c "dhcp6c_script: \$REASON on {$wanif} executing"

    /usr/local/sbin/ifctl -i {$wanif} -6nd
    /usr/local/sbin/ifctl -i {$wanif} -6sd
    /usr/local/sbin/ifctl -i {$wanif} -6pd

    /usr/local/sbin/configctl -d interface newipv6 {$wanif}
    ;;
*)
    /usr/bin/logger -t dhcp6c "dhcp6c_script: \$REASON on {$wanif} ignored"
    ;;
esac

EOF;

    @file_put_contents("/var/etc/dhcp6c_{$interface}_script.sh", $dhcp6cscript);
    @chmod("/var/etc/dhcp6c_{$interface}_script.sh", 0755);

    $dhcp6ccommand = exec_safe('/usr/local/sbin/dhcp6c -c %s -p %s', [
            '/var/etc/dhcp6c.conf',
            '/var/run/dhcp6c.pid',
    ]);

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
        if (empty($_wancfg['ipaddrv6']) || ($_wancfg['ipaddrv6'] != 'dhcp6' && $_wancfg['ipaddrv6'] != 'slaac')) {
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
if grep -q "^interface \${1} " /var/etc/radvd.conf; then
       echo "Rejecting own configuration."
       exit 0
fi
if [ -n "\${2}" ]; then
    # Note that the router file can be written by ppp-linkup.sh or
    # this script so do not clear the file as it may already exist.
    /usr/local/sbin/ifctl -i \${1} -6rd -a \${2}
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
    /usr/bin/logger -t dhcp6c "RTSOLD script - Starting dhcp6c daemon"
    {$dhcp6ccommand}
fi

EOD;

    @file_put_contents('/var/etc/rtsold_script.sh', $rtsold_script);
    @chmod('/var/etc/rtsold_script.sh', 0755);
}

function DHCP6_Config_File_Basic($interface, $wancfg, $wanif, $id = 0)
{
    $dhcp6cconf = "interface {$wanif} {\n";

    if ($wancfg['ipaddrv6'] == 'slaac') {
        /* for SLAAC interfaces we do fire off a dhcp6 client for just our name servers */
        $dhcp6cconf .= "  information-only;\n";
        $dhcp6cconf .= "  request domain-name-servers;\n";
        $dhcp6cconf .= "  request domain-name;\n";
        $dhcp6cconf .= "  script \"/var/etc/dhcp6c_{$interface}_script.sh\"; # we'd like some nameservers please\n";
        $dhcp6cconf .= "};\n";
    } else {
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
            $dhcp6cconf .= "id-assoc pd {$id} {\n";
            if (isset($wancfg['dhcp6-ia-pd-send-hint'])) {
                $preflen = 64 - $wancfg['dhcp6-ia-pd-len'];
                $dhcp6cconf .= "  prefix ::/{$preflen} infinity;\n";
            }
            if (isset($wancfg['dhcp6-prefix-id']) && is_numeric($wancfg['dhcp6-prefix-id'])) {
                $dhcp6cconf .= "  prefix-interface {$wanif} {\n";
                $dhcp6cconf .= "    sla-id {$wancfg['dhcp6-prefix-id']};\n";
                $dhcp6cconf .= "    sla-len {$wancfg['dhcp6-ia-pd-len']};\n";
                if (isset($wancfg['dhcp6_ifid'])) {
                    $dhcp6cconf .= "    ifid {$wancfg['dhcp6_ifid']};\n";
                }
                $dhcp6cconf .= "  };\n";
            }
            foreach (link_interface_to_track6($interface) as $friendly => $lancfg) {
                if (is_numeric($lancfg['track6-prefix-id'])) {
                    $trackifv6 = get_real_interface($friendly, 'inet6');
                    $dhcp6cconf .= "  prefix-interface {$trackifv6} {\n";
                    $dhcp6cconf .= "    sla-id {$lancfg['track6-prefix-id']};\n";
                    $dhcp6cconf .= "    sla-len {$wancfg['dhcp6-ia-pd-len']};\n";
                    if (isset($lancfg['track6_ifid'])) {
                        $dhcp6cconf .= "    ifid {$lancfg['track6_ifid']};\n";
                    }
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
    if (!empty($wancfg['adv_dhcp6_id_assoc_statement_address_enable'])) {
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

        if (isset($wancfg['dhcp6-prefix-id']) && is_numeric($wancfg['dhcp6-prefix-id'])) {
            $id_assoc_statement_prefix .= "  prefix-interface {$wanif} {\n";
            $id_assoc_statement_prefix .= "    sla-id {$wancfg['dhcp6-prefix-id']};\n";
            if (
                ($wancfg['adv_dhcp6_prefix_interface_statement_sla_len'] >= 0) &&
                ($wancfg['adv_dhcp6_prefix_interface_statement_sla_len'] <= 128)
            ) {
                $id_assoc_statement_prefix .= "    sla-len {$wancfg['adv_dhcp6_prefix_interface_statement_sla_len']};\n";
            }
            if (isset($wancfg['dhcp6_ifid'])) {
                $id_assoc_statement_prefix .= "    ifid {$wancfg['dhcp6_ifid']};\n";
            }
            $id_assoc_statement_prefix .= "  };\n";
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
                if (isset($lancfg['track6_ifid'])) {
                    $id_assoc_statement_prefix .= "    ifid {$lancfg['track6_ifid']};\n";
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
        if (preg_match("/((([0-9]{4}-)?[0-9]{2}-[0-9]{2} )?[0-9]{2}:[0-9]{2})|(forever)/", $wancfg['adv_dhcp6_key_info_statement_expire'])) {
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
        log_msg("Interface '{$interface}' does not have a DHCP configuration.", LOG_ERR);
        return;
    }

    $wanif = get_real_interface($interface);
    if (empty($wanif)) {
        log_msg("Invalid interface '{$interface}' in interface_dhcp_configure()", LOG_ERR);
        return;
    }

    killbypid("/var/run/dhclient.{$wanif}.pid");

    $fd = fopen("/var/etc/dhclient_{$interface}.conf", "w");
    if (!$fd) {
        log_msg("Error: cannot open dhclient_{$interface}.conf in interface_dhcp_configure() for writing.", LOG_ERR);
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

    if (!empty($wancfg['dhcprejectfrom'])) {
        $dhclientconf .= "  reject {$wancfg['dhcprejectfrom']};\n";
    }

    if (isset($wancfg['dhcpvlanprio'])) {
        $dhclientconf .= "  vlan-pcp {$wancfg['dhcpvlanprio']};\n";
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
            log_msg("DHCP config file override does not exist: '{$dhclientfile}'", LOG_ERR);
        }
    }

    fwrite($fd, $dhclientconf);
    fclose($fd);

    legacy_interface_flags($wanif, 'up');

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
            ${$pt_variable} = "\t{$PT_Name} {$wancfg[$Protocol_Timing]};\n";
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
    if (!empty($wancfg['dhcprejectfrom'])) {
        $dhclientconf .= "\treject {$wancfg['dhcprejectfrom']};\n";
    }
    if (isset($wancfg['dhcpvlanprio'])) {
        $dhclientconf .= "\tvlan-pcp {$wancfg['dhcpvlanprio']};\n";
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
    // search direct
    $all_interfaces = legacy_config_get_interfaces();
    foreach ($all_interfaces as $ifname => $ifcfg) {
        if ($ifname == $interface || $ifcfg['if'] == $interface) {
            return $ifname;
        }
    }

    // search related
    foreach (array_keys($all_interfaces) as $ifname) {
        if (get_real_interface($ifname) == $interface) {
            return $ifname;
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
            } elseif (strstr($interface, '_vip')) {
                if (isset($config['virtualip']['vip'])) {
                    foreach ($config['virtualip']['vip'] as $counter => $vip) {
                        if ($vip['mode'] == 'carp') {
                            if ($interface == "{$vip['interface']}_vip{$vip['vhid']}") {
                                return "{$vip['descr']} ({$vip['subnet']})";
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

/* collect hardware device parents for VLAN, LAGG and bridges */
function interface_parent_devices($device, $as_interface = false)
{
    $parents = [];

    if (strstr($device, 'vlan') || strstr($device, 'qinq')) {
        /* XXX maybe if we have a qinq type return both parents? */
        foreach (config_read_array('vlans', 'vlan') as $vlan) {
            if ($device == $vlan['vlanif']) {
                $parents[] = $vlan['if'];
                break;
            }
        }
    } elseif (strstr($device, 'bridge')) {
        foreach (config_read_array('bridges', 'bridged') as $bridge) {
            if ($device == $bridge['bridgeif']) {
                foreach (explode(',', $bridge['members'] ?? '') as $member) {
                    /* bridge stores members as configured interfaces */
                    $parents[] = $as_interface ? $member : get_real_interface($member);
                }
                break;
            }
        }
    } elseif (strstr($device, 'lagg')) {
        foreach (config_read_array('laggs', 'lagg') as $lagg) {
            if ($device == $lagg['laggif']) {
                foreach (explode(',', $lagg['members']) as $member) {
                    $parents[] = $member;
                }
                break;
            }
        }
    }

    return $parents;
}

function interface_get_wireless_base($wlif)
{
    if (!strstr($wlif, '_wlan')) {
        return $wlif;
    } else {
        return substr($wlif, 0, stripos($wlif, '_wlan'));
    }
}

function get_real_interface($interface = 'wan', $family = 'all')
{
    global $config;

    $realif = $interface;

    switch ($interface) {
        /* XXX legacy cruft starts here */
        case 'enc0':
        case 'openvpn':
        case 'ppp':
            break;
        case 'ipsec':
            $realif = 'enc0';
            break;
        /* XXX legacy cruft ends here */
        default:
            if (empty($config['interfaces'][$interface])) {
                /* assume the interface exists to force an error elsewhere */
                break;
            }

            $cfg = &config_read_array('interfaces', $interface);

            /* set default for IPv4 and IPv6 lookups */
            $realif = $cfg['if'];

            if (isset($cfg['wireless']) && !strstr($realif, '_wlan')) {
                $realif .= '_wlan0';
            }

            if ($family == 'inet6') {
                switch ($cfg['ipaddrv6'] ?? 'none') {
                    case '6rd':
                    case '6to4':
                        $realif = "{$interface}_stf";
                        break;
                    default:
                        break;
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

    /* search for the route with the largest subnet mask */
    $largest_mask = 0;
    $best_if = null;

    foreach ($output as $line) {
        $fields = preg_split("/\s+/", $line);
        if (is_subnet($fields[0])) {
            if (ip_in_subnet($ipaddress, $fields[0])) {
                list($ip, $mask) = explode('/', $fields[0]);
                if ($mask > $largest_mask) {
                    $best_if = $fields[5];
                    $largest_mask = $mask;
                }
            }
        }
    }

    if (!empty($best_if)) {
        return $best_if;
    }

    $ret = shell_safe('/sbin/route -n get %s | /usr/bin/awk \'/interface/ { print $2; };\'', $ipaddress);
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
    $list = [];

    if (empty($wanif)) {
        return $list;
    }

    $wancfg = config_read_array('interfaces', $wanif);

    if (!isset($wancfg['enable']) || empty($wancfg['ipaddrv6'])) {
        return $list;
    }

    foreach (legacy_config_get_interfaces(['virtual' => false]) as $lanif => $lancfg) {
        if (!isset($lancfg['enable']) || empty($lancfg['ipaddrv6'])) {
            continue;
        }

        if ($lancfg['ipaddrv6'] == 'track6' && $lancfg['track6-interface'] == $wanif) {
            $list[$lanif] = $lancfg;
        }
    }

    if ($update) {
        foreach ($list as $lanif => $lancfg) {
            interface_track6_configure($lanif, $lancfg);
        }

        if (count($list)) {
            plugins_configure('dhcp', false, ['inet6']);
        }
    }

    return $list;
}

function interfaces_restart_by_device($verbose, $devices, $reconfigure = true)
{
    $restartifs = [];

    foreach (legacy_config_get_interfaces(['enable' => true, 'virtual' => false]) as $ifname => $ifparent) {
        foreach ($devices as $device) {
            if ($ifparent['if'] == $device) {
                $restartifs[$ifname] = 1;
            }
        }
    }

    if ($reconfigure) {
        foreach (array_keys($restartifs) as $ifname) {
            interface_configure($verbose, $ifname);
        }
    }

    system_routing_configure($verbose, array_keys($restartifs));
}

function link_interface_to_bridge($interface, $attach_device = null, $ifconfig_details = [/* must be set for attach to work */])
{
    foreach (config_read_array('bridges', 'bridged') as $bridge) {
        if (!in_array($interface, explode(',', $bridge['members'] ?? ''))) {
            continue;
        }

        if ($attach_device) {
            if (empty($ifconfig_details[$bridge['bridgeif']])) {
                log_msg("Device {$attach_device} cannot be added to non-existent {$bridge['bridgeif']}, skipping now.");
                break;
            } elseif (empty($ifconfig_details[$attach_device])) {
                log_msg("Device {$attach_device} was not found so it cannot be added to {$bridge['bridgeif']}, skipping now.");
                break;
            }

            configure_interface_hardware($attach_device);

            /*
             * The MTU of the first member interface to be added is used as the bridge
             * MTU.  All additional members are required to have exactly the same MTU
             * value.
             */
            if ($ifconfig_details[$bridge['bridgeif']]['mtu'] != $ifconfig_details[$attach_device]['mtu']) {
                legacy_interface_mtu($attach_device, $ifconfig_details[$bridge['bridgeif']]['mtu']);
            }

            legacy_interface_flags($attach_device, 'up');
            legacy_bridge_member($bridge['bridgeif'], $attach_device);
        }

        return $bridge['bridgeif'];
    }

    return null;
}

function link_interface_to_gre($interface, $update = false, $family = null)
{
    global $config;

    $aliaslist = get_configured_ip_aliases_list();
    $result = [];

    if (isset($config['gres']['gre'])) {
        foreach ($config['gres']['gre'] as $gre) {
            $parent = explode('_vip', $gre['if'])[0];
            if (is_ipaddr($parent)) {
                foreach ($aliaslist as $ip => $int) {
                    if ($ip == $parent) {
                        $parent = $int;
                        break;
                    }
                }
            }

            if ($parent != $interface) {
                continue;
            } elseif ($family == 4 && !is_ipaddrv4($gre['remote-addr'])) {
                continue;
            } elseif ($family == 6 && !is_ipaddrv6($gre['remote-addr'])) {
                continue;
            }

            if ($update && empty(_interfaces_gre_configure($gre))) {
                /* only return the ones that did configure correctly */
                continue;
            }

            /* callers are only concerned with the resulting device names */
            $result[] = $gre['greif'];
        }
    }

    return $result;
}

function link_interface_to_gif($interface, $update = false, $family = null)
{
    global $config;

    $result = [];

    if (isset($config['gifs']['gif'])) {
        foreach ($config['gifs']['gif'] as $gif) {
            if (explode('_vip', $gif['if'])[0] != $interface) {
                continue;
            } elseif ($family == 4 && !is_ipaddrv4($gif['remote-addr'])) {
                continue;
            } elseif ($family == 6 && !is_ipaddrv6($gif['remote-addr'])) {
                continue;
            }

            if ($update && empty(_interfaces_gif_configure($gif))) {
                /* only return the ones that did configure correctly */
                continue;
            }

            /* callers are only concerned with the resulting device names */
            $result[] = $gif['gifif'];
        }
    }

    return $result;
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
    if (is_ipaddrv4($interface)) {
        return $interface;
    }

    if (strstr($interface, '_vip')) {
        foreach (config_read_array('virtualip', 'vip') as $vip) {
            if ($vip['mode'] == 'carp') {
                if ($interface == "{$vip['interface']}_vip{$vip['vhid']}" && is_ipaddrv4($vip['subnet'])) {
                    return $vip['subnet'];
                }
            }
        }
    }

    list ($ip) = interfaces_primary_address($interface, $ifconfig_details);

    return $ip;
}

function get_interface_ipv6($interface = 'wan', $ifconfig_details = null, $mode = 'primary')
{
    if (is_ipaddrv6($interface)) {
        return $interface;
    }

    if (strstr($interface, '_vip')) {
        foreach (config_read_array('virtualip', 'vip') as $vip) {
            if ($vip['mode'] == 'carp') {
                if ($interface == "{$vip['interface']}_vip{$vip['vhid']}" && is_ipaddrv6($vip['subnet'])) {
                    return $vip['subnet'];
                }
            }
        }
    }

    switch ($mode) {
        case 'routed':
            list ($ipv6) = interfaces_routed_address6($interface, $ifconfig_details);
            break;
        case 'scoped':
            list ($ipv6) = interfaces_scoped_address6($interface, $ifconfig_details);
            break;
        case 'primary':
        default:
            list ($ipv6) = interfaces_primary_address6($interface, $ifconfig_details);
            break;
    }

    return $ipv6;
}

function get_interface_mac($interface, $ifconfig_details = null)
{
    $intf_details = [];

    if (empty($ifconfig_details)) {
        $intf_details = legacy_interface_details($interface);
    } elseif (!empty($ifconfig_details[$interface])) {
        $intf_details = $ifconfig_details[$interface];
    }

    return $intf_details['macaddr'];
}

function get_vip_descr($ipaddress)
{
    global $config;

    foreach ($config['virtualip']['vip'] as $vip) {
        if ($vip['subnet'] == $ipaddress) {
            return ($vip['descr'] ?? '');
        }
    }

    return '';
}

function interfaces_staticarp_configure($if, $ifconfig_details = null)
{
    global $config;

    $ifcfg = $config['interfaces'][$if];
    if (empty($ifcfg['if']) || !isset($ifcfg['enable'])) {
        return;
    }

    if (empty($ifconfig_details)) {
        $ifconfig_details = legacy_interfaces_details($ifcfg['if']);
    }

    if (empty($ifconfig_details[$ifcfg['if']])) {
        return;
    }

    $have = in_array('staticarp', $ifconfig_details[$ifcfg['if']]['flags']);
    $want = isset($config['dhcpd'][$if]['staticarp']);

    if ($have !== $want) {
        mwexecf('/sbin/ifconfig %s %sstaticarp', [$ifcfg['if'], $want ? '' : '-']);
        mwexecf('/usr/sbin/arp -d -i %s -a', [$ifcfg['if']]);
    }

    interfaces_neighbors_configure($ifcfg['if'], $ifconfig_details);
}

function interfaces_neighbors_configure($device = null, $ifconfig_details = null)
{
    $subnets = [];

    if (!empty($device)) {
        if (empty($ifconfig_details) || empty($ifconfig_details[$device])) {
            /* when called with an interface, require $ifconfig_details being passed */
            return;
        }
        foreach (['ipv4', 'ipv6'] as $proto) {
            if (!empty($ifconfig_details[$device])) {
                foreach ($ifconfig_details[$device][$proto] as $item) {
                    $subnets[] = $item['ipaddr'] . '/' . $item['subnetbits'];
                }
            }
        }
    }

    $current_neightbors = [];
    foreach ((new \OPNsense\Interfaces\Neighbor())->neighbor->iterateItems() as $key => $node) {
        $found = empty($if);    /* unfiltered when no $if provided */
        foreach ($subnets as $subnet) {
            $found = ip_in_subnet((string)$node->ipaddress, $subnet);
            if ($found) {
                break;
            }
        }
        if ($found) {
            // IPv4 [arp] or IPv6 [ndp]
            if (strpos($node->ipaddress, ":") === false) {
                mwexecf('/usr/sbin/arp -s %s %s', [$node->ipaddress, $node->etheraddr]);
            } else {
                mwexecf('/usr/sbin/ndp -s %s %s', [$node->ipaddress, $node->etheraddr]);
            }
        }
        $current_neightbors[] = (string)$node->ipaddress;
    }

    /* persist accounted addresses, without a cleanup that would be all seen since last cleanup */
    $fobj = new \OPNsense\Core\FileObject('/tmp/interfaces_neighbors.json', 'a+');
    $current = $fobj->readJson() ?? [];
    $fobj->truncate(0)->writeJson(
        !empty($device) ? array_unique(array_merge($current_neightbors, $current)) : $current_neightbors
    );
    unset($fobj);
    /* only cleanup when applying all interfaces */
    if (empty($device) && is_array($current)) {
        foreach ($current as $item) {
            if (is_string($item) && is_ipaddr($item) && !in_array($item, $current_neightbors)) {
                if (strpos($item, ":") === false) {
                    mwexecf('/usr/sbin/arp -d %s', [$item]);
                } else {
                    mwexecf('/usr/sbin/ndp -d %s', [$item]);
                }
            }
        }
    }
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

function interfaces_addresses($interfaces, $as_subnet = false, $ifconfig_details = null)
{
    global $config;

    $ifcache = [];
    $realifs = [];
    $result = [];

    if (!is_array($interfaces)) {
        $interfaces = [$interfaces];
    }

    foreach ($interfaces as $interface) {
        if (isset($config['interfaces'][$interface])) {
            foreach (['all', 'inet6'] as $family) {
                $tmpif = get_real_interface($interface, $family);
                if (empty($config['interfaces'][$interface]['virtual'])) {
                    $ifcache[$tmpif] = $interface;
                }
                $realifs[] = $tmpif;
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
        foreach (['ipv4', 'ipv6'] as $proto) {
            if (empty($intf_details[$realif][$proto])) {
                continue;
            }
            foreach ($intf_details[$realif][$proto] as $address) {
                if (empty($address['ipaddr'])) {
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
                $key = "{$address['ipaddr']}{$scope}{$suffix}";
                $result[$key] = [
                    'address' => $address['ipaddr'],
                    'alias' => false,
                    'autoconf' => !empty($address['autoconf']),
                    'bind' => true,
                    'bits' => $address['subnetbits'],
                    'deprecated' => !empty($address['deprecated']),
                    'detached' => !empty($address['detached']),
                    'tentative' => !empty($address['tentative']),
                    'family' => $proto == 'ipv4' ? 'inet' : 'inet6',
                    'interface' => !empty($ifcache[$realif]) ? $ifcache[$realif] : null,
                    'key' => $key,
                    'name' => $realif,
                    'scope' => !empty($address['link-local']),
                    'unique' => ($proto == 'ipv4' || !empty($address['link-local'])) ? false : is_uniquelocal($address['ipaddr']),
                ];
            }
        }
    }

    foreach ($result as &$info) {
        foreach (config_read_array('virtualip', 'vip') as $vip) {
            if (empty($info['interface']) || $info['interface'] != $vip['interface']) {
                continue;
            }

            if ($vip['mode'] != 'ipalias' && $vip['mode'] != 'carp') {
                continue;
            }

            $match = false;

            if ($info['family'] == 'inet' && strpos($vip['subnet'], ':') === false) {
                $match = $vip['subnet'] == $info['address'];
            } elseif ($info['family'] == 'inet6' && strpos($vip['subnet'], ':') !== false) {
                /*
                 * Since we do not know what subnet value was given by user
                 * uncompress/compress to match correctly compressed system
                 * value.
                 */
                $match = Net_IPv6::compress(Net_IPv6::uncompress($vip['subnet'])) == $info['address'];
            }

            if (!$match) {
                continue;
            }

            $info['alias'] = true;

            if (!empty($vip['nobind'])) {
                $info['bind'] = false;
            }
        }
    }

    /* move ULAs to the bottom to prefer GUA addresses */
    uasort($result, function ($a, $b) {
        return $a['unique'] - $b['unique'];
    });

    return $result;
}

function interfaces_has_prefix_only($interface)
{
    $interfaces_a = config_read_array('interfaces');
    $ret = false;

    switch ($interfaces_a[$interface]['ipaddrv6'] ?? 'none') {
        case 'dhcp6':
            $ret = empty($interfaces_a[$interface]['adv_dhcp6_config_file_override']) &&
                ((!empty($interfaces_a[$interface]['adv_dhcp6_config_advanced']) &&
                empty($interfaces_a[$interface]['adv_dhcp6_id_assoc_statement_address_enable']) &&
                !isset($interfaces_a[$interface]['dhcp6-prefix-id'])) ||
                (isset($interfaces_a[$interface]['dhcp6prefixonly']) &&
                !isset($interfaces_a[$interface]['dhcp6-prefix-id'])));
            break;
        default:
            break;
    }

    return $ret;
}

function interfaces_primary_address($interface, $ifconfig_details = null)
{
    /* primary returns preferred local address according to configuration */
    $ifcfgip = $network = $subnetbits = $device = null;

    foreach (interfaces_addresses($interface, false, $ifconfig_details) as $addr) {
        if ($addr['family'] != 'inet') {
            continue;
        }

        /*
         * In IPv4 the strict ordering for addresses is ensured so that
         * we do include IP alias or CARP addresses.  If the need arises
         * to get to a "dynamic" primary address we could add another
         * argument for callers to enforce alias exclusion.
         */

        $network = gen_subnet($addr['address'], $addr['bits']) . "/{$addr['bits']}";
        $subnetbits = $addr['bits'];
        $ifcfgip = $addr['address'];
        $device = $addr['name'];
        break; /* all done */
    }

    return [ $ifcfgip, $network, $subnetbits, $device ];
}

function _interfaces_primary_address6($interface, $ifconfig_details = null, $allow_track = true, $link_local = false)
{
    $ifcfgipv6 = $networkv6 = $subnetbitsv6 = $devicev6 = null;

    if ($allow_track && !$link_local && interfaces_has_prefix_only($interface)) {
        /* extend the search scope for a non-NA mode to tracking interfaces */
        $interface = array_merge([$interface], array_keys(link_interface_to_track6($interface)));
    }

    foreach (interfaces_addresses($interface, false, $ifconfig_details) as $addr) {
        /* XXX consider excluding 'autoconf', but only when it's not in SLAAC mode */
        if ($addr['family'] != 'inet6' || $addr['deprecated'] || $addr['detached'] || $addr['tentative'] || $addr['alias']) {
            continue;
        }

        if ($link_local && !$addr['scope']) {
            continue;
        } elseif (!$link_local && $addr['scope']) {
            continue;
        }

        $networkv6 = gen_subnetv6($addr['address'], $addr['bits']) . "/{$addr['bits']}";
        $subnetbitsv6 = $addr['bits'];
        $ifcfgipv6 = $addr['address'];
        if ($link_local) {
            $ifcfgipv6 .= "%{$addr['name']}";
        }
        $devicev6 = $addr['name'];
        break; /* all done */
    }

    return [ $ifcfgipv6, $networkv6, $subnetbitsv6, $devicev6 ];
}

function interfaces_routed_address6($interface, $ifconfig_details = null)
{
   /* "routed" returns a non-link-local address only, possiby derived from tracking interfaces */
    return _interfaces_primary_address6($interface, $ifconfig_details, true, false);
}

function interfaces_scoped_address6($interface, $ifconfig_details = null)
{
    /* "scoped" returns own link-local address only */
    return _interfaces_primary_address6($interface, $ifconfig_details, false, true);
}

function interfaces_primary_address6($interface, $ifconfig_details = null)
{
    /* primary returns preferred local address according to configuration */
    $ifcfgipv6 = $networkv6 = $subnetbitsv6 = null;

    if (interfaces_has_prefix_only($interface)) {
        return _interfaces_primary_address6($interface, $ifconfig_details, false, true);
    }

    return _interfaces_primary_address6($interface, $ifconfig_details, false, false);
}
