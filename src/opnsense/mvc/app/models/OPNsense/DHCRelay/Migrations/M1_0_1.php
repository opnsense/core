<?php

/*
 * Copyright (C) 2024 Deciso B.V.
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

namespace OPNsense\DHCRelay\Migrations;

use OPNsense\Base\BaseModelMigration;
use OPNsense\Base\FieldTypes\BooleanField;
use OPNsense\Base\FieldTypes\NetworkField;
use OPNsense\Base\FieldTypes\PortField;
use OPNsense\Core\Config;

class M1_0_1 extends BaseModelMigration
{
    /**
     * Migrate older models into shared model
     * @param $model
     */
    public function run($model)
    {
        $config = Config::getInstance()->object();

        $legacy = $config->dhcrelay6;
        if (empty($legacy->interface) || empty($legacy->server)) {
            /* no value in partial migration so skip all */
            return;
        }

        $node = $model->destinations->add();
        $node->setNodes([
            'name' => 'Migrated IPv6 server entry',
            'server' => (string)$legacy->server,
        ]);
        $dest_uuid = $node->getAttribute('uuid');

        foreach (explode(',', (string)$legacy->interface) as $interface) {
            $node = $model->relays->add();
            $node->setNodes([
                'agent_info' => !empty($legacy->agentoption) ? '1' : '0',
                'enabled' => !empty($legacy->enable) ? '1' : '0',
                'interface' => (string)$interface,
                'destination' => $dest_uuid,
            ]);
            $node->interface->normalizeValue();
            if (empty((string)$node->interface)) {
                $model->relays->del($node->getAttribute('uuid'));
            }
        }
    }

    public function post($model)
    {
        $config = Config::getInstance()->object();
        unset($config->dhcrelay6);
    }
}
