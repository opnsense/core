<?php

/*
 * Copyright (C) 2018 Deciso B.V.
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

function core_services()
{
    global $config;

    $services = array();

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
            "stop" => array('system_syslogd_stop'),
            "start" => array('system_syslogd_start'),
            "restart" => array('system_syslogd_start')
        ),
        'pidfile' => '/var/run/syslog.pid',
        'name' => 'syslogd',
    );

    $services[] = array(
        'description' => gettext('Remote Syslog'),
        'php' => array(
            "stop" => array('system_syslogd_stop'),
            "start" => array('system_syslogd_start'),
            "restart" => array('system_syslogd_start')
        ),
        'pidfile' => '/var/run/syslog-ng.pid',
        'name' => 'syslog-ng',
    );

    return $services;
}

function core_devices()
{
    $devices = array();

    # XXX is a plugin collection...
    $devices[] = array('pattern' => '^bridge', 'volatile' => true);
    $devices[] = array('pattern' => '^cua', 'volatile' => true);
    $devices[] = array('pattern' => '^enc', 'volatile' => true);
    $devices[] = array('pattern' => '^gif', 'volatile' => true, 'configurable' => false);
    $devices[] = array('pattern' => '^gre', 'volatile' => true, 'configurable' => false);
    $devices[] = array('pattern' => '^ipsec', 'volatile' => true, 'configurable' => false);
    $devices[] = array('pattern' => '^l2tp', 'volatile' => true);
    $devices[] = array('pattern' => '^lagg', 'volatile' => true);
    $devices[] = array('pattern' => '^ocvpn', 'volatile' => true);
    $devices[] = array('pattern' => '^ovpn', 'volatile' => true, 'configurable' => false);
    $devices[] = array('pattern' => '^ppp', 'volatile' => true);
    $devices[] = array('pattern' => '^pptp', 'volatile' => true);
    $devices[] = array('pattern' => '^tinc', 'volatile' => true);
    $devices[] = array('pattern' => '^tun|^tap', 'volatile' => true);
    $devices[] = array('pattern' => '^ue', 'volatile' => true);
    $devices[] = array('pattern' => '^wg', 'volatile' => true);
    $devices[] = array('pattern' => '^zt', 'volatile' => true);
    $devices[] = array('pattern' => '_stf', 'volatile' => true);
    $devices[] = array('pattern' => '_wlan', 'volatile' => true);
    $devices[] = array('pattern' => 'vlan', 'volatile' => true);

    return $devices;
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

    if (!empty($config['system']['rrdbackup']) && $config['system']['rrdbackup'] > 0) {
        $jobs[]['autocron'] = array(
            '/usr/local/etc/rc.syshook.d/backup/20-rrd',
            '0',
            '*/' . $config['system']['rrdbackup']
        );
    }

    if (!empty($config['system']['dhcpbackup']) && $config['system']['dhcpbackup'] > 0) {
        $jobs[]['autocron'] = array(
            '/usr/local/etc/rc.syshook.d/backup/20-dhcpleases',
            '0',
            '*/' . $config['system']['dhcpbackup']
        );
    }

    if (!empty($config['system']['netflowbackup']) && $config['system']['netflowbackup'] > 0) {
        $jobs[]['autocron'] = array(
            '/usr/local/etc/rc.syshook.d/backup/20-netflow',
            '0',
            '*/' . $config['system']['netflowbackup']
        );
    }

    if (!empty($config['system']['captiveportalbackup']) && $config['system']['captiveportalbackup'] > 0) {
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

    foreach ((new OPNsense\Backup\BackupFactory())->listProviders() as $classname => $provider) {
        if ($provider['handle']->isEnabled()) {
            $jobs[]['autocron'] = array('configctl system remote backup', 0, 1);
            break;
        }
    }

    return $jobs;
}

function core_syslog()
{
    $logfacilities = array();

    $logfacilities['configd'] = array('facility' => array('configd.py'));
    $logfacilities['dhcpd'] = array('facility' => array('dhcpd', 'dhcrelay'));
    $logfacilities['filter'] = array('facility' => array('filterlog'));
    $logfacilities['gateways'] = array('facility' => array('dpinger'));
    $logfacilities['lighttpd'] = array('facility' => array('lighttpd'));
    $logfacilities['pkg'] = array('facility' => array('pkg', 'pkg-static'));
    $logfacilities['portalauth'] = array('facility' => array('captiveportal'));
    $logfacilities['ppps'] = array('facility' => array('ppp'));
    $logfacilities['resolver'] = array('facility' => array('unbound'));
    $logfacilities['routing'] = array('facility' => array(
          'radvd', 'routed', 'rtsold', 'olsrd', 'zebra', 'ospfd', 'bgpd', 'miniupnpd'
    ));
    $logfacilities['wireless'] = array('facility' => array('hostapd'));

    return $logfacilities;
}

/**
 *  sync configuration via xmlrpc
 * @return array
 */
function core_xmlrpc_sync()
{
    $result = array();

    $result[] = array(
        'description' => gettext('Network Time'),
        'section' => 'ntpd,system.timeservers',
        'id' => 'ntpd',
    );

    return $result;
}
