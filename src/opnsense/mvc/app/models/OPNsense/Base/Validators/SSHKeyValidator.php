<?php

/**
 *    Copyright (C) 2018 Fabian Franz
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

use \Phalcon\Validation\Validator;
use \Phalcon\Validation\ValidatorInterface;
use \Phalcon\Validation\Message;

/**
 * Class SSHKeyValidator
 * @package OPNsense\Base\Validators
 */
class SSHKeyValidator extends Validator implements ValidatorInterface
{

    /**
    * Executes SSH Key validation
    *
    * @param \Phalcon\Validation $validator
    * @param string $attribute
    * @return boolean
    */
    public function validate(\Phalcon\Validation $validator, $attribute)
    {
        $value = trim($validator->getValue($attribute)); // content
        $msg = $this->getOption('message');
        // check if a SSH Key header exist
        if (!preg_match('/\-{5}BEGIN.*PRIVATE KEY\-{5}/', $value)) {
            $validator->appendMessage(new Message($msg, $attribute, 'SSHKeyValidator'));
            return false;
        }
        // check if a ssh trailor exists
        if (!preg_match('/\-{5}END.*PRIVATE KEY\-{5}/', $value)) {
            $validator->appendMessage(new Message($msg, $attribute, 'SSHKeyValidator'));
            return false;
        }
        $lines = explode("\n", $value);
        array_shift($lines); // remove BEGIN ... Message
        array_pop($lines);   // remove END ... Message
        // remove empty lines if ssh generator adds headers
        // like in HTTP, remove them too
        while (isset($lines[0]) && ($lines[0] == '' || strstr($lines[0], ':') !== FALSE))
        {
            array_shift($lines);
        }
        $base64key = implode($lines);
        if (!preg_match('/^(?:[A-Za-z0-9\+\/]{4})*(?:[A-Za-z0-9\+\/]{2}==|[A-Za-z0-9\+\/]{3}=)?$/', $base64key)) {
            $validator->appendMessage(new Message($msg, $attribute, 'SSHKeyValidator'));
            return false;
        }
        return true;
    }
}
