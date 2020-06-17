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

/**
 * Class ArrayField
 * @package OPNsense\Base\FieldTypes
 */
class ArrayField extends BaseField
{
    /**
     * @var null|BaseField node to use for copying
     */
    protected $internalTemplateNode = null;

    /**
     * Copy first node pointer as template node to make sure we always have a template to create new nodes from.
     * If the first node is virtual (no source data), remove that from the list.
     */
    protected function actionPostLoadingEvent()
    {
        // always make sure there's a node to copy our structure from
        if ($this->internalTemplateNode == null) {
            $firstKey = array_keys($this->internalChildnodes)[0];
            $this->internalTemplateNode = $this->internalChildnodes[$firstKey];
            /**
             * if first node is empty, remove reference node.
             */
            if ($this->internalChildnodes[$firstKey]->getInternalIsVirtual()) {
                unset($this->internalChildnodes[$firstKey]);
            }
        }
    }

    /**
     * Construct new content container and attach to this items model
     * @param $ref
     * @param $tagname
     * @return ContainerField
     */
    public function newContainerField($ref, $tagname)
    {
        $container_node = new ContainerField($ref, $tagname);
        $parentmodel = $this->getParentModel();
        $container_node->setParentModel($parentmodel);
        return $container_node;
    }

    /**
     * retrieve read only template with defaults (copy of internal structure)
     * @return null|BaseField template node
     */
    public function getTemplateNode()
    {
        $result = clone $this->internalTemplateNode;
        return $result;
    }

    /**
     * add new node containing the types from the first node (copy)
     * @return ContainerField created node
     * @throws \Exception
     */
    public function add()
    {
        $new_record = array();
        foreach ($this->internalTemplateNode->iterateItems() as $key => $node) {
            if ($node->isContainer()) {
                // validate child nodes, nesting not supported in this version.
                throw new \Exception("Unsupported copy, Array doesn't support nesting.");
            }
            $new_record[$key] = clone $node;
        }

        $nodeUUID = $this->generateUUID();
        $container_node = $this->newContainerField($this->__reference . "." . $nodeUUID, $this->internalXMLTagName);
        foreach ($new_record as $key => $node) {
            // initialize field with new internal id and defined default value
            $node->setInternalReference($container_node->__reference . "." . $key);
            $node->applyDefault();
            $node->setChanged();
            $container_node->addChildNode($key, $node);
        }

        // make sure we have a UUID on repeating child items
        $container_node->setAttributeValue("uuid", $nodeUUID);

        // add node to this object
        $this->addChildNode($nodeUUID, $container_node);

        return $container_node;
    }

    /**
     * remove item by id (number)
     * @param string $index index number
     * @return bool item found/deleted
     */
    public function del($index)
    {
        if (isset($this->internalChildnodes[(string)$index])) {
            unset($this->internalChildnodes[$index]);
            return true;
        } else {
            return false;
        }
    }

    /**
     * retrieve field validators for this field type
     * @param string|array $fieldNames sort by fieldname
     * @param bool $descending sort descending
     * @param int $sort_flags sorting behavior
     * @return array
     */
    public function sortedBy($fieldNames, $descending = false, $sort_flags = SORT_NATURAL)
    {
        // reserve at least X number of characters for every field to improve sorting of multiple fields
        $MAX_KEY_LENGTH = 30;

        if (empty($fieldNames)) {
            // unsorted, just return, without any guarantee about the ordering.
            return iterator_to_array($this->iterateItems());
        } elseif (!is_array($fieldNames)) {
            // fieldnames may be a list or a single item, always convert to a list
            $fieldNames = array($fieldNames);
        }

        // collect sortable data as key/value store
        $sortedData = array();
        foreach ($this->iterateItems() as $nodeKey => $node) {
            // populate sort key
            $sortKey = '';
            foreach ($fieldNames as $fieldName) {
                if (isset($node->internalChildnodes[$fieldName])) {
                    if (is_numeric((string)$node->$fieldName)) {
                        // align numeric values right for sorting, not perfect but works for integer type values
                        $sortKey .=  sprintf("%" . $MAX_KEY_LENGTH . "s,", $node->$fieldName);
                    } else {
                        // normal text sorting, align left
                        $sortKey .=  sprintf("%-" . $MAX_KEY_LENGTH . "s,", $node->$fieldName);
                    }
                }
            }
            $sortKey .= $nodeKey; // prevent overwrite of duplicates
            $sortedData[$sortKey] = $node;
        }

        // sort by key on ascending or descending order
        if (!$descending) {
            ksort($sortedData, $sort_flags);
        } else {
            krsort($sortedData, $sort_flags);
        }

        return array_values($sortedData);
    }
}
