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
    private $internalTemplateNode = null;

    /**
     * @var list statically defined children, key value store for static defined model entries
     */
    protected static $internalStaticChildren = [];

    /**
     * @return key value store of static model items, overwrite when needed
     */
    protected static function getStaticChildren()
    {
        return [];
    }

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
        // init static entries when returned by getStaticChildren()
        foreach (static::getStaticChildren() as $skey => $payload) {
            $container_node = $this->newContainerField($this->__reference . "." . $skey, $this->internalXMLTagName);
            $container_node->setAttributeValue("uuid", $skey);
            $container_node->setInternalIsVirtual();
            foreach ($this->getTemplateNode()->iterateItems() as $key => $value) {
                $node = clone $value;
                $node->setInternalReference($container_node->__reference . "." . $key);
                if (isset($payload[$key])) {
                    $node->setValue($payload[$key]);
                }
                $node->markUnchanged();
                $container_node->addChildNode($key, $node);
            }
            static::$internalStaticChildren[$skey] = $container_node;
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
        $nodeUUID = $this->generateUUID();
        $container_node = $this->newContainerField($this->__reference . "." . $nodeUUID, $this->internalXMLTagName);

        $template_ref = $this->internalTemplateNode->__reference;
        foreach ($this->internalTemplateNode->iterateItems() as $key => $node) {
            $new_node = clone $node;
            $new_node->setInternalReference($container_node->__reference . "." . $key);
            $new_node->applyDefault();
            $new_node->setChanged();
            $container_node->addChildNode($key, $new_node);

            if ($node->isContainer()) {
                foreach ($node->iterateRecursiveItems() as $subnode) {
                    if (is_a($subnode, "OPNsense\\Base\\FieldTypes\\ArrayField")) {
                        // validate child nodes, nesting not supported in this version.
                        throw new \Exception("Unsupported copy, Array doesn't support nesting.");
                    }
                }

                /**
                 * XXX: incomplete, only supports one nesting level of container fields. In the long run we probably
                 *      should refactor the add() function to push identifiers differently.
                 */
                foreach ($node->iterateItems() as $subkey => $subnode) {
                    $new_subnode = clone $subnode;
                    $new_subnode->setInternalReference($new_node->__reference . "." . $subkey);
                    $new_subnode->applyDefault();
                    $new_subnode->setChanged();
                    $new_node->addChildNode($subkey, $new_subnode);
                }
            }
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
    public function sortedBy($fieldNames, $descending = false, $sort_flags = SORT_NATURAL | SORT_FLAG_CASE)
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
                    $payload = $node->$fieldName->getDescription();
                    if (is_numeric($payload)) {
                        // align numeric values right for sorting, not perfect but works for integer type values
                        $sortKey .= sprintf("%" . $MAX_KEY_LENGTH . "s,", $payload);
                    } else {
                        // normal text sorting, align left
                        $sortKey .= sprintf("%-" . $MAX_KEY_LENGTH . "s,", $payload);
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

    /**
     * {@inheritdoc}
     */
    public function hasChild($name)
    {
        if (isset(static::$internalStaticChildren[$name])) {
            return true;
        } else {
            return parent::hasChild($name);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getChild($name)
    {
        if (isset(static::$internalStaticChildren[$name])) {
            return static::$internalStaticChildren[$name];
        } else {
            return parent::getChild($name);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function iterateItems()
    {
        foreach (parent::iterateItems() as $key => $value) {
            yield $key => $value;
        }
        foreach (static::$internalStaticChildren as $key => $node) {
            yield $key => $node;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function isFieldChanged()
    {
        foreach (parent::iterateItems() as $child) {
            if ($child->isFieldChanged()) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param bool $include_static include non importable static items
     * @param array $exclude fieldnames to exclude
     * @return array simple array set
     */
    public function asRecordSet($include_static = false, $exclude = [])
    {
        $records = [];
        $iterator =  $include_static ? $this->iterateItems() : parent::iterateItems();
        foreach ($iterator as $akey => $anode) {
            $record = [];
            foreach ($anode->iterateItems() as $tag => $node) {
                if (!in_array($tag, $exclude)) {
                    $record[$tag] = (string)$node;
                }
            }
            $records[] = $record;
        }
        return $records;
    }

    /**
     * @param array $records payload to merge
     * @param array $keyfields search criteria
     * @param function $data_callback inline data modification
     * @return array exceptions
     */
    public function importRecordSet($records, $keyfields = [], $data_callback = null)
    {
        $results = ['validations' => [], 'inserted' => 0, 'updated' => 0, 'uuids' => []];
        $records = is_array($records) ? $records : [];
        $current = [];
        if (!empty($keyfields)) {
            foreach (parent::iterateItems() as $node) {
                $keydata = [];
                foreach ($keyfields as $keyfield) {
                    $keydata[] = (string)$node->$keyfield;
                }
                $key = implode("\n", $keydata);
                if (isset($current[$key])) {
                    $current[$key] = null;
                } else {
                    $current[$key] = $node;
                }
            }
        }

        foreach ($records as $idx => $record) {
            if (is_callable($data_callback)) {
                $data_callback($record);
            }
            $keydata = [];
            foreach ($keyfields as $keyfield) {
                $keydata[] = (string)$record[$keyfield] ?? '';
            }
            $key = implode("\n", $keydata);
            $node = null;
            if (isset($current[$key])) {
                if ($current[$key] === null) {
                    $results['validations'][] = ['sequence' => $idx, 'message' => gettext('Duplicate key entry found')];
                    continue;
                } else {
                    $node = $current[$key];
                }
            }
            if ($node === null) {
                $results['inserted'] += 1;
                $node = $this->add();
            } else {
                $results['updated'] += 1;
            }
            $results['uuids'][$node->getAttributes()['uuid']] = $idx;
            foreach ($record as $fieldname => $content) {
                $node->$fieldname = (string)$content;
            }
        }
        return $results;
    }
}
