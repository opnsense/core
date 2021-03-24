<?php

/*
 * Copyright (C) 2016-2018 Franco Fichtner <franco@opnsense.org>
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

function netflow_enabled()
{
    $mdl = new \OPNsense\Diagnostics\Netflow();

    return !empty((string)$mdl->capture->targets) && !empty((string)$mdl->capture->interfaces);
}

function netflow_local_enabled()
{
    $mdl = new \OPNsense\Diagnostics\Netflow();

    return $mdl->collect->enable == '1';
}

function netflow_services()
{
    $services = array();

    if (!netflow_enabled()) {
        return $services;
    }

    $services[] = array(
        'description' => gettext('NetFlow Distributor'),
        'configd' => array(
            'restart' => array('netflow restart'),
            'start' => array('netflow start'),
            'stop' => array('netflow stop'),
        ),
        'name' => 'samplicate',
    );

    if (netflow_local_enabled()) {
        $services[] = array(
            'description' => gettext('Insight Aggregator'),
            'configd' => array(
                'restart' => array('netflow aggregate restart'),
                'start' => array('netflow aggregate start'),
                'stop' => array('netflow aggregate stop'),
            ),
            'pidfile' => '/var/run/flowd_aggregate.pid',
            'name' => 'flowd_aggregate',
        );
    }

    return $services;
}

/**
 *  sync configuration via xmlrpc
 * @return array
 */
function netflow_xmlrpc_sync()
{
    $result = [];
    $result[] = array(
        'description' => gettext('Netflow / Insight'),
        'section' => 'OPNsense.Netflow',
        'id' => 'syslog',
        'services' => ["samplicate", "flowd_aggregate"],
    );

    return $result;
}
