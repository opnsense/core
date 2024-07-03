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

function kea_services()
{

    $services = [];
    if (!empty((string)(new \OPNsense\Kea\KeaDhcpv4())->general->enabled)) {
        $services[] = [
            'description' => gettext('KEA DHCPv4 server'),
            'pidfile' => '/var/run/kea/kea-dhcp4.kea-dhcp4.pid',
            'configd' => [
                'restart' => ['kea restart'],
                'start' => ['kea start'],
                'stop' => ['kea stop'],
            ],
            'name' => 'kea-dhcpv4',
        ];
    }
    return $services;
}

function kea_run()
{
    return [
        'static_mapping' => 'kea_staticmap',
    ];
}

function kea_staticmap($proto = null, $valid_addresses = true, $ifconfig_details = null)
{
    $staticmap = [];

    $keav4 = new \OPNsense\Kea\KeaDhcpv4();

    if ($proto == 6 || empty((string)$keav4->general->enabled)) {
        /* unsupported protocol or not enabled */
        return $staticmap;
    }

    foreach ($keav4->reservations->reservation->iterateItems() as $reservation) {
        $ip_address = (string)$reservation->ip_address;
        if (empty($ip_address) && $valid_addresses) {
            continue;
        }

        $hostname = !empty((string)$reservation->hostname) ? (string)$reservation->hostname : null;
        $description = !empty((string)$reservation->description) ? (string)$reservation->description : null;

        $subnet_node = $keav4->getNodeByReference("subnets.subnet4.{$reservation->subnet}");
        $domain = null;
        if ($subnet_node) {
            if (!empty((string)$subnet_node->option_data->domain_name)) {
                $domain = (string)$subnet_node->option_data->domain_name;
            }
        }

        $entry = [
            'descr' => $description,
            'domain' => $domain,
            'hostname' => $hostname,
            'interface' => null, /* XXX reservations are bound to "floating" subnets */
            'ipaddr' => $ip_address,
        ];

        $staticmap[] = $entry;
    }

    return $staticmap;
}

function kea_configure()
{
    return [
        'kea_sync' => ['kea_configure_do']
    ];
}

function kea_configure_do($verbose = false)
{
    $keaDhcpv4 = new \OPNsense\Kea\KeaDhcpv4();
    if ($keaDhcpv4->isEnabled()) {
        service_log('Sync KEA DHCP config...', $verbose);
        $keaDhcpv4->generateConfig();
        (new \OPNsense\Kea\KeaCtrlAgent())->generateConfig();
        service_log("done.\n", $verbose);
    }
}

function kea_syslog()
{
    $logfacilities = [];
    $logfacilities['kea'] = ['facility' => ['kea-dhcp4', 'kea-dhcp6', 'kea-ctrl-agent']];
    return $logfacilities;
}


function kea_firewall($fw)
{
    global $config;
    $keav4 = new \OPNsense\Kea\KeaDhcpv4();
    if ($keav4->fwrulesEnabled()) {
        // automatic (IPv4) rules enabled
        foreach (explode(',', $keav4->general->interfaces) as $intf) {
            $fw->registerFilterRule(
                1,
                [
                    'protocol' => 'udp',
                    'direction' => 'in',
                    'from_port' => 68,
                    'to' => '255.255.255.255',
                    '#ref' => 'ui/kea/dhcp/v4',
                    'to_port' => 67,
                    'interface' => $intf,
                    'descr' => 'allow access to DHCP server',
                    'log' => !isset($config['syslog']['nologdefaultpass'])
                ]
            );
            $fw->registerFilterRule(
                1,
                [
                    'protocol' => 'udp',
                    'direction' => 'in',
                    'from_port' => 68,
                    'to' => '(self)',
                    '#ref' => 'ui/kea/dhcp/v4',
                    'to_port' => 67,
                    'interface' => $intf,
                    'descr' => 'allow access to DHCP server',
                    'log' => !isset($config['syslog']['nologdefaultpass'])
                ]
            );
        }
    }
}

function kea_xmlrpc_sync()
{
    $result = [];

    $result[] = [
        'description' => gettext('Kea DHCP'),
        'section' => 'OPNsense.Kea',
        'id' => 'kea',
        'services' => ["kea-dhcpv4"],
    ];

    return $result;
}
