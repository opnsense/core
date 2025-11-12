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
    private static $itemmap = [];
    private static $validationSequence = 0;

    /**
     * Executes validation
     *
     * @param $validator
     * @param string $attribute
     * @return boolean
     */
    public function validate($validator, $attribute): bool
    {
        $node = $this->getOption('node');
        $checkCaseInsensitive = $this->getOption('caseInsensitive', 'N') == 'Y';
        $fieldSeparator = chr(10) . chr(0);
        if ($node) {
            if (!$node->isRequired() && $node->isEmpty()) {
                return true;
            }
            $mdl = $node->getParentModel();
            if ($mdl === null || $mdl->getValidationSequence() != static::$validationSequence) {
                // reset cache, new validation round.
                static::$validationSequence = $mdl !== null ? $mdl->getValidationSequence() : 0;
                static::$itemmap = [];
            }
            $keyFields = array_unique(
                array_merge(
                    [$node->getInternalXMLTagName()],
                    $this->getOptionValueList('addFields')
                )
            );
            asort($keyFields);
            $nodeKey = implode('|', $keyFields);
            $parentNode = $node->getParentNode();
            // calculate the key for this node
            if (!isset(static::$itemmap[$nodeKey])) {
                static::$itemmap[$nodeKey] = [];
                $level = 0;
                // dive into parent
                $containerNode = $node;
                while ($containerNode != null && !$containerNode->isArrayType()) {
                    $containerNode = $containerNode->getParentNode();
                    $level++;
                }
                if ($containerNode != null && $level == 2) {
                    // when an ArrayField is found in range, traverse nodes and compare keys
                    foreach ($containerNode->iterateItems() as $item) {
                        $itemValue = '';
                        foreach ($keyFields as $field) {
                            $itemValue .= $fieldSeparator . $item->$field;
                        }
                        $itemValue = $checkCaseInsensitive ? strtolower($itemValue) : $itemValue;
                        if (empty(static::$itemmap[$nodeKey][$itemValue])) {
                            static::$itemmap[$nodeKey][$itemValue] = 0;
                        }
                        static::$itemmap[$nodeKey][$itemValue]++;
                    }
                }
            }
            $nodeValue = '';
            foreach ($keyFields as $field) {
                $nodeValue .= $fieldSeparator . $parentNode->$field;
            }
            $nodeValue = $checkCaseInsensitive ? strtolower($nodeValue) : $nodeValue;
            if ((static::$itemmap[$nodeKey][$nodeValue] ?? 0) > 1) {
                $this->appendMessage($validator, $attribute);
                return false;
            }
        }
        return true;
    }
}
