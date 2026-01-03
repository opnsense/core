<?php

/*
 * Copyright (C) 2017-2024 Deciso B.V.
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

/**
 * @package OPNsense\Base\FieldTypes
 */
class HostnameField extends BaseSetField
{
    /**
     * @var bool IP address allowed
     */
    protected $internalIpAllowed = true;

    /**
     * @var bool wildcard (*) enabled
     */
    protected $internalHostWildcardAllowed = false;

    /**
     * @var bool wildcard (*.my.top.level.domain) enabled
     */
    protected $internalFqdnWildcardAllowed = false;

    /**
     * @var bool zone root (@) enabled
     */
    protected $internalZoneRootAllowed = false;

    /**
     * @var bool dns name as defined by RFC2181 (lifting some constraints)
     */
    protected $internalIsDNSName = false;

    /**
     * is dns name as defined by RFC2181
     * @param string $value Y/N
     */
    public function setIsDNSName($value)
    {
        $this->internalIsDNSName = trim(strtoupper($value)) == "Y";
    }

    /**
     * ip addresses allowed
     * @param string $value Y/N
     */
    public function setIpAllowed($value)
    {
        $this->internalIpAllowed = trim(strtoupper($value)) == "Y";
    }

    /**
     * host wildcard (*) allowed
     * @param string $value Y/N
     */
    public function setHostWildcardAllowed($value)
    {
        $this->internalHostWildcardAllowed = trim(strtoupper($value)) == "Y";
    }

    /**
     * fqdn (prefix) wildcard (*.my.top.level.domain) allowed
     * @param string $value Y/N
     */
    public function setFqdnWildcardAllowed($value)
    {
        $this->internalFqdnWildcardAllowed = trim(strtoupper($value)) == "Y";
    }

    /**
     * zone root (@) allowed
     * @param string $value Y/N
     */
    public function setZoneRootAllowed($value)
    {
        $this->internalZoneRootAllowed = trim(strtoupper($value)) == "Y";
    }

    /**
     * always trim hostnames
     * @param string $value
     */
    public function setValue($value)
    {
        parent::setValue(trim($value));
    }

    /**
     * {@inheritdoc}
     */
    protected function defaultValidationMessage()
    {
        return gettext('Please specify a valid IP address or hostname.');
    }

    /**
     * retrieve field validators for this field type
     * @return array returns Text/regex validator
     */
    public function getValidators()
    {
        $validators = parent::getValidators();
        $sender = $this;

        if ($this->internalValue != null) {
            $validators[] = new CallbackValidator(["callback" => function ($data) use ($sender) {
                $result = false;
                $response = [];

                foreach ($sender->iterateInput($data) as $value) {
                    // set filter options
                    $filterOptDomain = $sender->internalIsDNSName ? 0 : FILTER_FLAG_HOSTNAME;
                    $val_is_ip = filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6) !== false;
                    if ($sender->internalFqdnWildcardAllowed && substr($value, 0, 2) == '*.') {
                        $value = substr($value, 2);
                    } elseif ($sender->internalZoneRootAllowed && substr($value, 0, 2) == '@.') {
                        $value = substr($value, 2);
                    }

                    if ($sender->internalZoneRootAllowed && $value == '@') {
                        $result = true;
                    } elseif ($sender->internalHostWildcardAllowed && $value == '*') {
                        $result = true;
                    } elseif ($sender->internalIpAllowed && $val_is_ip) {
                        $result = true;
                    } elseif (filter_var($value, FILTER_VALIDATE_DOMAIN, $filterOptDomain) !== false) {
                        // internalIpAllowed = false and ip address offered,  trigger validation
                        $result = $val_is_ip ? false : preg_match_all('/(\s|\/|\*)/', $value) === 0;
                    }

                    if (!$result) {
                        // append validation message
                        $response[] = $sender->getValidationMessage();
                        break;
                    }
                }

                return $response;
            }]);
        }

        return $validators;
    }
}
