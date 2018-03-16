<?php

/*
    Copyright (C) 2018 Deciso B.V.
    All rights reserved.

    Redistribution and use in source and binary forms, with or without
    modification, are permitted provided that the following conditions are met:

    1. Redistributions of source code must retain the above copyright notice,
       this list of conditions and the following disclaimer.

    2. Redistributions in binary form must reproduce the above copyright
       notice, this list of conditions and the following disclaimer in the
       documentation and/or other materials provided with the distribution.

    THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
    INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
    AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
    AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
    OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
    SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
    INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
    CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
    ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
    POSSIBILITY OF SUCH DAMAGE.
*/

function core_services()
{
    global $config;

    $services = array();

    if (is_radvd_enabled()) {
        $pconfig = array();
        $pconfig['name'] = "radvd";
        $pconfig['description'] = gettext("Router Advertisement Daemon");
        $pconfig['php']['restart'] = array('services_radvd_configure');
        $pconfig['php']['start'] = array('services_radvd_configure');
        $pconfig['pidfile'] = '/var/run/radvd.pid';
        $services[] = $pconfig;
    }

    if (isset($config['dhcrelay']['enable'])) {
        $pconfig = array();
        $pconfig['name'] = "dhcrelay";
        $pconfig['description'] = gettext("DHCP Relay");
        $pconfig['php']['restart'] = array('services_dhcrelay_configure');
        $pconfig['php']['start'] = array('services_dhcrelay_configure');
        $pconfig['pidfile'] = '/var/run/dhcrelay.pid';
        $services[] = $pconfig;
    }

    if (isset($config['dhcrelay6']['enable'])) {
        $pconfig = array();
        $pconfig['name'] = "dhcrelay6";
        $pconfig['description'] = gettext("DHCPv6 Relay");
        $pconfig['php']['restart'] = array('services_dhcrelay6_configure');
        $pconfig['php']['start'] = array('services_dhcrelay6_configure');
        $pconfig['pidfile'] = '/var/run/dhcrelay6.pid';
        $services[] = $pconfig;
    }

    if (is_dhcpv4_server_enabled()) {
        $pconfig = array();
        $pconfig['name'] = 'dhcpd';
        $pconfig['description'] = gettext("DHCP Server");
        $pconfig['php']['restart'] = array('services_dhcpdv4_configure');
        $pconfig['php']['start'] = array('services_dhcpdv4_configure');
        $pconfig['pidfile'] = '/var/dhcpd/var/run/dhcpd.pid';
        $services[] = $pconfig;
    }

    if (is_dhcpv6_server_enabled()) {
        $pconfig = array();
        $pconfig['name'] = 'dhcpd6';
        $pconfig['description'] = gettext("DHCPv6 Server");
        $pconfig['php']['restart'] = array('services_dhcpdv6_configure');
        $pconfig['php']['start'] = array('services_dhcpdv6_configure');
        $pconfig['pidfile'] = '/var/dhcpd/var/run/dhcpdv6.pid';
        $services[] = $pconfig;
    }

    if (is_apinger_enabled()) {
        $pconfig = array();
        $pconfig['name'] = "apinger";
        $pconfig['description'] = gettext("Gateway Monitoring Daemon");
        $pconfig['php']['restart'] = array('setup_gateways_monitor');
        $pconfig['php']['start'] = array('setup_gateways_monitor');
        $pconfig['pidfile'] = '/var/run/apinger.pid';
        $services[] = $pconfig;
    }

    if (isset($config['OPNsense']['captiveportal']['zones']['zone'])) {
        $enabled = false;
        if (!empty($config['OPNsense']['captiveportal']['zones']['zone']['enabled'])) {
            // single zone and enabled
            $enabled = true;
        } else {
            // possible more zones, traverse items
            foreach ($config['OPNsense']['captiveportal']['zones']['zone'] as $zone) {
                if (!empty($zone['enabled'])) {
                    $enabled = true;
                }
            }
        }
        if ($enabled) {
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
    }

    $services[] = array(
        'description' => gettext('System Configuration Daemon'),
        'pidfile' => '/var/run/configd.pid',
        'mwexec' => array(
            'restart' => array('/usr/local/etc/rc.d/configd restart'),
            'start' => array('/usr/local/etc/rc.d/configd start'),
            'stop' => array('/usr/local/etc/rc.d/configd stop'),
        ),
        'name' => 'configd',
    );

    $services[] = array(
        'description' => gettext('login (sync users and groups)'),
        'php' => array(
            "restart" => array('system_login_configure')
        ),
        'nocheck' => true,
        'name' => 'login',
    );

    $services[] = array(
        'description' => gettext('Syslog'),
        'php' => array(
            "start" => array('system_syslogd_start'),
            "restart" => array('system_syslogd_start')
        ),
        'pidfile' => '/var/run/syslog.pid',
        'name' => 'syslog',
    );

    return $services;
}
