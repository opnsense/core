<?php
/*
 * Copyright (C) 2018 Fabian Franz
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

namespace OPNsense\Base\Constraints;


class FilterDependentOptionConstraint
{

    /**
     * Executes validation, where the value must be set if another field is
     * set to a specific value and this one is filtered. Configuration example
     *
     * @param \Phalcon\Validation $validator
     * @param string $attribute
     * @return boolean
     */
    public function validate(\Phalcon\Validation $validator, $attribute)
    {
        $node = $this->getOption('node');
        $field_name = $this->getOption('field');
        if ($node) {
            $parentNode = $node->getParentNode();
            if (!$this->isEmpty($node)) {
                $other_value = (string)$parentNode->$field_name;
                if (!empty($other_value)) {
                    $other_values = explode(',', $other_value);
                    $this_values = explode(',', (string)$node);
                    $this->validateEntries($validator, $attribute, $this_values, $other_values);
                }
            }
        }
        return true;
    }

    private function validateEntries(\Phalcon\Validation $validator, string $attribute, $this_values, $other_values)
    {
        foreach ($other_values as $other_value) {
            $supportedOptions = $this->getOption($other_value);
            if (empty($supportedOptions)) {
                $supportedOptions = $this->getOption('fallbackDefaultRestriction');
                if (empty($supportedOptions)) {
                    continue;
                }
            }
            $supportedOptions = explode(',', $supportedOptions);

            foreach ($this_values as $this_value) {
                if (!in_array($this_value, $supportedOptions)) {
                    $this->appendMessage($validator, $attribute);
                    return;
                }
            }
        }
    }
}
