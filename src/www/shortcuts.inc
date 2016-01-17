<?php

/*
	Copyright (C) 2014-2015 Deciso B.V.
	Copyright (C) 2012 Jim Pingle
	Copyright (C) 2007, 2008 Scott Ullrich <sullrich@gmail.com>
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

$shortcuts = array();

function get_shortcut_by_service_name($servicename)
{
	global $shortcuts;

	foreach ($shortcuts as $name => $shortcut) {
		if (!empty($shortcut['service']) && ($shortcut['service'] == $servicename)) {
			return $name;
		}
	}

	return null;
}

$shortcuts['upnp'] = array();
$shortcuts['upnp']['service'] = "miniupnpd";

$shortcuts['relayd'] = array();
$shortcuts['relayd']['service'] = "relayd";

$shortcuts['dhcp'] = array();
$shortcuts['dhcp']['service'] = "dhcpd";

$shortcuts['ipsec'] = array();
$shortcuts['ipsec']['service'] = "ipsec";

$shortcuts['openvpn'] = array();
$shortcuts['openvpn']['service'] = "openvpn";

$shortcuts['forwarder'] = array();
$shortcuts['forwarder']['service'] = 'dnsmasq';

$shortcuts['resolver'] = array();
$shortcuts['resolver']['service'] = 'unbound';

$shortcuts['ntp'] = array();
$shortcuts['ntp']['service'] = 'ntpd';

$shortcuts['gateways'] = array();
$shortcuts['gateways']['service'] = "apinger";

$shortcuts['snmp'] = array();
$shortcuts['snmp']['service'] = "bsnmpd";

$shortcuts['squid'] = array();
$shortcuts['squid']['service'] = "squid";
