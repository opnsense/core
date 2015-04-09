<?php
/**
 *    Copyright (C) 2015 Deciso B.V.
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

use Phalcon\Validation\Validator\Regex;

/**
 * Class OptionField
 * @package OPNsense\Base\FieldTypes
 */
class OptionField extends BaseField
{
    /**
     * @var bool marks if this is a data node or a container
     */
    protected $internalIsContainer = false;

    /**
     * @var array
     */
    private $internalOptionList = array();


    /**
     * setter for option values
     * @param $data
     */
    public function setOptionValues($data)
    {
        if (is_array($data)) {
            $this->internalOptionList = array();
            // copy options to internal structure, make sure we don't copy in array structures
            foreach ($data as $key => $value) {
                if (!is_array($value)) {
                    $this->internalOptionList[$key] = $value ;
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
        foreach ($this->internalOptionList as $optKey => $optValue) {
            if ($optKey == $this->internalValue) {
                $selected = 0;
            } else {
                $selected = 1;
            }
            $result[$optKey] = array("value"=>$optKey,"description" => $optValue, "selected" => $selected);
        }

        return $result;
    }

    /**
     * @return array returns Text/regex validator
     */
    public function getValidators()
    {
        // build validation mask
        $validationMask = '(';
        $countid = 0 ;
        foreach ($this->internalOptionList as $key => $value) {
            if ($countid > 0) {
                $validationMask .= '|';
            }
            $validationMask .= $key ;
            $countid++;
        }
        $validationMask .= ')';

        if ($this->internalValidationMessage == null) {
            $msg = "option not in list" ;
        } else {
            $msg = $this->internalValidationMessage;
        }
        if (($this->internalIsRequired == true || $this->internalValue != null) && $validationMask != null) {
            return array(new Regex(array('message' => $msg,'pattern'=>trim($validationMask))));
        } else {
            // empty field and not required, skip this validation.
            return array();
        }
    }
}