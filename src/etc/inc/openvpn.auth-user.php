#!/usr/local/bin/php
<?php

/*
	Copyright (C) 2008 Shrew Soft Inc
	Copyright (C) 2010 Ermal Luçi
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

/*
 * OpenVPN calls this script to authenticate a user
 * based on a username and password. We lookup these
 * in our config.xml file and check the credentials.
 */

require_once("config.inc");
require_once("radius.inc");
require_once("auth.inc");
require_once("util.inc");
require_once("interfaces.inc");

function cisco_to_cidr($addr) {
	if (!is_ipaddr($addr))
		return 0;
	$mask = decbin(~ip2long($addr));
	$mask = substr($mask, -32);
	$k = 0;
	for ($i = 0; $i <= 32; $i++) {
		$k += intval($mask[$i]);
	}
	return $k;
}

function cisco_extract_index($prule) {

	$index = explode("#", $prule);
	if (is_numeric($index[1]))
		return intval($index[1]);
	else
		syslog(LOG_WARNING, "Error parsing rule {$prule}: Could not extract index");
	return -1;;
}

function parse_cisco_acl($attribs) {
	global $devname, $attributes;
	if (!is_array($attribs))
		return "";
	$finalrules = "";
	if (is_array($attribs['ciscoavpair'])) {
		$inrules = array();
		$outrules = array();
		foreach ($attribs['ciscoavpair'] as $avrules) {
			$rule = explode("=", $avrules);
			$dir = "";
			if (strstr($rule[0], "inacl")) {
				$dir = "in";
			} else if (strstr($rule[0], "outacl"))
				$dir = "out";
			else if (strstr($rule[0], "dns-servers")) {
				$attributes['dns-servers'] = explode(" ", $rule[1]);
				continue;
			} else if (strstr($rule[0], "route")) {
				if (!is_array($attributes['routes']))
					$attributes['routes'] = array();
				$attributes['routes'][] = $rule[1];
				continue;
			}
			$rindex = cisco_extract_index($rule[0]);
			if ($rindex < 0)
				continue;

			$rule = $rule[1];
			$rule = explode(" ", $rule);
			$tmprule = "";
			$index = 0;
			$isblock = false;
			if ($rule[$index] == "permit")
				$tmprule = "pass {$dir} quick on {$devname} ";
			else if ($rule[$index] == "deny") {
				//continue;
				$isblock = true;
				$tmprule = "block {$dir} quick on {$devname} ";
			} else {
				continue;
			}

			$index++;

			switch ($rule[$index]) {
			case "tcp":
			case "udp":
				$tmprule .= "proto {$rule[$index]} ";
				break;

			}

			$index++;
			/* Source */
			if (trim($rule[$index]) == "host") {
				$index++;
				$tmprule .= "from {$rule[$index]} ";
				$index++;
				if ($isblock == true)
					$isblock = false;
			} else if (trim($rule[$index]) == "any") {
				$tmprule .= "from any";
				$index++;
			} else {
				$tmprule .= "from {$rule[$index]}";
				$index++;
				$netmask = cisco_to_cidr($rule[$index]);
				$tmprule .= "/{$netmask} ";
				$index++;
				if ($isblock == true)
					$isblock = false;
			}
			/* Destination */
			if (trim($rule[$index]) == "host") {
				$index++;
				$tmprule .= "to {$rule[$index]} ";
				$index++;
				if ($isblock == true)
					$isblock = false;
			} else if (trim($rule[$index]) == "any") {
				$index++;
				$tmprule .= "to any";
			} else {
				$tmprule .= "to {$rule[$index]}";
				$index++;
				$netmask = cisco_to_cidr($rule[$index]);
				$tmprule .= "/{$netmask} ";
				$index++;
				if ($isblock == true)
					$isblock = false;
			}

			if ($isblock == true)
				continue;

			if ($dir == "in")
				$inrules[$rindex] = $tmprule;
			else if ($dir == "out")
				$outrules[$rindex] = $tmprule;
		}


		$state = "";
		if (!empty($outrules))
			$state = "no state";
		ksort($inrules, SORT_NUMERIC);
		foreach ($inrules as $inrule)
			$finalrules .= "{$inrule} {$state}\n";
		if (!empty($outrules)) {
			ksort($outrules, SORT_NUMERIC);
			foreach ($outrules as $outrule)
				$finalrules .= "{$outrule} {$state}\n";
		}
	}
	return $finalrules;
}


/**
 * Get the NAS-Identifier
 *
 * We will use our local hostname to make up the nas_id
 */
if (!function_exists("getNasID")) {
function getNasID()
{
    global $g;

    $nasId = gethostname();
    if(empty($nasId))
        $nasId = $g['product_name'];
    return $nasId;
}
}

/**
 * Get the NAS-IP-Address based on the current wan address
 *
 * Use functions in interfaces.inc to find this out
 *
 */
if (!function_exists("getNasIP")) {
function getNasIP()
{
    $nasIp = get_interface_ip();
    if(!$nasIp)
        $nasIp = "0.0.0.0";
    return $nasIp;
}
}
/* setup syslog logging */
openlog("openvpn", LOG_ODELAY, LOG_AUTH);

if (count($argv) > 6) {
	$authmodes = explode(',', $argv[5]);
        $username = base64_decode(str_replace('%3D', '=', $argv[1]));
        $password = base64_decode(str_replace('%3D', '=', $argv[2]));
	$common_name = $argv[3];
	$modeid = $argv[6];
	$strictusercn = $argv[4] == 'false' ? false : true;
} else {
	/* read data from environment */
	$username = getenv("username");
	$password = getenv("password");
	$common_name = getenv("common_name");
}

if (!$username || !$password) {
	syslog(LOG_ERR, "invalid user authentication environment");
	closelog();
	exit(-1);
}

/* Replaced by a sed with propper variables used below(ldap parameters). */
//<template>

if (file_exists("/var/etc/openvpn/{$modeid}.ca")) {
	putenv("LDAPTLS_CACERT=/var/etc/openvpn/{$modeid}.ca");
	putenv("LDAPTLS_REQCERT=never");
}

$authenticated = false;

if (($strictusercn === true) && ($common_name != $username)) {
	syslog(LOG_WARNING, "Username does not match certificate common name ({$username} != {$common_name}), access denied.\n");
	closelog();
	exit(1);
}

if (!is_array($authmodes)) {
	syslog(LOG_WARNING, "No authentication server has been selected to authenticate against. Denying authentication for user {$username}");
	closelog();
	exit(1);
}

$attributes = array();
foreach ($authmodes as $authmode) {
	$authcfg = auth_get_authserver($authmode);
	if (!$authcfg && $authmode != "local")
		continue;

	$authenticated = authenticate_user($username, $password, $authcfg);
	if ($authenticated == true)
		break;
}

if ($authenticated == false) {
	syslog(LOG_WARNING, "user '{$username}' could not authenticate.\n");
	closelog();
	exit(-1);
}

if (empty($common_name)) {
	$common_name = getenv("common_name");
	if (empty($common_name))
		$common_name = getenv("username");
}

$devname = getenv("dev");
if (empty($devname))
	$devname = "openvpn";


$rules = parse_cisco_acl($attributes);
if (!empty($rules)) {
	$pid = getmypid();
	@file_put_contents("/tmp/ovpn_{$pid}{$common_name}.rules", $rules);
	mwexec("/sbin/pfctl -a " . escapeshellarg("openvpn/{$common_name}") . " -f /tmp/ovpn_{$pid}" . escapeshellarg($common_name) . ".rules");
	@unlink("/tmp/ovpn_{$pid}{$common_name}.rules");
}

$content = "";
if (is_array($attributes['dns-servers'])) {
        foreach ($attributes['dns-servers'] as $dnssrv) {
                if (is_ipaddr($dnssrv))
                        $content .= "push \"dhcp-option DNS {$dnssrv}\"\n";
        }
}
if (is_array($attributes['routes'])) {
        foreach ($attributes['routes'] as $route)
		$content .= "push \"route {$route} vpn_gateway\"\n";
}

if (isset($attributes['framed_ip'])) {
/* XXX: only use when TAP windows driver >= 8.2.x */
/*      if (isset($attributes['framed_mask'])) {
                $content .= "topology subnet\n";
                $content .= "ifconfig-push {$attributes['framed_ip']} {$attributes['framed_mask']}";
        } else {
*/
                $content .= "topology net30\n";
                $content .= "ifconfig-push {$attributes['framed_ip']} ". long2ip((ip2long($attributes['framed_ip']) + 1));
//      }
}

if (!empty($content)) {
        @file_put_contents("/tmp/{$username}", $content);
}

syslog(LOG_NOTICE, "user '{$username}' authenticated\n");
closelog();

exit(0);
