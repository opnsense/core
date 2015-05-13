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

/**
 * Class ArrayField
 * @package OPNsense\Base\FieldTypes
 */
class ArrayField extends BaseField
{
    /**
     * @var int item index
     */
    private $internalArrayCounter = 0 ;

    /**
     * @var null|BaseField node to use for copying
     */
    private $internalTemplateNode = null;

    /**
     * add Childnode (list), ignore the name of this item
     * @param string $name property name
     * @param BaseField $node content (must be of type BaseField)
     */
    public function addChildNode($name, $node)
    {
            $this->internalChildnodes[(string)$this->internalArrayCounter] = $node;
            $this->internalArrayCounter++;
    }

    /**
     * Copy first node pointer as template node to make sure we always have a template to create new nodes from.
     * If the first node is virtual (no source data), remove that from the list.
     */
    protected function actionPostLoadingEvent()
    {
        // always make sure there's a node to copy our structure from
        if ($this->internalTemplateNode ==null) {
            $this->internalTemplateNode = $this->internalChildnodes["0"];
            /**
             * if first node is empty, remove reference node.
             */
            if ($this->internalChildnodes["0"]->getInternalIsVirtual()) {
                unset($this->internalChildnodes["0"]);
                $this->internalArrayCounter--;
            }
        }

        // check if all children have a uuid, generate one if missing
        foreach ($this->internalChildnodes as $nodeKey => $node) {
            if (!array_key_exists('uuid', $node->getAttributes())) {
                $node->setAttributeValue("uuid", $this->generateUUID());
            }
        }
    }

    /**
     * add new node containing the types from the first node (copy)
     * @return ContainerField created node
     * @throws \Exception
     */
    public function add()
    {
        $new_record = array();
        foreach ($this->internalTemplateNode->__items as $key => $node) {
            if ($node->isContainer()) {
                // validate child nodes, nesting not supported in this version.
                throw new \Exception("Unsupported copy, Array doesn't support nesting.");
            }
            $new_record[$key] = clone $node ;
        }

        $container_node = new ContainerField(
            $this->__reference . "." . $this->internalArrayCounter,
            $this->internalXMLTagName
        );

        foreach ($new_record as $key => $node) {
            $node->setInternalReference($container_node->__reference.".".$key);
            $container_node->addChildNode($key, $node);
            // make sure we have a UUID on repeating child items
            $container_node->setAttributeValue("uuid", $this->generateUUID());
        }

        // add node to this object
        $this->addChildNode(null, $container_node);

        return $container_node;
    }

    /**
     * remove item by id (number)
     * @param $index index number
     */
    public function del($index)
    {
        if (array_key_exists((string)$index, $this->internalChildnodes)) {
            unset($this->internalChildnodes[$index]);
        }
    }


    /**
     * search child item by UUID
     * @param $uuid item uuid
     * @return BaseField|null
     */
    public function findByUUID($uuid)
    {
        foreach ($this->internalChildnodes as $nodeKey => $node) {
            $nodeAttr = $node->getAttributes();
            if (array_key_exists('uuid', $nodeAttr) && $nodeAttr['uuid'] == $uuid) {
                return $node;
            }
        }

        return null;
    }
}
