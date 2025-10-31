<?php

/*
 * Copyright (C) 2023-2025 Deciso B.V.
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
    $template = [
        'configd' => [
            'restart' => ['kea restart'],
            'start' => ['kea start'],
            'stop' => ['kea stop'],
        ],
        'name' => 'kea-dhcp',
    ];

    if (!(new \OPNsense\Kea\KeaDhcpv4())->general->enabled->isEmpty()) {
        $service = $template;
        $service['description'] = gettext('KEA DHCPv4 server');
        $service['pidfile'] = '/var/run/kea/kea-dhcp4.kea-dhcp4.pid';
        $service['id'] = 'v4';
        $services[] = $service;
    }

    if (!(new \OPNsense\Kea\KeaDhcpv6())->general->enabled->isEmpty()) {
        $service = $template;
        $service['description'] = gettext('KEA DHCPv6 server');
        $service['pidfile'] = '/var/run/kea/kea-dhcp6.kea-dhcp6.pid';
        $service['id'] = 'v6';
        $services[] = $service;
    }

    if (!(new \OPNsense\Kea\KeaCtrlAgent())->general->enabled->isEmpty()) {
        $service = $template;
        $service['pidfile'] = '/var/run/kea/kea-ctrl-agent.kea-ctrl-agent.pid';
        $service['description'] = gettext('KEA control agent');
        $service['id'] = 'ca';
        $services[] = $service;
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
    foreach (empty($proto) ? [4,6] : [$proto] as $proto) {
        if ($proto == 6) {
            $keamdl = new \OPNsense\Kea\KeaDhcpv6();
            $ipaddr = 'ipaddrv6';
        } else {
            $keamdl = new \OPNsense\Kea\KeaDhcpv4();
            $ipaddr = 'ipaddr';
        }

        if (empty((string)$keamdl->general->enabled)) {
            /* not enabled */
            return $staticmap;
        }

        foreach ($keamdl->reservations->reservation->iterateItems() as $reservation) {
            $hostname = !empty((string)$reservation->hostname) ? (string)$reservation->hostname : null;
            $ip_address = (string)$reservation->ip_address;
            if ($valid_addresses) {
                if (empty($ip_address) || empty($hostname)) {
                    continue;
                } elseif (
                    filter_var((string)$reservation->hostname, FILTER_VALIDATE_DOMAIN) === false
                ) {
                    syslog(
                        LOG_WARNING,
                        sprintf("KEA: refusing to register non standard hostname [%s]", $reservation->hostname)
                    );
                    continue;
                }
            }

            $description = !empty((string)$reservation->description) ? (string)$reservation->description : null;

            $domain = null;
            if ($proto == 4) {
                $subnet_node = $keamdl->getNodeByReference("subnets.subnet4.{$reservation->subnet}");
                if ($subnet_node) {
                    if (!empty((string)$reservation->option_data->domain_name)) {
                        $domain = (string)$reservation->option_data->domain_name;
                    } elseif (!empty((string)$subnet_node->option_data->domain_name)) {
                        $domain = (string)$subnet_node->option_data->domain_name;
                    }
                }
            }

            $entry = [
                'descr' => $description,
                'domain' => $domain,
                'hostname' => $hostname,
                'interface' => null, /* XXX reservations are bound to "floating" subnets */
                $ipaddr => $ip_address,
            ];

            $staticmap[] = $entry;
        }
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
    $keaDhcpv6 = new \OPNsense\Kea\KeaDhcpv6();

    killbypid('/var/run/kea_prefix_watcher.pid');

    if ($keaDhcpv4->isEnabled() || $keaDhcpv6->isEnabled()) {
        service_log('Sync KEA DHCP config...', $verbose);
        if ($keaDhcpv4->isEnabled() && $keaDhcpv4->general->manual_config->isEmpty()) {
            /* skip kea-dhcp4.conf when configured manually */
            $keaDhcpv4->generateConfig();
        }
        if ($keaDhcpv6->isEnabled() && $keaDhcpv6->general->manual_config->isEmpty()) {
            /* skip kea-dhcp6.conf when configured manually */
            $keaDhcpv6->generateConfig();
        }
        (new \OPNsense\Kea\KeaCtrlAgent())->generateConfig();
        if ($keaDhcpv6->isEnabled()) {
            mwexecf(
                '/usr/sbin/daemon -f -p %s -o %s %s %s',
                [
                    '/var/run/kea_prefix_watcher.pid',
                    '/var/log/kea/kea_prefix_watcher.log',
                    '/usr/local/opnsense/scripts/kea/kea_prefix_watcher.py',
                    '/var/db/kea/kea-leases6.csv'
                ]
            );
        }
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
    $keav6 = new \OPNsense\Kea\KeaDhcpv6();

    if ($keav4->fwrulesEnabled()) {
        // automatic (IPv4) rules enabled
        foreach ($keav4->general->interfaces->getValues() as $intf) {
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

    if ($keav6->fwrulesEnabled()) {
        foreach ($keav6->general->interfaces->getValues() as $intf) {
            $default_opts = [
                'protocol' => 'udp',
                'ipprotocol' => 'inet6',
                'interface' => $intf,
                '#ref' => 'ui/kea/dhcp/v6',
                'descr' => 'allow access to DHCPv6 server',
                'log' => !isset($config['syslog']['nologdefaultpass'])
            ];
            $fw->registerFilterRule(
                1,
                [
                    'from' => 'fe80::/10',
                    'to' => 'fe80::/10,ff02::/16',
                    'to_port' => 546
                ],
                $default_opts
            );
            $fw->registerFilterRule(
                1,
                [
                    'from' => 'fe80::/10',
                    'to' => 'ff02::/16',
                    'to_port' => 547
                ],
                $default_opts
            );
            $fw->registerFilterRule(
                1,
                [
                    'from' => 'ff02::/16',
                    'to' => 'fe80::/10',
                    'to_port' => 547
                ],
                $default_opts
            );
            $fw->registerFilterRule(
                1,
                [
                    'from' => 'fe80::/10',
                    'to' => '(self)',
                    'to_port' => 546
                ],
                $default_opts
            );
            $fw->registerFilterRule(
                1,
                [
                    'from' => '(self)',
                    'to' => 'fe80::/10',
                    'from_port' => 547,
                    'direction' => 'out'
                ],
                $default_opts
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
        'services' => ["kea-dhcpv4", "kea-dhcpv6"],
    ];

    return $result;
}
