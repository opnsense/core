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

/**
 * Class ModelRelationField defines a relation to another entity within the model, acts like a select item.
 * @package OPNsense\Base\FieldTypes
 */
class ModelRelationField extends BaseField
{
    /**
     * @var bool marks if this is a data node or a container
     */
    protected $internalIsContainer = false;

    /**
     * @var array collected options
     */
    private static $internalOptionList = null;

    /**
     * Set model as reference list, use uuid's as key
     * @param $mdlStructure nested array structure defining the usable datasources.
     */
    public function setModel($mdlStructure)
    {
        if (!is_array(self::$internalOptionList)) {
            self::$internalOptionList = array();
            if (is_array($mdlStructure)) {
                foreach ($mdlStructure as $modelData) {
                    if (array_key_exists("source", $modelData)) {
                        $className = str_replace(".", "\\", $modelData['source']);
                        $modelObj = new $className;
                        if (array_key_exists("items", $modelData)) {
                            foreach ($modelObj->getNodeByReference($modelData['items'])->__items as $node) {
                                $displayKey = $modelData['display'];
                                if (array_key_exists("uuid", $node->getAttributes()) && $node->$displayKey != null) {
                                    $uuid = $node->getAttributes()['uuid'];
                                    self::$internalOptionList[$uuid] = $node->$displayKey->__toString();
                                }
                            }
                        }
                        unset($modelObj);
                    }
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
        if (is_array(self::$internalOptionList)) {
            foreach (self::$internalOptionList as $optKey => $optValue) {
                if ($optKey == $this->internalValue && $this->internalValue != null) {
                    $selected = 1;
                } else {
                    $selected = 0;
                }
                $result[$optKey] = array("value"=>$optValue, "selected" => $selected);
            }
        }

        return $result;
    }

    /**
     * @return array returns Text/regex validator
     */
    public function getValidators()
    {
        if ($this->internalValidationMessage == null) {
            $msg = "option not in list";
        } else {
            $msg = $this->internalValidationMessage;
        }

        if (($this->internalIsRequired == true || $this->internalValue != null) &&
            count(self::$internalOptionList) > 0
        ) {
            return array(new InclusionIn(array('message' => $msg, 'domain' => array_keys(self::$internalOptionList))));
        } else {
            // empty field and not required, skip this validation.
            return array();
        }
    }
}
