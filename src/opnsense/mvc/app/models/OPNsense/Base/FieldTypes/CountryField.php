<?php

/*
 * Copyright (C) 2015-2019 Deciso B.V.
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

namespace OPNsense\Base\FieldTypes;

use OPNsense\Core\AppConfig;

/**
 * Class CountryField field type to select iso3166 countries
 * @package OPNsense\Base\FieldTypes
 */
class CountryField extends BaseListField
{
    /**
     * @var array collected options
     */
    private static $internalCacheOptionList = array();

    /**
     * @var bool field for adding inverted items to the selection
     */
    private $internalAddInverse = false;

    /**
     * @return string identifying selected options
     */
    private function optionSetId()
    {
        return $this->internalAddInverse ? "1" : "0";
    }

    /**
     * generate validation data (list of countries)
     */
    protected function actionPostLoadingEvent()
    {
        $setid = $this->optionSetId();
        if (!isset(self::$internalCacheOptionList[$setid])) {
            self::$internalCacheOptionList[$setid] = [];
        }
        if (empty(self::$internalCacheOptionList[$setid])) {
            $contribDir = (new AppConfig())->application->contribDir;
            $filename = $contribDir . '/iana/tzdata-iso3166.tab';
            $data = file_get_contents($filename);
            foreach (explode("\n", $data) as $line) {
                $line = trim($line);
                if (strlen($line) > 3 && substr($line, 0, 1) != '#') {
                    $code = substr($line, 0, 2);
                    $name = trim(substr($line, 2, 9999));
                    self::$internalCacheOptionList[$setid][$code] = $name;
                    if ($this->internalAddInverse) {
                        self::$internalCacheOptionList[$setid]["!" . $code] = $name . " (not)";
                    }
                }
            }
            natcasesort(self::$internalCacheOptionList[$setid]);
        }
        $this->internalOptionList = self::$internalCacheOptionList[$setid];
    }

    /**
     * Add inverted countries to selection (prefix !, meaning not)
     * @param string $value boolean value Y/N
     */
    public function setAddInverted($value)
    {
        if (trim(strtoupper($value)) == "Y") {
            $this->internalAddInverse = true;
        } else {
            $this->internalAddInverse = false;
        }
    }
}
