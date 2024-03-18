<?php

/*
 * Copyright (C) 2014-2023 Franco Fichtner <franco@opnsense.org>
 * Copyright (C) 2010 Ermal Luçi
 * Copyright (C) 2005-2006 Colin Smith <ethethlay@gmail.com>
 * Copyright (C) 2004-2007 Scott Ullrich <sullrich@gmail.com>
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

require_once 'IPv6.inc';

/* XXX only two callers left, better remove this unreliable function */
function killbyname($procname, $sig = 'TERM', $waitforit = true)
{
    /* pgrep -n only kills the newest matching process */
    _killbypid(shell_safe('/bin/pgrep -anx %s', $procname), $sig, $waitforit, "name:$procname");
}

function killbypid($pid_or_file, $sig = 'TERM', $waitforit = true)
{
    $pid = $pid_or_file;

    if (strpos($pid_or_file, '/') !== false) {
        $pid = trim(@file_get_contents($pid_or_file) ?? '');
    }

    _killbypid($pid, $sig, $waitforit, "pid:$pid_or_file");
}

function _killbypid($pid, $sig, $waitforit, $caller)
{
    if (!is_numeric($pid) || $pid <= 1) {
        return;
    }

    mwexecf('/bin/kill -%s %s', [$sig, $pid], $caller);

    if (!$waitforit || $sig == 'HUP') {
        return;
    }

    while (mwexecf('/bin/kill -0 %s', $pid, true) == 0) {
        usleep(200 * 1000);
    }
}

function isvalidpid($pidfile)
{
    if (file_exists($pidfile)) {
        return mwexecf('/bin/pgrep -nF %s', $pidfile, true) == 0;
    }

    return false;
}

function waitforpid($pidfile, $timeout = -1)
{
    $msec = $timeout * 1000;

    while (!isvalidpid($pidfile)) {
        usleep(200 * 1000);
        if ($timeout != -1) {
            $msec -= 200;
            if ($msec < 0) {
                return 0;
            }
        }
    }

    return trim(file_get_contents($pidfile));
}

function is_process_running($process)
{
    exec('/bin/pgrep -anx ' . escapeshellarg($process), $output, $retval);

    return (intval($retval) == 0);
}

function service_by_filter($filter = [])
{
    return service_by_name('*', $filter);
}

function service_by_name($name, $filter = [])
{
    $services = plugins_services();

    foreach ($services as $service) {
        /* skip unless filter wildcard is set */
        if ($name != '*' && $service['name'] != $name) {
            continue;
        }
        if (!count($filter)) {
            /* force (mis)match if filter is empty (standard behaviour) */
            $filter['name'] = $name;
        }
        foreach ($filter as $key => $value) {
            if (
                isset($service[$key]) && ($service[$key] == $value ||
                (is_array($service[$key]) && in_array($value, $service[$key])))
            ) {
                return $service;
            }
        }
    }

    return [];
}

function service_status($service)
{
    if (!empty($service['nocheck'])) {
        return true;
    }

    if (isset($service['pidfile'])) {
        return isvalidpid($service['pidfile']);
    }

    return is_process_running($service['name']);
}

function service_message($service)
{
   /*
    * Emulate the following messages from rc system:
    *
    *     syslog_ng is running as pid 11645.
    *     syslog_ng is not running.
    */

    $pid = '';
    $status = false;

    if (!empty($service['nocheck'])) {
        /* do not expose pid / may not exist */
        $status = true;
    } elseif (isset($service['pidfile'])) {
        $status = isvalidpid($service['pidfile']);
        if ($status) {
            /* could be wrong pid but best effort only */
            $pid = ' as pid ' . trim(@file_get_contents($service['pidfile']) ?? '');
        }
    } else {
        $status = is_process_running($service['name']);
        if ($status) {
            /* could be wrong pid but best effort only */
            $pid = ' as pid ' . shell_safe('/bin/pgrep -anx %s', $service['name']);
        }
    }

    return sprintf('%s is %s.', service_name($service), $status ? 'running' . $pid : 'not running');
}

function service_name($service)
{
    return $service['name'] . (array_key_exists('id', $service) ? "[{$service['id']}]" : '');
}

function service_control_get($name, $extras)
{
    $filter = [];

    if (array_key_exists('id', $extras) && $extras['id'] !== '') {
        $filter['id'] = $extras['id'];
    }

    $service = service_by_name($name, $filter);
    if (empty($service)) {
        $name .= isset($filter['id']) ? "[{$filter['id']}]" : '';
        return sprintf(gettext("Could not find unknown service `%s'"), htmlspecialchars($name));
    }

    return $service;
}

function service_control_start($name, $extras)
{
    $service = service_control_get($name, $extras);
    if (!is_array($service)) {
        return $service;
    }

    if (isset($service['configd']['start'])) {
        foreach ($service['configd']['start'] as $cmd) {
            configd_run($cmd);
        }
    } elseif (isset($service['php']['start'])) {
        foreach ($service['php']['start'] as $cmd) {
            $params = [];
            if (isset($service['php']['args'])) {
                foreach ($service['php']['args'] as $param) {
                    $params[] = $service[$param];
                }
            }
            call_user_func_array($cmd, $params);
        }
    } elseif (isset($service['mwexec']['start'])) {
        foreach ($service['mwexec']['start'] as $cmd) {
            mwexec($cmd);
        }
    } else {
        return sprintf(gettext("Could not start service `%s'"), htmlspecialchars(service_name($service)));
    }

    return sprintf(gettext("Service `%s' has been started."), htmlspecialchars(service_name($service)));
}

function service_control_stop($name, $extras)
{
    $service = service_control_get($name, $extras);
    if (!is_array($service)) {
        return $service;
    }

    if (isset($service['configd']['stop'])) {
        foreach ($service['configd']['stop'] as $cmd) {
            configd_run($cmd);
        }
    } elseif (isset($service['php']['stop'])) {
        foreach ($service['php']['stop'] as $cmd) {
            $cmd();
        }
    } elseif (isset($service['mwexec']['stop'])) {
        foreach ($service['mwexec']['stop'] as $cmd) {
            mwexec($cmd);
        }
    } elseif (isset($service['pidfile'])) {
        killbypid($service['pidfile']);
    } else {
        /* last resort, but not very elegant */
        killbyname($service['name']);
    }

    return sprintf(gettext("Service `%s' has been stopped."), htmlspecialchars(service_name($service)));
}

function service_control_restart($name, $extras)
{
    $service = service_control_get($name, $extras);
    if (!is_array($service)) {
        return $service;
    }

    if (isset($service['configd']['restart'])) {
        foreach ($service['configd']['restart'] as $cmd) {
            configd_run($cmd);
        }
    } elseif (isset($service['php']['restart'])) {
        foreach ($service['php']['restart'] as $cmd) {
            $params = [];
            if (isset($service['php']['args'])) {
                foreach ($service['php']['args'] as $param) {
                    $params[] = $service[$param];
                }
            }
            call_user_func_array($cmd, $params);
        }
    } elseif (isset($service['mwexec']['restart'])) {
        foreach ($service['mwexec']['restart'] as $cmd) {
            mwexec($cmd);
        }
    } else {
        return sprintf(gettext("Could not restart service `%s'"), htmlspecialchars(service_name($service)));
    }

    return sprintf(gettext("Service `%s' has been restarted."), htmlspecialchars(service_name($service)));
}

function is_subsystem_dirty($subsystem = '')
{
    return file_exists("/tmp/{$subsystem}.dirty");
}

function mark_subsystem_dirty($subsystem = '')
{
    touch("/tmp/{$subsystem}.dirty");
}

function clear_subsystem_dirty($subsystem = '')
{
    @unlink("/tmp/{$subsystem}.dirty");
}

function exit_on_bootup($callback = null, $arguments = [])
{
    if (product::getInstance()->booting()) {
        if ($callback) {
            call_user_func_array($callback, $arguments);
        }
        /* intentionally we exit here to avoid future cleverness WRT bootup handling */
        exit(0);
    }
}

/**
 * service logger with additional boot-time performance logging
 * @param $message string message to log
 * @param $verbose boolean verbose|bool (send to console),
 * @return void
 */
function service_log(string $message, bool $verbose = true): void
{
    if ($verbose) {
        echo $message;
        flush();
    }

    if (product::getInstance()->booting()) {
        $bt = debug_backtrace();
        $caller = !empty($bt[1]['function']) ? $bt[1]['function'] : 'unknown';

        file_put_contents('/var/log/boot.log', implode(' ', [
            (new DateTime())->format('c'),
            $caller . '[' . getmypid() . ']',
            rtrim($message) . PHP_EOL
        ]), FILE_APPEND | LOCK_EX);
    }
}

/* validate non-negative numeric string, or equivalent numeric variable */
function is_numericint($arg)
{
    return (((is_int($arg) && $arg >= 0) || (is_string($arg) && strlen($arg) > 0 && ctype_digit($arg))) ? true : false);
}

/* return the subnet address given a host address and a subnet bit count */
function gen_subnet($ipaddr, $bits)
{
    if (!is_ipaddr($ipaddr) || !is_numeric($bits)) {
        return '';
    }
    return long2ip(ip2long($ipaddr) & gen_subnet_mask_long($bits));
}

/* return the subnet address given a host address and a subnet bit count */
function gen_subnetv6($ipaddr, $bits)
{
    if (!is_ipaddrv6($ipaddr) || !is_numeric($bits)) {
        return '';
    }

    $address = Net_IPv6::getNetmask($ipaddr, $bits);
    $address = Net_IPv6::compress($address);

    return $address;
}

/* return the highest (broadcast) address in the subnet given a host address and a subnet bit count */
function gen_subnet_max($ipaddr, $bits)
{
    if (!is_ipaddr($ipaddr) || !is_numeric($bits)) {
        return '';
    }

    return long2ip32(ip2long($ipaddr) | ~gen_subnet_mask_long($bits));
}

/* Generate end number for a given ipv6 subnet mask */
function gen_subnetv6_max($ipaddr, $bits)
{
    $result = false;
    if (!is_ipaddrv6($ipaddr)) {
        return false;
    }

    set_error_handler(
        function () {
            return;
        }
    );
    $mask = Net_IPv6::getNetmask('FFFF:FFFF:FFFF:FFFF:FFFF:FFFF:FFFF:FFFF', $bits);
    $inet_ip = (binary)inet_pton($ipaddr);
    if ($inet_ip) {
        $inet_mask = (binary)inet_pton($mask);
        if ($inet_mask) {
            $inet_end = $inet_ip | ~$inet_mask;
            $result = inet_ntop($inet_end);
        }
    }
    restore_error_handler();

    return $result;
}

/* returns the calculated bit length of the prefix delegation from a WAN interface */
function calculate_ipv6_delegation_length($if)
{
    global $config;

    $pdlen = -1;

    if (!isset($config['interfaces'][$if]['ipaddrv6'])) {
        return $pdlen;
    }

    switch ($config['interfaces'][$if]['ipaddrv6']) {
        case '6to4':
            $pdlen = 16;
            break;
        case '6rd':
            $rd6cfg = $config['interfaces'][$if];
            $rd6plen = explode('/', $rd6cfg['prefix-6rd']);
            $pdlen = 64 - ($rd6plen[1] + (32 - $rd6cfg['prefix-6rd-v4plen']));
            if ($pdlen == 0) {
                /* XXX bug reports this is not working, needs investigation */
                $pdlen = -1;
            }
            break;
        case 'dhcp6':
            $dhcp6cfg = $config['interfaces'][$if];
            if (!empty($dhcp6cfg['adv_dhcp6_config_file_override'])) {
                /* emit error since we cannot cope with custom config */
                $pdlen = -1;
            } elseif (!empty($dhcp6cfg['adv_dhcp6_config_advanced']) && is_numeric($dhcp6cfg['adv_dhcp6_prefix_interface_statement_sla_len'])) {
                $pdlen = $dhcp6cfg['adv_dhcp6_prefix_interface_statement_sla_len'];
            } elseif (is_numeric($dhcp6cfg['dhcp6-ia-pd-len'])) {
                $pdlen = $dhcp6cfg['dhcp6-ia-pd-len'];
            }
            break;
        default:
            break;
    }

    return $pdlen;
}

/* returns a subnet mask (long given a bit count) */
function gen_subnet_mask_long($bits)
{
    $sm = 0;
    for ($i = 0; $i < $bits; $i++) {
        $sm >>= 1;
        $sm |= 0x80000000;
    }
    return $sm;
}

/* same as above but returns a string */
function gen_subnet_mask($bits)
{
    return long2ip(gen_subnet_mask_long($bits));
}

/* Convert long int to IP address, truncating to 32-bits. */
function long2ip32($ip)
{
    return long2ip($ip & 0xFFFFFFFF);
}

/* Convert IP address to long int, truncated to 32-bits to avoid sign extension on 64-bit platforms. */
function ip2long32($ip)
{
    return ( ip2long($ip) & 0xFFFFFFFF );
}

/* Convert IP address to unsigned long int. */
function ip2ulong($ip)
{
    return sprintf("%u", ip2long32($ip));
}

/* Find the smallest possible subnet mask for given IP range */
function find_smallest_cidr($ips, $family = 'inet')
{
    return $family == 'inet6' ?
        find_smallest_cidr6($ips) :
        find_smallest_cidr4($ips);
}

/* Find the smallest possible subnet mask for given IPv4 range */
function find_smallest_cidr4($ips)
{
    foreach ($ips as $id => $ip) {
        $ips[$id] = ip2long($ip);
    }

    for ($bits = 0; $bits <= 32; $bits += 1) {
        $mask = (0xffffffff << $bits) & 0xffffffff;
        $test = [];
        foreach ($ips as $ip) {
            $test[$ip & $mask] = true;
        }
        if (count($test) == 1) {
            /* one element means CIDR size matches all */
            break;
        }
    }

    return 32 - $bits;
}

/* Find the smallest possible subnet mask for given IPv6 range */
function find_smallest_cidr6($ips)
{
    foreach ($ips as $id => $ip) {
        $ips[$id] = unpack('N*', inet_pton($ip));
    }

    for ($bits = 0; $bits <= 128; $bits += 1) {
        $mask1 = (0xffffffff << max($bits - 96, 0)) & 0xffffffff;
        $mask2 = (0xffffffff << max($bits - 64, 0)) & 0xffffffff;
        $mask3 = (0xffffffff << max($bits - 32, 0)) & 0xffffffff;
        $mask4 = (0xffffffff << $bits) & 0xffffffff;
        $test = [];
        foreach ($ips as $ip) {
            $test[sprintf('%032b%032b%032b%032b', $ip[1] & $mask1, $ip[2] & $mask2, $ip[3] & $mask3, $ip[4] & $mask4)] = true;
        }
        if (count($test) == 1) {
            /* one element means CIDR size matches all */
            break;
        }
    }

    return 128 - $bits;
}

/* merge IPv6 address prefix of default size 64 with suffix */
function merge_ipv6_address($prefix, $suffix, $size = 64)
{
    if (strpos($suffix, '::') !== 0) {
        /* do not override full addresses */
        return $suffix;
    }

    $size = 128 - $size;

    $prefix_bits = unpack('N*', inet_pton($prefix));
    $suffix_bits = unpack('N*', inet_pton($suffix));
    $mask = [
        1 => (0xffffffff << max($size - 96, 0)) & 0xffffffff,
        2 => (0xffffffff << max($size - 64, 0)) & 0xffffffff,
        3 => (0xffffffff << max($size - 32, 0)) & 0xffffffff,
        4 => (0xffffffff << $size) & 0xffffffff,
    ];

    $result = '';

    for ($pos = 1; $pos <= 4; $pos += 1) {
        $result .=  sprintf('%08x', ($prefix_bits[$pos] & $mask[$pos]) | ($suffix_bits[$pos] & (~$mask[$pos] & 0xffffffff)));
    }

    return Net_IPv6::compress(implode(':', str_split($result, 4)));
}

/* returns true if $ipaddr is a valid dotted IPv4 address or an IPv6 */
function is_ipaddr($ipaddr)
{
    if (is_ipaddrv4($ipaddr)) {
        return true;
    }
    if (is_ipaddrv6($ipaddr)) {
        return true;
    }
    return false;
}

/* returns true if $ipaddr is a valid IPv6 address */
function is_ipaddrv6($ipaddr)
{
    if (!is_string($ipaddr) || empty($ipaddr)) {
        return false;
    }
    if (strstr($ipaddr, "%") && is_linklocal($ipaddr)) {
        $tmpip = explode("%", $ipaddr);
        $ipaddr = $tmpip[0];
    }
    if (strpos($ipaddr, ":") === false) {
        return false;
    } elseif (strpos($ipaddr, "/") !== false) {
        return false; // subnet is not an address
    } else {
        return Net_IPv6::checkIPv6($ipaddr);
    }
}

/* returns true if $ipaddr is a valid dotted IPv4 address */
function is_ipaddrv4($ipaddr)
{
    if (!is_string($ipaddr) || empty($ipaddr)) {
        return false;
    }

    $ip_long = ip2long($ipaddr);
    $ip_reverse = long2ip32($ip_long);

    if ($ipaddr == $ip_reverse) {
        return true;
    } else {
        return false;
    }
}

/* returns true if $ipaddr is a valid linklocal address (inside fe80::/10) */
function is_linklocal($ipaddr)
{
    return !!preg_match('/^fe[89ab][0-9a-f]:/i', $ipaddr ?? '');
}

/* returns true if $ipaddr is a valid uniquelocal address (inside fd00::/8) */
function is_uniquelocal($ipaddr)
{
    return !!preg_match('/^fd[0-9a-f][0-9a-f]:/i', $ipaddr ?? '');
}

/* returns true if $ipaddr is a valid literal IPv6 address */
function is_literalipaddrv6($ipaddr)
{
    if (preg_match('/\[([0-9a-f:]+)\]/i', $ipaddr, $match)) {
        $ipaddr = $match[1];
    } else {
        return false;
    }
    return is_ipaddrv6($ipaddr);
}

function is_ipaddrwithport($ipport)
{
    $parts = explode(":", $ipport);
    $port = array_pop($parts);
    if (count($parts) == 1) {
        return is_ipaddrv4($parts[0]) && is_port($port);
    } elseif (count($parts) > 1) {
        return is_literalipaddrv6(implode(":", $parts)) && is_port($port);
    } else {
        return false;
    }
}

function is_hostnamewithport($hostport)
{
    $parts = explode(":", $hostport);
    $port = array_pop($parts);
    if (count($parts) == 1) {
        return is_hostname($parts[0]) && is_port($port);
    } else {
        return false;
    }
}

/* returns true if $ipaddr is a valid dotted IPv4 address or an alias thereof */
function is_ipaddroralias($ipaddr)
{
    if (is_alias($ipaddr)) {
        foreach ((new \OPNsense\Firewall\Alias())->aliasIterator() as $alias) {
            if ($alias['name'] == $ipaddr && !preg_match("/port/i", $alias['type'])) {
                return true;
            }
        }

        return false;
    }

    return is_ipaddr($ipaddr);
}

/* returns true if $subnet is a valid IPv4 or IPv6 subnet in CIDR format
  false - if not a valid subnet
  true (numeric 4 or 6) - if valid, gives type of subnet */
function is_subnet($subnet)
{
    if (is_string($subnet) && preg_match('/^(?:([0-9.]{7,15})|([0-9a-f:]{2,39}))\/(\d{1,3})$/i', $subnet, $parts)) {
        if (is_ipaddrv4($parts[1]) && $parts[3] <= 32) {
            return 4;
        }
        if (is_ipaddrv6($parts[2]) && $parts[3] <= 128) {
            return 6;
        }
    }
    return false;
}

/* same as is_subnet() but accepts IPv4 only */
function is_subnetv4($subnet)
{
    return (is_subnet($subnet) == 4);
}

/* same as is_subnet() but accepts IPv6 only */
function is_subnetv6($subnet)
{
    return (is_subnet($subnet) == 6);
}

/* returns true if $hostname is a valid hostname */
function is_hostname($hostname)
{
    if (!is_string($hostname)) {
        return false;
    }
    if (preg_match('/^(?:(?:[a-z0-9_]|[a-z0-9_][a-z0-9_\-]*[a-z0-9_])\.)*(?:[a-z0-9_]|[a-z0-9_][a-z0-9_\-]*[a-z0-9_])$/i', $hostname)) {
        return true;
    } else {
        return false;
    }
}

/* returns true if $domain is a valid domain name */
function is_domain($domain, $allow_root = false)
{
    if (!is_string($domain)) {
        return false;
    } elseif (preg_match('/^(?:(?:[a-z0-9]|[a-z0-9][a-z0-9\-]*[a-z0-9])\.)*(?:[a-z0-9]|[a-z0-9][a-z0-9\-]*[a-z0-9])$/i', $domain)) {
        return true;
    } elseif ($allow_root && $domain == '.') {
        return true;
    }

    return false;
}

/* returns true if $macaddr is a valid MAC address */
function is_macaddr($macaddr, $partial = false)
{
    return !!preg_match('/^[0-9A-F]{2}(?:[:][0-9A-F]{2}){' . ($partial ? '1,' : '') . '5}$/i', $macaddr);
}

/* returns true if $port is a valid TCP/UDP port */
function is_port($port)
{
    return OPNsense\Firewall\Util::isPort($port, false);
}

/* returns true if $portrange is a valid TCP/UDP portrange ("<port>:<port>") */
function is_portrange($portrange)
{
    $ports = explode(":", $portrange);
    return (count($ports) == 2 && is_port($ports[0]) && is_port($ports[1]));
}

/* returns true if $port is a valid port number or an alias thereof */
function is_portoralias($port)
{
    if (is_alias($port)) {
        foreach ((new \OPNsense\Firewall\Alias())->aliasIterator() as $alias) {
            if ($alias['name'] == $port && preg_match("/port/i", $alias['type'])) {
                return true;
            }
        }
        return false;
    }

    return is_port($port);
}

/* returns true if $test is in the range between $start and $end */
function is_inrange_v4($test, $start, $end)
{
    if ((ip2ulong($test) <= ip2ulong($end)) && (ip2ulong($test) >= ip2ulong($start))) {
        return true;
    } else {
        return false;
    }
}

/* returns true if $test is in the range between $start and $end */
function is_inrange_v6($test, $start, $end)
{
    if ((inet_pton($test) <= inet_pton($end)) && (inet_pton($test) >= inet_pton($start))) {
        return true;
    } else {
        return false;
    }
}

/* returns true if $test is in the range between $start and $end */
function is_inrange($test, $start, $end)
{
    return is_ipaddrv6($test) ? is_inrange_v6($test, $start, $end) : is_inrange_v4($test, $start, $end);
}

function get_configured_carp_interface_list()
{
    $carp_list = [];

    foreach (config_read_array('virtualip', 'vip') as $vip) {
        if ($vip['mode'] == 'carp') {
            $carp_list["{$vip['interface']}_vip{$vip['vhid']}"] = $vip['subnet'];
        }
    }

    return $carp_list;
}

function get_configured_ip_aliases_list()
{
    $alias_list = [];

    foreach (config_read_array('virtualip', 'vip') as $vip) {
        if ($vip['mode'] == 'ipalias') {
            $alias_list[$vip['subnet']] = $vip['interface'];
        }
    }

    return $alias_list;
}

function get_configured_interface_with_descr()
{
    $iflist = [];

    foreach (legacy_config_get_interfaces(['virtual' => false]) as $if => $ifdetail) {
        if (isset($ifdetail['enable'])) {
            $iflist[$if] = $ifdetail['descr'];
        }
    }

    return $iflist;
}

function get_primary_interface_from_list($iflist = null)
{
    $ret = null;

    if ($iflist === null) {
        $iflist = array_keys(get_configured_interface_with_descr());
    }

    /* we pull the primary entry from a "priority" list: lan, optX or wan */
    natsort($iflist);

    foreach ($iflist as $if) {
        if (preg_match('/^(lan|opt[0-9]+|wan)$/', $if)) {
            $ret = $if;
            break;
        }
    }

    return $ret;
}

/*
 *   get_configured_ip_addresses() - Return a list of all configured
 *   interfaces IP Addresses (ipv4+ipv6)
 */
function get_configured_ip_addresses()
{
    $ip_array = [];

    foreach (legacy_interfaces_details() as $ifname => $if) {
        foreach (['ipv4', 'ipv6'] as $iptype) {
            if (!empty($if[$iptype])) {
                foreach ($if[$iptype] as $addr) {
                    if (!empty($addr['ipaddr'])) {
                        $scope = !empty($addr['link-local']) ? "%{$ifname}" : '';
                        $ip_array[$addr['ipaddr'] . $scope] = $ifname;
                    }
                }
            }
        }
    }

    return $ip_array;
}

/****f* util/log_error
* NAME
*   log_error  - Sends a string to syslog with LOG_ERR severity.
* INPUTS
*   $error     - string containing the syslog message.
* RESULT
*   null
******/
function log_error($error)
{
    log_msg($error, LOG_ERR);
}

/****f* util/log_msg
* NAME
*   log_msg  - Sends a string to syslog with the required severity level.
* INPUTS
*   $msg     - string containing the syslog message.
*   $prio    - syslog severity level.
* RESULT
*   null
******/
function log_msg($msg, $prio = LOG_NOTICE)
{
    $page = $_SERVER['SCRIPT_NAME'];
    if (empty($page)) {
        $files = get_included_files();
        $page = basename($files[0]);
    }

    switch ($prio) {
        case LOG_DEBUG:
        case LOG_INFO:
            /* lowest handled value for the time being */
            $prio = LOG_NOTICE;
            break;
        default:
            break;
    }

    syslog($prio, "$page: $msg");
}

function url_safe($format, $args = [])
{
    if (!is_array($args)) {
        /* just in case there's only one argument */
        $args = [$args];
    }

    foreach ($args as $id => $arg) {
        /* arguments could be empty, so force default */
        $args[$id] = !empty($arg) ? urlencode($arg) : '';
    }

    return vsprintf($format, $args);
}

function mwexec($command, $mute = false)
{
    $oarr = [];
    $retval = 0;

    $garbage = exec("{$command} 2>&1", $oarr, $retval);
    unset($garbage);

    if ($retval != 0 && $mute !== true) {
        $output = implode(' ', $oarr);
        log_msg(sprintf("The command '%s'%s returned exit code '%d', the output was '%s'", $command, !empty($mute) ? "($mute) " : '', $retval, $output), LOG_ERR);
        unset($output);
    }

    unset($oarr);

    return $retval;
}

function mwexec_bg($command, $mute = false)
{
    mwexec("/usr/sbin/daemon -f {$command}", $mute);
}

function exec_safe($format, $args = [])
{
    if (!is_array($args)) {
        /* just in case there's only one argument */
        $args = [$args];
    }

    foreach ($args as $id => $arg) {
        $args[$id] = escapeshellarg($arg ?? '');
    }

    return vsprintf($format, $args);
}

function mwexecf($format, $args = [], $mute = false)
{
    return mwexec(exec_safe($format, $args), $mute);
}

function mwexecf_bg($format, $args = [], $mute = false)
{
    mwexec_bg(exec_safe($format, $args), $mute);
}

function shell_safe($format, $args = [])
{
    $ret = shell_exec(exec_safe($format, $args));

    /* shell_exec() has weird semantics */
    if ($ret === false || $ret === null) {
        $ret = '';
    }

    /* always trim() result on a string */
    return trim($ret);
}

/* check if an alias exists */
function is_alias($name)
{
    return \OPNsense\Firewall\Util::isAlias($name);
}

function subnet_size($subnet)
{
    if (is_subnetv4($subnet)) {
        list ($ip, $bits) = explode("/", $subnet);
        return round(exp(log(2) * (32 - $bits)));
    } elseif (is_subnetv6($subnet)) {
        list ($ip, $bits) = explode("/", $subnet);
        return round(exp(log(2) * (128 - $bits)));
    } else {
        return 0;
    }
}

/* find out whether two subnets overlap */
function check_subnets_overlap($subnet1, $bits1, $subnet2, $bits2)
{
    if (!is_numeric($bits1)) {
        $bits1 = 32;
    }
    if (!is_numeric($bits2)) {
        $bits2 = 32;
    }

    if ($bits1 < $bits2) {
        $relbits = $bits1;
    } else {
        $relbits = $bits2;
    }

    $sn1 = gen_subnet_mask_long($relbits) & ip2long($subnet1);
    $sn2 = gen_subnet_mask_long($relbits) & ip2long($subnet2);

    return ($sn1 == $sn2);
}

/* compare two IP addresses */
function ipcmp($a, $b)
{
    $na = inet_pton($a);
    $nb = inet_pton($b);
    if ($na < $nb) {
        return -1;
    } elseif ($na > $nb) {
        return 1;
    } else {
        return 0;
    }
}

/* return true if $addr is in $subnet, false if not */
function ip_in_subnet($addr, $subnet)
{
    if (empty($subnet)) {
        /* discard invalid input */
    } elseif (is_ipaddrv6($addr)) {
        return (Net_IPv6::isInNetmask($addr, $subnet));
    } elseif (is_ipaddrv4($addr)) {
        list($ip, $mask) = explode('/', $subnet);
        if (is_ipaddrv4($ip) && $mask <= 32) {
            $mask = (0xffffffff << (32 - $mask)) & 0xffffffff;
            return ((ip2long($addr) & $mask) == (ip2long($ip) & $mask));
        }
    }
    return false;
}

function is_private_ip($iptocheck)
{
    foreach (['10.0.0.0/8', '100.64.0.0/10', '172.16.0.0/12', '192.168.0.0/16'] as $private) {
        if (ip_in_subnet($iptocheck, $private) == true) {
            return true;
        }
    }
    return false;
}

function format_bytes($bytes)
{
    if ($bytes >= 1024 ** 5) {
        return sprintf("%.2f PB", $bytes / (1024 ** 5));
    } elseif ($bytes >= 1024 ** 4) {
        return sprintf("%.2f TB", $bytes / (1024 ** 4));
    } elseif ($bytes >= 1024 ** 3) {
        return sprintf("%.2f GB", $bytes / (1024 ** 3));
    } elseif ($bytes >= 1024 ** 2) {
        return sprintf("%.2f MB", $bytes / (1024 ** 2));
    } elseif ($bytes >= 1024) {
        return sprintf("%.0f KB", $bytes / 1024);
    } else {
        return sprintf("%d bytes", $bytes);
    }
}

/*
 * get_sysctl($names)
 * Get values of sysctl OID's listed in $names (accepts an array or a single
 * name) and return an array of key/value pairs set for those that exist
 */
function get_sysctl($names)
{
    if (empty($names)) {
        return [];
    }

    if (is_array($names)) {
        $name_list = [];
        foreach ($names as $name) {
            $name_list[] = escapeshellarg($name);
        }
    } else {
        $name_list = [escapeshellarg($names)];
    }

    exec("/sbin/sysctl -i " . implode(" ", $name_list), $output);
    $values = [];
    foreach ($output as $line) {
        $line = explode(": ", $line, 2);
        if (count($line) == 2) {
            $values[$line[0]] = $line[1];
        }
    }

    return $values;
}

/*
 * get_single_sysctl($name)
 * Wrapper for get_sysctl() to simplify read of a single sysctl value
 * return the value for sysctl $name or null if it does not exist
 */
function get_single_sysctl($name)
{
    $ret = null;

    if (!empty($name)) {
        $value = get_sysctl($name);
        if (isset($value[$name])) {
            $ret = $value[$name];
        }
    }

    return $ret;
}

/*
 * set_sysctl($value_list)
 * Set sysctl OID's listed as key/value pairs and return
 * an array with keys set for those that succeeded
 */
function set_sysctl($values, $filter = true)
{
    $ret = [];

    if (empty($values)) {
        return $ret;
    }

    $sysctls = null;

    if ($filter) {
        exec('/sbin/sysctl -WaN', $list, $success);
        if ($success == 0) {
            $sysctls = $list;
        }
    }

    $value_list = [];
    foreach ($values as $key => $value) {
        if ($sysctls !== null && !in_array($key, $sysctls)) {
            continue;
        }
        $value_list[] = escapeshellarg($key) . '=' . escapeshellarg($value);
    }

    if (count($value_list)) {
        exec('/sbin/sysctl ' . implode(' ', $value_list), $output);

        foreach ($output as $line) {
            $line = explode(': ', $line, 2);
            if (count($line) == 2) {
                $ret[$line[0]] = true;
            }
        }
    }

    return $ret;
}

/*
 * set_single_sysctl($name, $value)
 * Wrapper to set_sysctl() to make it simple to set only one sysctl
 */
function set_single_sysctl($name, $value)
{
    set_sysctl([$name => $value], false);
}

/****f* util/is_URL
 * NAME
 *   is_URL
 * INPUTS
 *   string to check
 * RESULT
 *   Returns true if item is a URL
 ******/
function is_URL($url)
{
    $match = preg_match("'\b(([\w-]+://?|www[.])[^\s()<>]+(?:\([\w\d]+\)|([^[:punct:]\s]|/)))'", $url);
    if ($match) {
        return true;
    }
    return false;
}

function get_staticroutes($returnsubnetsonly = false)
{
    global $aliastable;

    $allstaticroutes = [];
    $allsubnets = [];
    foreach (config_read_array('staticroutes', 'route') as $route) {
        if (is_subnet($route['network'])) {
            $allstaticroutes[] = $route;
            $allsubnets[] = $route['network'];
        }
    }

    if ($returnsubnetsonly) {
        return $allsubnets;
    }

    return $allstaticroutes;
}

function is_fqdn($fqdn)
{
    $hostname = false;

    if (preg_match("/[-A-Z0-9\.]+\.[-A-Z0-9\.]+/i", $fqdn)) {
        $hostname = true;
    }
    if (preg_match("/\.\./", $fqdn)) {
        $hostname = false;
    }
    if (preg_match("/^\./i", $fqdn)) {
        $hostname = false;
    }
    if (preg_match("/\//i", $fqdn)) {
        $hostname = false;
    }

    return $hostname;
}

function is_install_media()
{
    /*
     * Despite unionfs underneath, / is still not writeable,
     * making the following the perfect test for install media.
     */
    $file = '/.probe.for.readonly';

    if (file_exists($file)) {
        return false;
    }

    $fd = @fopen($file, 'w');
    if ($fd) {
        fclose($fd);
        return false;
    }

    return true;
}

function dhcp6c_duid_read()
{
    $parts = [];
    $skip = 2;

    if (file_exists('/var/db/dhcp6c_duid')) {
        $size = filesize('/var/db/dhcp6c_duid');
        if ($size > $skip && ($fd = fopen('/var/db/dhcp6c_duid', 'r'))) {
            $ret = unpack('Slen/H*buf', fread($fd, $size));
            fclose($fd);

            if (isset($ret['len']) && isset($ret['buf'])) {
                if ($ret['len'] + $skip == $size && strlen($ret['buf']) == $ret['len'] * 2) {
                    $parts = str_split($ret['buf'], 2);
                }
            }
        }
    }

    $duid = strtoupper(implode(':', $parts));

    return $duid;
}

function dhcp6c_duid_write($duid)
{
    $fd = fopen('/var/db/dhcp6c_duid', 'wb');
    if ($fd) {
        $parts = explode(':', $duid);
        /* length is unsigned 16 bit integer, machine-dependent:*/
        fwrite($fd, pack('S', count($parts)));
        /* buffer is binary string, according to advertised length: */
        fwrite($fd, pack('H*', implode('', $parts)));
        fclose($fd);
    }
}

function dhcp6c_duid_clear()
{
    @unlink('/var/db/dhcp6c_duid');
    /* clear the backup so that it will not be restored: */
    @unlink('/conf/dhcp6c_duid');
}

/**
 * check if interface is assigned
 * @param $interface technical interface name
 * @return string interface name (lan, wan, optX)
 */
function is_interface_assigned($interface)
{
    global $config;

    foreach (legacy_config_get_interfaces() as $if => $intf) {
        if (isset($intf['if']) && $intf['if'] == $interface) {
            return true;
        }
    }
    if (isset($config['vlans']['vlan'])) {
        foreach ($config['vlans']['vlan'] as $vlan) {
            if ($vlan['if'] == $interface) {
                return true;
            }
        }
    }
    return false;
}
