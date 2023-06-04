<?php

/*
 * Copyright (C) 2022 Deciso B.V.
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

namespace OPNsense\Base;

use Phalcon\Messages\Messages;

class Validation
{
    private $validators = [];
    private $messages = null;
    public function __construct($validators = [])
    {
        $this->validators = $validators;
        $this->phalcon_validation = explode('.', phpversion("phalcon"))[0] < 5
            ? new \Phalcon\Validation()
            : new \Phalcon\Filter\Validation();
        $this->messages = new Messages();
        $this->data = [];
    }

    /**
     *  Appends a message to the messages list
     *  @$message MessageInterface $message
     */
    public function appendMessage($message)
    {
        $this->messages->appendMessage($message);
    }

    /**
     * Adds a validator to a field
     *
     * @param string|array       $key
     * @param BaseValidator|ValidatorInterface $validator
     *
     * @return Validation
     */
    public function add($key, $validator)
    {
        if (is_a($validator, "OPNsense\\Base\\BaseValidator")) {
            if (empty($this->validators[$key])) {
                $this->validators[$key] = [];
            }
            $this->validators[$key][] = $validator;
        } else {
            $this->phalcon_validation->add($key, $validator);
        }
        return $this;
    }

    /**
     * Validate a set of data according to a set of rules
     *
     * @param array $data
     */
    public function validate($data)
    {
        $validatorData = $this->validators;
        $this->data = $data;

        foreach ($validatorData as $field => $validators) {
            foreach ($validators as $validator) {
                $validator->validate($this, $field);
            }
        }

        // XXX: temporary dual validation
        $phalconMsgs = $this->phalcon_validation->validate($data);
        if (!empty($phalconMsgs)) {
            foreach ($phalconMsgs as $phalconMsg) {
                $this->messages[] = $phalconMsg;
            }
        }
        return $this->messages;
    }

    public function getValue($attribute)
    {
        return isset($this->data[$attribute]) ? $this->data[$attribute] : null;
    }

    /**
     * Only used by tests
     */
    public function getMessages()
    {
        return $this->messages;
    }
}
