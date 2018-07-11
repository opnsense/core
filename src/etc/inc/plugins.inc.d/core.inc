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
        $pconfig['description'] = gettext("DHCPv4 Relay");
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
        $pconfig['description'] = gettext("DHCPv4 Server");
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

    if (is_dpinger_enabled()) {
        $gateways_arr_all = return_gateways_array(true);

        foreach ($gateways_arr_all as $name => $gateway) {
            if (isset($gateway['monitor_disable'])) {
                continue;
            }

            $pconfig = array();
            $pconfig['description'] = sprintf(gettext('Gateway Monitor (%s)'), $gateway['name']);
            $pconfig['php']['restart'] = array('setup_gateways_monitor');
            $pconfig['php']['start'] = array('setup_gateways_monitor');
            $pconfig['pidfile'] = "/var/run/dpinger_{$name}.pid";
            $pconfig['php']['args'] = array('verbose', 'id');
            $pconfig['name'] = 'dpinger';
            $pconfig['verbose'] = false;
            $pconfig['id'] = $name;
            $services[] = $pconfig;
        }
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
        'description' => gettext('Users and Groups'),
        'php' => array(
            "restart" => array('system_login_configure')
        ),
        'nocheck' => true,
        'name' => 'login',
    );

    $services[] = array(
        'description' => gettext('Local Syslog'),
        'php' => array(
            "start" => array('system_syslogd_start'),
            "restart" => array('system_syslogd_start')
        ),
        'pidfile' => '/var/run/syslog.pid',
        'name' => 'syslog',
    );

    return $services;
}

function core_cron()
{
    global $config;

    $jobs = array();

    $jobs[]['autocron'] = array('/usr/local/sbin/expiretable -v -t 3600 webConfiguratorlockout', '1');
    $jobs[]['autocron'] = array('/usr/local/sbin/expiretable -v -t 3600 sshlockout', '2');
    $jobs[]['autocron'] = array('/usr/local/sbin/expiretable -v -t 3600 virusprot', '3');
    $jobs[]['autocron'] = array('/usr/local/etc/rc.expireaccounts', '5');

    $jobs[]['autocron'] = array('/usr/local/sbin/ping_hosts.sh', '*/4');

    if (!empty($config['system']['rrdbackup'])) {
        $jobs[]['autocron'] = array(
            '/usr/local/etc/rc.syshook.d/backup/20-rrd',
            '0',
            '*/' . $config['system']['rrdbackup']
        );
    }

    if (!empty($config['system']['dhcpbackup'])) {
        $jobs[]['autocron'] = array(
            '/usr/local/etc/rc.syshook.d/backup/20-dhcpleases',
            '0',
            '*/' . $config['system']['dhcpbackup']
        );
    }

    if (!empty($config['system']['netflowbackup'])) {
        $jobs[]['autocron'] = array(
            '/usr/local/etc/rc.syshook.d/backup/20-netflow',
            '0',
            '*/' . $config['system']['netflowbackup']
        );
    }

    if (!empty($config['system']['captiveportalbackup'])) {
        $jobs[]['autocron'] = array(
            '/usr/local/etc/rc.syshook.d/backup/20-captiveportal',
            '0',
            '*/' . $config['system']['captiveportalbackup']
        );
    }

    if (!empty($config['system']['dhparamusage'])) {
        switch ($config['system']['dhparamusage']) {
            case 'weekly':
                $jobs[]['autocron'] = array('configctl system ssl dhparam', '30', '3', '*', '*', '0');
                break;
            case 'monthly':
                $jobs[]['autocron'] = array('configctl system ssl dhparam', '30', '3', '1', '*', '*');
                break;
            default:
                /* cron job operated by user manually or RFC 7919 */
                break;
        }
    }

    $jobs[]['autocron'] = array('configctl system remote backup', 0, 1);

    return $jobs;
}
