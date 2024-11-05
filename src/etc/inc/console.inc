<?php

/*
 * Copyright (C) 2015-2022 Franco Fichtner <franco@opnsense.org>
 * Copyright (C) 2004-2010 Scott Ullrich <sullrich@gmail.com>
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

function timeout($timer = 7)
{
    while (!isset($key)) {
        if ($timer >= 9) {
            echo chr(8) . chr(8) . ($timer == 9 ? chr(32) : null)  . "{$timer}";
        } else {
            echo chr(8) . "{$timer}";
        }
        shell_exec('/bin/stty -icanon min 0 time 10');
        $key = shell_exec('/bin/dd count=1 status=none');
        shell_exec('/bin/stty icanon');
        if ($key == '') {
            unset($key);
        }
        $timer--;
        if ($timer == 0) {
            break;
        }
    }

    return $key;
}

function is_interface_mismatch($locked = true)
{
    $mismatch = false;
    $patterns = [];

    foreach (plugins_devices() as $device) {
        if ($device['volatile']) {
            $patterns[] = "({$device['pattern']})";
        }
    }

    $regex = count($patterns) ? '/' . implode('|', $patterns) . '/' : null;

    foreach (legacy_config_get_interfaces(['virtual' => false]) as $ifname => $ifcfg) {
        if ($locked && !empty($ifcfg['lock'])) {
            /* Do not mismatch if any lock was issued */
            $mismatch = false;
            break;
        } elseif (!empty($regex) && preg_match($regex, $ifcfg['if'])) {
            /* Do not check these interfaces */
            continue;
        } elseif (does_interface_exist($ifcfg['if']) == false) {
            /* Continue loop, may still find a lock */
            $mismatch = true;
        }
    }

    return $mismatch;
}

function set_networking_interfaces_ports($probe = false)
{
    exec('/sbin/conscontrol mute on');
    $ret = _set_networking_interfaces_ports($probe);
    exec('/sbin/conscontrol mute off');

    return $ret;
}

function _set_networking_interfaces_ports($probe = false)
{
    global $config;

    $fp = fopen('php://stdin', 'r');
    $yes_no_prompt = '[y/N]: ';
    $interactive = true;
    $key = null;

    $iflist = get_interface_list(false, true);
    $iflist_wlan = legacy_interface_listget('wlan');
    $iflist_lagg = [];
    $iflist_all = [];

    foreach ($iflist as $iface => $ifa) {
        $iflist_all[$iface] = $ifa;
        legacy_interface_flags($iface, 'up');
    }

    if ($probe) {
        echo PHP_EOL . 'Press any key to start the manual interface assignment:  ';

        $key = timeout();
        if (!isset($key)) {
            $interactive = false;
        }

        if ($key != "\n") {
            echo PHP_EOL;
        }
    }

    if (!empty($iflist)) {
        echo <<<EOD
Do you want to configure LAGGs now? {$yes_no_prompt}
EOD;
        if ($interactive) {
            $key = chop(fgets($fp));
        } else {
            $key = 'n';
            echo $key . PHP_EOL;
        }

        if (in_array($key, array('y', 'Y'))) {
            lagg_setup($iflist, $fp);
            echo "\n";
        }

        if (isset($config['laggs']['lagg'][0])) {
            foreach ($config['laggs']['lagg'] as $lagg) {
                $iflist_lagg[$lagg['laggif']] = $iflist_all[$lagg['laggif']] = [
                    'dmesg' => "Link aggregation, member interfaces {$lagg['members']}",
                    'mac' => '00:00:00:00:00:00',
                ];
            }
        }

        echo <<<EOD
Do you want to configure VLANs now? {$yes_no_prompt}
EOD;
        if ($interactive) {
            $key = chop(fgets($fp));
        } else {
            $key = 'n';
            echo $key . PHP_EOL;
        }

        if (in_array($key, array('y', 'Y'))) {
            vlan_setup(array_merge($iflist, $iflist_lagg), $fp);
        }

        if (isset($config['vlans']['vlan'][0])) {
            foreach ($config['vlans']['vlan'] as $vlan) {
                $iflist_all[$vlan['vlanif']] = [
                    'dmesg' => "VLAN tag {$vlan['tag']}, parent interface {$vlan['if']}",
                    'mac' => '00:00:00:00:00:00',
                ];
            }
        }
    }

    /* add WLAN parents now as LAGG/VLAN are not capable of handling it */
    foreach ($iflist_wlan as $iface) {
        $iflist_all[$iface] = [
            'dmesg' => 'WLAN device parent',
            'mac' => '00:00:00:00:00:00',
        ];
    }

    echo <<<EOD

Valid interfaces are:


EOD;

    if (empty($iflist_all)) {
        echo "No interfaces found!\n";
    } else {
        foreach ($iflist_all as $iface => $ifa) {
            echo sprintf("%-16s %s %s\n", $iface, $ifa['mac'], $ifa['dmesg']);
        }
    }

    echo <<<EOD

If you do not know the names of your interfaces, you may choose to use
auto-detection. In that case, disconnect all interfaces now before
hitting 'a' to initiate auto detection.

EOD;

    $ifnames = array_keys($iflist_all); /* only for non-interactive mode */

    do {
        echo "\nEnter the WAN interface name or 'a' for auto-detection: ";

        if ($interactive) {
            $wanif = chop(fgets($fp));
        } else {
            /* more than one interface: put WAN as second one */
            $wanif = count($ifnames) > 1 ? $ifnames[1] : '';
            echo $wanif . PHP_EOL;
        }

        if ($wanif == '') {
            break;
        }

        if ($wanif == 'a') {
            $wanif = autodetect_interface('WAN', $fp);
            if ($wanif == '') {
                continue;
            }
        }

        if (!array_key_exists($wanif, $iflist_all)) {
            printf("\nInvalid interface name '%s'\n", $wanif);
            $wanif = '';
        }
    } while ($wanif == '');

    do {
        echo "\nEnter the LAN interface name or 'a' for auto-detection\n" .
            "NOTE: this enables full Firewalling/NAT mode.\n" .
            "(or nothing if finished): ";

        if ($interactive) {
            $lanif = chop(fgets($fp));
        } else {
            /* at least one interface: put LAN as first one */
            $lanif = count($ifnames) > 0 ? $ifnames[0] : '';
            echo $lanif . PHP_EOL;
        }

        if ($lanif == '') {
            break;
        }

        if ($lanif == 'a') {
            $lanif = autodetect_interface('LAN', $fp);
            if ($lanif == '') {
                continue;
            }
        }

        if (!array_key_exists($lanif, $iflist_all)) {
            printf("\nInvalid interface name '%s'\n", $lanif);
            unset($lanif);
        }

        if ($wanif != '' && $lanif == $wanif) {
            $lanif = '';
            echo <<<EOD

Error: you cannot assign the same interface name twice!

EOD;
        }
    } while ($lanif == '');

    $done = false;
    while (!$done) {
        /* optional interfaces */
        $optif = [];
        $i = 0;

        while (1) {
            if (isset($optif[$i])) {
                $i++;
            }
            $io = $i + 1;

            printf("\nEnter the Optional interface %s name or 'a' for auto-detection\n" .
                "(or nothing if finished): ", $io);

            if ($interactive) {
                $optif[$i] = chop(fgets($fp));
            } else {
                /* never configure OPT in automatic assign */
                $optif[$i] = '';
                echo $optif[$i] . PHP_EOL;
            }

            if ($optif[$i] == '') {
                unset($optif[$i]);
                $done = true;
                break;
            }

            if ($optif[$i] == 'a') {
                $optif[$i] = autodetect_interface('OPT' . $io, $fp);
                if ($optif[$i] == '') {
                    unset($optif[$i]);
                    continue;
                }
            }

            if (!array_key_exists($optif[$i], $iflist_all)) {
                printf("\nInvalid interface name '%s'\n", $optif[$i]);
                unset($optif[$i]);
                continue;
            }

            /* check for double assignments */
            $ifarr = array_merge([$lanif, $wanif], $optif);
            $again = false;

            for ($k = 0; $k < (count($ifarr) - 1); $k++) {
                for ($j = ($k + 1); $j < count($ifarr); $j++) {
                    if ($ifarr[$k] != '' && $ifarr[$k] == $ifarr[$j]) {
                        $again = true;
                        echo <<<EOD

Error: you cannot assign the same interface name twice!

EOD;
                    }
                }
            }

            if ($again) {
                unset($optif[$i]);
            }
        }
    }

    if ($wanif != '' || $lanif != '' || count($optif)) {
        echo "\nThe interfaces will be assigned as follows:\n\n";

        if ($wanif != '') {
            echo "WAN  -> " . $wanif . "\n";
        }
        if ($lanif != '') {
            echo "LAN  -> " . $lanif . "\n";
        }
        for ($i = 0; $i < count($optif); $i++) {
            echo "OPT" . ($i + 1) . " -> " . $optif[$i] . "\n";
        }
    } else {
        echo "\nNo interfaces will be assigned!\n";
    }

    echo <<<EOD

Do you want to proceed? {$yes_no_prompt}
EOD;
    if ($interactive) {
        $key = chop(fgets($fp));
    } else {
        $key = 'y';
        echo $key . PHP_EOL;
    }

    if (!in_array($key, array('y', 'Y'))) {
        fclose($fp);
        return false;
    }

    /*
     * XXX Ideally, at this point we'd import the default settings here,
     * not hardcode them.  It was this way before, so fixing for now.
     */
    if ($lanif != '') {
        $new = false;

        if (!isset($config['interfaces']['lan'])) {
            $new = true;
        }

        config_read_array('interfaces', 'lan');
        $config['interfaces']['lan']['if'] = $lanif;
        $config['interfaces']['lan']['enable'] = true;

        if ($new) {
            $config['interfaces']['lan']['ipaddr'] = '192.168.1.1';
            $config['interfaces']['lan']['subnet'] = '24';
            if ($wanif) {
                $config['interfaces']['lan']['track6-interface'] = 'wan';
                $config['interfaces']['lan']['track6-prefix-id'] = '0';
                $config['interfaces']['lan']['ipaddrv6'] = 'track6';
                $config['interfaces']['lan']['subnetv6'] = '64';
            }

            config_read_array('dhcpd', 'lan', 'range');
            $config['dhcpd']['lan']['enable'] = true;
            $config['dhcpd']['lan']['range']['from'] = '192.168.1.100';
            $config['dhcpd']['lan']['range']['to'] = '192.168.1.199';

            config_read_array('nat', 'outbound');
            $config['nat']['outbound']['mode'] = 'automatic';
        }

        if (in_array($config['interfaces']['lan']['if'], $iflist_wlan)) {
            config_read_array('interfaces', 'lan', 'wireless');
            $config['interfaces']['lan']['if'] .= '_wlan0';
        } elseif (isset($config['interfaces']['lan']['wireless'])) {
            unset($config['interfaces']['lan']['wireless']);
        }
    } elseif (isset($config['interfaces']['lan'])) {
        unset($config['interfaces']['lan']['enable']);
        interface_reset('lan');

        if (isset($config['dhcpd']['lan'])) {
            unset($config['dhcpd']['lan']);
        }
        if (isset($config['dhcpdv6']['lan'])) {
            unset($config['dhcpdv6']['lan']);
        }
        if (isset($config['interfaces']['wan']['blockpriv'])) {
            unset($config['interfaces']['wan']['blockpriv']);
        }
        if (isset($config['nat'])) {
            unset($config['nat']);
        }
        unset($config['interfaces']['lan']);
    }

    if ($wanif != '') {
        config_read_array('interfaces', 'wan');
        $config['interfaces']['wan']['if'] = $wanif;
        $config['interfaces']['wan']['enable'] = true;
        $config['interfaces']['wan']['ipaddr'] = 'dhcp';
        $config['interfaces']['wan']['ipaddrv6'] = 'dhcp6';
        $config['interfaces']['wan']['blockbogons'] = true;
        if ($lanif != '') {
            $config['interfaces']['wan']['blockpriv'] = true;
        }

        if (in_array($config['interfaces']['wan']['if'], $iflist_wlan)) {
            config_read_array('interfaces', 'wan', 'wireless');
            $config['interfaces']['wan']['if'] .= '_wlan0';
        } elseif (isset($config['interfaces']['wan']['wireless'])) {
            unset($config['interfaces']['wan']['wireless']);
        }
    } elseif (isset($config['interfaces']['wan'])) {
        unset($config['interfaces']['wan']['enable']);
        interface_reset('wan');
        unset($config['interfaces']['wan']);
    }

    for ($i = 0; $i < count($optif); $i++) {
        config_read_array('interfaces', 'opt' . ($i + 1));
        $config['interfaces']['opt' . ($i + 1)]['if'] = $optif[$i];
        $config['interfaces']['opt' . ($i + 1)]['enable'] = true;

        if (in_array($config['interfaces']['opt' . ($i + 1)]['if'], $iflist_wlan)) {
            config_read_array('interfaces', 'opt' . ($i + 1), 'wireless');
            $config['interfaces']['opt' . ($i + 1)]['if'] .= '_wlan0';
        } elseif (isset($config['interfaces']['opt' . ($i + 1)]['wireless'])) {
            unset($config['interfaces']['opt' . ($i + 1)]['wireless']);
        }
    }

    /* remove all other (old) optional interfaces */
    for (; isset($config['interfaces']['opt' . ($i + 1)]); $i++) {
        unset($config['interfaces']['opt' . ($i + 1)]['enable']);
        interface_reset('opt' . ($i + 1));
        unset($config['interfaces']['opt' . ($i + 1)]);
    }

    echo "\nWriting configuration...";
    flush();
    write_config("Console assignment of interfaces");
    echo "done.\n";

    fclose($fp);

    return true;
}

function autodetect_interface($name, $fp)
{
    $iflist_prev = get_interface_list(true);

    echo <<<EOD

Connect the {$name} interface now and make sure that the link is up.
Then press ENTER to continue.

EOD;
    fgets($fp);

    $iflist = get_interface_list(true);

    if (is_array($iflist)) {
        foreach ($iflist as $ifn => $ifa) {
            if (!isset($iflist_prev[$ifn])) {
                printf("Detected link-up: %s\n", $ifn);
                return $ifn;
            }
        }
    }

    echo "No link-up detected.\n";

    return '';
}

function lagg_setup($iflist, $fp)
{
    $laggcfg = &config_read_array('laggs', 'lagg');
    $yes_no_prompt = '[y/N]: ';

    if (count($laggcfg)) {
        echo <<<EOD

WARNING: all existing LAGGs will be cleared if you proceed!

Do you want to proceed? {$yes_no_prompt}
EOD;

        if (strcasecmp(chop(fgets($fp)), "y") != 0) {
            return;
        }
    }

    $laggcfg = [];
    $laggif = 0;

    $unused_ifs = [];
    foreach ($iflist as $iface => $ifa) {
        $unused_ifs[$iface] = $ifa;
    }

    while (1) {
        $lagg = [];
        $lagg['@attributes'] = ['uuid' => generate_uuid()];

        echo "\nLAGG-capable interfaces:\n\n";

        foreach ($unused_ifs as $iface => $ifa) {
            echo sprintf("%-8s %s %s\n", $iface, $ifa['mac'], $ifa['dmesg']);
        }

        if (empty($unused_ifs)) {
            echo "No LAGG-capable interfaces detected.\n";
            return;
        }

        echo "\nEnter the LAGG members to aggregate (or nothing if finished): ";
        $members_str = chop(fgets($fp));

        if ($members_str) {
            $members = preg_split('/[\s\t,;]+/', $members_str);
            $members = array_unique($members);
            $unused_ifnames = array_keys($unused_ifs);
            $valid_ifs = array_intersect($unused_ifnames, $members);
            if (count($members) != count($valid_ifs)) {
                $invalid_ifs = array_diff($members, $unused_ifnames);
                printf("\nInvalid interfaces: %s\n", implode(", ", $invalid_ifs));
                continue;
            }
            $lagg['members'] = implode(',', $members);
            foreach ($members as $member) {
                unset($unused_ifs[$member]);
            }
        } else {
            break;
        }

        echo 'Enter the LAGG protocol (default:none,lacp,failover,fec,loadbalance,roundrobin): ';
        $lagg['proto'] = strtolower(chop(fgets($fp)));
        if (!in_array($lagg['proto'], ['none', 'lacp', 'failover', 'fec', 'loadbalance', 'roundrobin'])) {
            $lagg['proto'] = 'none';
        }

        if ($lagg['proto'] == "lacp") {
            echo "Do you want to enable LACP fast timeout? {$yes_no_prompt}";

            if (strcasecmp(chop(fgets($fp)), "y") == 0) {
                $lagg['lacp_fast_timeout'] = true;
            }
        }

        echo 'Enter the LAGG MTU (leave blank for auto): ';
        $lagg['mtu'] = chop(fgets($fp));
        if (!$lagg['mtu']) {
            $lagg['mtu'] = null;
        }

        $lagg['laggif'] = 'lagg' . $laggif;

        $laggcfg[] = $lagg;
        $laggif++;
    }
}

function vlan_setup($iflist, $fp)
{
    $vlancfg = &config_read_array('vlans', 'vlan');
    $yes_no_prompt = '[y/N]: ';

    if (count($vlancfg)) {
        echo <<<EOD

WARNING: all existing VLANs will be cleared if you proceed!

Do you want to proceed? {$yes_no_prompt}
EOD;

        if (strcasecmp(chop(fgets($fp)), "y") != 0) {
            return;
        }
    }

    $vlancfg = [];
    $vlanif = 0;

    while (1) {
        $vlan = [];

        echo "\nVLAN-capable interfaces:\n\n";

        foreach ($iflist as $iface => $ifa) {
            echo sprintf("%-8s %s %s\n", $iface, $ifa['mac'], $ifa['dmesg']);
        }

        echo "\nEnter the parent interface name for the new VLAN (or nothing if finished): ";
        $vlan['if'] = chop(fgets($fp));

        if ($vlan['if']) {
            if (!array_key_exists($vlan['if'], $iflist)) {
                printf("\nInvalid interface name '%s'\n", $vlan['if']);
                continue;
            }
        } else {
            break;
        }

        echo 'Enter the VLAN tag (1-4094): ';
        $vlan['tag'] = chop(fgets($fp));
        $vlan['vlanif'] = "{$vlan['if']}_vlan{$vlan['tag']}";
        $vlan['@attributes'] = ['uuid' => generate_uuid()];
        if (!is_numericint($vlan['tag']) || ($vlan['tag'] < 1) || ($vlan['tag'] > 4094)) {
            printf("\nInvalid VLAN tag '%s'\n", $vlan['tag']);
            continue;
        }

        $vlancfg[] = $vlan;
        $vlanif++;
    }
}
