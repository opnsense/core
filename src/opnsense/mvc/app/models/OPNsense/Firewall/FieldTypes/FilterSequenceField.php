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
 * THIS SOFTWARE IS PROVIDED ``AS IS`` AND ANY EXPRESS OR IMPLIED WARRANTIES,
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

namespace OPNsense\Firewall\FieldTypes;

use OPNsense\Base\FieldTypes\AutoNumberField;

/**
 * Class FilterSequenceField
 * Extends the built-in AutoNumberField but increments by 10 instead of 1.
 * The next number will not be in an available gap, but always at the end of the sequence.
 * This ensures there is always space between firewall rules, so that rules can be moved inbetween
 * without reordering the whole ruleset. New rules will always be added at the end of of the ruleset.
 */
class FilterSequenceField extends AutoNumberField
{
    /**
     * Apply default value as "next available" integer stepping by 10.
     */
    public function applyDefault()
    {
        // Initialize max with the minimum value minus step to handle cases when no numbers exist.
        $step = 10;
        $maxNumber = $this->minimum_value - $step;
    
        if (isset($this->internalParentNode->internalParentNode)) {
            foreach ($this->internalParentNode->internalParentNode->iterateItems() as $node) {
                $currentNumber = (int)((string)$node->{$this->internalXMLTagName});
                // Update maxNumber if this value is greater
                if ($currentNumber > $maxNumber) {
                    $maxNumber = $currentNumber;
                }
            }
        }
    
        /**
         * Set the new value to be max found + step
         * If its higher than the allowed max value
         * the default validation of the AutoNumberField will trigger
         */
        $this->internalValue = (string)($maxNumber + $step);
    }    
}
