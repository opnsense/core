<?php

/*
    Copyright (C) 2015-2017 Franco Fichtner <franco@opnsense.org>
    Copyright (C) 2015 Manuel Faux <mfaux@conf.at>
    Copyright (C) 2014 Warren Baker <warren@decoy.co.za>
    Copyright (C) 2004-2007 Scott Ullrich <sullrich@gmail.com>
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

function unbound_enabled()
{
    global $config;

    return isset($config['unbound']['enable']);
}

function unbound_configure()
{
    return array(
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
        'section' => 'unbound',
        'id' => 'dnsresolver',
    );

    return $result;
}

function unbound_optimization()
{
    $optimization_settings = array();

    /*
     * Set the number of threads equal to the nearest power of 2 when counting the number of CPUs.
     * Use 1 to disable threading, if for some reason this sysctl fails.
     */
    $numprocs = pow(2, floor(log(intval(get_single_sysctl('kern.smp.cpus')), 2)));
    if ($numprocs > 0) {
        $optimization['number_threads'] = "num-threads: {$numprocs}";
    } else {
        $optimization['number_threads'] = "num-threads: 1";
    }

    // Slabs to help reduce lock contention.
    if ($numprocs > 4) {
        $optimization['msg_cache_slabs'] = "msg-cache-slabs: {$numprocs}";
        $optimization['rrset_cache_slabs'] = "rrset-cache-slabs: {$numprocs}";
        $optimization['infra_cache_slabs'] = "infra-cache-slabs: {$numprocs}";
        $optimization['key_cache_slabs'] = "key-cache-slabs: {$numprocs}";
    } else {
        $optimization['msg_cache_slabs'] = "msg-cache-slabs: 4";
        $optimization['rrset_cache_slabs'] = "rrset-cache-slabs: 4";
        $optimization['infra_cache_slabs'] = "infra-cache-slabs: 4";
        $optimization['key_cache_slabs'] = "key-cache-slabs: 4";
    }

    return $optimization;
}

function unbound_generate_config()
{
    global $config;

    // Setup optimization
    $optimization = unbound_optimization();

    // Setup DNSSEC support
    if (isset($config['unbound']['dnssec'])) {
        $module_config = 'validator iterator';
        $anchor_file = 'auto-trust-anchor-file: /var/unbound/root.key';
    } else {
        $module_config = 'iterator';
    }

    // Setup DNS Rebinding
    if (!isset($config['system']['webgui']['nodnsrebindcheck'])) {
        // Private-addresses for DNS Rebinding
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
private-address: 192.0.0.0/24    # IANA IPv4 special purpose net
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

    // Determine interfaces to run on
    $bindints = "";
    if (!empty($config['unbound']['active_interface'])) {
        $active_interfaces = explode(",", $config['unbound']['active_interface']);
        foreach ($active_interfaces as $ubif) {
            $intip = get_interface_ip($ubif);
            if (!empty($intip)) {
                $bindints .= "interface: $intip\n";
            }
            $intip = get_interface_ipv6($ubif);
            if (!empty($intip)) {
                $bindints .= "interface: $intip\n";
            }
        }
    } else {
        $bindints .= "interface: 0.0.0.0\n";
        $bindints .= "interface: ::0\n";
        $bindints .= "interface-automatic: yes\n";
    }

    // Determine interfaces to run on
    $outgoingints = "";
    if (!empty($config['unbound']['outgoing_interface'])) {
        $outgoingints = "# Outgoing interfaces to be used\n";
        $outgoing_interfaces = explode(",", $config['unbound']['outgoing_interface']);
        foreach ($outgoing_interfaces as $outif) {
            $outip = get_interface_ip($outif);
            if (!empty($outip)) {
                $outgoingints .= "outgoing-interface: $outip\n";
            }
            $outip = get_interface_ipv6($outif);
            if (!empty($outip)) {
                $outgoingints .= "outgoing-interface: $outip\n";
            }
        }
    }

    // Allow DNS Rebind for forwarded domains
    if ((isset($config['unbound']['domainoverrides']) && is_array($config['unbound']['domainoverrides'])) && !isset($config['system']['webgui']['nodnsrebindcheck'])) {
        $private_domains = "# Set private domains in case authoritative name server returns a Private IP address\n";
        $private_domains .= unbound_add_domain_overrides(true);
    }

    // Configure static Host entries
    unbound_add_host_entries();

    // Configure Domain Overrides
    unbound_add_domain_overrides();

    // Configure Unbound statistics
    $statistics = unbound_statistics();

    // Configure Unbound access-lists
    unbound_acls_config();

    // Add custom Unbound options
    $custom_options = '';
    if (!empty($config['unbound']['custom_options'])) {
        $custom_options = "# Unbound custom options\n{$config['unbound']['custom_options']}\n";
    }

    // Server configuration variables
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
    $msgcachesize = !empty($config['unbound']['msgcachesize']) ? $config['unbound']['msgcachesize'] : 4;
    $rrsetcachesize = $msgcachesize * 2;
    $dnssecstripped = !empty($config['unbound']['dnssecstripped']) ? 'yes' : 'no';
    $serveexpired = !empty($config['unbound']['serveexpired']) ? 'yes' : 'no';

    if (isset($config['unbound']['regdhcp'])) {
        // include dynamic leases
        @touch('/var/unbound/dhcpleases.conf');
        $include_dhcpleases = 'include: /var/unbound/dhcpleases.conf';
    } else {
        $include_dhcpleases = '';
    }

    // Set up forwarding if it configured
    if (isset($config['unbound']['forwarding'])) {
        $dnsservers = array();
        if (isset($config['system']['dnsallowoverride'])) {
            foreach (get_nameservers() as $nameserver) {
                $dnsservers[] = $nameserver;
            }
        } else {
            foreach (get_dns_servers() as $nameserver) {
                $dnsservers[] = $nameserver;
            }
        }

        if (!empty($dnsservers)) {
            $forward_conf .=<<<EOD
# Forwarding
forward-zone:
    name: "."

EOD;
            foreach ($dnsservers as $dnsserver) {
                $forward_conf .= "\tforward-addr: $dnsserver\n";
            }
        }
    } else {
        $forward_conf = "";
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
use-syslog: yes
port: {$port}
verbosity: {$verbosity}
hide-identity: {$hide_id}
hide-version: {$hide_version}
harden-referral-path: no
do-ip4: yes
do-ip6: yes
do-udp: yes
do-tcp: yes
do-daemonize: yes
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
prefetch: {$prefetch}
prefetch-key: {$prefetch_key}
# Statistics
{$statistics}
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

{$custom_options}

{$forward_conf}

###
# Remote Control Config
###
include: /var/unbound/remotecontrol.conf

EOD;

    file_put_contents('/var/unbound/unbound.conf', $unboundconf);

    return 0;
}

function unbound_remote_control_setup()
{
    if (!file_exists('/var/unbound/remotecontrol.conf') || !file_exists('/var/unbound/unbound_control.key')) {
        $remotcfg = <<<EOF
remote-control:
    control-enable: yes
    control-interface: 127.0.0.1
    control-port: 953
    server-key-file: /var/unbound/unbound_server.key
    server-cert-file: /var/unbound/unbound_server.pem
    control-key-file: /var/unbound/unbound_control.key
    control-cert-file: /var/unbound/unbound_control.pem

EOF;

        file_put_contents('/var/unbound/remotecontrol.conf', $remotcfg);

        mwexecf(
            'chroot -u unbound -g unbound / %s -d %s',
            array('/usr/local/sbin/unbound-control-setup', '/var/unbound')
        );
    }
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

    if (!unbound_interface($interface)) {
        return;
    }

    killbypid('/var/run/unbound_dhcpd.pid', 'TERM', true);
    killbypid('/var/run/unbound.pid', 'TERM', true);

    if (!isset($config['unbound']['enable'])) {
        return;
    }

    if ($verbose) {
        echo 'Starting Unbound DNS...';
        flush();
    }

    mwexecf(
        'chroot -u unbound -g unbound / %s -a %s',
        array('/usr/local/sbin/unbound-anchor', '/var/unbound/root.key'),
        true
    );

    unbound_remote_control_setup();
    unbound_generate_config();

    $domain = $config['system']['domain'];
    if (isset($config['unbound']['regdhcpdomain'])) {
        $domain = $config['unbound']['regdhcpdomain'];
    }
    killbypid('/var/run/unbound_dhcpd.pid', 'TERM', true);
    if (isset($config['unbound']['regdhcp'])) {
        mwexecf('/usr/local/opnsense/scripts/dns/unbound_dhcpd.py /domain %s', $domain);
    }
    mwexecf('/usr/local/sbin/unbound -c %s', '/var/unbound/unbound.conf');

    if ($verbose) {
        echo "done.\n";
    }
}

function unbound_add_domain_overrides($pvt = false)
{
    global $config;

    $domains = $config['unbound']['domainoverrides'];

    $sorted_domains = msort($domains, "domain");
    $result = array();
    foreach ($sorted_domains as $domain) {
        $domain_key = current($domain);
        if (!isset($result[$domain_key])) {
            $result[$domain_key] = array();
        }
        $result[$domain_key][] = $domain['ip'];
    }

    // Domain overrides that have multiple entries need multiple stub-addr: added
    $domain_entries = "";
    foreach ($result as $domain => $ips) {
        if ($pvt == true) {
            $domain_entries .= "private-domain: \"$domain\"\n";
            $domain_entries .= "domain-insecure: \"$domain\"\n";
            if (preg_match('/.+\.in-addr\.arpa\.?$/', $domain)) {
                $domain_entries .= "local-zone: \"$domain\" typetransparent\n";
            }
        } else {
            $domain_entries .= "stub-zone:\n";
            $domain_entries .= "\tname: \"$domain\"\n";
            foreach ($ips as $ip) {
                $domain_entries .= "\tstub-addr: $ip\n";
            }
            $domain_entries .= "\tstub-prime: no\n";
        }
    }

    if ($pvt == true) {
        return $domain_entries;
    } else {
        file_put_contents('/var/unbound/domainoverrides.conf', $domain_entries);
    }
}

function unbound_add_host_entries()
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
        $interfaces = get_configured_interface_list();
    }

    foreach ($interfaces as $interface) {
        if ($interface == 'lo0') {
            continue;
        }
        $if = get_real_interface($interface);
        if (!does_interface_exist($if)) {
            continue;
        }
        $laddr = find_interface_ip($if);
        if (is_ipaddrv4($laddr)) {
            $domain = $config['system']['domain'];
            if (isset($config['dhcpd'][$interface]['enable']) && !empty($config['dhcpd'][$interface]['domain'])) {
                $domain = $config['dhcpd'][$interface]['domain'];
            }
            $unbound_entries .= "local-data-ptr: \"{$laddr} {$config['system']['hostname']}.{$domain}\"\n";
            $unbound_entries .= "local-data: \"{$config['system']['hostname']}.{$domain} A {$laddr}\"\n";
            $unbound_entries .= "local-data: \"{$config['system']['hostname']} A {$laddr}\"\n";
        }
        $laddr6 = find_interface_ipv6($if);
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
            $lladdr6 = find_interface_ipv6_ll($if);
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

    /* Static Host entries */

    if (isset($config['unbound']['hosts'])) {
        $added_item = array();

        foreach ($config['unbound']['hosts'] as $host) {
            if ($host['host'] != "") {
                $host['host'] = $host['host'].".";
            }

            /* Backwards compatibility for records created before introducing RR types. */
            if (!isset($host['rr'])) {
                $host['rr'] = (is_ipaddrv6($host['ip'])) ? 'AAAA' : 'A';
            }

            switch ($host['rr']) {
                case 'A':
                case 'AAAA':
                    /* Handle wildcard entries which have "*" as a hostname. Since we added a . above, we match on "*.". */
                    if (trim($host['host']) == '*.') {
                        $unbound_entries .= "local-zone: \"{$host['domain']}\" redirect\n";
                        $unbound_entries .= "local-data: \"{$host['domain']} IN {$host['rr']} {$host['ip']}\"\n";
                    } else {
                        $unbound_entries .= "local-data-ptr: \"{$host['ip']} {$host['host']}{$host['domain']}\"\n";
                        $unbound_entries .= "local-data: \"{$host['host']}{$host['domain']} IN {$host['rr']} {$host['ip']}\"\n";
                    }
                    break;
                case 'MX':
                    $unbound_entries .= "local-data: \"{$host['host']}{$host['domain']} IN MX {$host['mxprio']} {$host['mx']}\"\n";
                    break;
            }

            if (!empty($host['descr']) && isset($config['unbound']['txtsupport'])) {
                $unbound_entries .= "local-data: '{$host['host']}{$host['domain']} TXT \"".addslashes($host['descr'])."\"'\n";
            }
        }
    }

    /* Static DHCP entries */

    if (isset($config['unbound']['regdhcpstatic']) && is_array($config['dhcpd'])) {
        foreach ($config['dhcpd'] as $dhcpif => $dhcpifconf) {
            if (isset($dhcpifconf['staticmap']) && isset($dhcpifconf['enable'])) {
                foreach ($dhcpifconf['staticmap'] as $host) {
                    if (!$host['ipaddr'] || !$host['hostname']) {
                        continue;
                    }

                    $domain = $config['system']['domain'];
                    if ($host['domain']) {
                        $domain = $host['domain'];
                    } elseif ($dhcpifconf['domain']) {
                        $domain = $dhcpifconf['domain'];
                    }

                    $unbound_entries .= "local-data-ptr: \"{$host['ipaddr']} {$host['hostname']}.{$domain}\"\n";
                    $unbound_entries .= "local-data: \"{$host['hostname']}.{$domain} IN A {$host['ipaddr']}\"\n";
                    if (!empty($host['descr']) && $unboundcfg['txtsupport'] == 'on') {
                        $unbound_entries .= "local-data: '{$host['hostname']}.{$domain} TXT \"".addslashes($host['descr'])."\"'\n";
                    }
                }
            }
        }
    }

    if (isset($config['unbound']['regdhcpstatic']) && is_array($config['dhcpdv6'])) {
        foreach ($config['dhcpdv6'] as $dhcpif => $dhcpifconf) {
            if (isset($dhcpifconf['staticmap']) && isset($dhcpifconf['enable'])) {
                foreach ($dhcpifconf['staticmap'] as $host) {
                    if (!$host['ipaddrv6'] || !$host['hostname']) {
                        continue;
                    }

                    $domain = $config['system']['domain'];
                    if ($host['domain']) {
                        $domain = $host['domain'];
                    } elseif ($dhcpifconf['domain']) {
                        $domain = $dhcpifconf['domain'];
                    }

                    $unbound_entries .= "local-data-ptr: \"{$host['ipaddrv6']} {$host['hostname']}.{$domain}\"\n";
                    $unbound_entries .= "local-data: \"{$host['hostname']}.{$domain} IN AAAA {$host['ipaddrv6']}\"\n";
                    if (!empty($host['descr']) && $unboundcfg['txtsupport'] == 'on') {
                        $unbound_entries .= "local-data: '{$host['hostname']}.{$domain} TXT \"".addslashes($host['descr'])."\"'\n";
                    }
                }
            }
        }
    }

    file_put_contents('/var/unbound/host_entries.conf', $unbound_entries);
}

// Generation of Unbound statistics
function unbound_statistics()
{
    global $config;

    if ($config['stats'] == "on") {
        $stats_interval = $config['unbound']['stats_interval'];
        $cumulative_stats = $config['cumulative_stats'];
        if ($config['extended_stats'] == "on") {
            $extended_stats = "yes";
        } else {
            $extended_stats = "no";
        }
    } else {
        $stats_interval = "0";
        $cumulative_stats = "no";
        $extended_stats = "no";
    }
    /* XXX To do - add RRD graphs */
    $stats = <<<EOF
# Unbound Statistics
statistics-interval: {$stats_interval}
extended-statistics: yes
statistics-cumulative: yes

EOF;

    return $stats;
}

function unbound_acls_config()
{
    global $config;

    $aclcfg = "access-control: 127.0.0.1/32 allow\n";
    $aclcfg .= "access-control: ::1 allow\n";
    // Add our networks for active interfaces including localhost
    if (!empty($config['unbound']['active_interface'])) {
        $active_interfaces = array_flip(explode(",", $config['unbound']['active_interface']));
    } else {
        $active_interfaces = get_configured_interface_with_descr();
    }

    $bindints = "";
    foreach ($active_interfaces as $ubif => $ifdesc) {
        $ifip = get_interface_ip($ubif);
        if (!empty($ifip)) {
            $subnet_bits = get_interface_subnet($ubif);
            $subnet_ip = gen_subnet($ifip, $subnet_bits);
            if (!empty($subnet_bits) && !empty($subnet_ip)) {
                $aclcfg .= "access-control: {$subnet_ip}/{$subnet_bits} allow\n";
            }
        }
        $ifip = get_interface_ipv6($ubif);
        if (!empty($ifip)) {
            $subnet_bits = get_interface_subnetv6($ubif);
            $subnet_ip = gen_subnetv6($ifip, $subnet_bits);
            if (!empty($subnet_bits) && !empty($subnet_ip)) {
                $aclcfg .= "access-control: {$subnet_ip}/{$subnet_bits} allow\n";
            }
        }
    }

    // Configure the custom ACLs
    if (isset($config['unbound']['acls'])) {
        foreach ($config['unbound']['acls'] as $unbound_acl) {
            $aclcfg .= "#{$unbound_acl['aclname']}\n";
            foreach ($unbound_acl['row'] as $network) {
                if ($unbound_acl['aclaction'] == "allow snoop") {
                    $unbound_acl['aclaction'] = "allow_snoop";
                }
                $aclcfg .= "access-control: {$network['acl_network']}/{$network['mask']} {$unbound_acl['aclaction']}\n";
            }
        }
    }

    // Write out Access list
    file_put_contents('/var/unbound/access_lists.conf', $aclcfg);
}

function unbound_hosts_generate()
{
    global $config;

    if (!isset($config['unbound']['enable'])) {
        return;
    }

    unbound_add_host_entries();

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
        'redirect' => 'redirect',
        'refuse' => 'refuse',
        'static' => 'static',
        'typetransparent' => 'typetransparent',
    );
}
