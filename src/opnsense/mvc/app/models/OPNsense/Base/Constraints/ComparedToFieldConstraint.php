<?php

/*
 * Copyright (C) 2019 Fabian Franz
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
 * limit the value range depending on the setting of another field
 * containing a specific value
 * Class ComparedToFieldConstraint
 * @package OPNsense\Base\Constraints
 */
class ComparedToFieldConstraint extends BaseConstraint
{
    /**
     * Executes validation, where the value must be set if another field is
     * set to a specific value. Configuration example:
     *
     *   &lt;ValidationMessage&gt;This field must be set.&lt;/ValidationMessage&gt;
     *   &lt;type&gt;ComparedToFieldConstraint&lt;/type&gt;
     *   &lt;field&gt;name of another field which has the same parent node&lt;/field&gt;
     *   &lt;operator&gt;operator to check; one of gt|gte|lt|lte|eq|neq&lt;/operator&gt;
     *
     * @param \Phalcon\Validation $validator
     * @param string $attribute
     * @return boolean
     */
    public function validate(\Phalcon\Validation $validator, $attribute): bool
    {
        $node = $this->getOption('node');
        $field_name = $this->getOption('field');
        $operator = $this->getOption('operator');
        if ($node && !($this->isEmpty($node) || empty($operator) || empty($field_name))) {
            $parent_node = $node->getParentNode();
            $other_node_content = $parent_node->$field_name;

            // if the other field is not set, or invalid type -> ignore this constraint
            if (
                $this->isEmpty($other_node_content) ||
                    !is_numeric((string)$node) && !is_numeric((string)$other_node_content)
            ) {
                return true;
            }

            if (
                !$this->is_contraint_fulfilled(
                    $operator,
                    floatval((string)$node),
                    floatval((string)$other_node_content)
                )
            ) {
                $this->appendMessage($validator, $attribute);
            }
        }
        return true;
    }

    /**
     * @param $operator string one of gt|gte|lt|lte|eq|neq
     * @param $our_value float the value of this field
     * @param $foreign_value float the value of the referenced field
     * @return bool if the contraint is fulfilled
     */
    private function is_contraint_fulfilled($operator, $our_value, $foreign_value)
    {
        switch ($operator) {
            case 'gt':
                return $our_value > $foreign_value;
            case 'gte':
                return $our_value >= $foreign_value;
            case 'lt':
                return $our_value < $foreign_value;
            case 'lte':
                return $our_value <= $foreign_value;
            case 'eq':
                return $our_value == $foreign_value;
            case 'neq':
                return $our_value != $foreign_value;
            default:
                return false;
        }
    }
}
