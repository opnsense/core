<?php

/*
    Copyright (C) 2016 Franco Fichtner <franco@opnsense.org>
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

function relayd_firewall($fw)
{
    global $config;

    if (isset($config['load_balancer']['virtual_server']) && isset($config['load_balancer']['lbpool']) &&
        count($config['load_balancer']['virtual_server']) && count($config['load_balancer']['lbpool'])) {
        $fw->registerAnchor('relayd/*', 'rdr');
        $fw->registerAnchor('relayd/*', 'fw');
    }
}

function relayd_services()
{
    global $config;

    $services = array();

    if (isset($config['load_balancer']['virtual_server']) && isset($config['load_balancer']['lbpool']) &&
        count($config['load_balancer']['virtual_server']) && count($config['load_balancer']['lbpool'])) {
        $pconfig = array();
        $pconfig['name'] = 'relayd';
        $pconfig['description'] = gettext('Server load balancing daemon');
        $pconfig['php']['restart'] = array('relayd_configure', 'filter_configure');
        $pconfig['php']['start'] = array('relayd_configure', 'filter_configure');
        $services[] = $pconfig;
    }

    return $services;
}

function relayd_syslog()
{
    $logfacilities = array();

    $logfacilities['relayd'] = array('facility' => array('relayd'), 'remote' => 'relayd');

    return $logfacilities;
}
