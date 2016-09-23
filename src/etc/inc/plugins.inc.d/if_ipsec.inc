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

function if_ipsec_syslog()
{
    $logfacilities = array();

    $logfacilities['ipsec'] = array(
        'facility' => array('charon'),
        'remote' => 'vpn',
    );

    return $logfacilities;
}

function if_ipsec_services()
{
    global $config;

    $services = array();

    if (isset($config['ipsec']['enable']) || isset($config['ipsec']['client']['enable'])) {
        $pconfig = array();
        $pconfig['name'] = 'ipsec';
        $pconfig['description'] = gettext('IPsec VPN');
        $pconfig['pidfile'] = '/var/run/charon.pid';
        $pconfig['php']['restart'] = array('ipsec_force_reload');
        $pconfig['php']['start'] = array('ipsec_force_reload');
        $pconfig['mwexec']['stop'] = array('/usr/local/sbin/ipsec stop');
        $services[] = $pconfig;
    }

    return $services;
}

function if_ipsec_interfaces()
{
    global $config;

    $interfaces = array();

    if (isset($config['ipsec']['phase1']) && isset($config['ipsec']['phase2'])) {
        foreach ($config['ipsec']['phase1'] as $ph1ent) {
            if (isset($ph1ent['disabled'])) {
                continue;
            }

            foreach ($config['ipsec']['phase2'] as $ph2ent) {
                if (isset($ph2ent['disabled']) || $ph1ent['ikeid'] != $ph2ent['ikeid']) {
                    continue;
                }

                if ((isset($ph2ent['mobile']) && !isset($config['ipsec']['client']['enable'])) ||
                    !isset($config['ipsec']['enable'])) {
                    continue;
                }

                $oic = array('enable' => true);
                $oic['if'] = 'enc0';
                $oic['descr'] = 'IPsec';
                $oic['type'] = 'none';
                $oic['virtual'] = true;
                $oic['networks'] = array();
                $interfaces['enc0'] = $oic;

                break 2;
            }
        }
    }

    return $interfaces;
}
