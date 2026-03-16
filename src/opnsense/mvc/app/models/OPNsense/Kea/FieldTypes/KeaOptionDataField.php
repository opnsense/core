<?php

/*
 * Copyright (C) 2026 Deciso B.V.
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

namespace OPNsense\Kea\FieldTypes;

use OPNsense\Base\FieldTypes\BaseField;
use OPNsense\Base\Validators\CallbackValidator;

class KeaOptionDataField extends BaseField
{
    protected $internalIsContainer = false;
    protected $internalValidationMessage = "Invalid option data";

    public function getValidators()
    {
        $validators = parent::getValidators();
        if (!empty($this->internalValue)) {
            $validators[] = new CallbackValidator([
                "callback" => function ($data) {
    
                    $messages = [];
                    $encoding = $this->getParentNode()->encoding->getValue();
    
                    if ($encoding === "hex") {
                        if (!preg_match('/^([0-9A-F]{2})+$/', $data)) {
                            $messages[] = gettext("Hex value must contain uppercase hexadecimal byte pairs.");
                        }
                    }
    
                    if ($encoding === "string") {
                        if (preg_match('/[\'"]/', $data)) {
                            $messages[] = gettext("String value must not contain quotes.");
                        }
                    }
    
                    return $messages;
                }
            ]);
        }
        return $validators;
    }
}