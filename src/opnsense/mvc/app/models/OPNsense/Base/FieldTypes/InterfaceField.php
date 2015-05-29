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

use OPNsense\Core\Config;
use Phalcon\Validation\Validator\InclusionIn;

/**
 * Class InterfaceField field type to select usable interfaces, currently this is kind of a backward compatibility
 * package to glue legacy interfaces into the model.
 * @package OPNsense\Base\FieldTypes
 */
class InterfaceField extends BaseField
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
     * @var array
     */
    private $internalFilters = array();

    /**
     * @var string key to use for option selections, to prevent excessive reloading
     */
    private $internalCacheKey = '*';

    /**
     * generate validation data (list of interfaces and well know ports)
     */
    public function eventPostLoading()
    {
        if (!array_key_exists($this->internalCacheKey, self::$internalOptionList)) {
            self::$internalOptionList[$this->internalCacheKey] = array();

            $allInterfaces = array();
            $configObj = Config::getInstance()->object();
            // Iterate over all interfaces configuration and collect data
            foreach ($configObj->interfaces->children() as $key => $value) {
                $allInterfaces[$key] = $value;
            }

            foreach ($allInterfaces as $key => $value) {
                // use filters to determine relevance
                $isMatched = true;
                foreach ($this->internalFilters as $filterKey => $filterData) {
                    if (isset($value->$filterKey)) {
                        $fieldData = $value->$filterKey;
                    } else {
                        // not found, might be a boolean.
                        $fieldData = "0";
                    }

                    if (!preg_match('/'.$filterData.'/', $fieldData)) {
                        $isMatched = false;
                    }
                }
                if ($isMatched) {
                    if ($value->descr == '') {
                        self::$internalOptionList[$this->internalCacheKey][$key] = $key;
                    } else {
                        self::$internalOptionList[$this->internalCacheKey][$key] = $value->descr->__toString();
                    }
                }
            }
        }
    }

    /**
     * set filters to use (in regex) per field, all tags are combined and cached for the next object using the same filters
     * @param $filters filters to use
     */
    public function setFilters($filters)
    {
        if (is_array($filters)) {
            $this->internalFilters = $filters;
            $this->internalCacheKey = md5(serialize($this->internalFilters));
        }
    }

    /**
     * get valid options, descriptions and selected value
     * @return array
     */
    public function getNodeData()
    {
        $result = array ();
        foreach (self::$internalOptionList[$this->internalCacheKey] as $optKey => $optValue) {
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
     * @return array returns Text/regex validator
     */
    public function getValidators()
    {

        if ($this->internalValidationMessage == null) {
            $msg = "please specify a valid interface";
        } else {
            $msg = $this->internalValidationMessage;
        }

        if (($this->internalIsRequired == true || $this->internalValue != null)) {
            return array(new InclusionIn(array('message' => $msg,
                'domain'=>array_keys(self::$internalOptionList[$this->internalCacheKey]))));
        } else {
            // empty field and not required, skip this validation.
            return array();
        }
    }
}

