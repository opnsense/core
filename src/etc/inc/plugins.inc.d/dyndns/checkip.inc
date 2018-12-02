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

function get_dyndns_ip($int, $ipver = 4)
{
    global $config;

    $ip_address = $ipver == 6 ? get_interface_ipv6($int) : get_interface_ip($int);
    if (empty($ip_address)) {
        log_error("Aborted IPv{$ipver} detection: no address for {$int}");
        return 'down';
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
        log_error("Aborted IPv{$ipver} detection: gateway down for {$int}");
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
