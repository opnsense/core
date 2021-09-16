<?php

/*
 * Copyright (C) 2014-2021 Franco Fichtner <franco@opnsense.org>
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

require_once("IPv6.inc");

function killbyname($procname, $sig = 'TERM')
{
    if (!is_process_running($procname)) {
        return;
    }

    mwexecf('/bin/pkill -%s %s', array($sig, $procname));
}

function killbypid($pidfile, $sig = 'TERM', $waitforit = false)
{
    if (is_numeric($pidfile) && $pidfile > 1) {
        mwexecf('/bin/kill -%s %s', array($sig, $pidfile));
    } elseif (isvalidpid($pidfile)) {
        mwexecf('/bin/pkill -%s -F %s', array($sig, $pidfile));
    } else {
        return;
    }

    if (!$waitforit) {
        return;
    }

    while (isvalidpid($pidfile)) {
        usleep(200 * 1000);
    }
}

function isvalidpid($pidfile)
{
    if (is_numeric($pidfile) && $pidfile > 1) {
        return mwexecf('/bin/kill -0 %s', $pidfile, true) == 0;
    } elseif (file_exists($pidfile)) {
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

function service_by_name($name, $filter = array())
{
    $services = plugins_services();

    foreach ($services as $service) {
        if ($service['name'] != $name) {
            continue;
        }
        if (!count($filter)) {
            /* force match if filter wasn't set (standard behaviour) */
            $filter['name'] = $name;
        }
        foreach ($filter as $key => $value) {
            if (isset($service[$key]) && $service[$key] == $value) {
                return $service;
            }
        }
    }

    return array();
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

function service_control_start($name, $extras)
{
    $filter = array();

    if (!empty($extras['id'])) {
        $filter['id'] = $extras['id'];
    }

    $service = service_by_name($name, $filter);
    if (!isset($service['name'])) {
        return sprintf(gettext("Could not start unknown service `%s'"), htmlspecialchars($name));
    }

    if (isset($service['configd']['start'])) {
        foreach ($service['configd']['start'] as $cmd) {
            configd_run($cmd);
        }
    } elseif (isset($service['php']['start'])) {
        foreach ($service['php']['start'] as $cmd) {
            $params = array();
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
        return sprintf(gettext("Could not start service `%s'"), htmlspecialchars($name));
    }

    return sprintf(gettext("Service `%s' has been started."), htmlspecialchars($name));
}

function service_control_stop($name, $extras)
{
    $filter = array();

    if (!empty($extras['id'])) {
        $filter['id'] = $extras['id'];
    }

    $service = service_by_name($name, $filter);
    if (!isset($service['name'])) {
        return sprintf(gettext("Could not stop unknown service `%s'"), htmlspecialchars($name));
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
        killbypid($service['pidfile'], 'TERM', true);
    } else {
        /* last resort, but not very elegant */
        killbyname($service['name']);
    }

    return sprintf(gettext("Service `%s' has been stopped."), htmlspecialchars($name));
}

function service_control_restart($name, $extras)
{
    $filter = array();

    if (!empty($extras['id'])) {
        $filter['id'] = $extras['id'];
    }

    $service = service_by_name($name, $filter);
    if (!isset($service['name'])) {
        return sprintf(gettext("Could not restart unknown service `%s'"), htmlspecialchars($name));
    }

    if (isset($service['configd']['restart'])) {
        foreach ($service['configd']['restart'] as $cmd) {
            configd_run($cmd);
        }
    } elseif (isset($service['php']['restart'])) {
        foreach ($service['php']['restart'] as $cmd) {
            $params = array();
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
        return sprintf(gettext("Could not restart service `%s'"), htmlspecialchars($name));
    }

    return sprintf(gettext("Service `%s' has been restarted."), htmlspecialchars($name));
}

function list_dh_parameters()
{
    global $config;

    $available = array(1024, 2048, 4096);

    if (!empty($config['system']['dhparamusage'])) {
        switch ($config['system']['dhparamusage']) {
            case 'rfc7919':
                $available = array(2048, 3072, 4096);
                break;
            default:
                /* XXX whatever we have on the disk */
                break;
        }
    }

    return $available;
}

function get_dh_parameters($bits)
{
    global $config;

    $file = "/usr/local/etc/dh-parameters.{$bits}";

    if (!empty($config['system']['dhparamusage'])) {
        switch ($config['system']['dhparamusage']) {
            case 'rfc7919':
                return "{$file}.rfc7919";
            default:
                break;
        }
    }

    if (empty($config['system']['dhparamusage']) || !file_exists($file)) {
        /*
         * Use the sample file if the normal file is MIA
         * or when the renew interval is not set:
         */
        $file .= '.sample';
    }

    return $file;
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

/* lock configuration file */
function lock($lock, $op = LOCK_SH)
{
    if (!$lock) {
        die(gettext("WARNING: You must give a name as parameter to lock() function."));
    }

    if (!file_exists("{/tmp/{$lock}.lock")) {
        @touch("/tmp/{$lock}.lock");
        @chmod("/tmp/{$lock}.lock", 0666);
    }

    if ($fp = fopen("/tmp/{$lock}.lock", "w")) {
        if (flock($fp, $op)) {
            return $fp;
        } else {
            fclose($fp);
        }
    }
}


/* unlock configuration file */
function unlock($cfglckkey = null)
{
    if ($cfglckkey) {
        flock($cfglckkey, LOCK_UN);
        fclose($cfglckkey);
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
            if (is_numeric($dhcp6cfg['dhcp6-ia-pd-len'])) {
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

/* returns true if $ipaddr is a valid linklocal address */
function is_linklocal($ipaddr)
{
    return (strtolower(substr($ipaddr, 0, 5)) == "fe80:");
}

/* returns true if $ipaddr is a valid literal IPv6 address */
function is_literalipaddrv6($ipaddr)
{
    if (preg_match("/\[([0-9a-f:]+)\]/i", $ipaddr, $match)) {
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
function is_domain($domain)
{
    if (!is_string($domain)) {
        return false;
    }
    if (preg_match('/^(?:(?:[a-z0-9]|[a-z0-9][a-z0-9\-]*[a-z0-9])\.)*(?:[a-z0-9]|[a-z0-9][a-z0-9\-]*[a-z0-9])$/i', $domain)) {
        return true;
    } else {
        return false;
    }
}

/* returns true if $macaddr is a valid MAC address */
function is_macaddr($macaddr, $partial = false)
{
    $repeat = ($partial) ? '1,5' : '5';
    return preg_match('/^[0-9A-F]{2}(?:[:][0-9A-F]{2}){' . $repeat . '}$/i', $macaddr) == 1 ? true : false;
}

/* returns true if $port is a valid TCP/UDP port */
function is_port($port)
{
    if (getservbyname($port, "tcp") || getservbyname($port, "udp")) {
        return true;
    }
    if (!ctype_digit($port)) {
        return false;
    } elseif ((intval($port) < 1) || (intval($port) > 65535)) {
        return false;
    }
    return true;
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

/* XXX: return the configured carp interface list */
function get_configured_carp_interface_list($carpinterface = '', $family = 'inet')
{
    $iflist = array();

    foreach (config_read_array('virtualip', 'vip') as $vip) {
        switch ($vip['mode']) {
            case 'carp':
                if (!empty($carpinterface)) {
                    if ($carpinterface == "{$vip['interface']}_vip{$vip['vhid']}") {
                        if ($family == 'inet' && is_ipaddrv4($vip['subnet'])) {
                            return $vip['subnet'];
                        } elseif ($family == 'inet6' && is_ipaddrv6($vip['subnet'])) {
                            return $vip['subnet'];
                        }
                    }
                } else {
                    $iflist["{$vip['interface']}_vip{$vip['vhid']}"] = $vip['subnet'];
                }
                break;
        }
    }

    return $iflist;
}

function get_configured_ip_aliases_list()
{
    $alias_list = array();

    foreach (config_read_array('virtualip', 'vip') as $vip) {
        if ($vip['mode'] == 'ipalias') {
            $alias_list[$vip['subnet']] = $vip['interface'];
        }
    }

    return $alias_list;
}

/* return all configured aliases list (IP, carp, proxyarp and other) */
function get_configured_vips_list()
{
    $alias_list = array();

    foreach (config_read_array('virtualip', 'vip') as $vip) {
        if ($vip['mode'] == 'carp') {
            $alias_list[] = array('ipaddr' => $vip['subnet'], 'if' => "{$vip['interface']}_vip{$vip['vhid']}");
        } else {
            $alias_list[] = array('ipaddr' => $vip['subnet'], 'if' => $vip['interface']);
        }
    }

    return $alias_list;
}

function get_configured_interface_with_descr()
{
    $iflist = array();

    foreach (legacy_config_get_interfaces(array('virtual' => false)) as $if => $ifdetail) {
        if (isset($ifdetail['enable'])) {
            $iflist[$if] = $ifdetail['descr'];
        }
    }

    return $iflist;
}

/*
 *   get_configured_ip_addresses() - Return a list of all configured
 *   interfaces IP Addresses (ipv4+ipv6)
 *
 */
function get_configured_ip_addresses()
{
    $ip_array = array();

    foreach (legacy_interfaces_details() as $ifname => $if) {
        foreach (array('ipv4', 'ipv6') as $iptype) {
            if (!empty($if[$iptype])) {
                foreach ($if[$iptype] as $addr) {
                    if (!empty($addr['ipaddr'])) {
                        $scope = $addr['link-local'] ? "%{$ifname}" : '';
                        $ip_array[$addr['ipaddr'] . $scope] = $ifname;
                    }
                }
            }
        }
    }

    return $ip_array;
}

/*
 *   get_interface_list() - Return a list of all physical interfaces
 *   along with MAC, IPv4 and status.
 *
 *   $only_active = false -- interfaces that are available in the system
 *                  true  -- interfaces that are physically connected
 *
 *   $include_dmesg = false -- skip probing dmesg for more information
 *                    true  -- probe dmesg for more information
 */
function get_interface_list($only_active = false, $include_dmesg = false)
{
    $dmesg_arr = array();
    $iflist = array();

    if ($include_dmesg) {
        exec('/sbin/dmesg', $dmesg_arr);
    }

    /* list of virtual interface types */
    $vfaces = array(
      '_vlan',
      '_wlan',
      'bridge',
      'carp',
      'enc',
      'faith',
      'gif',
      'gre',
      'ipfw',
      'l2tp',
      'lagg',
      'lo',
      'ng',
      'pflog',
      'plip',
      'ppp',
      'pppoe',
      'pptp',
      'pfsync',
      'sl',
      'tun',
      'vip'
    );

    $ifnames_up = legacy_interface_listget('up');
    $ifnames = legacy_interface_listget();
    $all_interfaces = legacy_config_get_interfaces(array('virtual' => false));
    $all_interface_data = legacy_interfaces_details();

    if ($only_active) {
        $_ifnames = array();
        $all_stats = legacy_interface_stats();
        foreach ($ifnames as $ifname) {
            $ifinfo = $all_stats[$ifname];
            if (!empty($ifinfo['link state']) && $ifinfo['link state'] == '2') {
                $_ifnames[] = $ifname;
            }
        }
        $ifnames = $_ifnames;
    }

    foreach ($ifnames as $ifname) {
        $tmp_ifnames = preg_split('/\d/', $ifname);
        if (in_array(array_shift($tmp_ifnames), $vfaces)) {
            continue;
        }

        $ifdata = !empty($all_interface_data[$ifname]) ? $all_interface_data[$ifname] : array();
        $toput = array(
          'up' => in_array($ifname, $ifnames_up),
          'ipaddr' => !empty($ifdata['ipv4'][0]['ipaddr']) ? $ifdata['ipv4'][0]['ipaddr'] : null,
          'mac' => !empty($ifdata['macaddr']) ? $ifdata['macaddr'] : null,
          'dmesg' => '',
        );

        foreach ($all_interfaces as $name => $int) {
            if ($int['if'] == $ifname) {
                $toput['friendly'] = $name;
                break;
            }
        }

        foreach ($dmesg_arr as $dmesg_line) {
            $dmesg = array();
            if (preg_match("/^{$ifname}: <(.*?)>/i", $dmesg_line, $dmesg) == 1) {
                $toput['dmesg'] = $dmesg[1];
                break;
            }
        }

        $iflist[$ifname] = $toput;
    }

    return $iflist;
}

/****f* util/log_error
* NAME
*   log_error  - Sends a string to syslog.
* INPUTS
*   $error     - string containing the syslog message.
* RESULT
*   null
******/
function log_error($error)
{
    $page = $_SERVER['SCRIPT_NAME'];
    if (empty($page)) {
        $files = get_included_files();
        $page = basename($files[0]);
    }

    syslog(LOG_ERR, "$page: $error");
}

function url_safe($format, $args = array())
{
    if (!is_array($args)) {
        /* just in case there's only one argument */
        $args = array($args);
    }

    foreach ($args as $id => $arg) {
        /* arguments could be empty, so force default */
        $args[$id] = !empty($arg) ? urlencode($arg) : '';
    }

    return vsprintf($format, $args);
}

function cache_safe($url)
{
    $info = stat('/usr/local/opnsense/www/index.php');
    if (!empty($info['mtime'])) {
        return "{$url}?v=" . substr(md5($info['mtime']), 0, 16);
    }

    return $url;
}

/****f* util/exec_command
 * NAME
 *   exec_command - Execute a command and return a string of the result.
 * INPUTS
 *   $command   - String of the command to be executed.
 * RESULT
 *   String containing the command's result.
 * NOTES
 *   This function returns the command's stdout and stderr.
 ******/
function exec_command($command)
{
    $output = array();
    exec($command . ' 2>&1', $output);
    return(implode("\n", $output));
}

function mwexec($command, $mute = false)
{
    $oarr = array();
    $retval = 0;

    $garbage = exec("{$command} 2>&1", $oarr, $retval);
    unset($garbage);

    if ($retval != 0 && $mute == false) {
        $output = implode(' ', $oarr);
        log_error(sprintf("The command '%s' returned exit code '%d', the output was '%s'", $command, $retval, $output));
        unset($output);
    }

    unset($oarr);

    return $retval;
}

function mwexec_bg($command, $mute = false)
{
    mwexec("/usr/sbin/daemon -f {$command}", $mute);
}

function exec_safe($format, $args = array())
{
    if (!is_array($args)) {
        /* just in case there's only one argument */
        $args = array($args);
    }

    foreach ($args as $id => $arg) {
        $args[$id] = escapeshellarg($arg);
    }

    return vsprintf($format, $args);
}

function mwexecf($format, $args = array(), $mute = false)
{
    return mwexec(exec_safe($format, $args), $mute);
}

function mwexecf_bg($format, $args = array(), $mute = false)
{
    mwexec_bg(exec_safe($format, $args), $mute);
}


/* check if an alias exists */
function is_alias($name)
{
    global $aliastable;
    return array_key_exists($name, $aliastable);
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
    if (is_ipaddrv6($addr)) {
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
    foreach (array("10.0.0.0/8", "100.64.0.0/10", "172.16.0.0/12", "192.168.0.0/16") as $private) {
        if (ip_in_subnet($iptocheck, $private) == true) {
            return true;
        }
    }
    return false;
}

function format_bytes($bytes)
{
    if ($bytes >= (1024 * 1024 * 1024)) {
        return sprintf("%.2f GB", $bytes / (1024 * 1024 * 1024));
    } elseif ($bytes >= 1024 * 1024) {
        return sprintf("%.2f MB", $bytes / (1024 * 1024));
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
        return array();
    }

    if (is_array($names)) {
        $name_list = array();
        foreach ($names as $name) {
            $name_list[] = escapeshellarg($name);
        }
    } else {
        $name_list = array(escapeshellarg($names));
    }

    exec("/sbin/sysctl -i " . implode(" ", $name_list), $output);
    $values = array();
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
 * return the value for sysctl $name or empty string if it doesn't exist
 */
function get_single_sysctl($name)
{
    if (empty($name)) {
        return '';
    }
    $value = get_sysctl($name);
    if (empty($value) || !isset($value[$name])) {
        return '';
    }
    return $value[$name];
}

/*
 * set_sysctl($value_list)
 * Set sysctl OID's listed as key/value pairs and return
 * an array with keys set for those that succeeded
 */
function set_sysctl($values)
{
    if (empty($values)) {
        return array();
    }

    $sysctls = null;
    exec('/sbin/sysctl -WaN', $list, $success);
    if ($success == 0) {
        $sysctls = $list;
    }

    $value_list = array();
    foreach ($values as $key => $value) {
        if ($sysctls != null && !in_array($key, $sysctls)) {
            continue;
        }
        $value_list[] = escapeshellarg($key) . "=" . escapeshellarg($value);
    }

    if (count($value_list)) {
        exec('/sbin/sysctl ' . implode(' ', $value_list), $output);

        $ret = array();
        foreach ($output as $line) {
            $line = explode(": ", $line, 2);
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
 * returns boolean meaning if it suceed
 */
function set_single_sysctl($name, $value)
{
    if (empty($name)) {
        return false;
    }
    $result = set_sysctl(array($name => $value));
    if (!isset($result[$name]) || $result[$name] != $value) {
        return false;
    }
    return true;
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

function get_staticroutes($returnsubnetsonly = false, $returnhostnames = false)
{
    global $aliastable;

    $allstaticroutes = array();
    $allsubnets = array();

    /* Loop through routes and expand aliases as we find them. */
    foreach (config_read_array('staticroutes', 'route') as $route) {
        if (is_alias($route['network'])) {
            if (!isset($aliastable[$route['network']])) {
                continue;
            }
            $subnets = preg_split('/\s+/', $aliastable[$route['network']]);
            foreach ($subnets as $net) {
                if (!is_subnet($net)) {
                    if (is_ipaddrv4($net)) {
                        $net .= "/32";
                    } elseif (is_ipaddrv6($net)) {
                        $net .= "/128";
                    } elseif ($returnhostnames === false || !is_fqdn($net)) {
                        continue;
                    }
                }
                $temproute = $route;
                $temproute['network'] = $net;
                $allstaticroutes[] = $temproute;
                $allsubnets[] = $net;
            }
        } elseif (is_subnet($route['network'])) {
            $allstaticroutes[] = $route;
            $allsubnets[] = $route['network'];
        }
    }

    if ($returnsubnetsonly) {
        return $allsubnets;
    }

    return $allstaticroutes;
}

function prefer_ipv4_or_ipv6()
{
    global $config;

    mwexecf(
        '/etc/rc.d/ip6addrctl %s',
        isset($config['system']['prefer_ipv4']) ? 'prefer_ipv4' : 'prefer_ipv6'
    );
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
    $file = '/.probe.for.install.media';

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
    $parts = array();
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

function get_dyndns_ip($int, $ipver = 4)
{
    $ip_address = $ipver == 6 ? get_interface_ipv6($int) : get_interface_ip($int);
    if (empty($ip_address)) {
        log_error("Aborted IPv{$ipver} detection: no address for {$int}");
        return 'down';
    }

    if ($ipver != 6 && is_private_ip($ip_address)) {
        /* Chinese alternative is http://ip.3322.net/ */
        $hosttocheck = 'http://checkip.dyndns.org';
        $ip_ch = curl_init($hosttocheck);
        curl_setopt($ip_ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ip_ch, CURLOPT_INTERFACE, $ip_address);
        curl_setopt($ip_ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ip_ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ip_ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
        $ip_result = curl_exec($ip_ch);
        if ($ip_result !== false) {
            preg_match('=<body>Current IP Address: (.*)</body>=siU', $ip_result, $matches);
            $ip_address = trim($matches[1]);
        } else {
            log_error('Aborted IPv4 detection: ' . curl_error($ip_ch));
            $ip_address = '';
        }
        curl_close($ip_ch);
    } elseif ($ipver == 6 && is_linklocal($ip_address)) {
        log_error('Aborted IPv6 detection: cannot bind to link-local address');
        $ip_address = '';
    }

    if (($ipver == 6 && !is_ipaddrv6($ip_address)) || ($ipver != 6 && !is_ipaddrv4($ip_address))) {
        return 'down';
    }

    return $ip_address;
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
