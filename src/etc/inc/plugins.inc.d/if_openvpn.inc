<?php

/*
    Copyright (C) 2016 Deciso B.V.
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

function if_openvpn_syslog()
{
    $logfacilities = array();

    $logfacilities['openvpn'] = array(
        'facility' => array('openvpn'),
        'remote' => 'vpn',
    );

    return $logfacilities;
}

function if_openvpn_services()
{
    global $config;

    $services = array();

    foreach (array('server', 'client') as $mode) {
        if (isset($config['openvpn']["openvpn-{$mode}"])) {
            foreach ($config['openvpn']["openvpn-{$mode}"] as $setting) {
                if (!isset($setting['disable'])) {
                    $pconfig = array();
                    /* XXX clean up name print */
                    $pconfig['description'] = gettext("OpenVPN") . " ".$mode.": ".htmlspecialchars($setting['description']);
                    $pconfig['php']['restart'] = array('openvpn_restart_by_id');
                    $pconfig['php']['start'] = array('openvpn_restart_by_id');
                    $pconfig['php']['args'] = array('mode', 'id');
                    $pconfig['pidfile'] = "/var/run/openvpn_{$mode}{$setting['vpnid']}.pid";
                    $pconfig['id'] = $setting['vpnid'];
                    $pconfig['name'] = 'openvpn';
                    $pconfig['mode'] = $mode;
                    $services[] = $pconfig;
                }
            }
        }
    }

    return $services;
}

function if_openvpn_interfaces()
{
    global $config;

    $interfaces = array();

    /* add openvpn interfaces */
    if (isset($config['openvpn']['openvpn-server']) || isset($config['openvpn']['openvpn-client'])) {
        $oic = array("enable" => true);
        $oic['if'] = "openvpn";
        $oic['descr'] = 'OpenVPN';
        $oic['type'] = "none";
        $oic['virtual'] = true;
        $oic['networks'] = array();
        $interfaces['openvpn'] = $oic;
    }

    return $interfaces;
}
