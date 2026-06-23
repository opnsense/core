<?php

/*
 * Copyright (C) 2026 Konstantinos Spartalis <cspartalis@potatonetworks.com>
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

namespace OPNsense\IDS\FieldTypes;

use OPNsense\Base\FieldTypes\ModelRelationField;
use OPNsense\Core\Backend;

/**
 * Class PolicyRulesetsField, extends the standard ruleset relation (downloadable rulesets) with
 * custom rule files found on disk so policies can also alter the action of custom suricata rules.
 * @package OPNsense\IDS\FieldTypes
 */
class PolicyRulesetsField extends ModelRelationField
{
    /**
     * @var array|null cached list of custom (local) rule files (filename => description)
     */
    private static $internalCustomRuleFiles = null;

    /**
     * collect custom rule files present on disk, keyed by their filename
     * @return array custom rule files
     */
    private function customRuleFiles()
    {
        if (self::$internalCustomRuleFiles === null) {
            self::$internalCustomRuleFiles = [];
            $response = (new Backend())->configdRun('ids list installablerulesets');
            $data = json_decode($response, true);
            if (is_array($data) && !empty($data['local']) && is_array($data['local'])) {
                foreach ($data['local'] as $filename) {
                    if (preg_match('/\A[A-Za-z0-9._-]+\.rules\z/', (string)$filename)) {
                        self::$internalCustomRuleFiles[$filename] = $filename;
                    }
                }
            }
        }
        return self::$internalCustomRuleFiles;
    }

    protected function actionPostLoadingEvent()
    {
        parent::actionPostLoadingEvent();
        $this->internalOptionList += $this->customRuleFiles();
    }

    /**
     * @return array validators for this field
     */
    public function getValidators()
    {
        $validators = parent::getValidators();
        $this->internalOptionList += $this->customRuleFiles();
        return $validators;
    }
}
