<?php

/*
 * Copyright (C) 2018 Fabian Franz
 * Copyright (C) 2015-2024 Franco Fichtner <franco@opnsense.org>
 * Copyright (C) 2015 Manuel Faux <mfaux@conf.at>
 * Copyright (C) 2014 Warren Baker <warren@decoy.co.za>
 * Copyright (C) 2004-2007 Scott Ullrich <sullrich@gmail.com>
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

function unbound_enabled()
{
    $mdl = new \OPNsense\Unbound\Unbound();
    return !empty((string)$mdl->general->enabled);
}

function unbound_configure()
{
    return [
        'bootup' => ['unbound_configure_do'],
        'dns' => ['unbound_configure_do'],
        'early' => ['unbound_cache_flush'],
        'local' => ['unbound_configure_do'],
        'newwanip' => ['unbound_configure_do:2'],
        'unbound_start' => ['unbound_configure_do'],
        'unbound_stop' => ['unbound_service_stop'],
    ];
}

function unbound_services()
{
    $services = [];

    if (!unbound_enabled()) {
        return $services;
    }

    $mdl = new \OPNsense\Unbound\Unbound();

    $pconfig = [];
    $pconfig['name'] = 'unbound';
    $pconfig['dns_ports'] = [(string)$mdl->general->port];
    $pconfig['description'] = gettext('Unbound DNS');
    $pconfig['php']['restart'] = ['unbound_configure_do'];
    $pconfig['php']['start'] = ['unbound_configure_do'];
    $pconfig['php']['stop'] = ['unbound_service_stop'];
    $pconfig['pidfile'] = '/var/run/unbound.pid';
    $services[] = $pconfig;

    return $services;
}

function unbound_xmlrpc_sync()
{
    $result = [];

    $result[] = [
        'section' => 'unbound,OPNsense.unboundplus',
        'description' => gettext('Unbound DNS'),
        'services' => ['unbound'],
        'id' => 'dnsresolver',
    ];

    return $result;
}

function unbound_optimization()
{
    $optimization = [];

    $numprocs = intval(get_single_sysctl('kern.smp.cpus'));
    $numprocs = $numprocs <= 0 ? 1 : $numprocs;
    $numslabs = pow(2, floor(log($numprocs, 2)) + 1);

    $optimization['number_threads'] = "num-threads: {$numprocs}";
    $optimization['msg_cache_slabs'] = "msg-cache-slabs: {$numslabs}";
    $optimization['rrset_cache_slabs'] = "rrset-cache-slabs: {$numslabs}";
    $optimization['infra_cache_slabs'] = "infra-cache-slabs: {$numslabs}";
    $optimization['key_cache_slabs'] = "key-cache-slabs: {$numslabs}";

    return $optimization;
}

function unbound_service_stop()
{
    $mdl = new \OPNsense\Unbound\Unbound();

    mwexec('/usr/local/bin/flock -E 0 -o /tmp/unbound_start.lock true');

    if (empty((string)$mdl->general->cacheflush)) {
        if (isvalidpid('/var/run/unbound.pid')) {
            configd_run('unbound cache dump');
        }
    } else {
        unbound_cache_flush();
    }

    killbypid('/var/run/unbound_logger.pid');
    killbypid('/var/run/unbound_dhcpd.pid');
    killbypid('/var/run/unbound.pid');

    mwexecf('/sbin/umount %s', '/var/unbound/dev', true);
    mwexecf('/sbin/umount %s', '/var/unbound/usr/local/lib/' . readlink('/usr/local/bin/python3'), true);
    mwexecf('/sbin/umount %s', '/var/unbound/lib', true);
}

function unbound_generate_config()
{
    global $config;
    $general = config_read_array('OPNsense', 'unboundplus', 'general');

    $pythonv = readlink('/usr/local/bin/python3');
    $python_dir = "/usr/local/lib/{$pythonv}";
    $chroot_python_dir = "/var/unbound{$python_dir}";

    $dirs = ['/data', '/dev', '/etc', '/lib', '/run', '/usr', '/var/db', '/var/run', $python_dir];

    foreach ($dirs as $dir) {
        mwexecf('/bin/mkdir -p %s', "/var/unbound{$dir}");
    }

    mwexecf('/sbin/mount -t devfs devfs %s', '/var/unbound/dev');
    mwexecf('/sbin/mount -r -t nullfs %s %s', [$python_dir, $chroot_python_dir]);
    mwexecf('/sbin/mount -r -t nullfs %s %s', ['/lib', '/var/unbound/lib']);

    $optimization = unbound_optimization();

    $module_config = 'python ';
    $anchor_file = '';
    $dns64_config = '';

    if (!empty($general['dns64'])) {
        if (!empty($general['dns64prefix'])) {
            $dns64_config .= "\ndns64-prefix: {$general['dns64prefix']}";
        }
        if (!empty($general['noarecords'])) {
            $module_config .= 'respip ';
            $dns64_config .= "\nresponse-ip: 0.0.0.0/0 redirect";
        }
        $module_config .= 'dns64 ';
    }
    if (!empty($general['dnssec'])) {
        $module_config .= 'validator iterator';
        $anchor_file = 'auto-trust-anchor-file: /var/unbound/root.key';
    } else {
        $module_config .= 'iterator';
    }

    $private_addr = "";
    if (!isset($config['system']['webgui']['nodnsrebindcheck'])) {
        $advanced = config_read_array('OPNsense', 'unboundplus', 'advanced');
        if (!empty($advanced) && !empty($advanced['privateaddress'])) {
            foreach (explode(',', $advanced['privateaddress']) as $address) {
                $private_addr .= "private-address: {$address}\n";
            }
        }
    }

    $bindints = '';
    if (!empty($general['active_interface'])) {
        $active_interfaces = explode(',', $general['active_interface']);
        array_unshift($active_interfaces, 'lo0');

        $addresses = [];

        foreach (interfaces_addresses($active_interfaces) as $tmpaddr => $info) {
            if ($info['name'] == 'lo0' && $info['family'] == 'inet' && $tmpaddr != '127.0.0.1') {
                /* allow other DNS services to bind to loopback aliases */
                continue;
            }

            if (!$info['bind']) {
                continue;
            }

            $addresses[] = $tmpaddr;
        }

        foreach ($addresses as $address) {
            $bindints .= "interface: $address\n";
        }
    } else {
        $bindints .= "interface: 0.0.0.0\n";
        $bindints .= "interface: ::\n";
        $bindints .= "interface-automatic: yes\n";
    }

    $outgoingints = '';
    $ifconfig_details = legacy_interfaces_details();
    if (!empty($general['outgoing_interface'])) {
        $outgoingints = "# Outgoing interfaces to be used\n";
        $outgoing_interfaces = explode(",", $general['outgoing_interface']);
        foreach ($outgoing_interfaces as $outif) {
            $outip = get_interface_ip($outif, $ifconfig_details);
            if (!empty($outip)) {
                $outgoingints .= "outgoing-interface: $outip\n";
            }
            $outip = get_interface_ipv6($outif, $ifconfig_details);
            if (!empty($outip)) {
                $outgoingints .= "outgoing-interface: $outip\n";
            }
        }
    }

    unbound_add_host_entries($ifconfig_details);

    $port = $general['port'];

    /* do not touch prefer-ip6 as it is defaulting to 'no' anyway */
    $do_ip6 = isset($config['system']['ipv6allow']) ? 'yes' : 'no';

    if (!empty($general['regdhcp'])) {
        $include_dhcpleases = 'include: /var/unbound/dhcpleases.conf';
        @touch('/var/unbound/dhcpleases.conf');
    } else {
        $include_dhcpleases = '';
    }

    $unbound_mdl = new \OPNsense\Unbound\Unbound();
    $unbound_enabled = (string)$unbound_mdl->forwarding->enabled;

    $forward_conf = '';
    $forward_local = '';
    $resolv_conf_root = '';
    if ($unbound_enabled) {
        $dnsservers = get_nameservers();

        if (!empty($dnsservers)) {
            $forward_conf .= <<<EOD
# Forwarding
forward-zone:
    name: "."

EOD;
            foreach ($dnsservers as $dnsserver) {
                if (strpos($dnsserver, '127.') === 0 || $dnsserver == '::1') {
                    $forward_local = "do-not-query-localhost: no\n";
                } else {
                    /* Generate a custom resolv.conf file for use by unbound-anchor.
                     * These servers all use port 53 so exclude localhost from being queried for bootstrapping
                     * in our custom resolv.conf file as Unbound doesn't exist yet.
                     */
                    $resolv_conf_root .= "nameserver $dnsserver\n";
                }
                $forward_conf .= "\tforward-addr: $dnsserver\n";
            }
        }
    }

    file_put_contents("/var/unbound/resolv.conf.root", $resolv_conf_root, LOCK_EX);

    $so_reuseport = empty(system_sysctl_get()['net.inet.rss.enabled']) ? 'yes' : 'no';

    $unboundconf = <<<EOD
##########################
# Unbound Configuration
##########################

##
# Server configuration
##
server:
chroot: /var/unbound
username: unbound
directory: /var/unbound
pidfile: /var/run/unbound.pid
root-hints: /var/unbound/root.hints
use-syslog: yes
port: {$port}
include: /var/unbound/advanced.conf
harden-referral-path: no
do-ip4: yes
do-ip6: {$do_ip6}
do-udp: yes
do-tcp: yes
do-daemonize: yes
so-reuseport: {$so_reuseport}
module-config: "{$module_config}"
{$optimization['number_threads']}
{$optimization['msg_cache_slabs']}
{$optimization['rrset_cache_slabs']}
{$optimization['infra_cache_slabs']}
{$optimization['key_cache_slabs']}
{$anchor_file}
{$forward_local}
{$dns64_config}

# Interface IP(s) to bind to
{$bindints}
{$outgoingints}

# Private networks for DNS Rebinding prevention (when enabled)
{$private_addr}

# Private domains (DNS Rebinding)
include: /var/unbound/private_domains.conf

# Static host entries
include: /var/unbound/host_entries.conf

# DHCP leases (if configured)
{$include_dhcpleases}

# Custom includes
include: /var/unbound/etc/*.conf

{$forward_conf}

python:
python-script: dnsbl_module.py

remote-control:
    control-enable: yes
    control-interface: 127.0.0.1
    control-port: 953
    server-key-file: /var/unbound/unbound_server.key
    server-cert-file: /var/unbound/unbound_server.pem
    control-key-file: /var/unbound/unbound_control.key
    control-cert-file: /var/unbound/unbound_control.pem

EOD;

    file_put_contents('/var/unbound/unbound.conf', $unboundconf);

    /* Unbound load tripping over this file is very strange so make it a very safe rewrite */
    $root_hints_tmp = tempnam('/var/unbound', 'root.hints.');
    copy('/usr/local/opnsense/data/unbound/root.min.hints', $root_hints_tmp);
    rename($root_hints_tmp, '/var/unbound/root.hints');

    configd_run('template reload OPNsense/Unbound/*');
}

function unbound_cache_flush()
{
    configd_run('unbound cache flush');
}

function unbound_match_interface($interface_map)
{
    $general = config_read_array('OPNsense', 'unboundplus', 'general');

    if (empty($interface_map)) {
        /* emulate non-interface reload */
        return true;
    }

    if (!empty($general['active_interface'])) {
        foreach (explode(',', $general['active_interface']) as $used) {
            if (in_array($used, $interface_map)) {
                return true;
            }
        }
    }

    if (!empty($general['outgoing_interface'])) {
        foreach (explode(',', $general['outgoing_interface']) as $used) {
            if (in_array($used, $interface_map)) {
                return true;
            }
        }
    }

    /*
     * We can ignore this request as we don't listen here
     * or always listen on :: / 0.0.0.0 so that a reload
     * is not necessary.
     */
    return false;
}

function unbound_configure_do($verbose = false, $interface_map = null)
{
    global $config;

    if (!plugins_argument_map($interface_map)) {
        return;
    }

    $mdl = new \OPNsense\Unbound\Unbound();

    /* try to avoid restarting, but make sure to let it start if it is not running */
    if (!unbound_match_interface($interface_map) && isvalidpid('/var/run/unbound.pid')) {
        return;
    }

    unbound_service_stop();

    if (!unbound_enabled()) {
        return;
    }

    service_log('Starting Unbound DNS...', $verbose);

    unbound_generate_config();

    $domain = '';
    if (!empty((string)$mdl->general->regdhcp)) {
        $domain = $config['system']['domain'];
        if (!empty((string)$mdl->general->regdhcpdomain)) {
            $domain = (string)$mdl->general->regdhcpdomain;
        }
    }

    if (!empty((string)$mdl->general->stats)) {
        @touch('/var/unbound/data/stats');
    } else {
        @unlink('/var/unbound/data/stats');
    }

    mwexecf_bg('/usr/local/bin/flock -n -E 0 -o /tmp/unbound_start.lock /usr/local/opnsense/scripts/unbound/start.sh %s', [$domain]);

    waitforpid('/var/run/unbound.pid', 10);

    service_log("done.\n", $verbose);
}

function unbound_add_host_entries($ifconfig_details)
{
    global $config;

    $general = config_read_array('OPNsense', 'unboundplus', 'general');

    $ptr_records = ['127.0.0.1', '::1'];

    openlog('unbound', LOG_DAEMON, LOG_LOCAL4);

    $local_zone_type = $general['local_zone_type'];

    $unbound_entries = "local-zone: \"{$config['system']['domain']}\" {$local_zone_type}\n";

    $unbound_entries .= "local-data-ptr: \"127.0.0.1 localhost\"\n";
    $unbound_entries .= "local-data: \"localhost A 127.0.0.1\"\n";
    $unbound_entries .= "local-data: \"localhost.{$config['system']['domain']} A 127.0.0.1\"\n";

    $unbound_entries .= "local-data-ptr: \"::1 localhost\"\n";
    $unbound_entries .= "local-data: \"localhost AAAA ::1\"\n";
    $unbound_entries .= "local-data: \"localhost.{$config['system']['domain']} AAAA ::1\"\n";

    if (!empty($general['active_interface'])) {
        $interfaces = explode(",", $general['active_interface']);
    } else {
        $interfaces = array_keys(get_configured_interface_with_descr());
    }

    if (empty($general['noregrecords'])) {
        foreach ($interfaces as $interface) {
            if ($interface == 'lo0' || substr($interface, 0, 4) == 'ovpn') {
                continue;
            }

            list ($laddr) = interfaces_primary_address($interface, $ifconfig_details);
            list ($laddr6) = interfaces_routed_address6($interface, $ifconfig_details);

            foreach (['4' => $laddr, '6' => $laddr6] as $ip_version => $addr) {
                if (empty($addr)) {
                    continue;
                }

                $domain = $config['system']['domain'];
                $dhcpd = $ip_version == '4' ? 'dhcpd' : 'dhcpd6';
                $record = $ip_version == '4' ? 'A' : 'AAAA';
                if (isset($config[$dhcpd][$interface]['enable']) && !empty($config[$dhcpd][$interface]['domain'])) {
                    $domain = $config[$dhcpd][$interface]['domain'];
                }
                if ($interface === get_primary_interface_from_list($interfaces)) {
                    $unbound_entries .= "local-data-ptr: \"{$addr} {$config['system']['hostname']}.{$domain}\"\n";
                    $ptr_records[] = $addr;
                }
                $unbound_entries .= "local-data: \"{$config['system']['hostname']}.{$domain} {$record} {$addr}\"\n";
                $unbound_entries .= "local-data: \"{$config['system']['hostname']} {$record} {$addr}\"\n";
            }

            if (empty($general['noreglladdr6'])) {
                if (!empty($lladdr6)) {
                    /* cannot embed scope */
                    $lladdr6 = explode('%', $lladdr6)[0];
                    $domain = $config['system']['domain'];
                    if (isset($config['dhcpdv6'][$interface]['enable']) && !empty($config['dhcpdv6'][$interface]['domain'])) {
                        $domain = $config['dhcpdv6'][$interface]['domain'];
                    }
                    $unbound_entries .= "local-data: \"{$config['system']['hostname']}.{$domain} AAAA {$lladdr6}\"\n";
                    $unbound_entries .= "local-data: \"{$config['system']['hostname']} AAAA {$lladdr6}\"\n";
                }
            }
        }
    }

    if (!empty($general['enable_wpad'])) {
        $webui_protocol = !empty($config['system']['webgui']['protocol']) ? $config['system']['webgui']['protocol'] : 'https';
        $webui_port = !empty($config['system']['webgui']['port']) ? $config['system']['webgui']['port'] : 443;
        // default domain
        $system_host_fqdn = $config['system']['hostname'];
        if (isset($config['system']['domain'])) {
            $system_host_fqdn .= '.' . $config['system']['domain'];
        }
        $unbound_entries .= "local-data: \"wpad.{$domain} IN CNAME {$system_host_fqdn}\"\n";
        $unbound_entries .= "local-data: \"wpad IN CNAME {$system_host_fqdn}\"\n";
        $unbound_entries .= "local-data: '{$domain} IN TXT \"service: wpad:{$webui_protocol}://{$system_host_fqdn}:{$webui_port}/wpad.dat\"'\n";
        // DHCP domains
        $tmp_known_domains = array($domain);
        foreach ($config['dhcpd'] as $dhcp_interface) {
            if (isset($dhcp_interface['domain']) && !empty($dhcp_interface['domain']) && !in_array($dhcp_interface['domain'], $tmp_known_domains)) {
                $unbound_entries .= "local-data: \"wpad.{$dhcp_interface['domain']} IN CNAME {$system_host_fqdn}\"\n";
                $unbound_entries .= "local-data: '{$dhcp_interface['domain']} IN TXT \"service: wpad:{$webui_protocol}://{$system_host_fqdn}:{$webui_port}/wpad.dat\"'\n";
                $tmp_known_domains[] = $dhcp_interface['domain'];
            }
        }
        unset($tmp_known_domains); // remove temporary variable
    }

    $unbound_mdl = new \OPNsense\Unbound\Unbound();
    $hosts = iterator_to_array($unbound_mdl->hosts->host->iterateItems());
    $aliases = iterator_to_array($unbound_mdl->aliases->alias->iterateItems());

    if (!empty($hosts)) {
        foreach ($hosts as $host) {
            if ($host->enabled == '1') {
                $tmp_aliases = array(array(
                    'domain' => (string)$host->domain,
                    'description' => (string)$host->description,
                    'hostname' => (string)$host->hostname
                ));

                if (!empty($aliases)) {
                    foreach ($aliases as $alias) {
                        if ($alias->enabled == '1' && $alias->host == $host->getAttribute('uuid')) {
                            $tmp_aliases[] = array(
                                'domain' => !empty((string)$alias->domain) ? $alias->domain : $tmp_aliases[0]['domain'],
                                'description' => !empty((string)$alias->description) ? $alias->description : $tmp_aliases[0]['description'],
                                'hostname' => !empty((string)$alias->hostname) ? $alias->hostname : $tmp_aliases[0]['hostname']
                            );
                        }
                    }
                }

                foreach ($tmp_aliases as $alias) {
                    $override_is_main = $alias === $tmp_aliases[0];

                    if ($alias['hostname'] != '') {
                        $alias['hostname'] .= '.';
                    }

                    switch ($host->rr) {
                        case 'A':
                        case 'AAAA':
                            /* Handle wildcard entries which have "*" as a hostname. Since we added a . above, we match on "*.". */
                            if ($alias['hostname'] == '*.') {
                                $unbound_entries .= "local-zone: \"{$alias['domain']}\" redirect\n";
                                $unbound_entries .= "local-data: \"{$alias['domain']} IN {$host->rr} {$host->server}\"\n";
                            } else {
                                if (($override_is_main || $tmp_aliases[0]['hostname'] === '*') && !in_array($host->server, $ptr_records, true)) {
                                    /* Only generate a PTR record for the non-alias override and only if the IP is not already associated with a PTR.
                                     * The exception to this is an alias whose parent uses a wildcard and as such does not specify a PTR record.
                                     */
                                    $unbound_entries .= "local-data-ptr: \"{$host->server} {$alias['hostname']}{$alias['domain']}\"\n";
                                    $ptr_records[] = $host->server;
                                } else {
                                    syslog(LOG_WARNING, 'PTR record already exists for ' . $alias['hostname'] . $alias['domain'] . '(' . $host->server . ')');
                                }
                                $unbound_entries .= "local-data: \"{$alias['hostname']}{$alias['domain']} IN {$host->rr} {$host->server}\"\n";
                            }
                            break;
                        case 'MX':
                            $unbound_entries .= "local-data: \"{$alias['hostname']}{$alias['domain']} IN MX {$host->mxprio} {$host->mx}\"\n";
                            break;
                    }

                    if (!empty($alias['description']) && !empty($general['txtsupport'])) {
                        $unbound_entries .= "local-data: '{$alias['hostname']}{$alias['domain']} TXT \"" . addslashes($alias['description']) . "\"'\n";
                    }
                }
            }
        }
    }

    if (!empty($general['regdhcpstatic'])) {
        foreach (plugins_run('static_mapping', [null, true, $ifconfig_details]) as $map) {
            foreach ($map as $host) {
                if (empty($host['hostname'])) {
                    /* cannot register without a hostname */
                    continue;
                }
                if (empty($host['domain'])) {
                    $host['domain'] = $config['system']['domain'];
                }
                if (isset($host['ipaddr'])) {
                    $unbound_entries .= "local-data-ptr: \"{$host['ipaddr']} {$host['hostname']}.{$host['domain']}\"\n";
                    $unbound_entries .= "local-data: \"{$host['hostname']}.{$host['domain']} IN A {$host['ipaddr']}\"\n";
                } else {
                    $unbound_entries .= "local-data-ptr: \"{$host['ipaddrv6']} {$host['hostname']}.{$host['domain']}\"\n";
                    $unbound_entries .= "local-data: \"{$host['hostname']}.{$host['domain']} IN AAAA {$host['ipaddrv6']}\"\n";
                }
                if (!empty($host['descr']) && !empty($general['txtsupport'])) {
                    $unbound_entries .= "local-data: '{$host['hostname']}.{$host['domain']} TXT \"" . addslashes($host['descr']) . "\"'\n";
                }
            }
        }
    }

    file_put_contents('/var/unbound/host_entries.conf', $unbound_entries);

    reopenlog();
}
