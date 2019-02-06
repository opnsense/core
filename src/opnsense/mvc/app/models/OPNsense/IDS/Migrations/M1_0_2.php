<?php

/*
 * Copyright (C) 2016-2018 Deciso B.V.
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

namespace OPNsense\IDS\Migrations;

use OPNsense\Base\FieldTypes\BaseField;
use OPNsense\Core\Config;
use OPNsense\Base\BaseModelMigration;
use OPNSense\IDS\IDS;

class M1_0_2 extends BaseModelMigration
{
    /**
     * Disable rules that have GeoIP config set
     * @param IDS $model
     */
    public function run($model)
    {
        $cfgObj = Config::getInstance()->object();
        $affectedUuids = [];
        if (!isset($cfgObj->OPNsense->IDS->userDefinedRules->rule)) {
            return;
        }
        foreach ($cfgObj->OPNsense->IDS->userDefinedRules->rule as $rule) {
            if (!empty($rule->geoip) || !empty($rule->geoip_direction)) {
                $affectedUuids[] = (string)$rule['uuid'];
            }
        }

        // Update the affected rule in the model
        /** @var BaseField $node */
        foreach ($model->userDefinedRules->rule->iterateItems() as $node) {
            if (in_array($node->getAttribute('uuid'), $affectedUuids)) {
                $node->enabled = '0';
                // Description can be up to 255 characters, truncate as necessary.
                $node->description = substr(
                    $node->description . ' - Old GeoIP rule, disabled by migration',
                    0,
                    255
                );
            }
        }
    }
}
