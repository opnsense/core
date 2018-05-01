<?php

/*
 * Copyright (C) 2018 Christoph Engelbert <me@noctarius.com>
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

require_once("interfaces.inc");
require_once("util.inc");
require_once("system.inc");

function dslite_find_tunnel_interface($parent)
{
    $dslite_tunnel_file = sprintf("/tmp/dslite_%s.state", $parent);
    $gifif = @file_get_contents($dslite_tunnel_file);
    return ($gifif);
}

function dslite_find_aftr_address($parent)
{
    $dslite_aftr_file = sprintf("/tmp/dslite_%s_aftr.state", $parent);
    $aftr_addr = @file_get_contents($dslite_aftr_file);
    return ($aftr_addr);
}

function setup_dslite_tunnel($verbose, $parent, $aftr)
{
    global $config;

    // Should be tested outside, but let's make sure we won't mess around
    if ($config['interfaces'][$parent]['ipaddr'] != "dslite") {
        return;
    }

    $wanconfig = $config['interfaces'][$parent];
    $mtu = !empty($wanconfig['dslite_tunnel_mtu']) ? $wanconfig['dslite_tunnel_mtu'] : '';
    $ip = get_interface_ipv6($parent);

    if ($verbose) {
        log_error(sprintf("Starting DS-Lite tunnel with AFTR '%s' on interface %s with external IP '%s'", $aftr, $parent, $ip));
    }

    $gifif = legacy_interface_create('gif');

    $pmtu = '';
    if (!empty($mtu)) {
        $pmtu = sprintf('mtu %s', $mtu);
    }

    mwexecf('/sbin/ifconfig %s inet6 tunnel %s %s %s -accept_rtadv ifdisabled', array($gifif, $ip, $aftr, $pmtu));
    mwexecf("/sbin/ifconfig %s inet 192.0.0.2 192.0.0.1 netmask 255.255.255.248", array($gifif));

    $dslite_tunnel_file = sprintf("/tmp/dslite_%s.state", $parent);
    @file_put_contents($dslite_tunnel_file, $gifif);

    $dslite_aftr_file = sprintf("/tmp/dslite_%s_aftr.state", $parent);
    @file_put_contents($dslite_aftr_file, $aftr);
}

function destroy_dslite_tunnel($verbose, $parent)
{
    global $config;

    // Should be tested outside, but let's make sure we won't mess around
    if ($config['interfaces'][$parent]['ipaddr'] != "dslite") {
        return;
    }

    $gifif = dslite_find_tunnel_interface($parent);
    if (!empty($gifif)) {
        if ($verbose) {
            log_error(sprintf("Destroying DS-Lite tunnel %s on interface %s", $gifif, $parent));
        }
        mwexecf("/sbin/ifconfig %s destroy", $gifif);
    }
}
