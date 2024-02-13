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

class Validation extends \ArrayObject
{
    private $validators = [];
    private $data = [];

    public function __construct($validators = [])
    {
        parent::__construct();
        $this->validators = $validators;
        $this->data = [];
    }

    /**
     *  Appends a message to the messages list
     *  @$message MessageInterface $message
     */
    public function appendMessage($message)
    {
        $this[] = $message;
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
        if (empty($this->validators[$key])) {
            $this->validators[$key] = [];
        }
        $this->validators[$key][] = $validator;
        return $this;
    }

    /**
     * Validate a set of data according to a set of rules
     *
     * @param array $data
     */
    public function validate($data)
    {
        $this->data = $data;
        $phalcon_validation = new \Phalcon\Filter\Validation();
        foreach ($this->validators as $field => $validators) {
            foreach ($validators as $validator) {
                if (is_a($validator, 'Phalcon\Filter\Validation\ValidatorInterface')) {
                    $phalcon_validation->add($field, $validator);
                } else {
                    $validator->validate($this, $field);
                }
            }
        }

        // XXX: temporary dual validation
        $phalconMsgs = $phalcon_validation->validate($data);
        if (!empty($phalconMsgs)) {
            foreach ($phalconMsgs as $phalconMsg) {
                $this[] = $phalconMsg;
            }
        }
        return $this;
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
        return $this;
    }
}
