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

namespace OPNsense\Interfaces\Migrations;

use OPNsense\Base\BaseModelMigration;
use OPNsense\Core\Config;

class WLAN1_0_0 extends BaseModelMigration
{
    public function run($model)
    {
        /**
         * Intentionally minimal content, we wish to migrate all data unconditionally
         * Only ensure one clone is marked as use_common to avoid primary migration tripping over this.
         **/

        $unqifs = [];
        foreach ($model->clone->iterateItems() as $clone) {
            if (!in_array($clone->if->getValue(), $unqifs)) {
                $clone->use_common = '1';
                $unqifs[] = $clone->if->getValue();
            } else {
                $clone->use_common = '0';
            }
        }
        return;
    }

    public function post($model)
    {
        /* migrate legacy data without validations to prevent single, possible unused, properties breaking the setup */
        $config = Config::getInstance()->object();
        $targets = [];
        foreach ($model->clone->iterateItems() as $clone) {
            $targets[$clone->cloneif->getValue()] = $clone;
        }
        foreach ($config->interfaces->children() as $key => $ifnode) {
            $target = $targets[(string)$ifnode->if] ?? null;
            if ($target === null || !isset($ifnode->wireless)) {
                continue;
            }
            $payload = [];
            $wep_key = [];
            foreach ($ifnode->wireless->children() as $wifiprop => $wifivalue) {
                if (in_array((string)$wifiprop, ['wep', 'wpa'])) {
                    $payload[$wifiprop] = [];
                    foreach ($wifivalue->children() as $p => $v) {
                        if ($wifiprop === 'wep' && $p == 'key') {
                            /* dump wep keys in a single container, primary first */
                            if (!empty($v->txkey)) {
                                array_unshift($wep_key, (string)$v->value);
                            } else {
                                $wep_key[] = (string)$v->value;
                            }
                        } elseif ($p == 'ieee8021x') {
                            $payload[$wifiprop][$p] = (string)$v->enable == '1' ? '1' : '0';
                        } else {
                            $payload[$wifiprop][$p] = (string)$v;
                        }
                    }
                    if ($wifiprop === 'wep') {
                        $payload[$wifiprop]['keys'] = implode("\n", $wep_key);
                    }
                } elseif (in_array((string)$wifiprop, ['pureg', 'puren', 'turbo', 'turbo', 'hidessid'])) {
                    $payload[$wifiprop] = (string)$wifivalue->enable == '1' ? '1' : '0';
                } else {
                    $payload[$wifiprop] = (string)$wifivalue;
                }
            }
            $target->setNodes($payload);
            unset($ifnode->wireless);
        }
        $model->serializeToConfig(false, true);
    }
}
