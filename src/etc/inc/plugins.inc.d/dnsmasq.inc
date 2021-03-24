<?php

/*
 * Copyright (C) 2014-2021 Franco Fichtner <franco@opnsense.org>
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
    return array(
        'dns' => array('dnsmasq_configure_do'),
        'hosts' => array('dnsmasq_hosts_generate:0'),
        'local' => array('dnsmasq_configure_do'),
    );
}

function dnsmasq_services()
{
    $services = array();

    if (!dnsmasq_enabled()) {
        return $services;
    }

    $pconfig = array();
    $pconfig['name'] = 'dnsmasq';
    $pconfig['description'] = gettext('Dnsmasq DNS');
    $pconfig['php']['restart'] = array('dnsmasq_configure_do');
    $pconfig['php']['start'] = array('dnsmasq_configure_do');
    $pconfig['pidfile'] = '/var/run/dnsmasq.pid';
    $services[] = $pconfig;

    return $services;
}

function dnsmasq_syslog()
{
    $logfacilities = array();

    $logfacilities['dnsmasq'] = array('facility' => array('dnsmasq'));

    return $logfacilities;
}

function dnsmasq_xmlrpc_sync()
{
    $result = array();

    $result[] = array(
        'description' => gettext('Dnsmasq DNS'),
        'section' => 'dnsmasq',
        'id' => 'dnsforwarder',
        'services' => ["dnsmasq"],
    );

    return $result;
}

function dnsmasq_configure_do($verbose = false)
{
    global $config;

    _dnsmasq_dhcpleases_stop();

    killbypid('/var/run/dnsmasq.pid', 'TERM', true);

    if (!dnsmasq_enabled()) {
        return;
    }

    if ($verbose) {
        echo 'Starting Dnsmasq DNS...';
        flush();
    }

    $args = '';
    if (!isset($config['system']['webgui']['nodnsrebindcheck'])) {
        $args .= '--rebind-localhost-ok --stop-dns-rebind';
    }

    $args .= ' -H /var/etc/dnsmasq-hosts ';

    /* Setup listen port, if non-default */
    if (isset($config['dnsmasq']['port']) && is_port($config['dnsmasq']['port'])) {
        $args .= " --port={$config['dnsmasq']['port']} ";
    }

    if (!empty($config['dnsmasq']['interface'])) {
        $interfaces = explode(',', $config['dnsmasq']['interface']);
        $interfaces[] = 'lo0';
        $addresses = array();

        foreach (interfaces_addresses($interfaces) as $tmpaddr => $info) {
            if ($info['name'] == 'lo0' && $info['family'] == 'inet' && $tmpaddr != '127.0.0.1') {
                /* allow other DNS services to bind to loopback aliases */
                continue;
            }

            if ($info['scope']) {
                /* link-local does not seem to be supported */
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
    }

    if (isset($config['dnsmasq']['domain_needed'])) {
        $args .= ' --domain-needed ';
    }

    if (isset($config['dnsmasq']['dnssec'])) {
        $args .= ' --dnssec ';
        $args .= ' --trust-anchor=.,19036,8,2,49AAC11D7B6F6446702E54A1607371607A1A41855200FD2CE1CDDE32F24E8FB5 ';
        $args .= ' --trust-anchor=.,20326,8,2,E06D44B80B8F1D39A95C0B0D7C65D08458E880409BBC683457104237C7F8EC8D ';
    }

    if (isset($config['dnsmasq']['log_queries'])) {
        $args .= ' --log-queries=extra ';
    }

    $args .= ' --dns-forward-max=5000 ';
    $args .= ' --cache-size=10000 ';
    $args .= ' --local-ttl=1 ';
    $args .= ' --conf-dir=/usr/local/etc/dnsmasq.conf.d,\*.conf ';

    _dnsmasq_add_host_entries();

    mwexec("/usr/local/sbin/dnsmasq --all-servers {$args}");

    _dnsmasq_dhcpleases_start();

    if ($verbose) {
        echo "done.\n";
    }
}

function _dnsmasq_add_host_entries()
{
    global $config;

    $dnsmasqcfg = $config['dnsmasq'];
    $lhosts = '';
    $dhosts = '';

    if (!isset($dnsmasqcfg['hosts']) || !is_array($dnsmasqcfg['hosts'])) {
        $dnsmasqcfg['hosts'] = array();
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
        require_once 'plugins.inc.d/dhcpd.inc';  /* XXX */

        $domain = $config['system']['domain'];
        if (!empty($config['dnsmasq']['regdhcpdomain'])) {
            $domain = $config['dnsmasq']['regdhcpdomain'];
        }

        foreach (dhcpd_staticmap($domain, legacy_interfaces_details()) as $host) {
            if (isset($host['ipaddr'])) {
                $dhosts .= "{$host['ipaddr']}\t{$host['hostname']}.{$host['domain']} {$host['hostname']}\n";
            } else {
                $dhosts .= "{$host['ipaddrv6']}\t{$host['hostname']}.{$host['domain']} {$host['hostname']}\n";
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
}

function _dnsmasq_dhcpleases_start()
{
    global $config;

    require_once 'plugins.inc.d/dhcpd.inc';  /* XXX */
    $leases = dhcpd_dhcpv4_leasesfile();

    if (isset($config['dnsmasq']['regdhcp']) && file_exists($leases)) {
        $domain = $config['system']['domain'];
        if (isset($config['dnsmasq']['regdhcpdomain'])) {
            $domain = $config['dnsmasq']['regdhcpdomain'];
        }
        mwexecf(
            '/usr/local/sbin/dhcpleases -l %s -d %s -p %s -h %s',
            array($leases, $domain, '/var/run/dnsmasq.pid', '/var/etc/dnsmasq-hosts')
        );
    }
}

function _dnsmasq_dhcpleases_stop()
{
    killbypid('/var/run/dhcpleases.pid', 'TERM', true);
}

function dnsmasq_hosts_generate()
{
    _dnsmasq_dhcpleases_stop();

    if (!dnsmasq_enabled()) {
        return;
    }

    _dnsmasq_add_host_entries();
    _dnsmasq_dhcpleases_start();

    killbypid('/var/run/dnsmasq.pid', 'HUP');
}
