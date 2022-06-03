<?php

/*
 * Copyright (C) 2015 Deciso B.V.
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

use Phalcon\Validation\Validator\InclusionIn;
use OPNsense\Base\Validators\CsvListValidator;

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
     * @var bool field may contain multiple data nodes at once
     */
    private $internalMultiSelect = false;

    /**
     * @var bool field content should remain sort order
     */
    private $internalIsSorted = false;

    /**
     * @var string default validation message string
     */
    protected $internalValidationMessage = "option not in list";

    /**
     * @var array collected options
     */
    private static $internalOptionList = array();

    /**
     * @var array|null model settings to use for validation
     */
    private $mdlStructure = null;

     /**
      * @var boolean selected options from the same model
      */
    private $internalOptionsFromThisModel = false;

    /**
     * @var string cache relations
     */
    private $internalCacheKey = "";

    /**
     * load model option list
     * @param boolean $force force option load if we already seen this model before
     */
    private function loadModelOptions($force = false)
    {
        // only collect options once per source/filter combination, we use a static to save our unique option
        // combinations over the running application.
        if (!isset(self::$internalOptionList[$this->internalCacheKey]) || $force) {
            self::$internalOptionList[$this->internalCacheKey] = array();
            foreach ($this->mdlStructure as $modelData) {
                // only handle valid model sources
                if (!isset($modelData['source']) || !isset($modelData['items']) || !isset($modelData['display'])) {
                    continue;
                }

                // handle optional/missing classes, i.e. from plugins
                $className = str_replace('.', '\\', $modelData['source']);
                if (!class_exists($className)) {
                    continue;
                }
                if (
                    $this->getParentModel() !== null &&
                        strcasecmp(get_class($this->getParentModel()), $className) === 0
                ) {
                    // model options from the same model, use this model in stead of creating something new
                    $modelObj = $this->getParentModel();
                    $this->internalOptionsFromThisModel = true;
                } else {
                    $modelObj = new $className();
                }

                $groupKey = isset($modelData['group']) ? $modelData['group'] : null;
                $displayKey = $modelData['display'];
                $groups = array();

                $searchItems = $modelObj->getNodeByReference($modelData['items']);
                if (!empty($searchItems)) {
                    foreach ($modelObj->getNodeByReference($modelData['items'])->iterateItems() as $node) {
                        if (!isset($node->getAttributes()['uuid']) || $node->$displayKey == null) {
                            continue;
                        }

                        if (isset($modelData['filters'])) {
                            foreach ($modelData['filters'] as $filterKey => $filterValue) {
                                $fieldData = $node->$filterKey;
                                if (!preg_match($filterValue, $fieldData) && $fieldData != null) {
                                    continue 2;
                                }
                            }
                        }

                        if (!empty($groupKey)) {
                            if ($node->$groupKey == null) {
                                continue;
                            }
                            $group = (string)$node->$groupKey;
                            if (isset($groups[$group])) {
                                continue;
                            }
                            $groups[$group] = 1;
                        }

                        $uuid = $node->getAttributes()['uuid'];
                        self::$internalOptionList[$this->internalCacheKey][$uuid] =
                            (string)$node->$displayKey;
                    }
                }
                unset($modelObj);
            }

            if (!$this->internalIsSorted) {
                natcasesort(self::$internalOptionList[$this->internalCacheKey]);
            }
        }
    }

    /**
     * Set model as reference list, use uuid's as key
     * @param $mdlStructure nested array structure defining the usable datasources.
     */
    public function setModel($mdlStructure)
    {
        // only handle array type input
        if (!is_array($mdlStructure)) {
            return;
        } else {
            $this->mdlStructure = $mdlStructure;
            // set internal key for this node based on sources and filter criteria
            $this->internalCacheKey = md5(serialize($mdlStructure));
        }
    }

    /**
     * load model options when initialized
     */
    protected function actionPostLoadingEvent()
    {
        $this->loadModelOptions();
    }

    /**
     * select if multiple data nodes may be selected at once
     * @param $value boolean value Y/N
     */
    public function setMultiple($value)
    {
        $this->internalMultiSelect = trim(strtoupper($value)) == "Y";
    }


    /**
     * select if sort order should be maintained
     * @param $value boolean value Y/N
     */
    public function setSorted($value)
    {
        $this->internalIsSorted = trim(strtoupper($value)) == "Y";
    }

    /**
     * get valid options, descriptions and selected value
     * @return array
     */
    public function getNodeData()
    {
        $result = array ();
        if (
            isset(self::$internalOptionList[$this->internalCacheKey]) &&
            is_array(self::$internalOptionList[$this->internalCacheKey])
        ) {
            // if relation is not required, add empty option
            if (!$this->internalIsRequired && !$this->internalMultiSelect) {
                $result[""] = array("value" => "none", "selected" => 0);
            }

            $datanodes = explode(',', $this->internalValue);
            if ($this->internalIsSorted) {
                $optKeys = $datanodes;
                foreach (array_keys(self::$internalOptionList[$this->internalCacheKey]) as $key) {
                    if (!in_array($key, $optKeys)) {
                        $optKeys[] = $key;
                    }
                }
            } else {
                $optKeys = array_keys(self::$internalOptionList[$this->internalCacheKey]);
            }
            foreach ($optKeys as $optKey) {
                if (isset(self::$internalOptionList[$this->internalCacheKey][$optKey])) {
                    if (in_array($optKey, $datanodes)) {
                        $selected = 1;
                    } else {
                        $selected = 0;
                    }
                    $result[$optKey] = array(
                        "value" => self::$internalOptionList[$this->internalCacheKey][$optKey],
                        "selected" => $selected
                    );
                }
            }
        }

        return $result;
    }

    /**
     * retrieve field validators for this field type
     * @return array returns Text/regex validator
     */
    public function getValidators()
    {
        $validators = parent::getValidators();
        if ($this->internalValue != null) {
            // if our options come from the same model, make sure to reload the options before validating them
            $this->loadModelOptions($this->internalOptionsFromThisModel);
            if ($this->internalMultiSelect) {
                // field may contain more than one entries
                $validators[] = new CsvListValidator(array(
                    'message' => $this->internalValidationMessage,
                    'domain' => array_keys(self::$internalOptionList[$this->internalCacheKey])
                ));
            } else {
                // single value selection
                $validators[] = new InclusionIn(array('message' => $this->internalValidationMessage,
                    'domain' => array_keys(self::$internalOptionList[$this->internalCacheKey])));
            }
        }
        return $validators;
    }
}
