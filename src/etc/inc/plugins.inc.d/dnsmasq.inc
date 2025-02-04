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
    global $config;

    $services = [];

    if (!dnsmasq_enabled() && !isvalidpid('/var/run/dnsmasq.pid')) {
        return $services;
    }

    $pconfig = [];
    $pconfig['name'] = 'dnsmasq';
    $pconfig['dns_ports'] = [$config['dnsmasq']['port'] ?? '53'];
    $pconfig['description'] = gettext('Dnsmasq DNS');
    $pconfig['php']['restart'] = ['dnsmasq_configure_do'];
    $pconfig['php']['start'] = ['dnsmasq_configure_do'];
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
        'description' => gettext('Dnsmasq DNS'),
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
    if (!$mdl->enable->isEmpty() && !$mdl->dhcp_default_fw_rules->isEmpty()) {
        $dhcp_ifs = $mdl->getDhcpInterfaces();
        if (empty($dhcp_ifs)) {
            return;
        }
        foreach ($fw->getInterfaceMapping() as $intf => $intfinfo) {
            if (in_array($intf, $dhcp_ifs)) {
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
            }
        }
    }
}

function dnsmasq_configure_do($verbose = false)
{
    global $config;

    killbypid('/var/run/dnsmasq_dhcpd.pid');
    killbypid('/var/run/dnsmasq.pid');

    if (!dnsmasq_enabled()) {
        return;
    }

    service_log('Starting Dnsmasq DNS...', $verbose);

    _dnsmasq_add_host_entries();

    mwexec("/usr/local/sbin/dnsmasq");

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
        if (!empty($host['host']) && empty($host['domain'])) {
            $lhosts .= "{$host['ip']}\t{$host['host']}\n";
        } elseif (!empty($host['host'])) {
            /* XXX: The question is if we do want "host" as a global alias */
            $lhosts .= "{$host['ip']}\t{$host['host']}.{$host['domain']} {$host['host']}\n";
        }
        if (!empty($host['aliases'])) {
            foreach (explode(",", $host['aliases']) as $alias) {
                /**
                 * XXX: pre migration all hosts where added here as alias, when we combine host.domain we
                 *      miss some information, which is likely not a very good idea anyway.
                 */
                $lhosts .= "{$host['ip']}\t{$alias}\n";
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

    if (!empty($dnsmasqcfg['dhcpfirst'])) {
        file_put_contents('/var/etc/dnsmasq-hosts', $dhosts . $lhosts);
    } else {
        file_put_contents('/var/etc/dnsmasq-hosts', $lhosts . $dhosts);
    }

    /*
     * Several reports indicated 600 permissions on this file.
     * The reason is currently unknown, but setting it to 644
     * as it should be brings the service back to life.
     */
    chmod('/var/etc/dnsmasq-hosts', 0644);

    @unlink('/var/etc/dnsmasq-leases');
    touch('/var/etc/dnsmasq-leases');
    chmod('/var/etc/dnsmasq-leases', 0644);
}
