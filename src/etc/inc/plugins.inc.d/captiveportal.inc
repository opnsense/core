<?php

/*
 * Copyright (C) 2025 Deciso B.V.
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

function captiveportal_services()
{
    $services = [];

    if ((new \OPNsense\CaptivePortal\CaptivePortal())->isEnabled()) {
        $services[] = [
            'pidfile' => '/var/run/lighttpd-api-dispatcher.pid',
            'description' => gettext('Captive Portal'),
            'configd' => [
                'restart' => ['captiveportal restart'],
                'start' => ['captiveportal start'],
                'stop' => ['captiveportal stop'],
            ],
            'name' => 'captiveportal',
        ];
    }

    return $services;
}

function captiveportal_syslog()
{
    $logfacilities = [];

    $logfacilities['portalauth'] = ['facility' => ['captiveportal']];

    return $logfacilities;
}

function captiveportal_auto_rules()
{
    $syslog = config_read_array('syslog', false);
    $rules = [];

    $cp = new \OPNsense\CaptivePortal\CaptivePortal();
    if (!$cp->isEnabled()) {
        return $rules;
    }

    foreach ($cp->zones->zone->iterateItems() as $zone) {
        if ($zone->enabled->isEmpty()) {
            continue;
        }

        $zoneid = $zone->zoneid->asInt();
        $uuid = $zone->getAttribute('uuid');

        if (!$zone->disableRules->isEmpty()) {
            continue;
        }

        foreach ($zone->interfaces->getValues() as $intf) {
            $rules['filter'][] = [
                'type' => 'pass',
                'interface' => $intf,
                'protocol' => 'tcp/udp',
                'direction' => 'in',
                'from' => 'any',
                'to' => '(self)',
                'to_port' => 53,
                'descr' => "Allow DNS for Captive Portal (zone {$zoneid})",
                'log' => !isset($syslog['nologdefaultpass']),
                '#ref' => "ui/captiveportal#edit={$uuid}",
                '#priority' => 1,
            ];

            foreach (['80', '443'] as $to_port) {
                $localport = $to_port === '443' ? (8000 + $zoneid) : (9000 + $zoneid);
                $to = $to_port === '443' ? "(self)" : "<__captiveportal_zone_{$zoneid}>";
                $to_not = $to_port === '443' ? false : true;

                // forward HTTP(S) to localhost if not authenticated (IPv4)
                // Only match HTTPS headed to (self) to facilitate RFC 8908
                $rules['destination'][] = [
                    'interface' => $intf,
                    'nordr' => false,
                    'ipprotocol' => 'inet',
                    'protocol' => 'tcp',
                    'from' => "<__captiveportal_zone_{$zoneid}>",
                    'from_not' => true,
                    'to' => $to,
                    'to_not' => $to_not,
                    'to_port' => $to_port,
                    'target' => '127.0.0.1',
                    'localport' => $localport,
                    'natreflection' => 'disable',
                    'descr' => "Redirect to Captive Portal (zone {$zoneid})",
                    '#ref' => "ui/captiveportal#edit={$uuid}",
                    '#priority' => 2,
                ];

                // forward to localhost if not authenticated (IPv6)
                $rules['destination'][] = [
                    'interface' => $intf,
                    'nordr' => false,
                    'ipprotocol' => 'inet6',
                    'protocol' => 'tcp',
                    'from' => "<__captiveportal_zone_{$zoneid}>",
                    'from_not' => true,
                    'to' => $to,
                    'to_not' => $to_not,
                    'to_port' => $to_port,
                    'target' => $intf . 'ip',
                    'localport' => $localport,
                    'natreflection' => 'disable',
                    'descr' => "Redirect to Captive Portal (zone {$zoneid})",
                    '#ref' => "ui/captiveportal#edit={$uuid}",
                    '#priority' => 2,
                ];

                $rdr_port = $to_port === '443' ? (8000 + $zoneid) : (9000 + $zoneid);
                $proto = $to_port === '443' ? 'HTTPS' : 'HTTP';

                $rules['filter'][] = [
                    'type' => 'pass',
                    'interface' => $intf,
                    'protocol' => 'tcp',
                    'direction' => 'in',
                    'from' => 'any',
                    'to' => '(self)',
                    'to_port' => $rdr_port,
                    'descr' => "Allow access to Captive Portal over {$proto} (zone {$zoneid})",
                    'log' => !isset($syslog['nologdefaultpass']),
                    '#ref' => "ui/captiveportal#edit={$uuid}",
                    '#priority' => 2,
                ];
            }

            $rules['filter'][] = [
                'type' => 'block',
                'interface' => $intf,
                'direction' => 'in',
                'from' => "<__captiveportal_zone_{$zoneid}>",
                'from_not' => true,
                'to' => "<__captiveportal_zone_{$zoneid}>",
                'to_not' => true,
                'descr' => "Default Captive Portal block rule (zone {$zoneid})",
                'log' => !isset($syslog['nologdefaultblock']),
                '#ref' => "ui/captiveportal#edit={$uuid}",
                '#priority' => 6,
            ];

            // we do not create a pass rule for authenticated clients here, any user-defined pass rule will
            // automatically apply to the authenticated clients of this zone.
        }
    }

    return $rules;
}

function captiveportal_auto_destination_nat()
{
    return captiveportal_auto_rules()['destination'] ?? [];
}

function captiveportal_run()
{
    return [
        'rules_destination_nat' => 'captiveportal_auto_destination_nat',
    ];
}

function captiveportal_firewall($fw)
{
    $auto = captiveportal_auto_rules();

    foreach ($auto['destination'] as $rule) {
        $fw->registerDestinationNatRule($rule['#priority'], $rule);
    }

    foreach ($auto['filter'] as $rule) {
        $fw->registerFilterRule($rule['#priority'], $rule);
    }
}
