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

use OPNsense\Base\Validators\MinMaxValidator;
use OPNsense\Base\Validators\IntegerValidator;

/**
 * Class AutoNumberField auto numbering integer type field, using its direct neighbors to determine the already used
 * numbers.
 * @package OPNsense\Base\FieldTypes
 */
class AutoNumberField extends BaseField
{
    /**
     * @var bool marks if this is a data node or a container
     */
    protected $internalIsContainer = false;

    /**
     * maximum value for this field
     * @var integer
     */
    private $maximum_value;

    /**
     * minimum value for this field
     * @var integer
     */
    private $minimum_value;

    /**
     * {@inheritdoc}
     */
    protected function defaultValidationMessage()
    {
        return gettext('Invalid integer value.');
    }

    /**
     * constructor, set absolute min and max values
     * @param null|string $ref direct reference to this object
     * @param null|string $tagname xml tagname to use
     */
    public function __construct($ref = null, $tagname = null)
    {
        parent:: __construct($ref, $tagname);

        $this->minimum_value = 1;
        $this->maximum_value = PHP_INT_MAX - 1;
    }

    /**
     * applyDefault is the trigger for requesting a new sequence number, when a child field is created by
     * ArrayField it always receives all of its defaults after clone.
     * (see ArrayField for how this works).
     *
     * In this case, the default is a new sequence based on the same field in this fields direct neighbors.
     * When maximum_value is reached, all succeeding items will get maximum_value+1. (which fails on validation)
     */
    public function applyDefault()
    {
        // collect all used sequence numbers
        $allIds = array();
        if (isset($this->internalParentNode->internalParentNode)) {
            foreach ($this->internalParentNode->internalParentNode->iterateItems() as $node) {
                $allIds[] = (string)$node->{$this->internalXMLTagName};
            }
        }

        // find first unused sequence
        for ($newId = $this->minimum_value; $newId <= $this->maximum_value + 1; ++$newId) {
            if (!in_array($newId, $allIds)) {
                break;
            }
        }
        $this->internalValue = (string)$newId;
    }

    /**
     * setter for maximum value
     * @param integer $value
     */
    public function setMaximumValue($value)
    {
        if (is_numeric($value)) {
            $this->maximum_value = $value;
        }
    }

    /**
     * setter for minimum value
     * @param integer $value
     */
    public function setMinimumValue($value)
    {
        if (is_numeric($value)) {
            $this->minimum_value = $value;
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
            $validators[] = new IntegerValidator(['message' => $this->getValidationMessage()]);
            $validators[] = new MinMaxValidator([
                'message' => $this->getValidationMessage(),
                'min' => $this->minimum_value,
                'max' => $this->maximum_value,
            ]);
        }
        return $validators;
    }
}
