<?php

/*
 * Copyright (C) 2018 Fabian Franz
 * Copyright (C) 2015-2021 Franco Fichtner <franco@opnsense.org>
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
    global $config;

    return isset($config['unbound']['enable']);
}

function unbound_configure()
{
    return array(
        'bootup' => array('unbound_configure_do'),
        'dns' => array('unbound_configure_do'),
        'hosts' => array('unbound_hosts_generate:0'),
        'local' => array('unbound_configure_do'),
        'newwanip' => array('unbound_configure_do:2'),
    );
}

function unbound_services()
{
    $services = array();

    if (!unbound_enabled()) {
        return $services;
    }

    $pconfig = array();
    $pconfig['name'] = 'unbound';
    $pconfig['description'] = gettext('Unbound DNS');
    $pconfig['php']['restart'] = array('unbound_configure_do');
    $pconfig['php']['start'] = array('unbound_configure_do');
    $pconfig['pidfile'] = '/var/run/unbound.pid';
    $services[] = $pconfig;

    return $services;
}

function unbound_xmlrpc_sync()
{
    $result = array();

    $result[] = array(
        'description' => gettext('Unbound DNS'),
        'section' => 'unbound,OPNsense.unboundplus',
        'id' => 'dnsresolver',
        'services' => ["unbound"],
    );

    return $result;
}

function unbound_optimization()
{
    $optimization = array();

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

function unbound_generate_config()
{
    global $config;

    if (!unbound_enabled()) {
        return;
    }

    $dirs = array('/dev', '/etc', '/lib', '/run', '/usr', '/usr/local/sbin', '/var/db', '/var/run');

    foreach ($dirs as $dir) {
        mwexecf('/bin/mkdir -p %s', "/var/unbound{$dir}");
    }

    if (mwexecf('/sbin/mount -uw %s', '/var/unbound/dev', true)) {
        mwexecf('/sbin/mount -t devfs devfs %s', '/var/unbound/dev');
    }

    foreach (glob('/var/unbound/etc/*') as $file) {
        unlink($file);
    }

    $optimization = unbound_optimization();

    $dns64prefix = '';
    if (isset($config['unbound']['dns64'])) {
        $module_config = 'dns64 ';
        if (!empty($config['unbound']['dns64prefix'])) {
            $dns64prefix = "dns64-prefix: {$config['unbound']['dns64prefix']}";
        }
    }
    if (isset($config['unbound']['dnssec'])) {
        $module_config .= 'validator iterator';
        $anchor_file = 'auto-trust-anchor-file: /var/unbound/root.key';
    } else {
        $module_config .= 'iterator';
    }

    if (!isset($config['system']['webgui']['nodnsrebindcheck'])) {
        $private_addr = <<<EOF
# For DNS Rebinding prevention
#
# All these addresses are either private or should not be routable in the global IPv4 or IPv6 internet.
#
# IPv4 Addresses
#
private-address: 0.0.0.0/8       # Broadcast address
private-address: 10.0.0.0/8
private-address: 100.64.0.0/10
private-address: 127.0.0.0/8     # Loopback Localhost
private-address: 169.254.0.0/16
private-address: 172.16.0.0/12
private-address: 192.0.2.0/24    # Documentation network TEST-NET
private-address: 192.168.0.0/16
private-address: 198.18.0.0/15   # Used for testing inter-network communications
private-address: 198.51.100.0/24 # Documentation network TEST-NET-2
private-address: 203.0.113.0/24  # Documentation network TEST-NET-3
private-address: 233.252.0.0/24  # Documentation network MCAST-TEST-NET
#
# IPv6 Addresses
#
private-address: ::1/128         # Loopback Localhost
private-address: 2001:db8::/32   # Documentation network IPv6
private-address: fc00::/8        # Unique local address (ULA) part of "fc00::/7", not defined yet
private-address: fd00::/8        # Unique local address (ULA) part of "fc00::/7", "/48" prefix group
private-address: fe80::/10       # Link-local address (LLA)

EOF;
    }

    $bindints = '';
    if (!empty($config['unbound']['active_interface'])) {
        $active_interfaces = explode(',', $config['unbound']['active_interface']);
        $active_interfaces[] = 'lo0';
        $addresses = array();

        foreach (interfaces_addresses($active_interfaces) as $tmpaddr => $info) {
            if ($info['name'] == 'lo0' && $info['family'] == 'inet' && $tmpaddr != '127.0.0.1') {
                /* allow other DNS services to bind to loopback aliases */
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
    if (!empty($config['unbound']['outgoing_interface'])) {
        $outgoingints = "# Outgoing interfaces to be used\n";
        $outgoing_interfaces = explode(",", $config['unbound']['outgoing_interface']);
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

    /* allow DNS Rebind for forwarded domains */
    if (!empty($config['unbound']['domainoverrides'])) {
        $private_domains = "# Set private domains in case authoritative name server returns a Private IP address\n";
        $private_domains .= unbound_add_domain_overrides(true);
    }

    unbound_add_host_entries($ifconfig_details);
    unbound_add_domain_overrides();
    unbound_acls_config();

    $port = is_port($config['unbound']['port']) ? $config['unbound']['port'] : "53";
    $hide_id = !empty($config['unbound']['hideidentity']) ? "yes" : "no";
    $hide_version = !empty($config['unbound']['hideversion']) ? "yes" : "no";
    $prefetch = !empty($config['unbound']['prefetch']) ? "yes" : "no";
    $prefetch_key = !empty($config['unbound']['prefetchkey']) ? "yes" : "no";
    $outgoing_num_tcp = !empty($config['unbound']['outgoing_num_tcp']) ? $config['unbound']['outgoing_num_tcp'] : "10";
    $incoming_num_tcp = !empty($config['unbound']['incoming_num_tcp']) ? $config['unbound']['incoming_num_tcp'] : "10";
    $num_queries_per_thread = !empty($config['unbound']['num_queries_per_thread']) ? $config['unbound']['num_queries_per_thread'] : "4096";
    $outgoing_range = $num_queries_per_thread * 2;
    $jostle_timeout = !empty($config['unbound']['jostle_timeout']) ? $config['unbound']['jostle_timeout'] : "200";
    $cache_max_ttl = !empty($config['unbound']['cache_max_ttl']) ? $config['unbound']['cache_max_ttl'] : "86400";
    $cache_min_ttl = !empty($config['unbound']['cache_min_ttl']) ? $config['unbound']['cache_min_ttl'] : "0";
    $infra_host_ttl = !empty($config['unbound']['infra_host_ttl']) ? $config['unbound']['infra_host_ttl'] : "900";
    $infra_cache_numhosts = !empty($config['unbound']['infra_cache_numhosts']) ? $config['unbound']['infra_cache_numhosts'] : "10000";
    $unwanted_reply_threshold = !empty($config['unbound']['unwanted_reply_threshold']) && is_numeric($config['unbound']['unwanted_reply_threshold']) ? $config['unbound']['unwanted_reply_threshold'] : "0";
    $verbosity = isset($config['unbound']['log_verbosity']) ? $config['unbound']['log_verbosity'] : 1;
    $extended_statistics = !empty($config['unbound']['extended_statistics']) ? 'yes' : 'no';
    $log_queries = !empty($config['unbound']['log_queries']) ? 'yes' : 'no';
    $msgcachesize = !empty($config['unbound']['msgcachesize']) ? $config['unbound']['msgcachesize'] : 4;
    $rrsetcachesize = $msgcachesize * 2;
    $dnssecstripped = !empty($config['unbound']['dnssecstripped']) ? 'yes' : 'no';
    $serveexpired = !empty($config['unbound']['serveexpired']) ? 'yes' : 'no';

    /* do not touch prefer-ip6 as it is defaulting to 'no' anyway */
    $do_ip6 = isset($config['system']['ipv6allow']) ? 'yes' : 'no';

    if (isset($config['unbound']['regdhcp'])) {
        $include_dhcpleases = 'include: /var/unbound/dhcpleases.conf';
        @touch('/var/unbound/dhcpleases.conf');
    } else {
        $include_dhcpleases = '';
    }

    $forward_conf = '';
    if (isset($config['unbound']['forwarding'])) {
        $dnsservers = array();

        if (isset($config['system']['dnsallowoverride'])) {
            foreach (get_nameservers() as $nameserver) {
                $dnsservers[] = $nameserver;
            }
        }

        if (!empty($config['system']['dnsserver'][0])) {
            foreach ($config['system']['dnsserver'] as $nameserver) {
                $dnsservers[] = $nameserver;
            }
        }

        if (!empty($dnsservers)) {
            $forward_conf .= <<<EOD
# Forwarding
forward-zone:
    name: "."

EOD;
            foreach ($dnsservers as $dnsserver) {
                $forward_conf .= "\tforward-addr: $dnsserver\n";
            }
        }
    }

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
verbosity: {$verbosity}
extended-statistics: {$extended_statistics}
log-queries: {$log_queries}
hide-identity: {$hide_id}
hide-version: {$hide_version}
harden-referral-path: no
do-ip4: yes
do-ip6: {$do_ip6}
do-udp: yes
do-tcp: yes
do-daemonize: yes
so-reuseport: yes
module-config: "{$module_config}"
cache-max-ttl: {$cache_max_ttl}
cache-min-ttl: {$cache_min_ttl}
harden-dnssec-stripped: {$dnssecstripped}
serve-expired: {$serveexpired}
outgoing-num-tcp: {$outgoing_num_tcp}
incoming-num-tcp: {$incoming_num_tcp}
num-queries-per-thread: {$num_queries_per_thread}
outgoing-range: {$outgoing_range}
infra-host-ttl: {$infra_host_ttl}
infra-cache-numhosts: {$infra_cache_numhosts}
unwanted-reply-threshold: {$unwanted_reply_threshold}
jostle-timeout: {$jostle_timeout}
msg-cache-size: {$msgcachesize}m
rrset-cache-size: {$rrsetcachesize}m
{$optimization['number_threads']}
{$optimization['msg_cache_slabs']}
{$optimization['rrset_cache_slabs']}
{$optimization['infra_cache_slabs']}
{$optimization['key_cache_slabs']}
{$optimization['so_rcvbuf']}
{$anchor_file}
{$dns64prefix}
prefetch: {$prefetch}
prefetch-key: {$prefetch_key}

# Interface IP(s) to bind to
{$bindints}
{$outgoingints}

# DNS Rebinding
{$private_addr}
{$private_domains}

# Access lists
include: /var/unbound/access_lists.conf

# Static host entries
include: /var/unbound/host_entries.conf

# DHCP leases (if configured)
{$include_dhcpleases}

# Domain overrides
include: /var/unbound/domainoverrides.conf

# Custom includes
include: /var/unbound/etc/*.conf

{$forward_conf}

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
    configd_run('template reload OPNsense/Unbound/*');
    mwexecf('cp /usr/local/etc/unbound.opnsense.d/*.conf /var/unbound/etc/');
    mwexecf('/usr/sbin/chown -R unbound:unbound %s', '/var/unbound');
}

function unbound_interface($interface)
{
    global $config;

    if (empty($interface)) {
        /* emulate non-interface reload */
        return true;
    }

    if (!empty($config['unbound']['active_interface'])) {
        foreach (explode(',', $config['unbound']['active_interface']) as $used) {
            if ($used == $interface) {
                return true;
            }
        }
    }

    if (!empty($config['unbound']['outgoing_interface'])) {
        foreach (explode(',', $config['unbound']['outgoing_interface']) as $used) {
            if ($used == $interface) {
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

function unbound_configure_do($verbose = false, $interface = '')
{
    global $config;

    unbound_generate_config();

    if (!unbound_interface($interface) && isvalidpid('/var/run/unbound.pid')) {
        return;
    }

    //configd_run('unbound cache dump');

    killbypid('/var/run/unbound_dhcpd.pid', 'TERM', true);
    killbypid('/var/run/unbound.pid', 'TERM', true);

    if (!unbound_enabled()) {
        return;
    }

    if ($verbose) {
        echo 'Starting Unbound DNS...';
        flush();
    }

    configd_run("unbound start", true);

    if (isset($config['unbound']['regdhcp'])) {
        $domain = $config['system']['domain'];
        if (isset($config['unbound']['regdhcpdomain'])) {
            $domain = $config['unbound']['regdhcpdomain'];
        }
        mwexecf('/usr/local/opnsense/scripts/dns/unbound_dhcpd.py --domain %s', $domain);
    }

    //configd_run('unbound cache load');

    if ($verbose) {
        echo "done.\n";
    }
}

function unbound_add_domain_overrides($pvt = false)
{
    global $config;

    $domains = config_read_array('unbound', 'domainoverrides');

    usort($domains, function ($a, $b) {
        return strcasecmp($a['domain'], $b['domain']);
    });

    $result = array();

    foreach ($domains as $domain) {
        $domain_key = current($domain);
        if (!isset($result[$domain_key])) {
            $result[$domain_key] = array();
        }
        $result[$domain_key][] = $domain['ip'];
    }

    $domain_entries = '';
    foreach ($result as $domain => $ips) {
        if ($pvt == true) {
            $domain_entries .= "domain-insecure: \"$domain\"\n";
            if (preg_match('/.+\.(in-addr|ip6)\.arpa\.?$/', $domain)) {
                $domain_entries .= "local-zone: \"$domain\" typetransparent\n";
            } elseif (!isset($config['system']['webgui']['nodnsrebindcheck'])) {
                $domain_entries .= "private-domain: \"$domain\"\n";
            }
        } else {
            $domain_entries .= "forward-zone:\n";
            $domain_entries .= "\tname: \"$domain\"\n";
            foreach ($ips as $ip) {
                $domain_entries .= "\tforward-addr: $ip\n";
            }
        }
    }

    if ($pvt == true) {
        return $domain_entries;
    } else {
        file_put_contents('/var/unbound/domainoverrides.conf', $domain_entries);
    }
}

function unbound_add_host_entries($ifconfig_details = null)
{
    global $config;

    $local_zone_type = 'transparent';

    if (!empty($config['unbound']['local_zone_type'])) {
        $local_zone_type = $config['unbound']['local_zone_type'];
    }

    $unbound_entries = "local-zone: \"{$config['system']['domain']}\" {$local_zone_type}\n";

    $unbound_entries .= "local-data-ptr: \"127.0.0.1 localhost\"\n";
    $unbound_entries .= "local-data: \"localhost A 127.0.0.1\"\n";
    $unbound_entries .= "local-data: \"localhost.{$config['system']['domain']} A 127.0.0.1\"\n";

    $unbound_entries .= "local-data-ptr: \"::1 localhost\"\n";
    $unbound_entries .= "local-data: \"localhost AAAA ::1\"\n";
    $unbound_entries .= "local-data: \"localhost.{$config['system']['domain']} AAAA ::1\"\n";

    if (!empty($config['unbound']['active_interface'])) {
        $interfaces = explode(",", $config['unbound']['active_interface']);
    } else {
        $interfaces = array_keys(get_configured_interface_with_descr());
    }
    foreach ($interfaces as $interface) {
        if ($interface == 'lo0' || substr($interface, 0, 4) == 'ovpn') {
            continue;
        }

        $realifv4 = get_real_interface($interface);
        $realifv6 = get_real_interface($interface, 'inet6');

        $laddr = find_interface_ip($realifv4, $ifconfig_details);
        if (is_ipaddrv4($laddr)) {
            $domain = $config['system']['domain'];
            if (isset($config['dhcpd'][$interface]['enable']) && !empty($config['dhcpd'][$interface]['domain'])) {
                $domain = $config['dhcpd'][$interface]['domain'];
            }
            $unbound_entries .= "local-data-ptr: \"{$laddr} {$config['system']['hostname']}.{$domain}\"\n";
            $unbound_entries .= "local-data: \"{$config['system']['hostname']}.{$domain} A {$laddr}\"\n";
            $unbound_entries .= "local-data: \"{$config['system']['hostname']} A {$laddr}\"\n";
        }
        $laddr6 = find_interface_ipv6($realifv6, $ifconfig_details);
        if (is_ipaddrv6($laddr6)) {
            $domain = $config['system']['domain'];
            if (isset($config['dhcpdv6'][$interface]['enable']) && !empty($config['dhcpdv6'][$interface]['domain'])) {
                $domain = $config['dhcpdv6'][$interface]['domain'];
            }
            $unbound_entries .= "local-data-ptr: \"{$laddr6} {$config['system']['hostname']}.{$domain}\"\n";
            $unbound_entries .= "local-data: \"{$config['system']['hostname']}.{$domain} AAAA {$laddr6}\"\n";
            $unbound_entries .= "local-data: \"{$config['system']['hostname']} AAAA {$laddr6}\"\n";
        }
        if (empty($config['unbound']['noreglladdr6'])) {
            $lladdr6 = find_interface_ipv6_ll($realifv6, $ifconfig_details);
            if (is_ipaddrv6($lladdr6)) {
                $domain = $config['system']['domain'];
                if (isset($config['dhcpdv6'][$interface]['enable']) && !empty($config['dhcpdv6'][$interface]['domain'])) {
                    $domain = $config['dhcpdv6'][$interface]['domain'];
                }
                $unbound_entries .= "local-data: \"{$config['system']['hostname']}.{$domain} AAAA {$lladdr6}\"\n";
                $unbound_entries .= "local-data: \"{$config['system']['hostname']} AAAA {$lladdr6}\"\n";
            }
        }
    }

    if (isset($config['unbound']['enable_wpad'])) {
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

    if (isset($config['unbound']['hosts'])) {
        foreach ($config['unbound']['hosts'] as $host) {
            $aliases = array(array(
                'domain' => $host['domain'],
                'descr' => $host['descr'],
                'host' => $host['host'],
            ));

            if (!empty($host['aliases']['item'])) {
                foreach ($host['aliases']['item'] as $alias) {
                    $aliases[] = array(
                        'domain' => !empty($alias['domain']) ? $alias['domain'] : $aliases[0]['domain'],
                        'descr' => !empty($alias['descr']) ? $alias['descr'] : $aliases[0]['descr'],
                        'host' => !empty($alias['host']) ? $alias['host'] : $aliases[0]['host'],
                    );
                }
            }

            /* Backwards compatibility for records created before introducing RR types. */
            if (!isset($host['rr'])) {
                $host['rr'] = (is_ipaddrv6($host['ip'])) ? 'AAAA' : 'A';
            }

            foreach ($aliases as $alias) {
                if ($alias['host'] != '') {
                    $alias['host'] .= '.';
                }

                switch ($host['rr']) {
                    case 'A':
                    case 'AAAA':
                        /* Handle wildcard entries which have "*" as a hostname. Since we added a . above, we match on "*.". */
                        if ($alias['host'] == '*.') {
                            $unbound_entries .= "local-zone: \"{$alias['domain']}\" redirect\n";
                            $unbound_entries .= "local-data: \"{$alias['domain']} IN {$host['rr']} {$host['ip']}\"\n";
                        } else {
                            $unbound_entries .= "local-data-ptr: \"{$host['ip']} {$alias['host']}{$alias['domain']}\"\n";
                            $unbound_entries .= "local-data: \"{$alias['host']}{$alias['domain']} IN {$host['rr']} {$host['ip']}\"\n";
                        }
                        break;
                    case 'MX':
                        $unbound_entries .= "local-data: \"{$alias['host']}{$alias['domain']} IN MX {$host['mxprio']} {$host['mx']}\"\n";
                        break;
                }

                if (!empty($alias['descr']) && isset($config['unbound']['txtsupport'])) {
                    $unbound_entries .= "local-data: '{$alias['host']}{$alias['domain']} TXT \"" . addslashes($alias['descr']) . "\"'\n";
                }
            }
        }
    }

    if (isset($config['unbound']['regdhcpstatic'])) {
        require_once 'plugins.inc.d/dhcpd.inc';  /* XXX */

        foreach (dhcpd_staticmap($config['system']['domain'], $ifconfig_details) as $host) {
            if (empty($host['hostname'])) {
                /* cannot register without a hostname */
                continue;
            }
            if (isset($host['ipaddr'])) {
                $unbound_entries .= "local-data-ptr: \"{$host['ipaddr']} {$host['hostname']}.{$host['domain']}\"\n";
                $unbound_entries .= "local-data: \"{$host['hostname']}.{$host['domain']} IN A {$host['ipaddr']}\"\n";
            } else {
                $unbound_entries .= "local-data-ptr: \"{$host['ipaddrv6']} {$host['hostname']}.{$host['domain']}\"\n";
                $unbound_entries .= "local-data: \"{$host['hostname']}.{$host['domain']} IN AAAA {$host['ipaddrv6']}\"\n";
            }
            if (!empty($host['descr']) && $unboundcfg['txtsupport'] == 'on') {
                $unbound_entries .= "local-data: '{$host['hostname']}.{$host['domain']} TXT \"" . addslashes($host['descr']) . "\"'\n";
            }
        }
    }

    file_put_contents('/var/unbound/host_entries.conf', $unbound_entries);
}

function unbound_acls_subnets()
{
    global $config;

    $any = true;

    if (!empty($config['unbound']['active_interface'])) {
        $active_interfaces = array_flip(explode(',', $config['unbound']['active_interface']));
        $any = false;
    } else {
        $active_interfaces = get_configured_interface_with_descr();
    }

    /* in case of OpenVPN interface we need to correct the subnet */
    foreach (array('server', 'client') as $mode) {
        foreach (config_read_array('openvpn', "openvpn-{$mode}") as $id => $setting) {
            $ovpn = 'ovpn' . substr($mode, 0, 1) . $setting['vpnid'];
            if (!$any && !array_key_exists($ovpn, $active_interfaces)) {
                continue;
            }
            $active_interfaces[$ovpn] = [];
            if (!empty($setting['tunnel_network'])) {
                $active_interfaces[$ovpn]['net4'] = explode('/', $setting['tunnel_network'])[1];
            }
            if (!empty($setting['tunnel_networkv6'])) {
                $active_interfaces[$ovpn]['net6'] = explode('/', $setting['tunnel_networkv6'])[1];
            }
        }
    }

    /* add our networks for active interfaces including localhost */
    $subnets = array('127.0.0.1/8', '::1/64');

    foreach (interfaces_addresses(array_keys($active_interfaces), true) as $subnet => $info) {
        if (!empty($active_interfaces[$info['name']]['net4']) && is_subnetv4($subnet)) {
            $subnet = explode('/', $subnet)[0] . '/' . $active_interfaces[$info['name']]['net4'];
        } elseif (!empty($active_interfaces[$info['name']]['net6']) && is_subnetv6($subnet) && !$info['scope']) {
            $subnet = explode('/', $subnet)[0] . '/' . $active_interfaces[$info['name']]['net6'];
        }
        $subnets[] = $subnet;
    }

    return array_unique($subnets);
}

function unbound_acls_config()
{
    global $config;

    $subnets = unbound_acls_subnets();
    $aclcfg = '';

    foreach ($subnets as $subnet) {
        $aclcfg .= "access-control: {$subnet} allow\n";
    }

    // Configure the custom ACLs
    if (isset($config['unbound']['acls'])) {
        foreach ($config['unbound']['acls'] as $unbound_acl) {
            $aclcfg .= "#{$unbound_acl['aclname']}\n";
            foreach ($unbound_acl['row'] as $network) {
                if ($unbound_acl['aclaction'] == "allow snoop") {
                    $unbound_acl['aclaction'] = "allow_snoop";
                } elseif ($unbound_acl['aclaction'] == "deny nonlocal") {
                        $unbound_acl['aclaction'] = "deny_non_local";
                } elseif ($unbound_acl['aclaction'] == "refuse nonlocal") {
                        $unbound_acl['aclaction'] = "refuse_non_local";
                }
                $aclcfg .= "access-control: {$network['acl_network']}/{$network['mask']} {$unbound_acl['aclaction']}\n";
            }
        }
    }

    file_put_contents('/var/unbound/access_lists.conf', $aclcfg);
}

function unbound_hosts_generate()
{
    if (!unbound_enabled()) {
        return;
    }

    $ifconfig_details = legacy_interfaces_details();
    unbound_add_host_entries($ifconfig_details);

    killbypid('/var/run/unbound.pid', 'HUP');
}

function unbound_local_zone_types()
{
    return array(
        '' => 'transparent',
        'always_nxdomain' => 'always_nxdomain',
        'always_refuse' => 'always_refuse',
        'always_transparent' => 'always_transparent',
        'deny' => 'deny',
        'inform' => 'inform',
        'inform_deny' => 'inform_deny',
        'nodefault' => 'nodefault',
        # requires more plumbing:
        #'redirect' => 'redirect',
        'refuse' => 'refuse',
        'static' => 'static',
        'typetransparent' => 'typetransparent',
    );
}
