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

use OPNsense\Base\Validators\NetworkValidator;

/**
 * @package OPNsense\Base\FieldTypes
 */
class NetworkField extends BaseField
{
    /**
     * @var bool marks if this is a data node or a container
     */
    protected $internalIsContainer = false;

    /**
     * @var bool marks if net mask is required
     */
    protected $internalNetMaskRequired = false;

    /**
     * @var bool marks if net mask is (dis)allowed
     */
    protected $internalNetMaskAllowed = true;

    /**
     * @var null when multiple values could be provided at once, specify the split character
     */
    protected $internalFieldSeparator = null;

    /**
     * @var bool wildcard (any) enabled
     */
    protected $internalWildcardEnabled = true;

    /**
     * @var string Network family (ipv4, ipv6)
     */
    protected $internalAddressFamily = null;

    /**
     * @var bool when set, results are returned as list (with all options enabled)
     */
    private $internalAsList = false;

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
     * if multiple addresses / networks maybe provided at once, set separator.
     * @param string $value separator
     */
    public function setFieldSeparator($value)
    {
        $this->internalFieldSeparator = $value;
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
     * select if multiple networks may be selected at once
     * @param $value boolean value 0/1
     */
    public function setAsList($value)
    {
        if (trim(strtoupper($value)) == "Y") {
            $this->internalAsList = true;
        } else {
            $this->internalAsList = false;
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
     * get valid options, descriptions and selected value
     * @return array
     */
    public function getNodeData()
    {
        if ($this->internalAsList) {
            // return result as list
            $result = array();
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
     * {@inheritdoc}
     */
    protected function defaultValidationMessage()
    {
        return gettext('Please specify a valid network segment or IP address.');
    }

    /**
     * retrieve field validators for this field type
     * @return array returns Text/regex validator
     */
    public function getValidators()
    {
        $validators = parent::getValidators();
        if ($this->internalValue != null) {
            if ($this->internalValue != "any" || $this->internalWildcardEnabled == false) {
                // accept any as target
                $validators[] = new NetworkValidator([
                    'message' => $this->getValidationMessage(),
                    'split' => $this->internalFieldSeparator,
                    'netMaskRequired' => $this->internalNetMaskRequired,
                    'netMaskAllowed' => $this->internalNetMaskAllowed,
                    'version' => $this->internalAddressFamily,
                    'strict' => $this->internalStrict
                ]);
            }
        }
        return $validators;
    }
}
