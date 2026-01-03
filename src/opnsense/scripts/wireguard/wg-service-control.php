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
require_once('config.inc');
require_once('interfaces.inc');
require_once('system.inc');

/**
 * collect carp status per vhid
 */
function get_vhid_status()
{
    $vhids = [];
    foreach ((new OPNsense\Interfaces\Vip())->vip->iterateItems() as $id => $item) {
        if ($item->mode == 'carp') {
            $vhids[$id] = ['status' => 'DISABLED', 'vhid' => (string)$item->vhid];
        }
    }
    foreach (legacy_interfaces_details() as $ifdata) {
        if (!empty($ifdata['carp'])) {
            foreach ($ifdata['carp'] as $data) {
                foreach ($vhids as $id => &$item) {
                    if ($item['vhid'] == $data['vhid']) {
                        $item['status'] = $data['status'];
                    }
                }
            }
        }
    }
    return $vhids;
}


/**
 * mimic wg-quick behaviour, but bound to our config
 */
function wg_start($server, $fhandle, $ifcfgflag = 'up', $reload = false)
{
    if (!does_interface_exist($server->interface)) {
        mwexecf('/sbin/ifconfig wg create name %s', [$server->interface]);
        mwexecf('/sbin/ifconfig %s group wireguard', [$server->interface]);
        $reload = true;
    }

    mwexecf('/usr/bin/wg syncconf %s %s', [$server->interface, $server->cnfFilename]);

    /* The tunneladdress can be empty, so array_filter without callback filters empty strings out. */
    foreach (array_filter(explode(',', (string)$server->tunneladdress)) as $alias) {
        $proto = strpos($alias, ':') === false ? "inet" : "inet6";
        mwexecf('/sbin/ifconfig %s %s %s alias', [$server->interface, $proto, $alias]);
    }
    if (!empty((string)$server->mtu)) {
        mwexecf('/sbin/ifconfig %s mtu %s', [$server->interface, $server->mtu]);
    }

    mwexecf('/sbin/ifconfig %s %sdebug', [$server->interface->getValue(), $server->debug->getValue() === '1' ? '' : '-']);

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
        $routes_to_add = $routes_to_skip = ['inet' => [], 'inet6' => []];

        /* calculate subnets to skip because these are automatically attached by instance address */
        foreach (array_filter(explode(',', (string)$server->tunneladdress)) as $alias) {
            $ipproto = strpos($alias, ':') === false ? 'inet' : 'inet6';
            $alias = explode('/', $alias);
            $alias = ($ipproto == 'inet' ? gen_subnet($alias[0], $alias[1]) :
                gen_subnetv6($alias[0], $alias[1])) . "/{$alias[1]}";
            $routes_to_skip[$ipproto][] = $alias;
        }

        foreach ((new OPNsense\Wireguard\Client())->clients->client->iterateItems() as $key => $client) {
            if (empty((string)$client->enabled) || !in_array($key, $peers)) {
                continue;
            }
            foreach (explode(',', (string)$client->tunneladdress) as $address) {
                $ipproto = strpos($address, ":") === false ? "inet" :  "inet6";
                $address = explode('/', $address);
                $address = ($ipproto == 'inet' ? gen_subnet($address[0], $address[1]) :
                    gen_subnetv6($address[0], $address[1])) . "/{$address[1]}";
                /* wg-quick seems to prevent /0 being routed and translates this automatically */
                if (str_ends_with(trim($address), '/0')) {
                    if ($ipproto == 'inet') {
                        array_push($routes_to_add[$ipproto], '0.0.0.0/1', '128.0.0.0/1');
                    } else {
                        array_push($routes_to_add[$ipproto], '::/1', '8000::/1');
                    }
                } elseif (!in_array($address, $routes_to_skip[$ipproto])) {
                    $routes_to_add[$ipproto][] = $address;
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
        $ipprefix = strpos($server->gateway, ":") === false ? "-4" :  "-6";
        mwexecf('/sbin/route -q -n add %s %s -iface %s', [$ipprefix, $server->gateway, $server->interface]);
    }

    if ($reload) {
        interfaces_restart_by_device(false, [(string)$server->interface]);
    }

    mwexecf('/sbin/ifconfig %s %s', [$server->interface, $ifcfgflag]);

    // flush checksum to ease change detection
    fseek($fhandle, 0);
    ftruncate($fhandle, 0);
    fwrite($fhandle, @md5_file($server->cnfFilename) . "|" . wg_reconfigure_hash($server));

    syslog(LOG_NOTICE, "wireguard instance {$server->name} ({$server->interface}) started");
}

/**
 * stop wireguard tunnel, kill the device, the routes should drop automatically.
 */
function wg_stop($server)
{
    if (does_interface_exist($server->interface)) {
        legacy_interface_destroy($server->interface);
    }
    syslog(LOG_NOTICE, "wireguard instance {$server->name} ({$server->interface}) stopped");
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
    echo "Usage: wg-service-control.php [-a] [-h] [stop|start|restart|configure] [uuid|vhid]\n\n";
    echo "\t-a all instances\n";
} elseif (isset($opts['a']) || !empty($args[1])) {
    // either a server id (uuid) or a vhid could be offered
    $server_id = $vhid = null;
    if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $args[1] ?? '') == 1) {
        $server_id = $args[1];
    } elseif (!empty($args[1])) {
        $vhid = explode('@', $args[1])[0];
    }

    $action = $args[0];

    $server_devs = [];
    if (!(new OPNsense\Wireguard\General())->enabled->isEmpty()) {
        $vhids = get_vhid_status();
        foreach ((new OPNsense\Wireguard\Server())->servers->server->iterateItems() as $key => $node) {
            $carp_depend_on = (string)$node->carp_depend_on;
            if (empty((string)$node->enabled)) {
                continue;
            } elseif ($server_id != null && $key != $server_id) {
                continue;
            } elseif ($vhid != null && (!empty($vhids[$carp_depend_on]) && $vhids[$carp_depend_on]['vhid'] != $vhid)) {
                continue;
            }
            /**
             * CARP may influence the interface status (up or down).
             * In order to fluently switch between roles, one should only have to change the interface flag in this
             * case, which means we can still reconfigure an interface in the usual way and just omit sending traffic
             * when in BACKUP or INIT mode.
             */
            $carp_if_flag = 'up';
            if (!empty($vhids[$carp_depend_on]) && $vhids[$carp_depend_on]['status'] != 'MASTER') {
                $carp_if_flag = 'down';
            }
            $server_devs[] = (string)$node->interface;
            $statHandle = fopen($node->statFilename, 'a+e');
            if (flock($statHandle, LOCK_EX)) {
                $ifdetails = legacy_interfaces_details((string)$node->interface);
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
                        $ifstatus = '-';
                        if (!empty($ifdetails[(string)$node->interface])) {
                            $ifstatus = in_array('up', $ifdetails[(string)$node->interface]['flags']) ? 'up' : 'down';
                        }

                        if (!empty($carp_depend_on) && !empty($vhid)) {
                            // CARP event traceability when a vhid is being passed
                            syslog(
                                LOG_NOTICE,
                                sprintf(
                                    "Wireguard configure event instance %s (%s) vhid: %s carp: %s interface: %s",
                                    $node->name,
                                    $node->interface,
                                    $vhid,
                                    !empty($vhids[$carp_depend_on]) ? $vhids[$carp_depend_on]['status'] : '-',
                                    $ifstatus
                                )
                            );
                        }

                        if (
                            @md5_file($node->cnfFilename) != get_stat_hash($statHandle)['file'] ||
                            empty($ifdetails[(string)$node->interface])
                        ) {
                            $reload = false;

                            if (get_stat_hash($statHandle)['interface'] != wg_reconfigure_hash($node)) {
                                // Fluent reloading not supported for this instance, make sure the user is informed
                                syslog(
                                    LOG_NOTICE,
                                    "wireguard instance {$node->name} ({$node->interface}) " .
                                    "can not reconfigure without stopping it first."
                                );

                                /*
                                 * Scrub interface, although dropping and recreating is more clean, there are
                                 * side affects in doing so. Dropping the addresses should drop the associated
                                 * routes and force a full reload (also of attached interface).
                                 */
                                interfaces_addresses_flush((string)$node->interface, 4, $ifdetails);
                                interfaces_addresses_flush((string)$node->interface, 6, $ifdetails);
                                $reload = true;
                            }

                            wg_start($node, $statHandle, $carp_if_flag, $reload);
                        /* when triggered via a CARP event, check our interface status [UP|DOWN] */
                        } elseif ($ifstatus != $carp_if_flag) {
                            syslog(
                                LOG_NOTICE,
                                "wireguard instance {$node->name} ({$node->interface}) " .
                                "switching to " . strtoupper($carp_if_flag)
                            );

                            mwexecf('/sbin/ifconfig %s %s', [$node->interface, $carp_if_flag]);
                        }
                        break;
                }
                flock($statHandle, LOCK_UN);
            }
            fclose($statHandle);
        }
    }

    /**
     * When -a is specified, cleanup up old or disabled instances (files and interfaces)
     */
    if ($server_id == null && $vhid == null) {
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

    if (count($server_devs) && $action == 'restart') {
        /* XXX required for filter/NAT rules, as interface was recreated, rules might not match anymore */
        configd_run('filter reload');
    }
}
