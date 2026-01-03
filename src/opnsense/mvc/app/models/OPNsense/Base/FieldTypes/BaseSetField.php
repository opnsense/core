<?php

/*
 * Copyright (C) 2025 Deciso B.V.
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
 * @package OPNsense\Base\FieldTypes
 */
class BaseSetField extends BaseField
{
    /**
     * @var bool marks if this is a data node or a container
     */
    protected $internalIsContainer = false;

    /**
     * @var null when multiple values could be provided at once, specify the split character
     */
    protected $internalFieldSeparator = ',';

    /**
     * @var bool when set, results are returned as list (with all options enabled)
     */
    protected $internalAsList = false;

    /**
     * set separator used for multiple entries
     * @param string $value separator
     */
    public function setFieldSeparator($value)
    {
        $this->internalFieldSeparator = $value;
    }

    /**
     * select if multiple entries can be selected at once
     * @param $value boolean value 0/1
     */
    public function setAsList($value)
    {
        $this->internalAsList = trim(strtoupper($value)) == 'Y';
    }

    /**
     * check if this is a list type
     * @return bool returns true if this field is behaving as a list
     */
    public function isList()
    {
        return $this->internalAsList;
    }

    /**
     * get valid options, descriptions and selected value
     * @return array
     */
    public function getNodeData()
    {
        if ($this->internalAsList) {
            /* return result as list */
            $result = [];
            foreach (explode($this->internalFieldSeparator, $this->internalValue) as $entry) {
                $result[$entry] = ['value' => $entry, 'selected' => 1];
            }
            return $result;
        }

        /* normal, single field response */
        return $this->internalValue;
    }

    /**
     * iterate input according to field rules
     * @return array
     */
    protected function iterateInput($input): array
    {
        return $this->internalAsList ? explode($this->internalFieldSeparator, $input) : [$input];
    }

    /**
     * {@inheritdoc}
     */
    public function getValues(): array
    {
        return array_values(array_filter($this->iterateInput($this->internalValue), function ($k) {
            return !!strlen($k);
        }));
    }
}
