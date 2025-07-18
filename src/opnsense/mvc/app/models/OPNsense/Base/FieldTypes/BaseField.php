<?php

/*
 * Copyright (C) 2015-2025 Deciso B.V.
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

use Exception;
use Generator;
use InvalidArgumentException;
use OPNsense\Base\Validators\PresenceOf;
use ReflectionClass;
use ReflectionException;
use SimpleXMLElement;

/**
 * Class BaseField
 * @package OPNsense\Base\FieldTypes
 * @property-read string $__reference this tag absolute reference (node.subnode.subnode)
 * @property-read string $__type this tag's class Name ( example TextField )
 * @property-read string $__Ixx get tag by index/name even if the name is a number
 */
abstract class BaseField
{
    /**
     * @var array child nodes
     */
    protected $internalChildnodes = [];

    /**
     * @var array constraints for this field, additional to fieldtype
     */
    protected $internalConstraints = [];

    /**
     * @var null pointer to parent
     */
    protected $internalParentNode = null;

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
    protected $internalIsRequired = false;

    /**
     * @var string validation message string
     */
    protected $internalValidationMessage = null;

    /**
     * @var bool node (and subnodes) is virtual
     */
    protected $internalIsVirtual = false;

    /**
     * @var bool node (and subnodes) is volatile (non persistent, but should validate when offered)
     */
    protected $internalIsVolatile = false;

    /**
     * @var array key value store for attributes (will be saved as xml attributes)
     */
    protected $internalAttributes = [];

    /**
     * @var string $internalToLower
     */
    private $internalChangeCase = null;

    /**
     * @var bool is field loaded (after post loading event)
     */
    private $internalFieldLoaded = false;

    /**
     * @var BaseModel|null keep record of the model which originally created this field
     */
    private $internalParentModel = null;


    /**
     * @param array $node input array to traverse
     * @param string $path reference to information to be fetched (e.g. my.data)
     * @return array
     */
    protected static function getArrayReference(array $node, string $path)
    {
        foreach (explode('.', $path) as $ref) {
            if (!isset($node[$ref]) || !is_array($node[$ref])) {
                return []; /* not found or not valid */
            }
            $node = $node[$ref];
        }
        return $node;
    }

    /**
     * @return bool
     */
    public function isArrayType()
    {
        return is_a($this, "OPNsense\\Base\\FieldTypes\\ArrayField") ||
            is_subclass_of($this, "OPNsense\\Base\\FieldTypes\\ArrayField");
    }

    /**
     * generate a new UUID v4 number
     * @return string uuid v4 number
     */
    public function generateUUID()
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            random_int(0, 0xffff),
            random_int(0, 0xffff),
            random_int(0, 0xffff),
            random_int(0, 0x0fff) | 0x4000,
            random_int(0, 0x3fff) | 0x8000,
            random_int(0, 0xffff),
            random_int(0, 0xffff),
            random_int(0, 0xffff)
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
     * @param BaseModel $object to which this field is attached
     */
    public function setParentModel(&$object)
    {
        if (empty($this->internalParentModel)) {
            // read only attribute, set from model
            $this->internalParentModel = $object;
        }
    }

    /**
     * Retrieve the model to which this field is attached
     * @return BaseModel parent model
     */
    public function getParentModel()
    {
        return $this->internalParentModel;
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
        $this->internalFieldLoaded = true;
    }

    /**
     * check if this is a container type without data
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
        $this->internalIsVirtual = false;
        $this->internalValue = "";
        $this->internalReference = null;
        /* clone children */
        foreach ($this->internalChildnodes as $nodeName => $node) {
            $this->internalChildnodes[$nodeName] = clone $node;
        }
    }

    /**
     * change internal reference, if set it can't be changed for safety purposes.
     * @param $ref internal reference
     * @throws Exception change exception
     */
    public function setInternalReference($ref)
    {
        if ($this->internalReference == null) {
            $this->internalReference = $ref;
        } else {
            throw new Exception("cannot change internal reference");
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
        $node->setParentNode($this);
    }

    /**
     * set pointer to parent node, used by addChildNode to backref this node
     * @param BaseField $node pointer to parent
     */
    private function setParentNode($node)
    {
        $this->internalParentNode = $node;
    }

    /**
     * return this nodes parent (or null if not found)
     * @return null|BaseField
     */
    public function getParentNode()
    {
        return $this->internalParentNode;
    }

    /**
     * Reflect default getter to internal child nodes.
     * Implements __reference to identify the field in this model.
     * @param string $name property name
     * @return mixed
     */
    public function __get($name)
    {
        if (isset($this->internalChildnodes[$name])) {
            return $this->internalChildnodes[$name];
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
     * Triggered by calling isset() or empty() on an internal child node.
     * Prevents the need for statically casting to a type on __get().
     * @param $name property name
     * @return bool
     */
    public function __isset($name)
    {
        return isset($this->internalChildnodes[$name]);
    }

    /**
     * iterate (non virtual) child nodes
     * @return Generator
     */
    public function iterateItems()
    {
        foreach ($this->internalChildnodes as $key => $value) {
            if ($value->internalIsVirtual == false) {
                yield $key => $value;
            }
        }
    }

    /**
     * iterate all nodes recursively.
     * @return Generator
     */
    public function iterateRecursiveItems()
    {
        if (count($this->getChildren()) == 0) {
            yield $this;
        } else {
            foreach ($this->iterateItems() as $node) {
                foreach ($node->iterateRecursiveItems() as $child) {
                    yield $child;
                }
            }
        }
    }

    /**
     * reflect default setter to internal child nodes
     * @param string $name property name
     * @param string $value property value
     */
    public function __set($name, $value)
    {
        if (isset($this->internalChildnodes[$name])) {
            $this->internalChildnodes[$name]->setValue($value);
        } else {
            throw new InvalidArgumentException($name . " not an attribute of " . $this->internalReference);
        }
    }

    /**
     * return string interpretation of this field
     * @return string string interpretation of this field
     */
    public function __toString()
    {
        return $this->getValue();
    }

    /**
     * return field current value in order to be able to override it
     * @return string field current value
     */
    public function getValue(): string
    {
        return (string)$this->internalValue;
    }

    /**
     * return field current value(s) as array (empty strings are omitted)
     * @return array field current values
     */
    public function getValues(): array
    {
        $value = $this->getValue();
        return strlen($value) ? [$value] : [];
    }

    /**
     * check if field value is numeric
     * @return bool
     */
    public function isNumeric(): bool
    {
        return is_numeric($this->getValue());
    }

    /**
     * check if field value is equal to given string
     * @return bool
     */
    public function isEqual(string $test): bool
    {
        return $this->getValue() === $test;
    }

    /**
     * Try to convert to current value as float
     * @return float
     */
    public function asFloat(): float
    {
        return floatval($this->getValue());
    }

    /**
     * default setter
     * @param SimpleXMLElement|string $value set field value
     */
    public function setValue($value)
    {
        // if first set and not altered by the user, store initial value
        if ($this->internalFieldLoaded === false && $this->internalInitialValue === false) {
            $this->internalInitialValue = (string)$value;
        }
        $this->internalValue = (string)$value;
        // apply filters, may be extended later.
        $filters = array('applyFilterChangeCase');
        foreach ($filters as $filter) {
            $this->$filter();
        }
    }

    /**
     * force field to act as changed, used after cloning.
     */
    public function setChanged()
    {
        $this->internalInitialValue = true;
    }

    /**
     * force field to act as unchanged (skip validations)
     */
    public function markUnchanged()
    {
        $this->internalInitialValue = $this->internalValue;
    }

    /**
     * check if field content has changed
     * @return bool change indicator
     */
    public function isFieldChanged()
    {
        return $this->internalInitialValue !== $this->internalValue;
    }

    /**
     * Set attribute on Field object
     * @param string $key attribute key
     * @param string $value attribute value
     */
    public function setAttributeValue($key, $value)
    {
        if ($value !== null) {
            $this->internalAttributes[$key] = $value;
        } elseif (isset($this->internalAttributes[$key])) {
            unset($this->internalAttributes[$key]);
        }
    }

    /**
     * retrieve field attributes
     * @return array Field attributes
     */
    public function getAttributes()
    {
        return $this->internalAttributes;
    }

    /**
     * get attribute by name
     * @param string $key attribute key
     * @return null|string value
     */
    public function getAttribute($key)
    {
        if (isset($this->internalAttributes[$key])) {
            return $this->internalAttributes[$key];
        } else {
            return null;
        }
    }

    /**
     * get this nodes children
     * @return array child items
     */
    public function getChildren()
    {
        return $this->internalChildnodes;
    }

    /**
     * check for existence of child attribute
     * @return bool if child exists
     */
    public function hasChild($name)
    {
        return isset($this->internalChildnodes[$name]);
    }

    /**
     * retrieve child object
     * @return null|object
     */
    public function getChild($name)
    {
        if ($this->hasChild($name)) {
            return $this->internalChildnodes[$name];
        }
        return null;
    }

    /**
     * check if current value is empty (either boolean field as false or an empty field)
     * @return bool
     */
    public function isEmpty(): bool
    {
        return empty($this->getValue());
    }

    /**
     * check if this field is required
     * @return bool
     */
    public function isRequired(): bool
    {
        return $this->internalIsRequired;
    }

    /**
     * check if this field is unused and required
     * @return bool
     */
    public function isEmptyAndRequired(): bool
    {
        return $this->internalIsRequired && $this->getValue() === '';
    }

    /**
     * retrieve constraint objects by defined constraints name (/key)
     * @param $name
     * @return null|object
     */
    public function getConstraintByName($name)
    {
        if (isset($this->internalConstraints[$name])) {
            $constraint = $this->internalConstraints[$name];
            if (!empty($constraint['type'])) {
                try {
                    $constr_class = new ReflectionClass('OPNsense\\Base\\Constraints\\' . $constraint['type']);
                    if ($constr_class->getParentClass()->name == 'OPNsense\Base\Constraints\BaseConstraint') {
                        $constraint['name'] = $name;
                        $constraint['node'] = $this;
                        return $constr_class->newInstance($constraint);
                    }
                } catch (ReflectionException $e) {
                    /* ignore configuration errors, if the constraint can't be found, skip. */
                }
            }
        }
        return null;
    }

    /**
     * fetch all additional validators
     * @return array
     */
    private function getConstraintValidators()
    {
        $result = [];
        foreach ($this->internalConstraints as $name => $constraint) {
            if (!empty($constraint['reference'])) {
                // handle references (should use the same level)
                $parts = explode('.', $constraint['reference']);
                $parentNode = $this->getParentNode();
                if (count($parts) == 2) {
                    $tagName = $parts[0];
                    if (isset($parentNode->$tagName) && !$parentNode->$tagName->getInternalIsVirtual()) {
                        $ref_constraint = $parentNode->$tagName->getConstraintByName($parts[1]);
                        if ($ref_constraint != null) {
                            $result[] = $ref_constraint;
                        }
                    }
                }
            } elseif (!empty($constraint['type'])) {
                $constraintObj = $this->getConstraintByName($name);
                if ($constraintObj != null) {
                    $result[] = $constraintObj;
                }
            }
        }
        return $result;
    }

    /**
     * return field validators for this field
     * @return array returns validators for this field type (empty if none)
     */
    public function getValidators()
    {
        $validators = $this->getConstraintValidators();
        if ($this->isEmptyAndRequired()) {
            $validators[] = new PresenceOf(['message' => gettext('A value is required.')]);
        }
        return $validators;
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
     * returns if this node is virtual, the framework uses this to determine if this node should only be used to
     * clone children. (using ArrayFields)
     * @return bool is virtual node
     */
    public function getInternalIsVirtual()
    {
        return $this->internalIsVirtual;
    }

    /**
     * Mark this node as volatile
     */
    public function setInternalIsVolatile()
    {
        $this->internalIsVolatile = true;
    }

    /**
     * returns if this node is volatile, the framework uses this to determine if this node should be stored.
     * @return bool is volatile node
     */
    public function getInternalIsVolatile()
    {
        return $this->internalIsVolatile;
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

        foreach ($this->iterateItems() as $node) {
            foreach ($node->getFlatNodes() as $childNode) {
                $result[$childNode->internalReference] = $childNode;
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
        $result = [];
        foreach ($this->iterateItems() as $key => $node) {
            $result[$key] = $node->isContainer() ? $node->getNodes() : $node->getNodeData();
        }
        return $result;
    }

    /**
     * get nodes as array structure using getValue() and (optionally) getDescription() as leaves,
     * the latter prefixed with a dollar sign ($) as these are impossible to exist in our xml structure.
     * (eg field, $field)
     * @return array
     */
    public function getNodeContent()
    {
        $result = [];
        foreach ($this->iterateItems() as $key => $node) {
            if ($node->isContainer()) {
                $result[$key] = $node->getNodeContent();
            } else {
                $result[$key] = $node->getValue();
                $descr = $node->getDescription();
                if ($descr != $result[$key]) {
                    $result['%' . $key] = $descr;
                }
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
        return (string)$this;
    }

    /**
     * Return descriptive value of the item.
     * For simple types this is usually the internal value, complex types may return what this value represents.
     * (descriptions of selected items)
     * @return null|string
     */
    public function getDescription()
    {
        return (string)$this;
    }

    /**
     * update model with data returning missing repeating tag types.
     * @param $data array structure containing new model content
     * @throws Exception
     */
    public function setNodes($data)
    {
        // update structure with new content
        foreach ($this->iterateItems() as $key => $node) {
            if ($data != null && isset($data[$key])) {
                if ($node->isContainer()) {
                    if (is_array($data[$key])) {
                        $node->setNodes($data[$key]);
                    } else {
                        throw new Exception("Invalid  input type for {$key} (configuration error?)");
                    }
                } else {
                    $node->setValue($data[$key]);
                }
            }
        }

        // add new items to array type objects
        if ($this->isArrayType()) {
            foreach ($data as $dataKey => $dataValue) {
                if (!isset($this->$dataKey)) {
                    $node = $this->add();
                    $node->setNodes($dataValue);
                }
            }
        }
    }

    /**
     * Add this node and its children to the supplied simplexml node pointer.
     * @param SimpleXMLElement $node target node
     */
    public function addToXMLNode($node)
    {
        if ($this->internalReference == "" || $this->isArrayType()) {
            // ignore tags without internal reference (root) and ArrayTypes
            $subnode = $node;
        } else {
            if ($this->internalValue != "") {
                $newNodeName = $this->getInternalXMLTagName();
                $subnode = $node->addChild($newNodeName);
                $node->$newNodeName = $this->internalValue;
            } else {
                $subnode = $node->addChild($this->getInternalXMLTagName());
            }

            // copy attributes into xml node
            foreach ($this->getAttributes() as $AttrKey => $AttrValue) {
                $subnode->addAttribute($AttrKey, $AttrValue);
            }
        }

        foreach ($this->iterateItems() as $key => $FieldNode) {
            if ($FieldNode->getInternalIsVirtual() || $FieldNode->getInternalIsVolatile()) {
                // Virtual and volatile fields should never be persisted
                continue;
            }
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
     * @return string default validation message
     */
    protected function defaultValidationMessage()
    {
        return gettext('Validation failed.');
    }

    /**
     * @return string current validation message
     */
    protected function getValidationMessage()
    {
        return $this->internalValidationMessage !== null ?
            gettext($this->internalValidationMessage) :
            $this->defaultValidationMessage();
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
     * change character case on save
     * @param string $value set case type, upper, lower, null (don't change)
     */
    public function setChangeCase($value)
    {
        if (strtoupper(trim($value)) == 'UPPER') {
            $this->internalChangeCase = 'UPPER';
        } elseif (strtoupper(trim($value)) == 'LOWER') {
            $this->internalChangeCase = 'LOWER';
        } else {
            $this->internalChangeCase = null;
        }
    }

    /**
     * set additional constraints
     * @param $constraints
     */
    public function setConstraints($constraints)
    {
        $this->internalConstraints = $constraints;
    }

    /**
     * apply change case to this node, called by setValue
     */
    private function applyFilterChangeCase()
    {
        if (!empty($this->internalValue)) {
            if ($this->internalChangeCase == 'UPPER') {
                $this->internalValue = strtoupper($this->internalValue);
            } elseif ($this->internalChangeCase == 'LOWER') {
                $this->internalValue = strtolower($this->internalValue);
            }
        }
    }

    /**
     * return object type as string
     * @return string
     */
    public function getObjectType()
    {
        $parts = explode("\\", get_class($this));
        return $parts[count($parts) - 1];
    }

    /**
     * normalize the internal value to allow passing validation
     */
    public function normalizeValue()
    {
        /* implemented where needed */
    }
}
