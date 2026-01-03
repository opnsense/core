<?php

/*
 * Copyright (C) 2015 Deciso B.V.
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

use OPNsense\Base\Validators\CallbackValidator;

/**
 * Class CSVListField
 * Physical stored as a single entry, stores multiple selections.
 * @package OPNsense\Base\FieldTypes
 */
class CSVListField extends BaseSetField
{
    /**
     * @var bool when set, results are returned as list (with all options enabled)
     */
    protected $internalAsList = true;

    /**
     * @var string basic regex validation to use for the complete field
     */
    protected $internalMask = null;

    /**
     * @var bool marks if regex validation should occur on a per-item basis.
     */
    protected $internalMaskPerItem = false;

    /**
     * {@inheritdoc}
     */
    protected function defaultValidationMessage()
    {
        return gettext('List validation error.');
    }

    /**
     * set validation mask
     * @param string $value regexp validation mask
     */
    public function setMask($value)
    {
        $this->internalMask = $value;
    }

    /**
     * set mask per csv
     * @param bool $value
     */
    public function setMaskPerItem($value)
    {
        $this->internalMaskPerItem = strtoupper(trim($value)) == 'Y';
    }

    /**
     * retrieve field validators for this field type
     * @return array returns regex validator
     */
    public function getValidators()
    {
        $validators = parent::getValidators();

        if ($this->internalValue != null && $this->internalMask != null) {
            $that = $this;
            $validators[] = new CallbackValidator(['callback' => function ($data) use ($that) {
                $regex_match = function ($value, $pattern) {
                    $matches = [];
                    preg_match(trim($pattern), $value, $matches);
                    return isset($matches[0]) ? $matches[0] == $value : false;
                };

                if ($that->internalMaskPerItem) {
                    $items = $that->iterateInput($that->internalValue);
                    foreach ($items as $item) {
                        if (!$regex_match($item, $that->internalMask)) {
                            return ["\"" . $item . "\" is invalid. " . $that->getValidationMessage()];
                        }
                    }
                } else {
                    if (!$regex_match($that->internalValue, $that->internalMask)) {
                        return [$that->getValidationMessage()];
                    }
                }

                return [];
            }]);
        }

        return $validators;
    }
}
