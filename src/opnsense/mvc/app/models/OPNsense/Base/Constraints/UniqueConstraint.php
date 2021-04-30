<?php

/**
 *    Copyright (C) 2016 Deciso B.V.
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

namespace OPNsense\Base\Constraints;

/**
 * Class UniqueConstraint, add a unique constraint to this field and optional additional fields.
 * @package OPNsense\Base\Constraints
 */
class UniqueConstraint extends BaseConstraint
{
    /**
     * Executes validation
     *
     * @param \Phalcon\Validation $validator
     * @param string $attribute
     * @return boolean
     */
    public function validate(\Phalcon\Validation $validator, $attribute): bool
    {
        $node = $this->getOption('node');
        $fieldSeparator = chr(10) . chr(0);
        if ($node) {
            $containerNode = $node;
            $nodeName = $node->getInternalXMLTagName();
            $parentNode = $node->getParentNode();
            $level = 0;
            // dive into parent
            while ($containerNode != null && !$containerNode->isArrayType()) {
                $containerNode = $containerNode->getParentNode();
                $level++;
            }
            if ($containerNode != null && $level == 2) {
                // collect (additional) key fields
                $keyFields = array($nodeName);
                $keyFields = array_unique(array_merge($keyFields, $this->getOptionValueList('addFields')));
                // calculate the key for this node
                $nodeKey = '';
                foreach ($keyFields as $field) {
                    $nodeKey .= $fieldSeparator . $parentNode->$field;
                }
                // when an ArrayField is found in range, traverse nodes and compare keys
                foreach ($containerNode->iterateItems() as $item) {
                    if ($item !== $parentNode) {
                        $itemKey = '';
                        foreach ($keyFields as $field) {
                            $itemKey .= $fieldSeparator . $item->$field;
                        }
                        if ($itemKey == $nodeKey) {
                            $this->appendMessage($validator, $attribute);
                            return false;
                        }
                    }
                }
            }
        }
        return true;
    }
}
