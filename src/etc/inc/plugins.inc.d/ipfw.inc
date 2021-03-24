<?php

/*
 * Copyright (C) 2016 Deciso B.V.
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

function ipfw_services()
{
    global $config;

    $services = array();

    if (!empty($config['OPNsense']['TrafficShaper']['rules']['rule'])) {
        $services[] = array(
            'description' => gettext('Shaper'),
            'configd' => array(
                'restart' => array('ipfw reload'),
                'start' => array('ipfw reload'),
                'stop' => array('ipfw reload'),
            ),
            'name' => 'ipfw',
            'nocheck' => true,
        );
    }

    return $services;
}

function ipfw_xmlrpc_sync()
{
    $result = array();

    $result[] = array(
        'description' => gettext('Shaper'),
        'section' => 'OPNsense.TrafficShaper',
        'id' => 'shaper',
        'services' => ["ipfw"],
    );
    $result[] = array(
        'description' => gettext('Captive Portal'),
        'section' => 'OPNsense.captiveportal',
        'id' => 'captiveportal',
        'services' => ["ipfw"],
    );

    return $result;
}
