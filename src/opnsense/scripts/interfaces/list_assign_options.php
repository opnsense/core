#!/usr/local/bin/php
<?php

/*
 * Copyright (C) 2026 Deciso B.V.
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
require_once("config.inc");
require_once("util.inc");


/**
 * XXX: needs to be refactored at some point, but for now keep it 100% compatible with legacy code.
 */

function list_devices()
{
    $interfaces = [];
    $excludes = [];
    $devices = plugins_devices();
    $ifdetails = legacy_interfaces_details();

    /* add physical network interfaces */
    foreach (get_interface_list() as $key => $item) {
        $interfaces[$key] = [
            'value' => $key . ' (' . $item['mac'] . ')',
            'optgroup' => 'hardware'
        ];
        if (!empty($ifdetails[$key]) && ($ifdetails[$key]['status'] ?? '') != 'active') {
            $interfaces[$key]['data'] = ['icon' => 'fa fa-plug text-danger'];
        } else {
            $interfaces[$key]['data'] = ['icon' => 'fa fa-plug text-success'];
        }
    }

    /* add virtual network interfaces */
    foreach ($devices as $device) {
        if (!empty($device['names'])) {
            foreach ($device['names'] as $key => $values) {
                if (!empty($values)) {
                    $interfaces[$key] = [
                        'value' => $values['descr'] ,
                        'optgroup' => $device['type'],
                        'data' => [
                            'icon' => 'fa fa-plug text-success'
                        ]
                    ];

                    if (!empty($values['exclude'])) {
                        $excludes = array_merge($excludes, $values['exclude']);
                    }
                }
            }
        }
    }

    /* enforce constraints */
    foreach ($excludes as $device) {
        if (isset($interfaces[$device])) {
            unset($interfaces[$device]);
        }
    }

    return $interfaces;
}

echo json_encode(list_devices());
