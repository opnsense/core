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

namespace OPNsense\Base\FieldTypes;

use OPNsense\Base\Validators\CallbackValidator;

class JsonField extends BaseField
{
    protected $internalIsContainer = false;

    protected $maxsize = 1024 * 1024;

    /**
     * {@inheritdoc}
     */
    protected function defaultValidationMessage()
    {
        return gettext('Invalid JSON type.');
    }

    /**
     * retrieve field validators for this field type
     * @return array returns Text/regex validator
     */
    public function getValidators()
    {
        $validators = parent::getValidators();
        if ($this->internalValue != null) {
            $validators[] = new CallbackValidator(["callback" => function ($data) {
                if (!json_validate($data)) {
                    return [$this->getValidationMessage()];
                } else {
                    return [];
                }
            }
            ]);
        }
        return $validators;
    }

    public function serialize(array $value): bool
    {
        $tmp = json_encode($value);
        if (strlen($tmp) > $this->maxsize) {
            return false;
        }
        $this->setValue(base64_encode($tmp));
        return true;
    }

    public function deserialize(): array
    {
        $result = [];
        if (!$this->isEmpty()) {
            $tmp = base64_decode($this->getValue());
            $result = json_decode($tmp, true) ?? [];
        }
        return $result;
    }
}
