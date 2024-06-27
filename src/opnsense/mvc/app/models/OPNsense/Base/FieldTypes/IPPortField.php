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

namespace OPNsense\Base\FieldTypes;

use OPNsense\Base\Validators\CallbackValidator;
use OPNsense\Firewall\Util;

/**
 * Class IPPortField
 * @package OPNsense\Base\FieldTypes
 */
class IPPortField extends BaseField
{
    /**
     * @var bool marks if this is a data node or a container
     */
    protected $internalIsContainer = false;

    /**
     * @var string when multiple values could be provided at once, specify the split character
     */
    protected $internalFieldSeparator = ',';

    /**
     * @var bool when set, results are returned as list (with all options enabled)
     */
    protected $internalAsList = false;

    /**
     * @var string Network family (ipv4, ipv6)
     */
    protected $internalAddressFamily = null;

    /**
     * @var bool hostname allowed
     */
    protected $internalHostnameAllowed = false;

    /**
     * trim IP-Port combination
     * @param string $value
     */
    public function setValue($value)
    {
        parent::setValue(trim($value));
    }

    /**
     * get valid options, descriptions and selected value
     * @return array
     */
    public function getNodeData()
    {
        if ($this->internalAsList) {
            // return result as list
            $result = array();
            foreach (explode($this->internalFieldSeparator, $this->internalValue) as $address) {
                $result[$address] = array("value" => $address, "selected" => 1);
            }
            return $result;
        } else {
            // normal, single field response
            return $this->internalValue;
        }
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
     * select if a hostname may be provided instead of an address (without addressfamily validation)
     * @param $value string value Y/N
     */
    public function setHostnameAllowed($value)
    {
        $this->internalHostnameAllowed = trim(strtoupper($value)) == "Y";
    }

    /**
     * select if multiple IP-Port combinations may be selected at once
     * @param $value string value Y/N
     */
    public function setAsList($value)
    {
        $this->internalAsList = trim(strtoupper($value)) == "Y";
    }

    /**
     * {@inheritdoc}
     */
    protected function defaultValidationMessage()
    {
        return gettext('Invalid IP-port combination.');
    }

    /**
     * retrieve field validators for this field type
     * @return array returns validators
     */
    public function getValidators()
    {
        $validators = parent::getValidators();
        if ($this->internalValue != null) {
            $validators[] = new CallbackValidator(["callback" => function ($data) {
                foreach ($this->internalAsList ? explode($this->internalFieldSeparator, $data) : [$data] as $value) {
                    $parts = explode(':', $value);
                    if ($this->internalAddressFamily == 'ipv4' || $this->internalAddressFamily == null) {
                        if (count($parts) == 2 && Util::isIpv4Address($parts[0]) && Util::isPort($parts[1])) {
                            continue;
                        }
                    }
                    if (
                        $this->internalHostnameAllowed &&
                        count($parts) == 2 &&
                        Util::isPort($parts[1]) &&
                        filter_var($parts[0], FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) !== false
                    ) {
                        continue;
                    }

                    if ($this->internalAddressFamily == 'ipv6' || $this->internalAddressFamily == null) {
                        $parts = preg_split('/\[([^\]]+)\]/', $value, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
                        if (
                            count($parts) == 2 &&
                            Util::isIpv6Address($parts[0]) &&
                            str_contains($parts[1], ':') &&
                            Util::isPort(trim($parts[1], ': '))
                        ) {
                            continue;
                        }
                    }

                    return ["\"" . $value . "\" is invalid. " . $this->getValidationMessage()];
                }
            }]);
        }

        return $validators;
    }
}
