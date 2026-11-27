<?php

/*
 * Copyright (C) 2023-2026 Deciso B.V.
 * Copyright (C) 2018 Michael Muenz <m.muenz@gmail.com>
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

function wireguard_enabled()
{
    return (string)(new \OPNsense\Wireguard\General())->enabled == '1';
}

function wireguard_services()
{
    $services = [];

    if (!wireguard_enabled()) {
        return $services;
    }

    foreach ((new OPNsense\Wireguard\Server())->servers->server->iterateItems() as $key => $node) {
        if (!empty((string)$node->enabled)) {
            $services[] = [
                'description' => 'WireGuard ' . htmlspecialchars($node->name),
                'configd' => [
                    'start' => ["wireguard start {$key}"],
                    'restart' => ["wireguard restart {$key}"],
                    'stop' => ["wireguard stop {$key}"],
                ],
                'name' => 'wireguard',
                'nocheck' => true,
                'id' => $key,
            ];
        }
    }

    return $services;
}

function wireguard_syslog()
{
    return [
        'wireguard' => ['facility' => ['wireguard']]
    ];
}

function wireguard_interfaces()
{
    $interfaces = [];

    if (!wireguard_enabled()) {
        return $interfaces;
    }

    $interfaces['wireguard'] = [
        'descr' => gettext('WireGuard (Group)'),
        'if' => 'wireguard',
        'virtual' => true,
        'enable' => true,
        'type' => 'group',
        'networks' => [],
    ];

    return $interfaces;
}

function wireguard_xmlrpc_sync()
{
    $result = [];

    $result['id'] = 'wireguard';
    $result['section'] = 'OPNsense.wireguard';
    $result['description'] = gettext('WireGuard');
    $result['services'] = ['wireguard'];

    return [$result];
}

function wireguard_devices()
{
    $names = [];
    foreach ((new OPNsense\Wireguard\Server())->servers->server->iterateItems() as $key => $node) {
        if (!empty((string)$node->enabled)) {
            $names[(string)$node->interface] = [
                'descr' => sprintf('%s (WireGuard - %s)', (string)$node->interface, (string)$node->name),
                'ifdescr' => (string)$node->name,
                'name' => (string)$node->interface
            ];
        }
    }
    return [[
        'function' => 'wireguard_prepare', /* XXX only (empty) device creation */
        'typedescr' => gettext('WireGuard'),
        'configurable' => false,
        'type' => 'wireguard',
        'volatile' => true,
        'pattern' => '^wg',
        'names' => $names,
    ]];
}

function wireguard_prepare($device)
{
    foreach ((new OPNsense\Wireguard\Server())->servers->server->iterateItems() as $node) {
        if ($device != (string)$node->interface) {
            continue;
        }

        /* deleting the stat file marks the interface for eventual reconfiguration */
        @unlink((string)$node->statFilename);

        if (!does_interface_exist($device)) {
            mwexecf('/sbin/ifconfig wg create name %s', $device);
            mwexecf('/sbin/ifconfig %s group wireguard', $device);
        }

        return $device;
    }

    return null;
}

function wireguard_configure()
{
    return [
        'newwanip' => ['wireguard_sync'],
        'vpn' => ['wireguard_configure_do'],
    ];
}

function wireguard_configure_do($verbose = false)
{
    if (!wireguard_enabled()) {
        return;
    }

    service_log('Configuring WireGuard VPN...', $verbose);

    /* XXX dispatch to unblock during boot -- consider always dispatching */
    configd_run('wireguard configure', product::getInstance()->booting());

    service_log("done.\n", $verbose);
}

function wireguard_sync($verbose = false)
{
    $instances = [];

    if (!wireguard_enabled()) {
        return;
    }

    foreach ((new OPNsense\Wireguard\Server())->servers->server->iterateItems() as $node) {
        if (!$node->enabled->isEmpty()) {
            $instances[] = $node;
        }
    }

    if (!count($instances)) {
        return;
    }

    service_log('Synchronizing WireGuard VPN...', $verbose);

    foreach ($instances as $node) {
        $statHandle = fopen($node->statFilename, 'a+e');
        if (flock($statHandle, LOCK_EX)) {
            mwexecf('/usr/bin/wg syncconf %s %s', [$node->interface, $node->cnfFilename]);
            flock($statHandle, LOCK_UN);
        }
        fclose($statHandle);
    }

    service_log("done.\n", $verbose);
}
