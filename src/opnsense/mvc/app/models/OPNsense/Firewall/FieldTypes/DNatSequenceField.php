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
 * Class DNatSequenceField
 * Extends the built-in AutoNumberField
 * The next number will not be in an available gap, but always at the end of the sequence and +100.
 */
class DNatSequenceField extends AutoNumberField
{
    private static $current_max_seq = null;

    public function applyDefault()
    {
        self::$current_max_seq += 100;
        $this->internalValue = (string)self::$current_max_seq;
    }

    protected function actionPostLoadingEvent()
    {
        if (self::$current_max_seq === null) {
            // Start from the minimum value if no entries exist
            self::$current_max_seq = (int)$this->minimum_value;
            if (isset($this->internalParentNode->internalParentNode)) {
                foreach ($this->internalParentNode->internalParentNode->iterateItems() as $node) {
                    $currentNumber = (int)((string)$node->{$this->internalXMLTagName});
                    // Update maxNumber if this value is greater
                    if ($currentNumber >= self::$current_max_seq) {
                        self::$current_max_seq = $currentNumber;
                    }
                }
            }
        }
        if ($this->internalValue == '') {
            self::$current_max_seq += 100;
            $this->internalValue = (string)self::$current_max_seq;
        }
        return parent::actionPostLoadingEvent();
    }
}
