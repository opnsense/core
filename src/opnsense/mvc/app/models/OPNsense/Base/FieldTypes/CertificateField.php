<?php

/**
 *    Copyright (C) 2015-2016 Deciso B.V.
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
     * @var string certificate type cert/ca, reflects config section to use as source
     */
    private $certificateType = "cert";

    /**
     * @var bool field may contain multiple certs at once
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
     * set certificate type (cert/ca)
     * @param $value certificate type
     */
    public function setType($value)
    {
        if (trim(strtolower($value)) == "ca") {
            $this->certificateType = "ca";
        } elseif (trim(strtolower($value)) == "crl") {
            $this->certificateType = "crl";
        } else {
            $this->certificateType = "cert";
        }
    }

    /**
     * select if multiple certs may be selected at once
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
        if (!array_key_exists($this->certificateType, self::$internalOptionList)) {
             self::$internalOptionList[$this->certificateType] = array();
             $configObj = Config::getInstance()->object();
            foreach ($configObj->{$this->certificateType} as $cert) {
                self::$internalOptionList[$this->certificateType][(string)$cert->refid] = (string)$cert->descr;
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
            $result[""] = array("value" => gettext("none"), "selected" => 0);
        }

        $certs = explode(',', $this->internalValue);
        foreach (self::$internalOptionList[$this->certificateType] as $optKey => $optValue) {
            if (in_array($optKey, $certs)) {
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
                    'domain'=>array_keys(self::$internalOptionList[$this->certificateType])));
            } else {
                // single cert selection
                $validators[] = new InclusionIn(array('message' => $this->internalValidationMessage,
                    'domain'=>array_keys(self::$internalOptionList[$this->certificateType])));
            }
        }
        return $validators;
    }
}
