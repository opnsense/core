<?php

/**
 *    Copyright (C) 2023 Deciso B.V.
 *
 *    All rights reserved.
 *
 *    Redistribution and use in source and binary forms, with or without
 *    modification, are permitted provided that the following conditions are met:
 *
 *    1. Redistributions of source code must retain the above copyright notice,
 *       this list of conditions and the following disclaimer.
 *
 *    2. Redistributions in binary form must reproduce the above copyright
 *       notice, this list of conditions and the following disclaimer in the
 *       documentation and/or other materials provided with the distribution.
 *
 *    THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
 *    INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 *    AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 *    AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 *    OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 *    SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 *    INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 *    CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 *    ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 *    POSSIBILITY OF SUCH DAMAGE.
 *
 */

namespace OPNsense\IDS\FieldTypes;

use OPNsense\Base\FieldTypes\ArrayField;
use OPNsense\Base\FieldTypes\TextField;
use OPNsense\Core\Backend;

class PolicyRulesField extends ArrayField
{
    protected static $internalRuleData = null;
    private static $queryRules = false;

    /**
     * by default the PolicyRulesField acts as a standard ArrayField, unless queryRuleInfo() in which case
     * the next created object will query for local rule data. (to limit load)
     */
    public function queryRuleInfo()
    {
        static::$queryRules = true;
    }

    protected function actionPostLoadingEvent()
    {
        parent::actionPostLoadingEvent();
        if (static::$queryRules) {
            if (static::$internalRuleData === null) {
                static::$internalRuleData = json_decode((new Backend())->configdRun('ids list rules'), true) ?? [];
            }
            foreach ($this->iterateItems() as $node) {
                $rule = static::$internalRuleData[(string)$node->sid] ?? [];
                $node->msg = $rule['msg'] ?? '';
                $node->source = $rule['source'] ?? '';
            }
        }
    }
}
