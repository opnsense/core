<?php

/**
 *    Copyright (C) 2017 Deciso B.V.
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
use Phalcon\Messages\Message;

/**
 * Class NetworkValidator validate domain and hostnames
 * @package OPNsense\Base\Validators
 */
class HostValidator extends AbstractValidator implements ValidatorInterface
{
    /**
     *
     * @param \Phalcon\Validation $validator
     * @param string $attribute
     * @return boolean
     */
    public function validate(\Phalcon\Validation $validator, $attribute): bool
    {
        $result = true;
        $msg = $this->getOption('message');
        $fieldSplit = $this->getOption('split', null);
        if ($fieldSplit == null) {
            $values = array($validator->getValue($attribute));
        } else {
            $values = explode($fieldSplit, $validator->getValue($attribute));
        }
        foreach ($values as $value) {
            // set filter options
            $filterOptDomain = FILTER_FLAG_HOSTNAME;
            $filterOptIp = FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6;

            if (filter_var($value, FILTER_VALIDATE_DOMAIN, $filterOptDomain) === false) {
                if ($this->getOption('allowip') === true) {
                    if (filter_var($value, FILTER_VALIDATE_IP, $filterOptIp) === false) {
                        $result = false;
                    }
                } else {
                    $result = false;
                }
            }

            if (!$result) {
                // append validation message
                $validator->appendMessage(new Message($msg, $attribute, 'HostValidator'));
            }
        }

        return $result;
    }
}
