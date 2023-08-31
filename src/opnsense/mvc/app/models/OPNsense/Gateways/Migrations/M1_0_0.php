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

namespace OPNsense\Gateways\Migrations;

use OPNsense\Base\BaseModelMigration;
use OPNsense\Core\Config;
use OPNsense\Gateways\Gateway;

class M1_0_0 extends BaseModelMigration
{
    /**
     * Migrate older models into shared model
     * @param $model
     */
    public function run($model)
    {
        $config = Config::getInstance()->object();

        if (!empty($config->gateways) && !empty($config->gateways->gateway_item)) {
            foreach ($config->gateways->gateway_item as $gateway) {
                $node = $model->gateway_item->Add();

                // monitor_disable was "on" when no node present
                $monitor_disable = !empty((string)$gateway->monitor_disable);
                $node->monitor_disable = $monitor_disable ? 1 : 0;

                foreach ($gateway as $key => $value) {
                    $node->$key = (string)$value;
                }

                $dpinger_defaults = (new Gateway)::getDpingerDefaults();
                // apply dpinger defaults if old model didn't have them set
                foreach ($dpinger_defaults as $key => $value) {
                    if (empty((string)$node->$key)) {
                        $node->$key = $value;
                    }
                }

            }
        }

        parent::run($model);
    }
}
