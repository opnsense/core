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
namespace OPNsense\Base\FieldTypes;

use Phalcon\Validation\Validator\InclusionIn;
use OPNsense\Base\Validators\CsvListValidator;
use OPNsense\Core\Config;


class AuthGroupField extends BaseField
{
    /**
     * @var bool marks if this is a data node or a container
     */
    protected $internalIsContainer = false;

    /**
     * @var bool field may contain multiple groups at once
     */
    private $internalMultiSelect = false;

    /**
     * @var string default validation message string
     */
    protected $internalValidationMessage = "option not in list";

    /**
     * @var array collected options
     */
    private static $internalOptionList = array();

    /**
     * select if multiple groups may be selected at once
     * @param $value boolean value Y/N
     */
    public function setMultiple($value)
    {
        if (trim(strtoupper($value)) == "Y") {
            $this->internalMultiSelect = true;
        } else {
            $this->internalMultiSelect = false;
        }
    }

    /**
     * generate validation data (list of certificates)
     */
    public function eventPostLoading()
    {
        if (empty(self::$internalOptionList)) {
            $cnf = Config::getInstance()->object();
            if (isset($cnf->system->group)) {
                foreach ($cnf->system->group as $group) {
                    self::$internalOptionList[(string)$group->gid] = (string)$group->name;
                }
            }
        }
    }

    /**
     * get valid options, descriptions and selected value
     * @return array
     */
    public function getNodeData()
    {
        $result = array ();
        // if certificate is not required, add empty option
        if (!$this->internalIsRequired) {
            $result[""] = array("value" => gettext("none"), "selected" => ($this->internalValue == "") ? 1 : 0);
        }

        $groups = explode(',', $this->internalValue);
        foreach (self::$internalOptionList as $optKey => $optValue) {
            if (in_array($optKey, $groups)) {
                $selected = 1;
            } else {
                $selected = 0;
            }
            $result[$optKey] = array("value" => $optValue, "selected" => $selected);
        }

        return $result;
    }

    /**
     * retrieve field validators for this field type
     * @return array returns InclusionIn validator
     */
    public function getValidators()
    {
        $validators = parent::getValidators();
        if ($this->internalValue != null) {
            if ($this->internalMultiSelect) {
                // field may contain more than one cert
                $validators[] = new CsvListValidator(array('message' => $this->internalValidationMessage,
                    'domain'=>array_keys(self::$internalOptionList)));
            } else {
                // single group selection
                $validators[] = new InclusionIn(array('message' => $this->internalValidationMessage,
                    'domain'=>array_keys(self::$internalOptionList)));
            }
        }
        return $validators;
    }
}
