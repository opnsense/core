<?php

/*
 * Copyright (C) 2025 Deciso B.V.
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

namespace OPNsense\Interfaces\Migrations;

use OPNsense\Base\BaseModelMigration;
use OPNsense\Core\Config;

class M1_0_0 extends BaseModelMigration
{
    private $keys = [
        'dhcp6_debug',
        'dhcp6_norelease',
        'disablechecksumoffloading',
        'disablelargereceiveoffloading',
        'disablesegmentationoffloading',
        'disablevlanhwfilter',
        'ipv6allow',
        'ipv6duid',
    ];

    public function run($model)
    {
        $config = Config::getInstance()->object();
        $nodes = [];

        foreach ($this->keys as $key) {
            $_key = $key;
            if ($key == 'ipv6allow') {
                $_key = 'disableipv6';
                if (!isset($config->system->$key)) {
                    $nodes[$_key] = '1';
                }
            } elseif ($key == 'ipv6duid') {
                $_key = 'dhcp6_duid';
                if (isset($config->system->$key)) {
                    $nodes[$_key] = (string)$config->system->$key;
                }
            } elseif ($key == 'dhcp6_debug') {
                $nodes[$key] = isset($config->system->$key) ? '1' : '0';
            } elseif (isset($config->system->$key)) {
                $nodes[$key] = (string)$config->system->$key;
            }

            if (!isset($nodes[$_key])) {
                $model->$_key->applyDefault();
            }
        }

        $model->setNodes($nodes);
    }

    public function post($model)
    {
        $config = Config::getInstance()->object();
        foreach ($this->keys as $key) {
            if (isset($config->system->$key)) {
                unset($config->system->$key);
            }
        }
    }
}
