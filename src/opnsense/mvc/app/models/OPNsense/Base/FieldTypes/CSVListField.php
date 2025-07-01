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
class CSVListField extends BaseField
{
    /**
     * @var bool marks if this is a data node or a container
     */
    protected $internalIsContainer = false;

    /**
     * selectable options, key/value store.
     * value = display option
     */
    private $selectOptions = array();

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
        if (strtoupper(trim($value)) == "Y") {
            $this->internalMaskPerItem = true;
        } else {
            $this->internalMaskPerItem = false;
        }
    }

    /**
     * retrieve data including selection options (from setSelectOptions)
     * @return array
     */
    public function getNodeData()
    {
        $selectlist = explode(',', $this->internalValue);
        $result = [];

        foreach ($this->selectOptions as $optKey => $optValue) {
            $result[$optKey] = array("value" => $optValue, "selected" => 0);
        }

        foreach ($selectlist as $optKey) {
            if (strlen($optKey) > 0) {
                if (isset($result[$optKey])) {
                    $result[$optKey]["selected"] = 1;
                } else {
                    $result[$optKey] = array("value" => $optKey, "selected" => 1);
                }
            }
        }

        return $result;
    }

    /**
     * set all options for this select item.
     * push a key/value array type to set all options or deliver a comma-separated list with keys and optional values
     * divided by a pipe | sign.
     * example :    optionA|text for option A, optionB|test for option B
     * @param array|string $list key/value option list
     */
    public function setSelectOptions($list)
    {
        if (is_array($list)) {
            foreach ($list as $optKey => $optValue) {
                $this->selectOptions[$optKey] = $optValue;
            }
        } else {
            // string list
            foreach (explode(',', $list) as $option) {
                if (strpos($option, "|") !== false) {
                    $tmp = explode("|", $option);
                    $this->selectOptions[$tmp[0]] = $tmp[1];
                } else {
                    $this->selectOptions[$option] = $option;
                }
            }
        }
    }

    /**
     * retrieve field validators for this field type
     * @return array returns regex validator
     */
    public function getValidators()
    {
        $validators = parent::getValidators();
        if ($this->internalValue != null && $this->internalMask != null) {
            $validators[] = new CallbackValidator(['callback' => function ($data) {
                $regex_match = function ($value, $pattern) {
                    $matches = [];
                    preg_match(trim($pattern), $value, $matches);
                    return isset($matches[0]) ? $matches[0] == $value : false;
                };

                if ($this->internalMaskPerItem) {
                    $items = explode(',', $this->internalValue);
                    foreach ($items as $item) {
                        if (!$regex_match($item, $this->internalMask)) {
                            return ["\"" . $item . "\" is invalid. " . $this->getValidationMessage()];
                        }
                    }
                } else {
                    if (!$regex_match($this->internalValue, $this->internalMask)) {
                        return [$this->getValidationMessage()];
                    }
                }
                return [];
            }]);
        }
        return $validators;
    }

    /**
     * {@inheritdoc}
     */
    public function getValues(): array
    {
        return array_values(array_filter(explode(',', $this->internalValue), function ($k) {
            return !!strlen($k);
        }));
    }
}
