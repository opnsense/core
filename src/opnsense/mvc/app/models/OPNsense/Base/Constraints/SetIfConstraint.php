<?php

/*
 * Copyright (C) 2018 Fabian Franz
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

namespace OPNsense\Base\Constraints;

/**
 * validate if a field is set depening on the setting of another field
 * containing a specific value
 * Class SetIfConstraint
 * @package OPNsense\Base\Constraints
 */
class SetIfConstraint extends BaseConstraint
{
    /**
     * Executes validation, where the value must be set if another field is
     * set to a specific value. Configuration example:
     *
     *   &lt;ValidationMessage&gt;This field must be set.&lt;/ValidationMessage&gt;
     *   &lt;type&gt;SetIfConstraint&lt;/type&gt;
     *   &lt;field&gt;name of another field which has the same parent node&lt;/field&gt;
     *   &lt;check&gt;the value to check for as a string (for example the value of a OptionField)&lt;/check&gt;
     *
     * @param \Phalcon\Validation $validator
     * @param string $attribute
     * @return boolean
     */
    public function validate(\Phalcon\Validation $validator, $attribute): bool
    {
        $node = $this->getOption('node');
        $field_name = $this->getOption('field');
        $check = $this->getOption('check');
        if ($node) {
            $parentNode = $node->getParentNode();
            if ($this->isEmpty($node) && (string)$parentNode->$field_name == $check) {
                $this->appendMessage($validator, $attribute);
            }
        }
        return true;
    }
}
