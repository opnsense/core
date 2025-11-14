<?php

/*
 * Copyright (C) 2025 Deciso B.V.
 * Copyright (C) 2014-2023 Franco Fichtner <franco@opnsense.org>
 * Copyright (C) 2010 Ermal Luçi
 * Copyright (C) 2005-2006 Colin Smith <ethethlay@gmail.com>
 * Copyright (C) 2003-2004 Manuel Kasper <mk@neon1.net>
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

function dnsmasq_enabled()
{
    global $config;

    return !empty($config['dnsmasq']['enable']);
}

function dnsmasq_configure()
{
    return [
        'dns' => ['dnsmasq_configure_do'],
        'local' => ['dnsmasq_configure_do']
    ];
}

function dnsmasq_services()
{
    $services = [];

    if (!dnsmasq_enabled()) {
        return $services;
    }

    $mdl = new \OPNsense\Dnsmasq\Dnsmasq();

    $pconfig = [];
    $pconfig['name'] = 'dnsmasq';
    if (!empty((string)$mdl->dns_port)) {
        $pconfig['dns_ports'] = [ (string)$mdl->dns_port ];
    }
    $pconfig['description'] = gettext('Dnsmasq DNS/DHCP');
    $pconfig['php']['restart'] = ['dnsmasq_configure_do'];
    $pconfig['php']['start'] = ['dnsmasq_configure_do'];
    $pconfig['configd']['stop'] = ['dnsmasq stop'];
    $pconfig['pidfile'] = '/var/run/dnsmasq.pid';
    $services[] = $pconfig;

    return $services;
}

function dnsmasq_syslog()
{
    $logfacilities = [];

    $logfacilities['dnsmasq'] = ['facility' => ['dnsmasq']];

    return $logfacilities;
}

function dnsmasq_xmlrpc_sync()
{
    $result = [];

    $result[] = [
        'description' => gettext('Dnsmasq DNS/DHCP'),
        'section' => 'dnsmasq',
        'id' => 'dnsforwarder',
        'services' => ['dnsmasq'],
    ];

    return $result;
}

/**
 *  Register automatic firewall rules
 */
function dnsmasq_firewall(\OPNsense\Firewall\Plugin $fw)
{
    global $config;

    $mdl = new \OPNsense\Dnsmasq\Dnsmasq();
    if (!$mdl->enable->isEmpty() && !$mdl->dhcp->default_fw_rules->isEmpty()) {
        $dhcp_ifs = $mdl->getDhcpInterfaces();
        if (empty($dhcp_ifs)) {
            return;
        }
        foreach ($fw->getInterfaceMapping() as $intf => $intfinfo) {
            if (in_array($intf, $dhcp_ifs)) {
                /* ipv4 */
                $baserule = [
                    'interface' => $intf,
                    'protocol' => 'udp',
                    'log' => !isset($config['syslog']['nologdefaultpass']),
                    '#ref' => "ui/dnsmasq/settings#general",
                    'descr' => 'allow access to DHCP server'
                ];
                $fw->registerFilterRule(
                    1,
                    ['direction' => 'in', 'from_port' => 68, 'to' => '255.255.255.255', 'to_port' => 67],
                    $baserule
                );
                $fw->registerFilterRule(
                    1,
                    ['direction' => 'in', 'from_port' => 68, 'to' => '(self)', 'to_port' => 67],
                    $baserule
                );
                $fw->registerFilterRule(
                    1,
                    ['direction' => 'out', 'from_port' => 67, 'from' => '(self)', 'to_port' => 68],
                    $baserule
                );
                /* ipv6 */
                $baserule['ipprotocol'] = 'inet6';
                $fw->registerFilterRule(
                    1,
                    ['from' => 'fe80::/10', 'to' => 'fe80::/10,ff02::/16', 'to_port' => 546],
                    $baserule
                );
                $fw->registerFilterRule(
                    1,
                    ['from' => 'fe80::/10', 'to' => 'ff02::/16', 'to_port' => 547],
                    $baserule
                );
                $fw->registerFilterRule(
                    1,
                    ['from' => 'ff02::/16', 'to' => 'fe80::/10', 'to_port' => 547],
                    $baserule
                );
                $fw->registerFilterRule(
                    1,
                    ['from' => 'fe80::/10', 'to' => '(self)', 'to_port' => 546],
                    $baserule
                );
                $fw->registerFilterRule(
                    1,
                    ['from' => '(self)', 'to' => 'fe80::/10', 'from_port' => 547, 'direction' => 'out'],
                    $baserule
                );
            }
        }
    }
}

function dnsmasq_configure_do($verbose = false)
{
    global $config;

    killbypid('/var/run/dnsmasq_dhcpd.pid');
    mwexecfm('/usr/local/etc/rc.d/dnsmasq stop');

    if (!dnsmasq_enabled()) {
        return;
    }

    service_log('Starting Dnsmasq...', $verbose);

    _dnsmasq_add_host_entries();

    configd_run('template reload OPNsense/Dnsmasq');

    mwexecf('/usr/local/etc/rc.d/dnsmasq start');

    if (!empty($config['dnsmasq']['regdhcp'])) {
        /* XXX: deprecate when moving ISC to plugins ? */
        $domain = $config['system']['domain'];
        if (!empty($config['dnsmasq']['regdhcpdomain'])) {
            $domain = $config['dnsmasq']['regdhcpdomain'];
        }
        mwexecf('/usr/local/opnsense/scripts/dhcp/dnsmasq_watcher.py --domain %s', $domain);
    }

    service_log("done.\n", $verbose);
}

function _dnsmasq_add_host_entries()
{
    global $config;

    $dnsmasqcfg = $config['dnsmasq'];
    $lhosts = '';
    $dhosts = '';

    if (!isset($dnsmasqcfg['hosts']) || !is_array($dnsmasqcfg['hosts'])) {
        $dnsmasqcfg['hosts'] = [];
    }

    foreach ($dnsmasqcfg['hosts'] as $host) {
        /* The host.ip field supports multiple IPv4 and IPv6 addresses */
        foreach (array_filter(explode(',', $host['ip'])) as $ip) {
            /* skip partial IPv6 addresses */
            if (str_starts_with($ip, '::')) {
                continue;
            }
            if (empty($host['host'])) {
                /* do not render partial DHCP lease entries */
            } elseif ($host['host'] === '*') {
                /* do not render wildcard hosts as they are handled directly by dnsmasq.conf */
            } elseif (empty($host['domain'])) {
                $lhosts .= "{$ip}\t{$host['host']}\n";
            } else {
                $lhosts .= "{$ip}\t{$host['host']}.{$host['domain']} {$host['host']}\n";
            }
            if (!empty($host['aliases'])) {
                foreach (explode(",", $host['aliases']) as $alias) {
                    /**
                    * XXX: pre migration all hosts where added here as alias, when we combine host.domain we
                    *      miss some information, which is likely not a very good idea anyway.
                    */
                    $lhosts .= "{$ip}\t{$alias}\n";
                }
            }
        }
    }

    if (!empty($dnsmasqcfg['regdhcpstatic'])) {
        /* XXX: deprecate when ISC is moved to plugins? It doesn't make much sense to offer these registrations for KEA*/
        foreach (plugins_run('static_mapping', [null, true, legacy_interfaces_details()]) as $map) {
            foreach ($map as $host) {
                if (empty($host['hostname'])) {
                    /* cannot register without a hostname */
                    continue;
                }
                if (empty($host['domain'])) {
                    $host['domain'] = $config['system']['domain'];
                }
                if (!empty($host['ipaddr'])) {
                    $dhosts .= "{$host['ipaddr']}\t{$host['hostname']}.{$host['domain']} {$host['hostname']}\n";
                } else {
                    $dhosts .= "{$host['ipaddrv6']}\t{$host['hostname']}.{$host['domain']} {$host['hostname']}\n";
                }
            }
        }
    }

    file_safe(
        '/var/etc/dnsmasq-hosts',
        !empty($dnsmasqcfg['dhcpfirst']) ? $dhosts . $lhosts : $lhosts . $dhosts
    );

    file_safe('/var/etc/dnsmasq-leases'); /* clear file for later use */
}
