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

namespace OPNsense\Routing\Migrations;

use OPNsense\Base\BaseModelMigration;
use OPNsense\Core\Config;
use OPNsense\Core\Syslog;
use OPNsense\Routing\Gateways;

class M1_0_0 extends BaseModelMigration
{
    /**
     * Migrate older models into shared model
     * @param $model
     */
    public function run($model)
    {
        $config = Config::getInstance()->object();

        // create logger to save possible consistency issues to
        $logger = new Syslog('config', null, LOG_LOCAL2);

        if (!empty($config->gateways) && count($config->gateways->children()) > 0) {
            foreach ($config->gateways->gateway_item as $gateway) {
                $node = $model->gateway_item->Add();

                // migrate set nodes
                $node_properties = iterator_to_array($node->iterateItems());
                foreach ($gateway as $key => $value) {
                    if (!array_key_exists($key, $node_properties)) {
                        // skip unknown nodes
                        continue;
                    }

                    if ($key === 'gateway') {
                        // change all occurences of "dynamic" to empty string
                        $node->gateway = str_replace('dynamic', '', (string)$value);
                        continue;
                    }

                    $node->$key = (string)$value;
                }

                // special handling of implied booleans
                $node->defaultgw = !empty((string)$gateway->defaultgw) ? '1' : '0';
                $node->disabled = !empty((string)$gateway->disabled) ? '1' : '0';
                $node->fargw = !empty((string)$gateway->fargw) ? '1' : '0';
                $node->force_down = !empty((string)$gateway->force_down) ? '1' : '0';
                $node->monitor_disable = !empty((string)$gateway->monitor_disable) ? '1' : '0';
                $node->monitor_noroute = !empty((string)$gateway->monitor_noroute) ? '1' : '0';

                if (empty((string)$gateway->priority)) {
                    $node->priority = '255';
                }

                if (empty((string)$gateway->ipprotocol)) {
                    $node->ipprotocol = 'inet';
                }

                if (empty((string)$gateway->weight)) {
                    $node->weight = '1';
                }

                $model->gateway_item->calculateCurrent($node);
                // increase time period if old model had it set too low
                $min_time_period = 2 * (
                    intval((string)$node->current_interval) + intval((string)$node->current_loss_interval)
                );
                if ((string)$node->current_time_period < $min_time_period) {
                    $node->time_period = $min_time_period;
                }
                $result = $model->performValidation();
                if (count($result) > 0) {
                    // save details of validation error
                    foreach ($result as $msg) {
                        error_log(sprintf('[%s] %s', $msg->getField(), $msg->getMessage()));
                    }
                    $logger->error(sprintf(
                        "Migration skipped gateway %s (%s). See crash reporter for details",
                        $gateway->name,
                        $gateway->gateway
                    ));
                    $model->gateway_item->del($node->getAttribute('uuid'));
                }
            }
        }

        parent::run($model);
    }

    /**
     * cleanup old config after config save
     * @param $model
     */
    public function post($model)
    {
        if ($model instanceof Gateways) {
            foreach ($model->gateway_item->iterateRecursiveItems() as $node) {
                if (!$node->getInternalIsVirtual() && !empty((string)$node)) {
                    /* There is at least one entry stored. */
                    unset(Config::getInstance()->object()->gateways->gateway_item);
                    return;
                }
            }
        }
    }
}
