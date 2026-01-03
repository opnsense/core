<?php

/*
 * Copyright (C) 2024-2025 Deciso B.V.
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
class IPPortField extends BaseSetField
{
    /**
     * @var string Network family (ipv4, ipv6)
     */
    protected $internalAddressFamily = null;

    /**
     * @var string port requirement
     */
    protected $internalPortOptional = false;

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
     * setter for address family
     * @param $value address family [ipv4, ipv6, empty for all]
     */
    public function setAddressFamily($value)
    {
        $this->internalAddressFamily = trim(strtolower($value));
    }

    /**
     * setter for optional port
     * @param $value port allowed Y/N
     */
    public function setPortOptional($value)
    {
        $this->internalPortOptional = trim(strtoupper($value)) == 'Y';
    }

    /**
     * select if a hostname may be provided instead of an address (without addressfamily validation)
     * @param $value string value Y/N
     */
    public function setHostnameAllowed($value)
    {
        $this->internalHostnameAllowed = trim(strtoupper($value)) == 'Y';
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
                foreach ($this->iterateInput($data) as $value) {
                    $parts = explode(':', $value);

                    if ($this->internalAddressFamily == 'ipv4' || $this->internalAddressFamily == null) {
                        if (count($parts) == 2 && Util::isIpv4Address($parts[0]) && Util::isPort($parts[1])) {
                            continue;
                        }
                        if ($this->internalPortOptional && Util::isIpv4Address($value)) {
                            continue;
                        }
                    }

                    if ($this->internalHostnameAllowed) {
                        if (count($parts) == 2 && Util::isPort($parts[1]) && filter_var($parts[0], FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) !== false) {
                            continue;
                        }
                        if ($this->internalPortOptional && filter_var($value, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) !== false) {
                            continue;
                        }
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
                        if ($this->internalPortOptional && Util::isIpv6Address($value)) {
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
