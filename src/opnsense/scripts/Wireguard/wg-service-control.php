#!/usr/local/bin/php
<?php

/*
 * Copyright (C) 2023 Deciso B.V.
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

require_once('script/load_phalcon.php');
require_once('util.inc');
require_once('interfaces.inc');

/**
 * collect carp status per vhid
 */
function get_vhid_status()
{
    $vhids = [];
    $uuids = [];
    foreach ((new OPNsense\Interfaces\Vip())->vip->iterateItems() as $id => $item) {
        if ($item->mode == 'carp') {
            $uuids[(string)$item->vhid] =  $id;
        }
    }
    foreach (legacy_interfaces_details() as $ifdata) {
        if (!empty($ifdata['carp'])) {
            foreach ($ifdata['carp'] as $data) {
                if (isset($uuids[$data['vhid']])) {
                    $vhids[$uuids[$data['vhid']]] = $data['status'];
                }
            }
        }
    }
    return $vhids;
}


/**
 * mimic wg-quick behaviour, but bound to our config
 */
function wg_start($server, $fhandle, $ifcfgflag = 'up')
{
    if (!does_interface_exist($server->interface)) {
        mwexecf('/sbin/ifconfig wg create name %s', [$server->interface]);
        mwexecf('/sbin/ifconfig %s group wireguard', [$server->interface]);
    }
    mwexecf('/usr/bin/wg setconf %s %s', [$server->interface, $server->cnfFilename]);

    foreach (explode(',', (string)$server->tunneladdress) as $alias) {
        $proto = strpos($alias, ':') === false ? "inet" : "inet6";
        mwexecf('/sbin/ifconfig %s %s %s alias', [$server->interface, $proto, $alias]);
    }
    if (!empty((string)$server->mtu)) {
        mwexecf('/sbin/ifconfig %s mtu %s', [$server->interface, $server->mtu]);
    }
    mwexecf('/sbin/ifconfig %s %s', [$server->interface, $ifcfgflag]);

    if (empty((string)$server->disableroutes)) {
        /**
         * Add routes for all configured peers, wg-quick seems to parse 'wg show wgX allowed-ips' for this,
         * but this should logically congtain the same networks.
         *
         * XXX: For some reason these routes look a bit off, not very well integrated into OPNsense.
         *      In the long run it might make sense to have some sort of pluggable model facility
         *      where these (and maybe other) static routes hook into.
         **/
        $peers = explode(',', $server->peers);
        $routes_to_add = ['inet' => [], 'inet6' => []];
        foreach ((new OPNsense\Wireguard\Client())->clients->client->iterateItems() as $key => $client) {
            if (empty((string)$client->enabled) || !in_array($key, $peers)) {
                continue;
            }
            foreach (explode(',', (string)$client->tunneladdress) as $tunneladdress) {
                $ipproto = strpos($tunneladdress, ":") === false ? "inet" :  "inet6";
                /* wg-quick seems to prevent /0 being routed and translates this automatically */
                if (str_ends_with(trim($tunneladdress), '/0')) {
                    if ($ipproto == 'inet') {
                        array_push($routes_to_add[$ipproto], '0.0.0.0/1', '128.0.0.0/1');
                    } else {
                        array_push($routes_to_add[$ipproto], '::/1', '8000::/1');
                    }
                } else {
                    $routes_to_add[$ipproto][] = $tunneladdress;
                }
            }
        }
        foreach ($routes_to_add as $ipproto => $routes) {
            foreach (array_unique($routes) as $route) {
                mwexecf('/sbin/route -q -n add -%s %s -interface %s', [$ipproto,  $route, $server->interface]);
            }
        }
    } elseif (!empty((string)$server->gateway)) {
        /* Only bind the gateway ip to the tunnel */
        $ipprefix = strpos($tunneladdress, ":") === false ? "-4" :  "-6";
        mwexecf('/sbin/route -q -n add %s %s -iface %s', [$ipprefix, $server->gateway, $server->interface]);
    }

    // flush checksum to ease change detection
    fseek($fhandle, 0);
    ftruncate($fhandle, 0);
    fwrite($fhandle, @md5_file($server->cnfFilename) . "|" . wg_reconfigure_hash($server));
    syslog(LOG_NOTICE, "Wireguard interface {$server->name} ({$server->interface}) started");
}

/**
 * stop wireguard tunnel, kill the device, the routes should drop automatically.
 */
function wg_stop($server)
{
    if (does_interface_exist($server->interface)) {
        legacy_interface_destroy($server->interface);
    }
    syslog(LOG_NOTICE, "Wireguard interface {$server->name} ({$server->interface}) stopped");
}


/**
 * Calculate a hash which determines if we are able to reconfigure without a restart of the tunnel.
 * We currently assume if something changed on the interface or peer routes are being pushed, it's safer to
 * restart then reload.
 */
function wg_reconfigure_hash($server)
{
    if (empty((string)$server->disableroutes)) {
        return md5(uniqid('', true));   // random hash, should always reconfigure
    }
    return md5(
        sprintf(
            '%s|%s|%s',
            $server->tunneladdress,
            $server->mtu,
            $server->gateway
        )
    );
}

/**
 * The stat hash file answers two questions, [1] has anything changed, which is answered using an md5 hash of the
 * configuration file. The second question, if something has changed, is it safe to only reload the configuration.
 * This is answered by wg_reconfigure_hash() for the instance in question.
 */
function get_stat_hash($fhandle)
{
    fseek($fhandle, 0);
    $payload = stream_get_contents($fhandle) ?? '';
    $parts = explode('|', $payload);
    return [
        'file' => $parts[0] ?? '',
        'interface' => $parts[1] ?? ''
    ];
}

$opts = getopt('ah', [], $optind);
$args = array_slice($argv, $optind);

/* setup syslog logging */
openlog("wireguard", LOG_ODELAY, LOG_AUTH);

if (isset($opts['h']) || empty($args) || !in_array($args[0], ['start', 'stop', 'restart', 'configure'])) {
    echo "Usage: wg-service-control.php [-a] [-h] [stop|start|restart|configure] [uuid]\n\n";
    echo "\t-a all instances\n";
} elseif (isset($opts['a']) || !empty($args[1])) {
    $server_id = $args[1] ?? null;
    $action = $args[0];

    $server_devs = [];
    if (!empty((string)(new OPNsense\Wireguard\General())->enabled)) {
        $ifdetails = legacy_interfaces_details();
        $vhids = get_vhid_status();
        foreach ((new OPNsense\Wireguard\Server())->servers->server->iterateItems() as $key => $node) {
            if (empty((string)$node->enabled)) {
                continue;
            }
            if ($server_id != null && $key != $server_id) {
                continue;
            }
            /**
             * CARP may influence the interface status (up or down).
             * In order to fluently switch between roles, one should only have to change the interface flag in this
             * case, which means we can still reconfigure an interface in the usual way and just omit sending traffic
             * when in BACKUP or INIT mode.
             */
            $carp_if_flag = 'up';
            if (
                !empty($vhids[(string)$node->carp_depend_on]) &&
                $vhids[(string)$node->carp_depend_on] != 'MASTER'
            ) {
                $carp_if_flag = 'down';
            }
            $server_devs[] = (string)$node->interface;
            $statHandle = fopen($node->statFilename, "a+");
            if (flock($statHandle, LOCK_EX)) {
                switch ($action) {
                    case 'stop':
                        wg_stop($node);
                        break;
                    case 'start':
                        wg_start($node, $statHandle, $carp_if_flag);
                        break;
                    case 'restart':
                        wg_stop($node);
                        wg_start($node, $statHandle, $carp_if_flag);
                        break;
                    case 'configure':
                        if (
                            @md5_file($node->cnfFilename) != get_stat_hash($statHandle)['file'] ||
                            !isset($ifdetails[(string)$node->interface]) || (
                                // Interface has been setup, but without configuration
                                empty($ifdetails[(string)$node->interface]['ipv4']) &&
                                empty($ifdetails[(string)$node->interface]['ipv6'])
                            )
                        ) {
                            if (get_stat_hash($statHandle)['interface'] != wg_reconfigure_hash($node)) {
                                // Fluent reloading not supported for this instance, make sure the user is informed
                                syslog(
                                    LOG_NOTICE,
                                    "Wireguard interface {$node->name} ({$node->interface}) " .
                                    "can not reconfigure without stopping it first."
                                );
                                wg_stop($node);
                            }
                            wg_start($node, $statHandle, $carp_if_flag);
                        } else {
                            // when triggered via a CARP event, check our interface status [UP|DOWN]
                            $tmp = in_array('up', $ifdetails[(string)$node->interface]['flags']) ? 'up' : 'down';
                            if ($tmp !=  $carp_if_flag) {
                                mwexecf('/sbin/ifconfig %s %s', [$node->interface, $carp_if_flag]);
                            }
                        }
                        break;
                }
                flock($statHandle, LOCK_UN);
            }
            fclose($statHandle);
        }
    }

    /**
     * When -a is specified, cleaup up old or disabled instances (files and interfaces)
     */
    if ($server_id == null) {
        foreach (glob('/usr/local/etc/wireguard/wg*') as $filename) {
            $this_dev = explode('.', basename($filename))[0];
            if (!in_array($this_dev, $server_devs)) {
                @unlink($filename);
                if (does_interface_exist($this_dev)) {
                    legacy_interface_destroy($this_dev);
                }
            }
        }
    }
    mwexecf('/usr/local/etc/rc.routing_configure');
}
closelog();
