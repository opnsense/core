<?php

/**
 *    Copyright (C) 2015-2017 Deciso B.V.
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
 * Class NetworkValidator validate networks and ip addresses
 * @package OPNsense\Base\Validators
 */
class NetworkValidator extends AbstractValidator implements ValidatorInterface
{
    /**
     * Executes network / ip validation, accepts the following parameters as attributes:
     *      version     : ipv4, ipv6, all (default)
     *      noReserved  : true, false (default)
     *      noPrivate   : true, false (default)
     *      noSubnet    : true, false (default)
     *      netMaskRequired : true, false (default)
     *
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
            // parse filter options
            $filterOpt = 0;
            switch (strtolower($this->getOption('version'))) {
                case "ipv4":
                    $filterOpt |= FILTER_FLAG_IPV4;
                    break;
                case "ipv6":
                    $filterOpt |= FILTER_FLAG_IPV6;
                    break;
                default:
                    $filterOpt |= FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6;
            }

            if ($this->getOption('noReserved') === true) {
                $filterOpt |= FILTER_FLAG_NO_RES_RANGE;
            }

            if ($this->getOption('noPrivate') === true) {
                $filterOpt |= FILTER_FLAG_NO_PRIV_RANGE;
            }

            // split network
            if (strpos($value, "/") !== false) {
                if ($this->getOption('netMaskAllowed') === false) {
                    $result = false;
                } else {
                    $parts = explode("/", $value);
                    if (count($parts) > 2 || !ctype_digit($parts[1])) {
                        // more parts then expected or second part is not numeric
                        $result = false;
                    } else {
                        $mask = $parts[1];
                        $value = $parts[0];
                        if (strpos($parts[0], ".")) {
                            // most likely ipv4 address, mask must be between 0..32
                            if ($mask < 0 || $mask > 32) {
                                $result = false;
                            }
                        } else {
                            // probably ipv6, mask must be between 0..128
                            if ($mask < 0 || $mask > 128) {
                                $result = false;
                            }
                        }
                    }
                }
            } elseif ($this->getOption('netMaskRequired') === true) {
                $result = false;
            }


            if (filter_var($value, FILTER_VALIDATE_IP, $filterOpt) === false) {
                $result = false;
            }

            if (!$result) {
                // append validation message
                $validator->appendMessage(new Message($msg, $attribute, 'NetworkValidator'));
            }
        }

        return $result;
    }
}
