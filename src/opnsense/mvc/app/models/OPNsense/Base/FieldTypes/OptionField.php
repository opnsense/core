<?php

/*
 * Copyright (C) 2015-2026 Deciso B.V.
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

/**
 * Class OptionField
 * @package OPNsense\Base\FieldTypes
 */
class OptionField extends BaseListField
{
    private static $optionCache = [];

    /**
     * setter for option values
     * @param $data
     */
    public function setOptionValues($data)
    {
        if (is_array($data)) {
            /* index option lists per unique type */
            $data_id = serialize($data);
            if (!isset(self::$optionCache[$data_id])) {
                self::$optionCache[$data_id] = [];
                // copy options to internal structure, make sure we don't copy in array structures
                foreach ($data as $key => $value) {
                    if (!is_array($value)) {
                        self::$optionCache[$data_id][$key] = strlen($value) ? gettext($value) : $key;
                    } else {
                        foreach ($value as $subkey => $subval) {
                            self::$optionCache[$data_id][$subkey] = [
                                'value' => strlen($subval) ? gettext($subval) : $subkey,
                                'optgroup' => gettext($key),
                            ];
                        }
                    }
                }
            }
            $this->internalOptionList = self::$optionCache[$data_id];
        }
    }
}
