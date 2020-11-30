<?php

/*
 * Copyright (C) 2020 Deciso B.V.
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

class M1_0_6 extends BaseModelMigration
{
    /**
     * Migrate ruleset filters
     * @param IDS $model
     */
    public function run($model)
    {
        $cfgObj = Config::getInstance()->object();
        if (!isset($cfgObj->OPNsense->IDS->files->file)) {
            return;
        }
        $rulesets = [];
        foreach ($cfgObj->OPNsense->IDS->files->file as $file) {
            if (!empty($file->filter) && !empty($file->enabled)) {
                $rulesets[] = (string)$file->attributes()['uuid'];
            }
        }
        if (!empty($rulesets)) {
            $policy = $model->policies->policy->Add();
            $policy->action = "alert";
            $policy->new_action = "drop";
            $policy->rulesets = implode(",", $rulesets);
            $policy->description = "imported legacy import filter";
        }
    }
}
