<?php

/*
 * Copyright (C) 2019-2025 Deciso B.V.
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
 * Class BaseListField
 * @package OPNsense\Base\FieldTypes
 */
abstract class BaseListField extends BaseField
{
    /**
     * @var bool marks if this is a data node or a container
     */
    protected $internalIsContainer = false;

    /**
     * @var array valid options for this list
     */
    protected $internalOptionList = [];

    /**
     * @var string default description for empty item
     */
    private $internalEmptyDescription = null;

    /**
     * @var bool field may contain multiple interfaces at once
     */
    protected $internalMultiSelect = false;

    /**
     * {@inheritdoc}
     */
    protected function defaultValidationMessage()
    {
        return gettext('Option not in list.');
    }

    /**
     * select if multiple interfaces may be selected at once
     * @param $value boolean value 0/1
     */
    public function setMultiple($value)
    {
        if (trim(strtoupper($value)) == "Y") {
            $this->internalMultiSelect = true;
        } else {
            $this->internalMultiSelect = false;
        }
    }

    /**
     * set descriptive text for empty value
     * @param $value string description
     */
    public function setBlankDesc($value)
    {
        $this->internalEmptyDescription = gettext($value);
    }

    /**
     * get valid options, descriptions and selected value
     * @return array
     */
    protected function getNodeOptions()
    {
        if (empty($this->internalEmptyDescription)) {
            $this->internalEmptyDescription = gettext('None');
        }
        $result = [];
        // if option is not required, add empty placeholder
        if (!$this->internalIsRequired && !$this->internalMultiSelect) {
            $result[""] = [
                "value" => $this->internalEmptyDescription,
                "selected" => empty((string)$this->internalValue) ? 1 : 0
            ];
        }

        // explode options
        $options = explode(',', $this->internalValue);
        foreach ($this->internalOptionList as $optKey => $optValue) {
            $selected = in_array($optKey, $options) ? 1 : 0;
            if (is_array($optValue) && isset($optValue['value'])) {
                // option container (multiple attributes), passthrough.
                $result[$optKey] = $optValue;
            } else {
                // standard (string) option
                $result[$optKey] = ["value" => $optValue];
            }
            $result[$optKey]["selected"] = $selected;
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function getNodeData()
    {
        return $this->getNodeOptions();
    }

    /**
     * {@inheritdoc}
     */
    public function getDescription()
    {
        $items = [];
        foreach ($this->getValues() as $item) {
            $val = $this->internalOptionList[$item] ?? $item;
            $items[] = is_array($val) && isset($val['value']) ? $val['value'] : $val;
        }
        return implode(', ', $items);
    }

    /**
     * {@inheritdoc}
     */
    public function getValidators()
    {
        $validators = parent::getValidators();
        if ($this->internalValue != null) {
            $that = $this;
            $validators[] = new CallbackValidator(["callback" => function ($data) use ($that) {
                $messages = [];
                if ($that->internalMultiSelect) {
                    foreach (explode(",", $data) as $valItem) {
                        if (!isset($this->internalOptionList[$valItem])) {
                            $messages[] = $this->getValidationMessage();
                            break;
                        }
                    }
                } elseif (!isset($this->internalOptionList[$data])) {
                    $messages[] = $this->getValidationMessage();
                }
                return $messages;
            }]);
        }
        return $validators;
    }

    /**
     * {@inheritdoc}
     */
    public function normalizeValue()
    {
        $values = [];

        $data = $this->getNodeData();
        if (!is_array($data)) {
            /* XXX e.g. PortField returning a string; do not normalize */
            return;
        }

        foreach ($data as $key => $node) {
            if ($node['selected']) {
                $values[] = $key;
            }
        }

        $this->setValue(implode(',', $values));
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
