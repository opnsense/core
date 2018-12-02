<?php

/*
 * Copyright (C) 2014-2018 Franco Fichtner <franco@opnsense.org>
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

require_once("config.inc");
require_once("interfaces.inc");
require_once("util.inc");

function get_dyndns_ip($int, $ipver = 4, $service = null)
{
    global $config;

    $int_ip_address = ($ipver == 6 ? get_interface_ipv6($int) : get_interface_ip($int)) ?: '';
    if (empty($int_ip_address)) {
        $status = sprintf(gettext("Aborted IPv%s detection: no address for %s"), "{$ipver}", "{$int}");
        log_error($status);
        $pub_ip_address = '';
        return array ('Service'=>($servicename ?: ''), 'Interface'=>$int, 'IPVersion'=>$ipver, 'IntIPAddress'=>$int_ip_address, 'PubIPAddress'=>$pub_ip_address, 'Status'=>$status);
    }

    $gateways_status = return_gateways_status();

    /*
     * If the gateway for this interface is down, then the external
     * check cannot work. Avoid the long wait for the external check
     * to timeout.
     */
    if (
        !empty($gateways_status[$config['interfaces'][$int]['gateway']]['status']) &&
        stristr($gateways_status[$config['interfaces'][$int]['gateway']]['status'], 'down')
    ) {
        $status = sprintf(gettext("Aborted IPv%s detection: gateway down for %s"), "{$ipver}", "{$int}");
        log_error($status);
        $pub_ip_address = '';
        return array ('Service'=>($servicename ?: ''), 'Interface'=>$int, 'IPVersion'=>$ipver, 'IntIPAddress'=>$int_ip_address, 'PubIPAddress'=>$pub_ip_address, 'Status'=>$status);
    }

    if ($ipver != 6 && is_private_ip($int_ip_address)) {

        // Use the Check IP Services plugin if it is installed.  Else use hard coded service.
        if (stream_resolve_include_path('plugins.inc.d/dyndns/checkipsrvsplugin.inc')) {
            include('plugins.inc.d/dyndns/checkipsrvsplugin.inc');
        } else {
            $servicename = 'DynDNS (hard coded)';               // Chinese alternative is: '3322 (hard coded)'
            $hosttocheck = 'http://checkip.dyndns.org';         // Chinese alternative is: 'http://ip.3322.net/'
            $regex = '<body>Current IP Address: (.*)</body>';   // Chinese alternative is: '^(.*)$'
            $username = '';
            $password = '';
            $verifysslpeer = false;
        }

        $ip_ch = curl_init($hosttocheck);
        curl_setopt($ip_ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ip_ch, CURLOPT_SSL_VERIFYPEER, $verifysslpeer);
        curl_setopt($ip_ch, CURLOPT_INTERFACE, $int_ip_address);
        curl_setopt($ip_ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ip_ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ip_ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
        curl_setopt($ip_ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
        curl_setopt($ip_ch, CURLOPT_USERPWD, "{$username}:{$password}");
        $ip_result = curl_exec($ip_ch);
        if ($ip_result !== false) {
            preg_match('=' . $regex . '=siU', $ip_result, $matches);
            $pub_ip_address = trim($matches[1]);
            $status = ($pub_ip_address ? gettext('Succeeded') : gettext('Failed'));
        } else {
            $status = sprintf(gettext("Aborted IPv4 detection: %s"), curl_error($ip_ch));
            log_error($status);
            $pub_ip_address = '';
        }
        curl_close($ip_ch);
    } elseif ($ipver == 6 && is_linklocal($int_ip_address)) {
        $status = gettext('Aborted IPv6 detection: cannot bind to link-local address');
        log_error($status);
        $pub_ip_address = '';
    } else {
        $pub_ip_address = $int_ip_address;
        $status = ($pub_ip_address ? gettext('Succeeded') : gettext('Failed'));
    }

    if (($ipver == 6 && !is_ipaddrv6($pub_ip_address)) || ($ipver != 6 && !is_ipaddrv4($pub_ip_address))) {
        $status = gettext('Invalid IP address');
        $pub_ip_address = '';
    }

    return array ('Service'=>($servicename ?: ''), 'Interface'=>$int, 'IPVersion'=>$ipver, 'IntIPAddress'=>$int_ip_address, 'PubIPAddress'=>$pub_ip_address, 'Status'=>$status);
}
