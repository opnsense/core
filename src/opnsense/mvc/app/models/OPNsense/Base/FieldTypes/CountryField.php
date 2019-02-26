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
use OPNsense\Base\Validators\CsvListValidator;

/**
 * Class CountryField field type to select iso3166 countries
 * @package OPNsense\Base\FieldTypes
 */
class CountryField extends BaseField
{
    /**
     * @var bool marks if this is a data node or a container
     */
    protected $internalIsContainer = false;

    /**
     * @var string default validation message string
     */
    protected $internalValidationMessage = "please specify a valid country";

    /**
     * @var array collected options
     */
    private static $internalOptionList = array();

    /**
     * @var bool field may contain multiple countries at once
     */
    private $internalMultiSelect = false;

    /**
     * @var bool field for adding inverted items to the selection
     */
    private $internalAddInverse = false;

    /**
     * generate validation data (list of countries)
     */
    protected function actionPostLoadingEvent()
    {
        if (count(self::$internalOptionList) == 0) {
            $filename = '/usr/local/opnsense/contrib/tzdata/iso3166.tab';
            $data = file_get_contents($filename);
            foreach (explode("\n", $data) as $line) {
                $line = trim($line);
                if (strlen($line) > 3 && substr($line, 0, 1) != '#') {
                    $code = substr($line, 0, 2);
                    $name = trim(substr($line, 2, 9999));
                    self::$internalOptionList[$code] = $name;
                    if ($this->internalAddInverse) {
                        self::$internalOptionList["!".$code] = $name . " (not)";
                    }
                }
            }
            natcasesort(self::$internalOptionList);
        }
    }

    /**
     * select if multiple countries may be selected at once
     * @param string $value boolean value Y/N
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
     * Add inverted countries to selection (prefix !, meaning not)
     * @param string $value boolean value Y/N
     */
    public function setAddInverted($value)
    {
        if (trim(strtoupper($value)) == "Y") {
            $this->internalAddInverse = true;
        } else {
            $this->internalAddInverse = false;
        }
    }

    /**
     * get valid options, descriptions and selected value
     * @return array
     */
    public function getNodeData()
    {
        $result = array();
        // if country is not required and single, add empty option
        if (!$this->internalIsRequired && !$this->internalMultiSelect) {
            $result[""] = array("value" => gettext("none"), "selected" => 0);
        }

        // explode countries
        $countries = explode(',', $this->internalValue);
        foreach (self::$internalOptionList as $optKey => $optValue) {
            if (in_array($optKey, $countries)) {
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
     * @return array returns validators
     */
    public function getValidators()
    {
        $validators = parent::getValidators();
        if ($this->internalValue != null) {
            if ($this->internalMultiSelect) {
                // field may contain more than one country
                $validators[] = new CsvListValidator(array('message' => $this->internalValidationMessage,
                    'domain'=>array_keys(self::$internalOptionList)));
            } else {
                // single country selection
                $validators[] = new InclusionIn(array('message' => $this->internalValidationMessage,
                    'domain'=>array_keys(self::$internalOptionList)));
            }
        }
        return $validators;
    }
}
