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
 * Class BaseField
 * @package OPNsense\Base\FieldTypes
 * @property-read string $__reference this tag absolute reference (node.subnode.subnode)
 * @property-read string $__type this tag's class Name ( example TextField )
 * @property-read string $__Ixx get tag by index/name even if the name is a number
 * @property-read array $__items this node's children
 */
abstract class BaseField
{
    /**
     * @var array child nodes
     */
    protected $internalChildnodes = array();

    /**
     * @var bool marks if this is a data node or a container
     */
    protected $internalIsContainer = true;

    /**
     * @var null|string node value
     */
    protected $internalValue = "";

    /**
     * @var null|string node default value
     */
    protected $internalDefaultValue = "";

    /**
     * @var null|bool|string initial value of this field (first set)
     */
    protected $internalInitialValue = false;

    /**
     * @var string direct reference to this field in the model object
     */
    protected $internalReference = null;

    /**
     * @var string tag name for this object, either the last part of the reference.
     */
    protected $internalXMLTagName = "";

    /**
     * @var bool is this a required attribute?
     */
    protected $internalIsRequired = false ;

    /**
     * @var string validation message string
     */
    protected $internalValidationMessage = null;

    /**
     * @var bool node (and subnodes) is virtual
     */
    protected $internalIsVirtual = false ;

    /**
     * @var array key value store for attributes (will be saved as xml attributes)
     */
    protected $internalAttributes = array();

    /**
     * @return string uuid v4 number
     */
    public function generateUUID()
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff)
        );
    }

    /**
     * Template action for post loading actions, triggered by eventPostLoadingEvent.
     * Overwrite this method for custom loading hooks.
     */
    protected function actionPostLoadingEvent()
    {
        return;
    }


    /**
     * trigger post loading event. (executed by BaseModel)
     */
    public function eventPostLoading()
    {
        foreach ($this->internalChildnodes as $nodeName => $node) {
            $node->eventPostLoading();
        }
        $this->actionPostLoadingEvent();
    }

    /**
     * @return bool returns if this a container type object (no data)
     */
    public function isContainer()
    {
        return $this->internalIsContainer;
    }

    /**
     * default constructor
     * @param null|string $ref direct reference to this object
     * @param null|string $tagname xml tagname to use
     */
    public function __construct($ref = null, $tagname = null)
    {
        $this->internalReference = $ref;
        $this->internalXMLTagName = $tagname;
    }

    /**
     * reset on clone
     */
    public function __clone()
    {
        $this->internalIsVirtual = false ;
        $this->internalValue = "";
        $this->internalReference = null;
    }

    /**
     * change internal reference, if set it can't be changed for safety purposes.
     * @param $ref internal reference
     * @throws \Exception change exception
     */
    public function setInternalReference($ref)
    {
        if ($this->internalReference == null) {
            $this->internalReference = $ref;
        } else {
            throw new \Exception("cannot change internal reference");
        }
    }

    /**
     * Add a childnode to this node.
     * @param string $name property name
     * @param BaseField $node content (must be of type BaseField)
     */
    public function addChildNode($name, $node)
    {
        $this->internalChildnodes[$name] = $node;
    }

    /**
     * Reflect default getter to internal child nodes.
     * Implements the special attribute __items to return all items and __reference to identify the field in this model.
     * @param string $name property name
     * @return mixed
     */
    public function __get($name)
    {
        if (array_key_exists($name, $this->internalChildnodes)) {
            return $this->internalChildnodes[$name];
        } elseif ($name == '__items') {
            // return all (no virtual/hidden) items
            $result = array();
            foreach ($this->internalChildnodes as $key => $value) {
                if ($value->internalIsVirtual == false) {
                    $result[$key] = $value ;
                }
            }
            return $result;
        } elseif ($name == '__reference') {
            return $this->internalReference;
        } elseif ($name == '__type') {
            return $this->getObjectType();
        } elseif (strrpos($name, "__I") === 0) {
            // direct index item assignment
            return $this->__get(substr($name, 3));
        } else {
            // not found
            return null;
        }

    }


    /**
     * reflect default setter to internal child nodes
     * @param string $name property name
     * @param string $value property value
     */
    public function __set($name, $value)
    {
        if (array_key_exists($name, $this->internalChildnodes)) {
            $this->internalChildnodes[$name]->setValue($value);
        } else {
            throw new \InvalidArgumentException($name." not an attribute of ". $this->internalReference);
        }
    }

    /**
     * @return null|string string interpretation of this field
     */
    public function __toString()
    {
        return $this->internalValue;
    }

    /**
     * default setter
     * @param string $value set field value
     */
    public function setValue($value)
    {
        // if first set, store initial value
        if ($this->internalInitialValue === false && !empty($value)) {
            $this->internalInitialValue = $value;
        }
        $this->internalValue = $value;
    }

    /**
     * force field to act as changed, used after cloning.
     */
    public function setChanged()
    {
        $this->internalInitialValue = true;
    }

    /**
     * check if field content has changed
     * @return bool change indicator
     */
    public function isFieldChanged()
    {
        if ($this->internalInitialValue !==  $this->internalValue) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Set attribute on Field object
     * @param $key attribute key
     * @param $value attribute value
     */
    public function setAttributeValue($key, $value)
    {
        $this->internalAttributes[$key] = $value;
    }

    /**
     * @return array Field attributes
     */
    public function getAttributes()
    {
        return $this->internalAttributes;
    }

    /**
     * @return array child items
     */
    public function getChildren()
    {
        return $this->internalChildnodes;
    }

    /**
     * @return array returns validators for this field type (empty if none)
     */
    public function getValidators()
    {
        return array();
    }

    /**
     * Mark this node as virtual, only used as template for structure behind it.
     * Used for array structures.
     */
    public function setInternalIsVirtual()
    {
        $this->internalIsVirtual = true;
    }

    /**
     * @return bool is virtual node
     */
    public function getInternalIsVirtual()
    {
        return $this->internalIsVirtual;
    }

    /**
     * getter for internal tag name
     * @return null|string xml tagname to use
     */
    public function getInternalXMLTagName()
    {
        return $this->internalXMLTagName;
    }

    /**
     * Recursive method to flatten tree structure for easy validation, returns only leaf nodes.
     * @return array named array with field type nodes, using the internal reference.
     */
    public function getFlatNodes()
    {
        $result = array ();
        if (count($this->internalChildnodes) == 0) {
            return array($this);
        }

        foreach ($this->__items as $node) {
            foreach ($node->getFlatNodes() as $childNode) {
                $result[$childNode->internalReference] = $childNode ;
            }
        }

        return $result;
    }


    /**
     * get nodes as array structure
     * @return array
     */
    public function getNodes()
    {
        $result = array ();
        foreach ($this->__items as $key => $node) {
            if ($node->isContainer()) {
                $result[$key] = $node->getNodes();
            } else {
                $result[$key] = $node->getNodeData();
            }
        }

        return $result;
    }

    /**
     * companion for getNodes, displays node content. may be overwritten for alternative presentation.
     * @return null|string
     */
    public function getNodeData()
    {
        return $this->__toString();
    }


    /**
     * update model with data returning missing repeating tag types.
     * @param $data named array structure containing new model content
     * @return array missing array keys in data
     */
    public function setNodes($data)
    {
        $delItems = array();

        // update structure with new content
        foreach ($this->__items as $key => $node) {
            if ($data != null && array_key_exists($key, $data)) {
                if ($node->isContainer()) {
                    $delItems += $node->setNodes($data[$key]);
                } else {
                    $node->setValue($data[$key]);
                }
            } elseif (get_class($this) == "OPNsense\\Base\\FieldTypes\\ArrayField") {
                // mark item as missing in input, return when finished
                $delItems[] = array("node" => $this, "key" => $key );
            } else {
                $delItems += $node->setNodes(array());
            }
        }

        // add new items to array type objects
        if (get_class($this) == "OPNsense\\Base\\FieldTypes\\ArrayField") {
            foreach ($data as $dataKey => $dataValue) {
                if (!array_key_exists($dataKey, $this->__items)) {
                    $node = $this->add();
                    $delItems += $node->setNodes($dataValue);
                }
            }
        }

        return $delItems;
    }


    /**
     * Add this node and it's children to the supplied simplexml node pointer.
     * @param \SimpleXMLElement $node target node
     */
    public function addToXMLNode($node)
    {
        if ($this->internalReference == "" || get_class($this) == "OPNsense\\Base\\FieldTypes\\ArrayField") {
            // ignore tags without internal reference (root) and ArrayTypes
            $subnode = $node ;
        } else {
            if ($this->internalValue != "") {
                $subnode = $node->addChild($this->getInternalXMLTagName(), $this->internalValue);
            } else {
                $subnode = $node->addChild($this->getInternalXMLTagName());
            }

            // copy attributes into xml node
            foreach ($this->getAttributes() as $AttrKey => $AttrValue) {
                $subnode->addAttribute($AttrKey, $AttrValue);
            }


        }

        foreach ($this->__items as $key => $FieldNode) {
            $FieldNode->addToXMLNode($subnode);
        }
    }

    /**
     * set Default field value ( for usage in model xml )
     * @param string $value default value
     */
    public function setDefault($value)
    {
        $this->internalValue = $value;
        $this->internalDefaultValue = $value;
    }

    /**
     * (re)Apply default value without changing the initial value of the field
     */
    public function applyDefault()
    {
        $this->internalValue = $this->internalDefaultValue;
    }

    /**
     * set Validation message ( for usage in model xml )
     * @param string $msg validation message (on failure)
     */
    public function setValidationMessage($msg)
    {
        $this->internalValidationMessage = $msg;
    }

    /**
     * Implements required property, the base class only implements the setter.
     * The implemented fieldtype should include the correct validation.
     * @param string $value set if this node/field is required (Y/N)
     */
    public function setRequired($value)
    {
        if (strtoupper(trim($value)) == "Y") {
            $this->internalIsRequired = true;
        } else {
            $this->internalIsRequired = false;
        }
    }

    /**
     * return object type as string
     * @return string
     */
    public function getObjectType()
    {
        $parts = explode("\\", get_class($this));
        return $parts[count($parts)-1];
    }
}
