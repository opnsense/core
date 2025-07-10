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
        $services[] = array(
            'pidfile' => '/var/run/lighttpd-api-dispatcher.pid',
            'description' => gettext('Captive Portal'),
            'configd' => array(
                'restart' => array('captiveportal restart'),
                'start' => array('captiveportal start'),
                'stop' => array('captiveportal stop'),
            ),
            'name' => 'captiveportal',
        );
    }

    return $services;
}

function captiveportal_cron()
{
    global $config;

    $jobs = [];

    if (!empty($config['system']['captiveportalbackup']) && $config['system']['captiveportalbackup'] > 0) {
        $jobs[]['autocron'] = array(
            '/usr/local/etc/rc.syshook.d/backup/20-captiveportal',
            '0',
            '*/' . $config['system']['captiveportalbackup']
        );
    }

    return $jobs;
}

function captiveportal_syslog()
{
    $logfacilities = [];

    $logfacilities['portalauth'] = ['facility' => ['captiveportal']];

    return $logfacilities;
}

function captiveportal_firewall($fw)
{
    global $config;

    $cp = new \OPNsense\CaptivePortal\CaptivePortal();
    if ($cp->isEnabled()) {
        foreach ($cp->zones->zone->iterateItems() as $zone) {
            if ($zone->enabled->isEmpty()) {
                continue;
            }

            $zoneid = (string)$zone->zoneid;
            $uuid = $zone->getAttribute('uuid');
            // register anchor, to be filled with ether rules for accounting
            $fw->registerAnchor("captiveportal_zone_{$zoneid}", "ether", 0, "head", false, (string)$zone->interfaces);

            if (!$zone->disableRules->isEmpty()) {
                continue;
            }

            foreach ($zone->interfaces->getValues() as $intf) {
                // allow DNS
                $fw->registerFilterRule(
                    1,
                    [
                        'type' => 'pass',
                        'interface' => $intf,
                        'protocol' => 'tcp/udp',
                        'direction' => 'in',
                        'from' => 'any',
                        'to' => '(self)',
                        'to_port' => 53,
                        'descr' => "Allow DNS for Captive Portal (zone {$zoneid})",
                        'log' => !isset($config['syslog']['nologdefaultpass']),
                        '#ref' => "ui/captiveportal#edit={$uuid}",
                    ]
                );

                foreach (['80', '443'] as $to_port) {
                    $rdr_port = $to_port === '443' ? (8000 + (int)$zoneid) : (9000 + (int)$zoneid);

                    // forward to localhost if not authenticated
                    $fw->registerForwardRule(
                        2,
                        [
                            'interface' => $intf,
                            'pass' => true,
                            'nordr' => false,
                            'ipprotocol' => 'inet',
                            'protocol' => 'tcp',
                            'from' => "<__captiveportal_zone_{$zoneid}>",
                            'from_not' => true,
                            'to' => "<__captiveportal_zone_{$zoneid}>",
                            'to_not' => true,
                            'to_port' => $to_port,
                            'target' => '127.0.0.1',
                            'localport' => $rdr_port,
                            'natreflection' => 'disable',
                            'descr' => "Redirect to Captive Portal (zone {$zoneid})",
                            '#ref' => "ui/captiveportal#edit={$uuid}"
                        ]
                    );

                    // Allow access to the captive portal
                    $proto = $to_port === '443' ? 'https' : 'http';
                    $fw->registerFilterRule(
                        2,
                        [
                            'type' => 'pass',
                            'interface' => $intf,
                            'protocol' => 'tcp',
                            'direction' => 'in',
                            'from' => 'any',
                            'to' => '(self)',
                            'to_port' => $rdr_port,
                            'descr' => "Allow access to Captive Portal ({$proto}, zone {$zoneid})",
                            'log' => !isset($config['syslog']['nologdefaultpass']),
                            '#ref' => "ui/captiveportal#edit={$uuid}",
                        ]
                    );
                }

                // block all non-authenticated users
                $fw->registerFilterRule(
                    3,
                    [
                        'type' => 'block',
                        'interface' => $intf,
                        'direction' => 'in',
                        'from' => "<__captiveportal_zone_{$zoneid}>",
                        'from_not' => true,
                        'to' => "<__captiveportal_zone_{$zoneid}>",
                        'to_not' => true,
                        'descr' => "Default Captive Portal block rule (zone {$zoneid})",
                        'log' => !isset($config['syslog']['nologdefaultblock']),
                        '#ref' => "ui/captiveportal#edit={$uuid}",
                    ]
                );

                // we do not create a pass rule for authenticated clients here, any user-defined pass rule will
                // automatically apply to the authenticated clients of this zone.
            }
        }
    }
}
