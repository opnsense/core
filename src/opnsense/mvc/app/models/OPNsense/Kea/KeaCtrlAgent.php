<?php

/*
 * Copyright (C) 2023 Deciso B.V.
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

namespace OPNsense\Kea;

use OPNsense\Core\File;
use OPNsense\Base\BaseModel;

class KeaCtrlAgent extends BaseModel
{
    public function generateConfig($target = '/usr/local/etc/kea/kea-ctrl-agent.conf')
    {
        $cnf = [
            'Control-agent' => [
                'http-host' => (string)$this->general->http_host,
                'http-port' => (int)$this->general->http_port->__toString(),
                'control-sockets' => [
                    'dhcp4' => [
                        'socket-type' => 'unix',
                        'socket-name' => '/var/run/kea/kea4-ctrl-socket',
                    ],
                    'dhcp6' => [
                        'socket-type' => 'unix',
                        'socket-name' => '/var/run/kea/kea6-ctrl-socket',
                    ],
                    'd2' => [
                        'socket-type' => 'unix',
                        'socket-name' => '/var/run/kea/kea-ddns-ctrl-socket',
                    ]
                ],
                'loggers' => [
                    [
                        'name' => 'kea-ctrl-agent',
                        'output_options' => [
                            [
                                'output' => 'syslog'
                            ]
                        ],
                        'severity' => 'INFO',
                        'debuglevel' => 0,
                    ]
                ]
            ]
        ];

        File::file_put_contents($target, json_encode($cnf, JSON_PRETTY_PRINT), 0600);
    }
}
