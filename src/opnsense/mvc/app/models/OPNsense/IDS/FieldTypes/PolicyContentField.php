<?php

/**
 *    Copyright (C) 2020 Deciso B.V.
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

use OPNsense\Base\FieldTypes\BaseListField;
use OPNsense\Core\Backend;

/**
 * Class PolicyContentField
 * @package OPNsense\IDS\FieldTypes
 */
class PolicyContentField extends BaseListField
{
    /**
     * @var array cached collected certs
     */
    private static $internalStaticOptionList = array();

    /**
     * generate validation data (list of metadata options)
     */
    protected function actionPostLoadingEvent()
    {
        if (empty(self::$internalStaticOptionList)) {
            self::$internalStaticOptionList = array();
            // XXX: we could add caching here if configd overhead is an issue
            $response = (new Backend())->configdRun("ids list rulemetadata");
            $data = json_decode($response, true);
            if (!empty($data)) {
                foreach ($data as $prop => $values) {
                    foreach ($values as $value) {
                        $item_key = "{$prop}.{$value}";
                        self::$internalStaticOptionList[$item_key] = $value;
                    }
                }
            }
        }
        $this->internalOptionList = self::$internalStaticOptionList;
    }
}
