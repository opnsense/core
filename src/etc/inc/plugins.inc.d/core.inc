<?php

/*
 * Copyright (C) 2018-2022 Deciso B.V.
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
        'locked' => true,
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
        'description' => gettext('System routing'),
        'php' => array(
            "restart" => array('system_routing_configure')
        ),
        'nocheck' => true,
        'name' => 'routing',
    );

    $services[] = array(
        'description' => gettext('System tunables'),
        'php' => array(
            "restart" => array('system_sysctl_configure')
        ),
        'nocheck' => true,
        'name' => 'sysctl',
    );

    $services[] = array(
        'description' => gettext('Cron'),
        'php' => array(
            'start' => ['system_cron_configure'],
            'restart' => ['system_cron_configure'],
        ),
        'pidfile' => '/var/run/cron.pid',
        'name' => 'cron',
    );

    $services[] = array(
        'description' => gettext('Syslog-ng Daemon'),
        'php' => array(
            'stop' => array('system_syslog_stop'),
            'start' => array('system_syslog_start'),
            'restart' => array('system_syslog_start')
        ),
        'pidfile' => '/var/run/syslog-ng.pid',
        'name' => 'syslog-ng',
    );

    return $services;
}

function core_devices()
{
    $devices = [];

    $bridge_names = [];

    foreach (config_read_array('bridges', 'bridged') as $dev) {
        $bridge_names[$dev['bridgeif']] = [
            'descr' => sprintf('%s (%s)', $dev['bridgeif'], $dev['descr']),
            'ifdescr' => sprintf('%s', $dev['descr']),
            'name' => $dev['bridgeif'],
        ];
    }

    $devices[] = [
        'function' => 'interfaces_bridge_configure',
        'names' => $bridge_names,
        'pattern' => '^bridge',
        'volatile' => true,
        'type' => 'bridge',
    ];

    $gif_names = [];

    foreach (config_read_array('gifs', 'gif') as $dev) {
        $gif_names[$dev['gifif']] = [
            'descr' => sprintf('%s %s (%s)', $dev['gifif'], $dev['remote-addr'], $dev['descr']),
            'ifdescr' => sprintf('%s', $dev['descr']),
            'name' => $dev['gifif'],
        ];
    }

    $devices[] = [
        'function' => 'interfaces_gif_configure',
        'configurable' => false,
        'names' => $gif_names,
        'pattern' => '^gif',
        'volatile' => true,
        'type' => 'gif',
    ];

    $gre_names = [];

    foreach (config_read_array('gres', 'gre') as $dev) {
        $gre_names[$dev['greif']] = [
            'descr' => sprintf('%s %s (%s)', $dev['greif'], $dev['remote-addr'], $dev['descr']),
            'ifdescr' => sprintf('%s', $dev['descr']),
            'name' => $dev['greif'],
        ];
    }

    $devices[] = [
        'function' => 'interfaces_gre_configure',
        'configurable' => false,
        'names' => $gre_names,
        'pattern' => '^gre',
        'volatile' => true,
        'type' => 'gre',
    ];

    $lagg_names = [];

    foreach (config_read_array('laggs', 'lagg') as $dev) {
        $lagg_names[$dev['laggif']] = [
            'descr' => sprintf('%s (%s)', $dev['laggif'], $dev['descr']),
            'ifdescr' => sprintf('%s', $dev['descr']),
            'exclude' => explode(',', $dev['members']),
            'name' => $dev['laggif'],
        ];
    }

    $devices[] = [
        'names' => $lagg_names,
        'pattern' => '^lagg',
        'volatile' => true,
        'type' => 'lagg',
    ];

    $ppp_names = [];

    foreach (config_read_array('ppps', 'ppp') as $dev) {
        $ppp_names[$dev['if']] = [
            'descr' => sprintf('%s (%s) - %s %s', $dev['if'], $dev['ports'], $dev['descr'] ?? '', $dev['username']),
            'ifdescr' => sprintf('%s', $dev['descr'] ?? ''),
            'ipaddr' => $dev['type'],
            'name' => $dev['if'],
        ];
    }

    $devices[] = [
        'pattern' => '^cua|^l2tp|^ppp|^pptp', /* XXX ^cua likely doesn't match since it's a /dev node */
        'names' => $ppp_names,
        'volatile' => true,
        'type' => 'ppp',
    ];

    $vlan_names = [];

    foreach (config_read_array('vlans', 'vlan') as $dev) {
        $vlan_names[$dev['vlanif']] = [
            'descr' => sprintf(gettext('%s %s (Parent: %s, Tag: %s)'), $dev['vlanif'], $dev['descr'], $dev['if'], $dev['tag']),
            'ifdescr' => sprintf('%s', $dev['descr']),
            'name' => $dev['vlanif'],
        ];
    }

    $devices[] = [
        'pattern' => '_vlan|^vlan|^qinq',
        'names' => $vlan_names,
        'volatile' => true,
        'type' => 'vlan',
    ];

    $wlan_names = [];

    foreach (config_read_array('wireless', 'clone') as $dev) {
        $wlan_names[$dev['cloneif']] = [
            'descr' => sprintf('%s (%s)', $dev['cloneif'], $dev['descr']),
            'ifdescr' => sprintf('%s', $dev['descr']),
            'name' => $dev['cloneif'],
        ];
    }

    /* also need to find implied clones that do not have explicit cloneif set */
    foreach (legacy_config_get_interfaces() as $id => $conf) {
        if (isset($conf['wireless']) && !isset($wlan_names[$conf['if']])) {
            $wlan_names[$conf['if']] = [
                'descr' => sprintf('%s (%s)', $conf['if'], gettext('wireless clone')),
                'ifdescr' => gettext('wireless clone'),
                'name' => $conf['if'],
            ];

            $realif = get_real_interface($id);
            if ($realif == $conf['if']) {
                continue;
            }

            /* return both implicit and explicit clone if implicit clone was given */
            $wlan_names[$realif] = [
                'descr' => sprintf('%s (%s)', $realif, gettext('wireless clone')),
                'ifdescr' => gettext('wireless clone'),
                'name' => $realif,
            ];
        }
    }

    $devices[] = [
        'function' => 'interfaces_wlan_clone',
        'names' => $wlan_names,
        'pattern' => '_wlan',
        'volatile' => true,
        'type' => 'wlan',
    ];

    /* historic handling of tunnel devices and other unstable things */
    $devices[] = ['pattern' => '_stf|^tap|^tun|^ue', 'volatile' => true];

    return $devices;
}

function core_cron()
{
    global $config;

    $jobs = array();

    $jobs[]['autocron'] = array('/usr/local/sbin/configctl -d syslog archive', '1');
    $jobs[]['autocron'] = array('/usr/local/sbin/expiretable -v -t 3600 sshlockout', '2');
    $jobs[]['autocron'] = array('/usr/local/sbin/expiretable -v -t 3600 virusprot', '3');
    $jobs[]['autocron'] = array('/usr/local/etc/rc.expireaccounts', '4');
    $jobs[]['autocron'] = array('/usr/local/sbin/ping_hosts.sh', '*/4');
    $jobs[]['autocron'] = array('/usr/local/sbin/configctl -d firmware changelog cron', '0', '22');

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

    foreach ((new OPNsense\Backup\BackupFactory())->listProviders() as $classname => $provider) {
        if ($provider['handle']->isEnabled()) {
            $jobs[]['autocron'] = array('/usr/local/sbin/configctl -d system remote backup 3600', 0, 1);
            break;
        }
    }

    return $jobs;
}

function core_syslog()
{
    $logfacilities = [];

    $logfacilities['audit'] = ['facility' => ['audit']];
    $logfacilities['configd'] = ['facility' => ['configd.py']];
    $logfacilities['dhcpd'] = ['facility' => ['dhcpd']];
    $logfacilities['lighttpd'] = ['facility' => ['lighttpd']];
    $logfacilities['pkg'] = ['facility' => ['pkg', 'pkg-static']];
    $logfacilities['portalauth'] = ['facility' => ['captiveportal']];
    $logfacilities['ppps'] = ['facility' => ['ppp']];
    $logfacilities['resolver'] = ['facility' => ['unbound']];
    $logfacilities['routing'] = ['facility' => ['routed', 'olsrd', 'zebra', 'ospfd', 'bgpd', 'miniupnpd']];
    $logfacilities['wireless'] = ['facility' => ['hostapd']];

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
        'description' => gettext('Dashboard'),
        'help' => gettext('Synchronize dashboard widgets.'),
        'section' => 'widgets',
        'id' => 'widgets',
    );
    $result[] = array(
        'description' => gettext('Users and Groups'),
        'help' => gettext('Synchronize the users and groups over to the other HA host.'),
        'section' => 'system.user,system.group',
        'id' => 'users',
        'services' => ["login"],
    );
    $result[] = array(
        'description' => gettext('Auth Servers'),
        'help' => gettext('Synchronize the authentication servers (e.g. LDAP, RADIUS) over to the other HA host.'),
        'section' => 'system.authserver',
        'id' => 'authservers',
        'services' => ["login"],
    );
    $result[] = array(
        'description' => gettext('Certificates'),
        'help' => gettext('Synchronize the Certificate Authorities, Certificates, and Certificate Revocation Lists over to the other HA host.'),
        'section' => 'cert,ca,crl',
        'id' => 'certs',
    );
    $result[] = array(
        'description' => gettext('DHCPD'),
        'help' => gettext('Synchronize the DHCP Server settings over to the other HA host. This only applies to DHCP for IPv4.'),
        'section' => 'dhcpd',
        'id' => 'dhcpd',
        'services' => ["dhcpd"],
    );
    $result[] = array(
        'description' => gettext('DHCPDv6'),
        'help' => gettext('Synchronize DHCPv6 including router advertisements.'),
        'section' => 'dhcpdv6',
        'id' => 'dhcpdv6',
        'services' => ["dhcpdv6", "radvd"],
    );
    $result[] = array(
        'description' => gettext('Virtual IPs'),
        'help' => gettext('Synchronize the CARP Virtual IPs to the other HA host.'),
        'section' => 'virtualip',
        'id' => 'virtualip',
    );
    $result[] = array(
        'description' => gettext('Static Routes'),
        'help' => gettext('Synchronize the Static Route configuration and Gateways to the other HA host.'),
        'section' => 'staticroutes,gateways,OPNsense.Gateways',
        'id' => 'staticroutes',
        'services' => ["routing", "dpinger"],
    );
    $result[] = array(
        'description' => gettext('Network Time'),
        'section' => 'ntpd,system.timeservers',
        'id' => 'ntpd',
        'services' => ["ntpd"],
    );
    $result[] = array(
        'description' => gettext('Syslog'),
        'section' => 'syslog,OPNsense.Syslog',
        'id' => 'syslog',
        'services' => ["syslogd"],
    );
    $result[] = array(
        'description' => gettext('Cron'),
        'section' => 'OPNsense.cron',
        'id' => 'cron',
        'services' => ["cron"],
    );
    $result[] = array(
        'description' => gettext('System Tunables'),
        'section' => 'sysctl',
        'id' => 'sysctl',
        'services' => ["sysctl"],
    );
    $result[] = array(
        'description' => gettext('Web GUI'),
        'section' => 'system.webgui',
        'id' => 'webgui',
        'services' => ["webgui"],
    );
    $result[] = array(
        'description' => gettext('Backup - Google Drive'),
        'section' => 'system.remotebackup',
        'id' => 'remotebackup'
    );

    return $result;
}

function core_configure()
{
    return [
        /* XXX these are all specialized and try to avoid extra script use */
        'dns_reload' => ['system_resolver_configure'],
        'firmware_reload' => ['system_firmware_configure'],
        'route_reload' => ['core_routing_batch:2'],
        'syslog_reset' => ['system_syslog_reset'],
        'trust_reload' => ['system_trust_configure'],
        'user_changed' => ['core_user_changed_groups:2'],
    ];
}

function core_run()
{
    return [
        'host_routes' => 'system_resolvconf_host_routes',
    ];
}

function core_routing_batch($verbose, $interfaces = [null])
{
    if (!is_array($interfaces)) {
        $interfaces = [$interfaces];
    }

    foreach ($interfaces as $interface) {
        system_routing_configure($verbose, $interface);
    }
}

/**
 * user changed event, synchronize attached system groups for requested user
 */
function core_user_changed_groups($unused, $username)
{
    global $config;
    if (is_array($config['system']['user'])) {
        foreach ($config['system']['user'] as $user) {
            if ($user['name'] == $username) {
                exec("/usr/bin/groups " . escapeshellarg($username) . ' 2>/dev/null', $out, $ret);
                $current_groups = [];
                if (!$ret) {
                    $current_groups = explode(" ", $out[0]);
                }
                foreach ($config['system']['group'] as $group) {
                    $in_group = !empty($group['member']) && in_array($user['uid'], $group['member']);
                    $to_remove = in_array($group['name'], $current_groups) && !$in_group;
                    $to_add = !in_array($group['name'], $current_groups) && $in_group;
                    if ($to_remove || $to_add) {
                        local_group_set($group);
                    }
                }
            }
        }
    }
}
