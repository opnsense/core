<?php

/*
 * Copyright (C) 2015-2017 Deciso B.V.
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

use OPNsense\Firewall\Util;
use OPNsense\Base\Validators\CallbackValidator;

/**
 * @package OPNsense\Base\FieldTypes
 */
class NetworkField extends BaseSetField
{
    /**
     * @var bool marks if net mask is required
     */
    protected $internalNetMaskRequired = false;

    /**
     * @var bool marks if net mask is (dis)allowed
     */
    protected $internalNetMaskAllowed = true;

    /**
     * @var bool wildcard (any) enabled
     */
    protected $internalWildcardEnabled = true;

    /**
     * @var string Network family (ipv4, ipv6)
     */
    protected $internalAddressFamily = null;

    /**
     * @var bool when set, host bits with a value other than zero are not allowed in the notation if a mask is provided
     */
    private $internalStrict = false;

    /**
     * always lowercase / trim networks
     * @param string $value
     */
    public function setValue($value)
    {
        parent::setValue(trim(strtolower($value)));
    }

    /**
     * setter for net mask required
     * @param integer $value
     */
    public function setNetMaskRequired($value)
    {
        if (trim(strtoupper($value)) == "Y") {
            $this->internalNetMaskRequired = true;
        } else {
            $this->internalNetMaskRequired = false;
        }
    }

    /**
     * setter for net mask required
     * @param integer $value
     */
    public function setNetMaskAllowed($value)
    {
        $this->internalNetMaskAllowed = (trim(strtoupper($value)) == "Y");
    }

    /**
     * setter for address family
     * @param $value address family [ipv4, ipv6, empty for all]
     */
    public function setAddressFamily($value)
    {
        $this->internalAddressFamily = trim(strtolower($value));
    }

    /**
     * enable "any" keyword
     * @param string $value Y/N
     */
    public function setWildcardEnabled($value)
    {
        if (trim(strtoupper($value)) == "Y") {
            $this->internalWildcardEnabled = true;
        } else {
            $this->internalWildcardEnabled = false;
        }
    }

    /**
     * select if host bits are allowed in the notation
     * @param $value
     */
    public function setStrict($value)
    {
        if (trim(strtoupper($value)) == "Y") {
            $this->internalStrict = true;
        } else {
            $this->internalStrict = false;
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function defaultValidationMessage()
    {
        return gettext('Please specify a valid network segment or IP address.');
    }

    /**
     * @param string $input data to test
     * @return bool if valid network address or segment (using this objects settings)
     */
    protected function isValidInput($input)
    {
        foreach ($this->iterateInput($input) as $value) {
            // parse filter options
            $filterOpt = 0;
            switch (strtolower($this->internalAddressFamily ?? '')) {
                case "ipv4":
                    $filterOpt |= FILTER_FLAG_IPV4;
                    break;
                case "ipv6":
                    $filterOpt |= FILTER_FLAG_IPV6;
                    break;
                default:
                    $filterOpt |= FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6;
            }

            // split network
            if (strpos($value, "/") !== false) {
                if ($this->internalNetMaskAllowed === false) {
                    return false;
                } else {
                    $cidr = $value;
                    $parts = explode("/", $value);
                    if (count($parts) > 2 || !ctype_digit($parts[1])) {
                        // more parts then expected or second part is not numeric
                        return false;
                    } else {
                        $mask = $parts[1];
                        $value = $parts[0];
                        if (strpos($parts[0], ":") !== false) {
                            // probably ipv6, mask must be between 0..128
                            if ($mask < 0 || $mask > 128) {
                                return false;
                            }
                        } else {
                            // most likely ipv4 address, mask must be between 0..32
                            if ($mask < 0 || $mask > 32) {
                                return false;
                            }
                        }
                    }

                    if ($this->internalStrict === true && !Util::isSubnetStrict($cidr)) {
                        return false;
                    }
                }
            } elseif ($this->internalNetMaskRequired === true) {
                return false;
            }

            if (filter_var($value, FILTER_VALIDATE_IP, $filterOpt) === false) {
                return false;
            }
        }

        return true;
    }

    /**
     * retrieve field validators for this field type
     * @return array returns validators
     */
    public function getValidators()
    {
        $validators = parent::getValidators();
        if ($this->internalValue != null) {
            if ($this->internalValue != "any" || $this->internalWildcardEnabled == false) {
                $that = $this;
                $validators[] = new CallbackValidator(["callback" => function ($data) use ($that) {
                    $messages = [];
                    if (!$that->isValidInput($data)) {
                        $messages[] =  $this->getValidationMessage();
                    }
                    return $messages;
                }]);
            }
        }
        return $validators;
    }
}
