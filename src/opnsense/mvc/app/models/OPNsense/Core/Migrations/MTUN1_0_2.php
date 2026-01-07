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

namespace OPNsense\Core\Migrations;

use OPNsense\Base\BaseModelMigration;
use OPNsense\Core\Tunables;
use OPNsense\Core\Config;

class MTUN1_0_2 extends BaseModelMigration
{
    /**
     * Migrate sharednet settings
     * @param $model
     */
    public function run($model)
    {
        if (!($model instanceof Tunables)) {
            return;
        }

        $config = Config::getInstance()->object();

        if (isset($config?->system?->sharednet)) {
            $model->item->add()->setNodes([
                'tunable' => 'net.link.ether.inet.log_arp_movements',
                'value' => '0',
            ]);
            $model->item->add()->setNodes([
                'tunable' => 'net.link.ether.inet.log_arp_wrong_iface',
                'value' => '0',
            ]);
        }
    }

    public function post($model)
    {
        $config = Config::getInstance()->object();
        if (isset($config?->system?->sharednet)) {
            unset($config->system->sharednet);
        }
    }
}
