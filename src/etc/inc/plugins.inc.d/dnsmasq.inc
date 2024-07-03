<?php

/*
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

    return isset($config['dnsmasq']['enable']);
}

function dnsmasq_configure()
{
    return [
        'dns' => ['dnsmasq_configure_do'],
        'local' => ['dnsmasq_configure_do'],
        'newwanip' => ['dnsmasq_configure_do'],
    ];
}

function dnsmasq_services()
{
    global $config;

    $services = [];

    if (!dnsmasq_enabled()) {
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

function dnsmasq_configure_do($verbose = false)
{
    global $config;

    killbypid('/var/run/dnsmasq_dhcpd.pid');
    killbypid('/var/run/dnsmasq.pid');

    if (!dnsmasq_enabled()) {
        return;
    }

    service_log('Starting Dnsmasq DNS...', $verbose);

    $args = '';
    if (!isset($config['system']['webgui']['nodnsrebindcheck'])) {
        $args .= '--rebind-localhost-ok --stop-dns-rebind';
    }

    $args .= ' -H /var/etc/dnsmasq-hosts ';
    $args .= ' -H /var/etc/dnsmasq-leases ';

    /* Setup listen port, if non-default */
    if (isset($config['dnsmasq']['port']) && is_port($config['dnsmasq']['port'])) {
        $args .= " --port={$config['dnsmasq']['port']} ";
    }

    if (!empty($config['dnsmasq']['interface'])) {
        $interfaces = explode(',', $config['dnsmasq']['interface']);
        $interfaces[] = 'lo0';
        $addresses = [];

        foreach (interfaces_addresses($interfaces) as $tmpaddr => $info) {
            if ($info['name'] == 'lo0' && $info['family'] == 'inet' && $tmpaddr != '127.0.0.1') {
                /* allow other DNS services to bind to loopback aliases */
                continue;
            }

            if ($info['scope']) {
                /* link-local does not seem to be supported */
                continue;
            }

            if (!$info['bind']) {
                continue;
            }

            $addresses[] = $tmpaddr;
        }

        foreach ($addresses as $address) {
            $args .= " --listen-address={$address} ";
        }

        if (!empty($addresses) && isset($config['dnsmasq']['strictbind'])) {
            $args .= ' --bind-interfaces ';
        }
    }

    if (isset($config['dnsmasq']['no_private_reverse'])) {
        $args .= ' --bogus-priv ';
    }

    foreach (config_read_array('dnsmasq', 'domainoverrides') as $override) {
        if ($override['ip'] == '!') {
            $override['ip'] = '';
        }

        $args .= ' --server=' . escapeshellarg('/' . $override['domain'] . '/' . $override['ip']);

        if (!isset($config['system']['webgui']['nodnsrebindcheck'])) {
            $args .= ' --rebind-domain-ok=' . escapeshellarg('/' . $override['domain'] . '/') . ' ';
        }
    }

    if (isset($config['dnsmasq']['strict_order'])) {
        $args .= ' --strict-order ';
    } else {
        $args .= ' --all-servers ';
    }

    if (isset($config['dnsmasq']['domain_needed'])) {
        $args .= ' --domain-needed ';
    }

    if (isset($config['dnsmasq']['dnssec'])) {
        $args .= ' --dnssec ';
        $args .= ' --trust-anchor=.,20326,8,2,E06D44B80B8F1D39A95C0B0D7C65D08458E880409BBC683457104237C7F8EC8D ';
    }

    if (isset($config['dnsmasq']['log_queries'])) {
        $args .= ' --log-queries=extra ';
    }

    if (isset($config['dnsmasq']['no_hosts'])) {
        $args .= ' --no-hosts ';
    }

    if (isset($config['dnsmasq']['dns_forward_max'])) {
        $args .= " --dns-forward-max={$config['dnsmasq']['dns_forward_max']} ";
    } else {
        $args .= ' --dns-forward-max=5000 ';
    }

    if (isset($config['dnsmasq']['cache_size'])) {
        $args .= " --cache-size={$config['dnsmasq']['cache_size']} ";
    } else {
        $args .= ' --cache-size=10000 ';
    }

    if (isset($config['dnsmasq']['local_ttl'])) {
        $args .= " --local-ttl={$config['dnsmasq']['local_ttl']} ";
    } else {
        $args .= ' --local-ttl=1 ';
    }

    $args .= ' --conf-dir=/usr/local/etc/dnsmasq.conf.d,\*.conf ';

    _dnsmasq_add_host_entries();

    mwexec("/usr/local/sbin/dnsmasq {$args}");

    if (isset($config['dnsmasq']['regdhcp'])) {
        $domain = $config['system']['domain'];
        if (isset($config['dnsmasq']['regdhcpdomain'])) {
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
        if ($host['host']) {
            $lhosts .= "{$host['ip']}\t{$host['host']}.{$host['domain']} {$host['host']}\n";
        } else {
            $lhosts .= "{$host['ip']}\t{$host['domain']}\n";
        }
        if (!isset($host['aliases']) || !is_array($host['aliases']) || !is_array($host['aliases']['item'])) {
            continue;
        }
        foreach ($host['aliases']['item'] as $alias) {
            if ($alias['host']) {
                $lhosts .= "{$host['ip']}\t{$alias['host']}.{$alias['domain']} {$alias['host']}\n";
            } else {
                $lhosts .= "{$host['ip']}\t{$alias['domain']}\n";
            }
        }
    }

    if (isset($dnsmasqcfg['regdhcpstatic'])) {
        foreach (plugins_run('static_mapping', [null, true, legacy_interfaces_details()]) as $map) {
            foreach ($map as $host) {
                if (empty($host['hostname'])) {
                    /* cannot register without a hostname */
                    continue;
                }
                if (empty($host['domain'])) {
                    $host['domain'] = $config['system']['domain'];
                }
                if (isset($host['ipaddr'])) {
                    $dhosts .= "{$host['ipaddr']}\t{$host['hostname']}.{$host['domain']} {$host['hostname']}\n";
                } else {
                    $dhosts .= "{$host['ipaddrv6']}\t{$host['hostname']}.{$host['domain']} {$host['hostname']}\n";
                }
            }
        }
    }

    if (isset($dnsmasqcfg['dhcpfirst'])) {
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
