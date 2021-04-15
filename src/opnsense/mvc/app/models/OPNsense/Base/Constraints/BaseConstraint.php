<?php

/**
 *    Copyright (C) 2016 Deciso B.V.
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

namespace OPNsense\Base\Constraints;

use Phalcon\Validation\AbstractValidator;
use Phalcon\Validation\ValidatorInterface;
use Phalcon\Messages\Message;

abstract class BaseConstraint extends AbstractValidator implements ValidatorInterface
{
    /**
     * check if field is empty  (either boolean field as false or an empty field)
     * @param $node
     * @return bool
     */
    public function isEmpty($node)
    {
        $node_class = get_class($node);
        if ($node_class == "OPNsense\Base\FieldTypes\BooleanField") {
            return empty((string)$node);
        } elseif (empty((string)$node) || (string)$node == "0") {
            return true;
        }
        return false;
    }

    /**
     * @param \Phalcon\Validation $validator
     * @param $attribute
     */
    protected function appendMessage(\Phalcon\Validation $validator, $attribute)
    {
        $message = $this->getOption('ValidationMessage');
        $name = $this->getOption('name');
        if (empty($message)) {
            $message = 'validation failure ' . get_class($this);
        }
        if (empty($name)) {
            $name =  get_class($this);
        }
        $validator->appendMessage(new Message($message, $attribute, $name));
    }

    /**
     * retrieve option value list
     * @param $fieldname
     * @return mixed
     */
    protected function getOptionValueList($fieldname)
    {
        $result = array();
        $options = $this->getOption($fieldname);
        if (!empty($options)) {
            foreach ($options as $option) {
                $result[] = $option;
            }
        }
        return $result;
    }
}
