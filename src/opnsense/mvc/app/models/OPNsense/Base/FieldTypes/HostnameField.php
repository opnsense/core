<?php

/*
 * Copyright (C) 2017-2022 Deciso B.V.
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

use OPNsense\Base\Validators\NetworkValidator;
use OPNsense\Base\Validators\HostValidator;

/**
 * @package OPNsense\Base\FieldTypes
 */
class HostnameField extends BaseField
{
    /**
     * @var bool marks if this is a data node or a container
     */
    protected $internalIsContainer = false;

    /**
     * @var string default validation message string
     */
    protected $internalValidationMessage = "please specify a valid address (IPv4/IPv6) or hostname";

    /**
     * @var null when multiple values could be provided at once, specify the split character
     */
    protected $internalFieldSeparator = null;

    /**
     * @var bool when set, results are returned as list (with all options enabled)
     */
    private $internalAsList = false;

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
     * if multiple hostnames maybe provided at once, set separator.
     * @param string $value separator
     */
    public function setFieldSeparator($value)
    {
        $this->internalFieldSeparator = $value;
    }

    /**
     * select if multiple hostnames may be selected at once
     * @param $value boolean value 0/1
     */
    public function setAsList($value)
    {
        $this->internalAsList = trim(strtoupper($value)) == "Y";
    }

    /**
     * get valid options, descriptions and selected value
     * @return array
     */
    public function getNodeData()
    {
        if ($this->internalAsList) {
            // return result as list
            $result = [];
            foreach (explode(',', $this->internalValue) as $net) {
                $result[$net] = array("value" => $net, "selected" => 1);
            }
            return $result;
        } else {
            // normal, single field response
            return $this->internalValue;
        }
    }

    /**
     * retrieve field validators for this field type
     * @return array returns Text/regex validator
     */
    public function getValidators()
    {
        $validators = parent::getValidators();
        if ($this->internalValue != null) {
            $validators[] = new HostValidator(array(
                'message' => $this->internalValidationMessage,
                'split' => $this->internalFieldSeparator,
                'allowip' => $this->internalIpAllowed,
                'hostwildcard' => $this->internalHostWildcardAllowed,
                'fqdnwildcard' => $this->internalFqdnWildcardAllowed,
                'zoneroot' => $this->internalZoneRootAllowed
            ));
        }
        return $validators;
    }
}
