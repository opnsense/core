<?php

/*
 * Copyright (C) 2016 Deciso B.V.
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

use OPNsense\Phalcon\Filter\Validation\Validator\InclusionIn;

/**
 * Class UniqueIdField
 * @package OPNsense\Base\FieldTypes
 */
class UniqueIdField extends BaseField
{
    /**
     * @var bool marks if this is a data node or a container
     */
    protected $internalIsContainer = false;

    /**
     * @var string default validation message string
     */
    protected $internalValidationMessage = "Unique ID is immutable";

    /**
     * @var null|string initial field value
     */
    private $initialValue = null;

    /**
     * retrieve field validators for this field type
     * @return array
     */
    public function getValidators()
    {
        if (empty($this->internalValue) && empty($this->initialValue)) {
            // trigger initial value on change, before returning validators
            // (new nodes will always be marked as "changed", see isFieldChanged())
            // Maybe we should add an extra event handler if this kind of scenarios happen more often, similar to
            // actionPostLoadingEvent. (which is not triggered on setting data for a complete new structure node)
            $this->internalValue = uniqid('', true);
            $this->initialValue = $this->internalValue;
        }
        $validators = parent::getValidators();
        // unique id may not change..
        $validators[] = new InclusionIn(array('message' => $this->internalValidationMessage,
            'domain' => array($this->initialValue)));
        return $validators;
    }
}
