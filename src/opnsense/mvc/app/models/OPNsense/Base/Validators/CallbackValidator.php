<?php

/**
 *    Copyright (C) 2018 Deciso B.V.
 *
 *    All rights reserved.
 *
 *    Redistribution and use in source and binary forms, with or without
 *    modification, are permitted provided that the following conditions are met:
 *
 *    1. Redistributions of source code must retain the above copyright notice,
 *       this list of conditions and the following disclaimer.
 *
 *    2. Redistributions in binary form must reproduce the above copyright
 *       notice, this list of conditions and the following disclaimer in the
 *       documentation and/or other materials provided with the distribution.
 *
 *    THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
 *    INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 *    AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 *    AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 *    OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 *    SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 *    INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 *    CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 *    ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 *    POSSIBILITY OF SUCH DAMAGE.
 *
 */

namespace OPNsense\Base\Validators;

use Phalcon\Validation\AbstractValidator;
use Phalcon\Validation\ValidatorInterface;
use Phalcon\Validation;
use Phalcon\Messages\Message;

/**
 * Class CallbackValidator
 * @package OPNsense\Base\Validators
 */
class CallbackValidator extends AbstractValidator implements ValidatorInterface
{

    /**
    * Executes callback validator, which should return validation messages on failure
    *
    * @param Validation $validator
    * @param string $attribute
    * @return boolean
    */
    public function validate(Validation $validator, $attribute): bool
    {
        $callback = $this->getOption('callback');
        if ($callback) {
            $messages = $callback($validator->getValue($attribute));
            if (!empty($messages)) {
                foreach ($messages as $message) {
                    $validator->appendMessage(new Message($message, $attribute, 'CallbackValidator'));
                }
                return false;
            } else {
                return true;
            }
        } else {
            // dropout with validation error when we miss a callback
            $validator->appendMessage(
                new Message(
                    gettext("Configuration error, missing callback in CallbackValidator"),
                    $attribute,
                    'CallbackValidator'
                )
            );
        }
        return false;
    }
}
