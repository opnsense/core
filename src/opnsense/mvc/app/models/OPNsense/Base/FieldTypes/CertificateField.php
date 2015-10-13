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

use Phalcon\Validation\Validator\InclusionIn;
use OPNsense\Core\Config;

/**
 * Class CertificateField field type to select certificates from the internal cert manager
 * package to glue legacy certificates into the model.
 * @package OPNsense\Base\FieldTypes
 */
class CertificateField extends BaseField
{
    /**
     * @var bool marks if this is a data node or a container
     */
    protected $internalIsContainer = false;

    /**
     * @var array collected options
     */
    private static $internalOptionList = array();

    /**
     * generate validation data (list of certificates)
     */
    public function eventPostLoading()
    {
        if (count($this->internalOptionList) ==0) {
            $configObj = Config::getInstance()->object();
            foreach ($configObj->cert as $cert) {
                self::$internalOptionList[(string)$cert->refid] = (string)$cert->descr ;
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
            $result[""] = array("value"=>"none", "selected" => 0);
        }
        foreach (self::$internalOptionList as $optKey => $optValue) {
            if ($optKey == $this->internalValue) {
                $selected = 1;
            } else {
                $selected = 0;
            }
            $result[$optKey] = array("value"=>$optValue, "selected" => $selected);
        }

        return $result;
    }

    /**
     * retrieve field validators for this field type
     * @return array returns InclusionIn validator
     */
    public function getValidators()
    {
        if ($this->internalValidationMessage == null) {
            $msg = "option not in list" ;
        } else {
            $msg = $this->internalValidationMessage;
        }
        if (($this->internalIsRequired == true || $this->internalValue != null)) {
            return array(new InclusionIn(array('message' => $msg,'domain'=>array_keys(self::$internalOptionList))));
        } else {
            // empty field and not required, skip this validation.
            return array();
        }
    }
}
