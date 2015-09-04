<?php

/*
	Copyright (C) 2003-2004 Manuel Kasper <mk@neon1.net>.
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
require_once("IPv6.inc");

function killbyname($procname, $sig = 'TERM')
{
	if (!is_process_running($procname)) {
		return;
	}

	mwexecf('/bin/pkill -%s %s', array($sig, $procname));
}

function killbypid($pidfile, $sig = 'TERM')
{
	if (!isvalidpid($pidfile)) {
		return;
	}

	mwexecf('/bin/pkill -%s -F %s', array($sig, $pidfile));
}

function isvalidpid($pidfile)
{
	if (!file_exists($pidfile)) {
		return false;
	}

	return mwexecf('/bin/pgrep -nF %s', $pidfile, true) == 0;
}

function is_process_running($process)
{
	exec('/bin/pgrep -anx ' . escapeshellarg($process), $output, $retval);

	return (intval($retval) == 0);
}

function is_subsystem_dirty($subsystem = '')
{
	if ($subsystem == '') {
		return false;
	}

	if (file_exists("/tmp/{$subsystem}.dirty")) {
		return true;
	}

	return false;
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
function unlock($cfglckkey = 0)
{
	flock($cfglckkey, LOCK_UN);
	fclose($cfglckkey);
}

function is_module_loaded($module_name) {
	$module_name = str_replace(".ko", "", $module_name);
	$running = 0;
	$_gb = exec("/sbin/kldstat -qn {$module_name} 2>&1", $_gb, $running);
	if (intval($running) == 0)
		return true;
	else
		return false;
}

/* validate non-negative numeric string, or equivalent numeric variable */
function is_numericint($arg) {
	return (((is_int($arg) && $arg >= 0) || (is_string($arg) && strlen($arg) > 0 && ctype_digit($arg))) ? true : false);
}

/* return the subnet address given a host address and a subnet bit count */
function gen_subnet($ipaddr, $bits) {
	if (!is_ipaddr($ipaddr) || !is_numeric($bits))
		return "";
	return long2ip(ip2long($ipaddr) & gen_subnet_mask_long($bits));
}

/* return the subnet address given a host address and a subnet bit count */
function gen_subnetv6($ipaddr, $bits) {
	if (!is_ipaddrv6($ipaddr) || !is_numeric($bits))
		return "";

	$address = Net_IPv6::getNetmask($ipaddr, $bits);
	$address = Net_IPv6::compress($address);
	return $address;
}

/* return the highest (broadcast) address in the subnet given a host address and a subnet bit count */
function gen_subnet_max($ipaddr, $bits) {
	if (!is_ipaddr($ipaddr) || !is_numeric($bits))
		return "";

	return long2ip32(ip2long($ipaddr) | ~gen_subnet_mask_long($bits));
}

/* Generate end number for a given ipv6 subnet mask */
function gen_subnetv6_max($ipaddr, $bits) {
	if(!is_ipaddrv6($ipaddr))
		return false;

	$mask = Net_IPv6::getNetmask('FFFF:FFFF:FFFF:FFFF:FFFF:FFFF:FFFF:FFFF',$bits);

	$inet_ip = (binary)inet_pton($ipaddr);
	$inet_mask = (binary)inet_pton($mask);

	$inet_end = $inet_ip | ~$inet_mask;

	return (inet_ntop($inet_end));
}

/* returns a subnet mask (long given a bit count) */
function gen_subnet_mask_long($bits) {
	$sm = 0;
	for ($i = 0; $i < $bits; $i++) {
		$sm >>= 1;
		$sm |= 0x80000000;
	}
	return $sm;
}

/* same as above but returns a string */
function gen_subnet_mask($bits) {
	return long2ip(gen_subnet_mask_long($bits));
}

/* Convert long int to IP address, truncating to 32-bits. */
function long2ip32($ip) {
	return long2ip($ip & 0xFFFFFFFF);
}

/* Convert IP address to long int, truncated to 32-bits to avoid sign extension on 64-bit platforms. */
function ip2long32($ip) {
	return ( ip2long($ip) & 0xFFFFFFFF );
}

/* Convert IP address to unsigned long int. */
function ip2ulong($ip) {
	return sprintf("%u", ip2long32($ip));
}

/* Find out how many IPs are contained within a given IP range
 *  e.g. 192.168.0.0 to 192.168.0.255 returns 256
 */
function ip_range_size($startip, $endip) {
	if (is_ipaddr($startip) && is_ipaddr($endip)) {
		// Operate as unsigned long because otherwise it wouldn't work
		//   when crossing over from 127.255.255.255 / 128.0.0.0 barrier
		return abs(ip2ulong($startip) - ip2ulong($endip)) + 1;
	}
	return -1;
}

/* Find the smallest possible subnet mask which can contain a given number of IPs
 *  e.g. 512 IPs can fit in a /23, but 513 IPs need a /22
 */
function find_smallest_cidr($number) {
	$smallest = 1;
	for ($b=32; $b > 0; $b--) {
		$smallest = ($number <= pow(2,$b)) ? $b : $smallest;
	}
	return (32-$smallest);
}

/* Return the previous IP address before the given address */
function ip_before($ip) {
	return long2ip32(ip2long($ip)-1);
}

/* Return the next IP address after the given address */
function ip_after($ip) {
	return long2ip32(ip2long($ip)+1);
}

/* Return true if the first IP is 'before' the second */
function ip_less_than($ip1, $ip2) {
	// Compare as unsigned long because otherwise it wouldn't work when
	//   crossing over from 127.255.255.255 / 128.0.0.0 barrier
	return ip2ulong($ip1) < ip2ulong($ip2);
}

/* Return true if the first IP is 'after' the second */
function ip_greater_than($ip1, $ip2) {
	// Compare as unsigned long because otherwise it wouldn't work
	//   when crossing over from 127.255.255.255 / 128.0.0.0 barrier
	return ip2ulong($ip1) > ip2ulong($ip2);
}

/* Convert a range of IPs to an array of subnets which can contain the range. */
function ip_range_to_subnet_array($startip, $endip) {
	if (!is_ipaddr($startip) || !is_ipaddr($endip)) {
		return array();
	}

	// Container for subnets within this range.
	$rangesubnets = array();

	// Figure out what the smallest subnet is that holds the number of IPs in the given range.
	$cidr = find_smallest_cidr(ip_range_size($startip, $endip));

	// Loop here to reduce subnet size and retest as needed. We need to make sure
	//   that the target subnet is wholly contained between $startip and $endip.
	for ($cidr; $cidr <= 32; $cidr++) {
		// Find the network and broadcast addresses for the subnet being tested.
		$targetsub_min = gen_subnet($startip, $cidr);
		$targetsub_max = gen_subnet_max($startip, $cidr);

		// Check best case where the range is exactly one subnet.
		if (($targetsub_min == $startip) && ($targetsub_max == $endip)) {
			// Hooray, the range is exactly this subnet!
			return array("{$startip}/{$cidr}");
		}

		// These remaining scenarios will find a subnet that uses the largest
		//  chunk possible of the range being tested, and leave the rest to be
		//  tested recursively after the loop.

		// Check if the subnet begins with $startip and ends before $endip
		if (($targetsub_min == $startip) && ip_less_than($targetsub_max, $endip)) {
			break;
		}

		// Check if the subnet ends at $endip and starts after $startip
		if (ip_greater_than($targetsub_min, $startip) && ($targetsub_max == $endip)) {
			break;
		}

		// Check if the subnet is between $startip and $endip
		if (ip_greater_than($targetsub_min, $startip) && ip_less_than($targetsub_max, $endip)) {
			break;
		}
	}

	// Some logic that will recursivly search from $startip to the first IP before the start of the subnet we just found.
	// NOTE: This may never be hit, the way the above algo turned out, but is left for completeness.
	if ($startip != $targetsub_min) {
		$rangesubnets = array_merge($rangesubnets, ip_range_to_subnet_array($startip, ip_before($targetsub_min)));
	}

	// Add in the subnet we found before, to preserve ordering
	$rangesubnets[] = "{$targetsub_min}/{$cidr}";

	// And some more logic that will search after the subnet we found to fill in to the end of the range.
	if ($endip != $targetsub_max) {
		$rangesubnets = array_merge($rangesubnets, ip_range_to_subnet_array(ip_after($targetsub_max), $endip));
	}
	return $rangesubnets;
}

function is_iprange($range) {
	if (substr_count($range, '-') != 1) {
		return false;
	}
	list($ip1, $ip2) = explode ('-', $range);
	return (is_ipaddr($ip1) && is_ipaddr($ip2));
}

/* returns true if $ipaddr is a valid dotted IPv4 address or a IPv6 */
function is_ipaddr($ipaddr) {
	if(is_ipaddrv4($ipaddr)) {
		return true;
	}
	if(is_ipaddrv6($ipaddr)) {
		return true;
	}
	return false;
}

/* returns true if $ipaddr is a valid IPv6 address */
function is_ipaddrv6($ipaddr) {
	if (!is_string($ipaddr) || empty($ipaddr))
		return false;
	if (strstr($ipaddr, "%") && is_linklocal($ipaddr)) {
		$tmpip = explode("%", $ipaddr);
		$ipaddr = $tmpip[0];
	}
	if (strpos($ipaddr, ":") === false) {
		return false;
	} else {
		return Net_IPv6::checkIPv6($ipaddr);
	}
}

/* returns true if $ipaddr is a valid dotted IPv4 address */
function is_ipaddrv4($ipaddr) {
	if (!is_string($ipaddr) || empty($ipaddr))
		return false;

	$ip_long = ip2long($ipaddr);
	$ip_reverse = long2ip32($ip_long);

	if ($ipaddr == $ip_reverse)
		return true;
	else
		return false;
}

/* returns true if $ipaddr is a valid linklocal address */
function is_linklocal($ipaddr) {
	return (strtolower(substr($ipaddr, 0, 5)) == "fe80:");
}

/* returns scope of a linklocal address */
function get_ll_scope($addr) {
	if (!is_linklocal($addr) || !strstr($addr, "%"))
		return "";
	list ($ll, $scope) = explode("%", $addr);
	return $scope;
}

/* returns true if $ipaddr is a valid literal IPv6 address */
function is_literalipaddrv6($ipaddr) {
	if(preg_match("/\[([0-9a-f:]+)\]/i", $ipaddr, $match))
		$ipaddr = $match[1];
	else
		return false;

	return is_ipaddrv6($ipaddr);
}

function is_ipaddrwithport($ipport) {
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

function is_hostnamewithport($hostport) {
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
	global $config;

	if (is_alias($ipaddr)) {
		if (isset($config['aliases']['alias'])) {
			foreach ($config['aliases']['alias'] as $alias) {
				if ($alias['name'] == $ipaddr && !preg_match("/port/i", $alias['type']))
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
function is_subnet($subnet) {
	if (is_string($subnet) && preg_match('/^(?:([0-9.]{7,15})|([0-9a-f:]{2,39}))\/(\d{1,3})$/i', $subnet, $parts)) {
		if (is_ipaddrv4($parts[1]) && $parts[3] <= 32)
			return 4;
		if (is_ipaddrv6($parts[2]) && $parts[3] <= 128)
			return 6;
	}
	return false;
}

/* same as is_subnet() but accepts IPv4 only */
function is_subnetv4($subnet) {
	return (is_subnet($subnet) == 4);
}

/* same as is_subnet() but accepts IPv6 only */
function is_subnetv6($subnet) {
	return (is_subnet($subnet) == 6);
}

/* returns true if $hostname is a valid hostname */
function is_hostname($hostname) {
	if (!is_string($hostname))
		return false;

	if (preg_match('/^(?:(?:[a-z0-9_]|[a-z0-9_][a-z0-9_\-]*[a-z0-9_])\.)*(?:[a-z0-9_]|[a-z0-9_][a-z0-9_\-]*[a-z0-9_])$/i', $hostname))
		return true;
	else
		return false;
}

/* returns true if $domain is a valid domain name */
function is_domain($domain) {
	if (!is_string($domain))
		return false;

	if (preg_match('/^(?:(?:[a-z0-9]|[a-z0-9][a-z0-9\-]*[a-z0-9])\.)*(?:[a-z0-9]|[a-z0-9][a-z0-9\-]*[a-z0-9])$/i', $domain))
		return true;
	else
		return false;
}

/* returns true if $macaddr is a valid MAC address */
function is_macaddr($macaddr, $partial=false) {
	$repeat = ($partial) ? '1,5' : '5';
	return preg_match('/^[0-9A-F]{2}(?:[:][0-9A-F]{2}){'.$repeat.'}$/i', $macaddr) == 1 ? true : false;
}

/* returns true if $name is a valid name for an alias
   returns NULL if a reserved word is used
   returns FALSE for bad chars in the name - this allows calling code to determine what the problem was.
   aliases cannot be:
	bad chars: anything except a-z 0-9 and underscore
	bad names: empty string, pure numeric, pure underscore
	reserved words: pre-defined service/protocol/port names which should not be ambiguous, and the words "port" and  "pass" */

function is_validaliasname($name) {
	/* Array of reserved words */
	$reserved = array("port", "pass");

	if (!is_string($name) || strlen($name) >= 32 || preg_match('/(^_*$|^\d*$|[^a-z0-9_])/i', $name))
		return false;
	if (in_array($name, $reserved, true) || getservbyname($name, "tcp") || getservbyname($name, "udp") || getprotobyname($name))
		return; /* return NULL */
	return true;
}

/* returns true if $port is a valid TCP/UDP port */
function is_port($port) {
	if (getservbyname($port, "tcp") || getservbyname($port, "udp"))
		return true;
	if (!ctype_digit($port))
		return false;
	else if ((intval($port) < 1) || (intval($port) > 65535))
		return false;
	return true;
}

/* returns true if $portrange is a valid TCP/UDP portrange ("<port>:<port>") */
function is_portrange($portrange) {
	$ports = explode(":", $portrange);

	return (count($ports) == 2 && is_port($ports[0]) && is_port($ports[1]));
}

/* returns true if $port is a valid port number or an alias thereof */
function is_portoralias($port)
{
	global $config;

	if (is_alias($port)) {
		if (isset($config['aliases']['alias'])) {
			foreach ($config['aliases']['alias'] as $alias) {
				if ($alias['name'] == $port && preg_match("/port/i", $alias['type']))
					return true;
				}
			}
			return false;
	}

	return is_port($port);
}

/* create ranges of sequential port numbers (200:215) and remove duplicates */
function group_ports($ports) {
	if (!is_array($ports) || empty($ports))
		return;

	$uniq = array();
	foreach ($ports as $port) {
		if (is_portrange($port)) {
			list($begin, $end) = explode(":", $port);
			if ($begin > $end) {
				$aux = $begin;
				$begin = $end;
				$end = $aux;
			}
			for ($i = $begin; $i <= $end; $i++)
				if (!in_array($i, $uniq))
					$uniq[] = $i;
		} else if (is_port($port)) {
			if (!in_array($port, $uniq))
				$uniq[] = $port;
		}
	}
	sort($uniq, SORT_NUMERIC);

	$result = array();
	foreach ($uniq as $idx => $port) {
		if ($idx == 0) {
			$result[] = $port;
			continue;
		}

		$last = end($result);
		if (is_portrange($last))
			list($begin, $end) = explode(":", $last);
		else
			$begin = $end = $last;

		if ($port == ($end+1)) {
			$end++;
			$result[count($result)-1] = "{$begin}:{$end}";
		} else {
			$result[] = $port;
		}
	}

	return $result;
}

/* returns true if $val is a valid shaper bandwidth value */
function is_valid_shaperbw($val) {
	return (preg_match("/^(\d+(?:\.\d+)?)([MKG]?b|%)$/", $val));
}

/* returns true if $test is in the range between $start and $end */
function is_inrange_v4($test, $start, $end) {
	if ( (ip2ulong($test) <= ip2ulong($end)) && (ip2ulong($test) >= ip2ulong($start)) )
		return true;
	else
		return false;
}

/* returns true if $test is in the range between $start and $end */
function is_inrange_v6($test, $start, $end) {
	if ( (inet_pton($test) <= inet_pton($end)) && (inet_pton($test) >= inet_pton($start)) )
		return true;
	else
		return false;
}

/* returns true if $test is in the range between $start and $end */
function is_inrange($test, $start, $end) {
	return is_ipaddrv6($test) ? is_inrange_v6($test, $start, $end) : is_inrange_v4($test, $start, $end);
}

/* XXX: return the configured carp interface list */
function get_configured_carp_interface_list($carpinterface = '', $family = 'inet')
{
	global $config;

	$iflist = array();

	if (isset($config['virtualip']['vip'])) {
		$viparr = &$config['virtualip']['vip'];
		foreach ($viparr as $vip) {
			switch ($vip['mode']) {
			case "carp":
				if (!empty($carpinterface)) {
					if ($carpinterface == "{$vip['interface']}_vip{$vip['vhid']}") {
						if ($family == "inet" && is_ipaddrv4($vip['subnet']))
							return $vip['subnet'];
						else if ($family == "inet6" && is_ipaddrv6($vip['subnet']))
							return $vip['subnet'];
					}
				} else {
					$iflist["{$vip['interface']}_vip{$vip['vhid']}"] = $vip['subnet'];
				}
				break;
			}
		}
	}

	return $iflist;
}

/* return the configured IP aliases list */
function get_configured_ip_aliases_list($returnfullentry = false)
{
	global $config;

	$alias_list = array();

	if (isset($config['virtualip']['vip'])) {
		$viparr = &$config['virtualip']['vip'];
		foreach ($viparr as $vip) {
			if ($vip['mode']=="ipalias") {
				if ($returnfullentry)
					$alias_list[$vip['subnet']] = $vip;
				else
					$alias_list[$vip['subnet']] = $vip['interface'];
			}
		}
	}

	return $alias_list;
}

/* return all configured aliases list (IP, carp, proxyarp and other) */
function get_configured_vips_list()
{
	global $config;

	$alias_list=array();

	if (isset($config['virtualip']['vip'])) {
		$viparr = &$config['virtualip']['vip'];
		foreach ($viparr as $vip) {
			if ($vip['mode'] == "carp")
				$alias_list[] = array("ipaddr" => $vip['subnet'], "if" => "{$vip['interface']}_vip{$vip['vhid']}");
			else
				$alias_list[] = array("ipaddr" => $vip['subnet'], "if" => $vip['interface']);
		}
	}

	return $alias_list;
}

/* comparison function for sorting by the order in which interfaces are normally created */
function compare_interface_friendly_names($a, $b) {
	if ($a == $b)
		return 0;
	else if ($a == 'wan')
		return -1;
	else if ($b == 'wan')
		return 1;
	else if ($a == 'lan')
		return -1;
	else if ($b == 'lan')
		return 1;

	return strnatcmp($a, $b);
}

/* return the configured interfaces list. */
function get_configured_interface_list($only_opt = false, $withdisabled = false) {
	global $config;

	$iflist = array();

	/* if list */
	if (isset($config['interfaces'])) {
		foreach($config['interfaces'] as $if => $ifdetail) {
			if ($only_opt && ($if == "wan" || $if == "lan")) {
				continue;
			}
			if (isset($ifdetail['enable']) || $withdisabled == true) {
				$iflist[$if] = $if;
			}
		}
	}

	return $iflist;
}

/* return the configured interfaces list. */
function get_configured_interface_list_by_realif($only_opt = false, $withdisabled = false) {
	global $config;

	$iflist = array();

	/* if list */
	if (isset($config['interfaces'])) {
		foreach($config['interfaces'] as $if => $ifdetail) {
			if ($only_opt && ($if == "wan" || $if == "lan"))
				continue;
			if (isset($ifdetail['enable']) || $withdisabled == true) {
				$tmpif = get_real_interface($if);
				if (!empty($tmpif))
					$iflist[$tmpif] = $if;
			}
		}
	}

	return $iflist;
}

/* return the configured interfaces list with their description. */
function get_configured_interface_with_descr($only_opt = false, $withdisabled = false) {
	global $config;

	$iflist = array();

	/* if list */
	if (isset($config['interfaces'])) {
		foreach($config['interfaces'] as $if => $ifdetail) {
			if ($only_opt && ($if == "wan" || $if == "lan"))
				continue;
			if (isset($ifdetail['enable']) || $withdisabled == true) {
				if(empty($ifdetail['descr']))
					$iflist[$if] = strtoupper($if);
				else
					$iflist[$if] = strtoupper($ifdetail['descr']);
			}
		}
	}

	return $iflist;
}

/*
 *   get_configured_ip_addresses() - Return a list of all configured
 *   interfaces IP Addresses
 *
 */
function get_configured_ip_addresses() {
	global $config;

	$ip_array = array();
	$interfaces = get_configured_interface_list();
	if (is_array($interfaces)) {
		foreach($interfaces as $int) {
			$ipaddr = get_interface_ip($int);
			$ip_array[$int] = $ipaddr;
		}
	}
	$interfaces = get_configured_carp_interface_list();
	if (is_array($interfaces)) {
		foreach($interfaces as $int => $ipaddr) {
			$ip_array[$int] = $ipaddr;
		}
	}

	/* pppoe server */
	if (isset($config['pppoes']['pppoe'])) {
		foreach($config['pppoes']['pppoe'] as $pppoe) {
			if ($pppoe['mode'] == "server") {
				if(is_ipaddr($pppoe['localip'])) {
					$int = "pppoes". $pppoe['pppoeid'];
					$ip_array[$int] = $pppoe['localip'];
				}
			}
		}
	}

	return $ip_array;
}

/*
 *   get_configured_ipv6_addresses() - Return a list of all configured
 *   interfaces IPv6 Addresses
 *
 */
function get_configured_ipv6_addresses()
{
	$ipv6_array = array();
	$interfaces = get_configured_interface_list();
	if(is_array($interfaces)) {
		foreach($interfaces as $int) {
			$ipaddrv6 = get_interface_ipv6($int);
			$ipv6_array[$int] = $ipaddrv6;
		}
	}
	$interfaces = get_configured_carp_interface_list();
	if(is_array($interfaces))
		foreach($interfaces as $int => $ipaddrv6)
			$ipv6_array[$int] = $ipaddrv6;
	return $ipv6_array;
}

/*
 *   get_interface_list() - Return a list of all physical interfaces
 *   along with MAC, IPv4 and status.
 *
 *   $only_active = false -- interfaces that are available in the system
 *                  true  -- interfaces that are physically connected
 */
function get_interface_list($only_active = false)
{
	global $config;
	$iflist = array();

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

	if ($only_active) {
		$_ifnames = array();

		foreach ($ifnames as $ifname) {
			$ifinfo = legacy_interface_stats($ifname);
			if (isset($ifinfo['link state'])) {
				if ($ifinfo['link state'] == '2') {
					$_ifnames[] = $ifname;
				}
			}
		}

		$ifnames = $_ifnames;
	}

	foreach ($ifnames as $ifname) {
		if (in_array(array_shift(preg_split('/\d/', $ifname)), $vfaces)) {
			continue;
		}

		$ifdata = pfSense_get_interface_addresses($ifname);

		$toput = array(
			'up' => in_array($ifname, $ifnames_up),
			'ipaddr' => $ifdata['ipaddr'],
			'mac' => $ifdata['macaddr']
		);

		if (isset($config['interfaces'])) {
			foreach($config['interfaces'] as $name => $int) {
				if ($int['if'] == $ifname) {
					$toput['friendly'] = $name;
					break;
				}
			}
		}

		$dmesg_arr = array();
		exec("/sbin/dmesg |grep $ifname | head -n1", $dmesg_arr);
		preg_match_all("/<(.*?)>/i", $dmesg_arr[0], $dmesg);
		if (isset($dmesg[1][0])) {
			$toput['dmesg'] = $dmesg[1][0];
		} else {
			$toput['dmesg'] = null;
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
function exec_command($command) {
	$output = array();
	exec($command . ' 2>&1', $output);
	return(implode("\n", $output));
}

/* wrapper for mwexec() ;) */
function mwexecf($format, $args = array(), $mute = false)
{
	if (!is_array($args)) {
		/* just in case there's only one argument */
		$args = array($args);
	}

	foreach ($args as $id => $arg) {
		$args[$id] = escapeshellarg($arg);
	}

	return mwexec(vsprintf($format, $args), $mute);
}

/* wrapper for exec() */
function mwexec($command, $mute = false)
{
	$oarr = array();
	$retval = 0;

	$garbage = exec("{$command} 2>&1", $oarr, $retval);
	unset($garbage);

	if ($retval != 0 && $mute == false) {
		$output = implode(' ', $oarr);
		log_error(sprintf(gettext("The command '%s' returned exit code '%d', the output was '%s'"), $command, $retval, $output));
		unset($output);
	}

	unset($oarr);

	return $retval;
}

/* wrapper for exec() in background */
function mwexec_bg($command, $mute = false)
{
	mwexec("/usr/sbin/daemon -f {$command}", $mute);
}


/* check if an alias exists */
function is_alias($name) {
	global $aliastable;

	return isset($aliastable[$name]);
}

function alias_get_type($name) {
	global $config;

	if (is_array($config['aliases']['alias'])) {
		foreach ($config['aliases']['alias'] as $alias) {
			if ($name == $alias['name'])
				return $alias['type'];
		}
	}

	return "";
}

/* expand a host or network alias, if necessary */
function alias_expand($name) {
	global $aliastable;

	if (isset($aliastable[$name]))
		return "\${$name}";
	else if (is_ipaddr($name) || is_subnet($name) || is_port($name) || is_portrange($name))
		return "{$name}";
	else
		return null;
}

function subnet_size($subnet) {
	if (is_subnetv4($subnet)) {
		list ($ip, $bits) = explode("/", $subnet);
		return round(exp(log(2) * (32 - $bits)));
	}
	else if (is_subnetv6($subnet)) {
		list ($ip, $bits) = explode("/", $subnet);
		return round(exp(log(2) * (128 - $bits)));
	}
	else {
		return 0;
	}
}

/* find out whether two subnets overlap */
function check_subnets_overlap($subnet1, $bits1, $subnet2, $bits2) {

	if (!is_numeric($bits1))
		$bits1 = 32;
	if (!is_numeric($bits2))
		$bits2 = 32;

	if ($bits1 < $bits2)
		$relbits = $bits1;
	else
		$relbits = $bits2;

	$sn1 = gen_subnet_mask_long($relbits) & ip2long($subnet1);
	$sn2 = gen_subnet_mask_long($relbits) & ip2long($subnet2);

	return ($sn1 == $sn2);
}

/* compare two IP addresses */
function ipcmp($a, $b) {
	if (ip_less_than($a, $b))
		return -1;
	else if (ip_greater_than($a, $b))
		return 1;
	else
		return 0;
}

/* return true if $addr is in $subnet, false if not */
function ip_in_subnet($addr,$subnet) {
	if(is_ipaddrv6($addr)) {
		return (Net_IPv6::isInNetmask($addr, $subnet));
	} else { /* XXX: Maybe check for IPv4 */
		list($ip, $mask) = explode('/', $subnet);
		$mask = (0xffffffff << (32 - $mask)) & 0xffffffff;
		return ((ip2long($addr) & $mask) == (ip2long($ip) & $mask));
	}
}



function mac_format($clientmac) {
	global $config, $cpzone;

	$mac = explode(":", $clientmac);
	$mac_format = $cpzone ? $config['captiveportal'][$cpzone]['radmac_format'] : false;

	switch($mac_format) {
	case 'singledash':
		return "$mac[0]$mac[1]$mac[2]-$mac[3]$mac[4]$mac[5]";

	case 'ietf':
		return "$mac[0]-$mac[1]-$mac[2]-$mac[3]-$mac[4]-$mac[5]";

	case 'cisco':
		return "$mac[0]$mac[1].$mac[2]$mac[3].$mac[4]$mac[5]";

	case 'unformatted':
		return "$mac[0]$mac[1]$mac[2]$mac[3]$mac[4]$mac[5]";

	default:
		return $clientmac;
	}
}

function resolve_retry($hostname, $retries = 5) {

	if (is_ipaddr($hostname))
		return $hostname;

	for ($i = 0; $i < $retries; $i++) {
		// FIXME: gethostbyname does not work for AAAA hostnames, boo, hiss
		$ip = gethostbyname($hostname);

		if ($ip && $ip != $hostname) {
			/* success */
			return $ip;
		}

		sleep(1);
	}

	return false;
}

function format_bytes($bytes) {
	if ($bytes >= 1073741824) {
		return sprintf("%.2f GB", $bytes/1073741824);
	} else if ($bytes >= 1048576) {
		return sprintf("%.2f MB", $bytes/1048576);
	} else if ($bytes >= 1024) {
		return sprintf("%.0f KB", $bytes/1024);
	} else {
		return sprintf("%d bytes", $bytes);
	}
}

function update_filter_reload_status($text)
{
	file_put_contents('/var/run/filter_reload_status', $text);
}

/****** util/return_dir_as_array
 * NAME
 *   return_dir_as_array - Return a directory's contents as an array.
 * INPUTS
 *   $dir          - string containing the path to the desired directory.
 *   $filter_regex - string containing a regular expression to filter file names. Default empty.
 * RESULT
 *   $dir_array - array containing the directory's contents. This array will be empty if the path specified is invalid.
 ******/
function return_dir_as_array($dir, $filter_regex = '') {
	$dir_array = array();
	if (is_dir($dir)) {
		if ($dh = opendir($dir)) {
			while (($file = readdir($dh)) !== false) {
				if (($file == ".") || ($file == ".."))
					continue;

				if (empty($filter_regex) || preg_match($filter_regex, $file))
					array_push($dir_array, $file);
			}
			closedir($dh);
		}
	}
	return $dir_array;
}

/*
 * get_sysctl($names)
 * Get values of sysctl OID's listed in $names (accepts an array or a single
 * name) and return an array of key/value pairs set for those that exist
 */
function get_sysctl($names) {
	if (empty($names))
		return array();

	if (is_array($names)) {
		$name_list = array();
		foreach ($names as $name) {
			$name_list[] = escapeshellarg($name);
		}
	} else
		$name_list = array(escapeshellarg($names));

	exec("/sbin/sysctl -i " . implode(" ", $name_list), $output);
	$values = array();
	foreach ($output as $line) {
		$line = explode(": ", $line, 2);
		if (count($line) == 2)
			$values[$line[0]] = $line[1];
	}

	return $values;
}

/*
 * get_single_sysctl($name)
 * Wrapper for get_sysctl() to simplify read of a single sysctl value
 * return the value for sysctl $name or empty string if it doesn't exist
 */
function get_single_sysctl($name) {
	if (empty($name))
		return "";

	$value = get_sysctl($name);
	if (empty($value) || !isset($value[$name]))
		return "";

	return $value[$name];
}

/*
 * set_sysctl($value_list)
 * Set sysctl OID's listed as key/value pairs and return
 * an array with keys set for those that succeeded
 */
function set_sysctl($values) {
	if (empty($values))
		return array();

	$value_list = array();
	foreach ($values as $key => $value) {
		$value_list[] = escapeshellarg($key) . "=" . escapeshellarg($value);
	}

	exec("/sbin/sysctl -i " . implode(" ", $value_list), $output, $success);

	/* Retry individually if failed (one or more read-only) */
	if ($success <> 0 && count($value_list) > 1) {
		foreach ($value_list as $value) {
			exec("/sbin/sysctl -i " . $value, $output);
		}
	}

	$ret = array();
	foreach ($output as $line) {
		$line = explode(": ", $line, 2);
		if (count($line) == 2)
			$ret[$line[0]] = true;
	}

	return $ret;
}

/*
 * set_single_sysctl($name, $value)
 * Wrapper to set_sysctl() to make it simple to set only one sysctl
 * returns boolean meaning if it suceed
 */
function set_single_sysctl($name, $value) {
	if (empty($name))
		return false;

	$result = set_sysctl(array($name => $value));

	if (!isset($result[$name]) || $result[$name] != $value)
		return false;

	return true;
}

function mute_kernel_msgs()
{
	global $config;

	if (isset($config['system']['enableserial'])) {
		return;
	}

	exec('/sbin/conscontrol mute on');
}

function unmute_kernel_msgs()
{
	exec('/sbin/conscontrol mute off');
}

/****f* util/isAjax
 * NAME
 *   isAjax - reports if the request is driven from prototype
 * INPUTS
 *   none
 * RESULT
 *   true/false
 ******/
function isAjax() {
	return isset ($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest';
}

/****f* util/msort
 * NAME
 *   msort - sort array
 * INPUTS
 *   $array to be sorted, field to sort by, direction of sort
 * RESULT
 *   returns newly sorted array
 ******/
function msort($array, $id="id", $sort_ascending=true) {
	$temp_array = array();
	while(count($array)>0) {
		$lowest_id = 0;
		$index=0;
		foreach ($array as $item) {
			if (isset($item[$id])) {
				if ($array[$lowest_id][$id]) {
					if (strtolower($item[$id]) < strtolower($array[$lowest_id][$id])) {
						$lowest_id = $index;
					}
				}
			}
			$index++;
		}
		$temp_array[] = $array[$lowest_id];
		$array = array_merge(array_slice($array, 0,$lowest_id), array_slice($array, $lowest_id+1));
	}
	if ($sort_ascending) {
		return $temp_array;
	} else {
		return array_reverse($temp_array);
	}
}

/****f* util/is_URL
 * NAME
 *   is_URL
 * INPUTS
 *   string to check
 * RESULT
 *   Returns true if item is a URL
 ******/
function is_URL($url) {
	$match = preg_match("'\b(([\w-]+://?|www[.])[^\s()<>]+(?:\([\w\d]+\)|([^[:punct:]\s]|/)))'", $url);
	if($match)
		return true;
	return false;
}

function get_staticroutes($returnsubnetsonly = false, $returnhostnames = false)
{
	global $config, $aliastable;

	/* Bail if there are no routes, but return an array always so callers don't have to check. */
	if (!isset($config['staticroutes']['route'])) {
		return array();
	}

	$allstaticroutes = array();
	$allsubnets = array();
	/* Loop through routes and expand aliases as we find them. */
	foreach ($config['staticroutes']['route'] as $route) {
		if (is_alias($route['network'])) {
			if (!isset($aliastable[$route['network']]))
				continue;

			$subnets = preg_split('/\s+/', $aliastable[$route['network']]);
			foreach ($subnets as $net) {
				if (!is_subnet($net)) {
					if (is_ipaddrv4($net))
						$net .= "/32";
					else if (is_ipaddrv6($net))
						$net .= "/128";
					else if ($returnhostnames === false || !is_fqdn($net))
						continue;
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
	if ($returnsubnetsonly)
		return $allsubnets;
	else
		return $allstaticroutes;
}

/****f* util/get_alias_list
 * NAME
 *   get_alias_list - Provide a list of aliases.
 * INPUTS
 *   $type          - Optional, can be a string or array specifying what type(s) of aliases you need.
 * RESULT
 *   Array containing list of aliases.
 *   If $type is unspecified, all aliases are returned.
 *   If $type is a string, all aliases of the type specified in $type are returned.
 *   If $type is an array, all aliases of any type specified in any element of $type are returned.
 */
function get_alias_list($type = null)
{
	global $config;

	$result = array();

	if (isset($config['aliases']['alias'])) {
		foreach ($config['aliases']['alias'] as $alias) {
			if ($type === null) {
				$result[] = $alias['name'];
			} elseif (is_array($type)) {
				if (in_array($alias['type'], $type)) {
					$result[] = $alias['name'];
				}
			} elseif ($type === $alias['type']) {
				$result[] = $alias['name'];
			}
		}
	}

	return $result;
}

/* Define what is preferred, IPv4 or IPv6 */
function prefer_ipv4_or_ipv6() {
	global $config;

	if (isset($config['system']['prefer_ipv4']))
		mwexec("/etc/rc.d/ip6addrctl prefer_ipv4");
	else
		mwexec("/etc/rc.d/ip6addrctl prefer_ipv6");
}

/* Redirect to page passing parameters via POST */
function post_redirect($page, $params) {
	if (!is_array($params))
		return;

	print "<html><body><form action=\"{$page}\" name=\"formredir\" method=\"post\">\n";
	foreach ($params as $key => $value) {
		print "<input type=\"hidden\" name=\"{$key}\" value=\"{$value}\" />\n";
	}
	print "</form><script type=\"text/javascript\">document.formredir.submit();</script>\n";
	print "</body></html>\n";
}
