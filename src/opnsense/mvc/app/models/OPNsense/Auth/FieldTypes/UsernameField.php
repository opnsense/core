<?php

/*
 * Copyright (C) 2024 Deciso B.V.
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

namespace OPNsense\Auth\FieldTypes;

use OPNsense\Base\FieldTypes\BaseField;
use OPNsense\Base\Validators\CallbackValidator;

class UsernameField extends BaseField
{
    protected $internalIsContainer = false;

    /**
     * {@inheritdoc}
     */
    protected function defaultValidationMessage()
    {
        if (!empty((string)$this->getParentNode()->shell)) {
            /* shell user */
            return gettext('A username must contain a maximum of 32 alphanumeric characters.');
        } else {
            /* user without shell account, different constraints */
            return gettext('A username must contain alphanumeric characters or a valid email address.');
        }
    }

    /**
     * retrieve field validators for this field type
     * @return array returns Text/regex validator
     */
    public function getValidators()
    {
        $validators = parent::getValidators();

        if ($this->internalValue != null) {
            $that = $this;
            $validators[] = new CallbackValidator(["callback" => function ($data) use ($that) {
                $messages = [];
                $failed = true;
                if (!empty((string)$this->getParentNode()->shell)) {
                    if (preg_match("/^[a-zA-Z0-9\.\-_]{1,32}$/", $data, $matches)) {
                        /* same as previous mask handling in TextField */
                        $failed = $matches[0] != $data;
                    }
                } elseif (strpos($data, '@') !== false) {
                    /* when an @ is offered, we assume an email addres which has more constraints */
                    $failed = filter_var($data, FILTER_VALIDATE_EMAIL) === false;
                } elseif (preg_match("/^[a-zA-Z0-9\.\-_@]{1,320}$/", $data, $matches)) {
                    $failed = $matches[0] != $data;
                }
                if ($failed) {
                    $messages[] = $that->getValidationMessage();
                }
                return $messages;
            }
            ]);
        }
        return $validators;
    }
}
