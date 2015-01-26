<?php
/*
    # Copyright (C) 2015 Deciso B.V.
    #
    # All rights reserved.
    #
    # Redistribution and use in source and binary forms, with or without
    # modification, are permitted provided that the following conditions are met:
    #
    # 1. Redistributions of source code must retain the above copyright notice,
    #    this list of conditions and the following disclaimer.
    #
    # 2. Redistributions in binary form must reproduce the above copyright
    #    notice, this list of conditions and the following disclaimer in the
    #    documentation and/or other materials provided with the distribution.
    #
    # THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
    # INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
    # AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
    # AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
    # OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
    # SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
    # INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
    # CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
    # ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
    # POSSIBILITY OF SUCH DAMAGE.

    --------------------------------------------------------------------------------------
    package : Frontend Model Base
    function:

*/

namespace OPNsense\Base\FieldTypes;

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
     * @var string direct reference to this field in the model object
     */
    protected $internalReference = "";

    /**
     * @var string validation message string
     */
    protected $internalValidationMessage = null;

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
     */
    public function __construct($ref = null)
    {
        $this->internalReference = $ref;
    }

    /**
     * @param $name property name
     * @param $node content (must be of type BaseField)
     */
    public function addChildNode($name, $node)
    {
        $this->internalChildnodes[$name] = $node;
    }

    /**
     * Reflect default getter to internal child nodes.
     * Implements the special attribute __items to return all items and __reference to identify the field in this model.
     * @param $name property name
     * @return mixed
     */
    public function __get($name)
    {
        if (array_key_exists($name, $this->internalChildnodes)) {
            return $this->internalChildnodes[$name];
        } elseif ($name == '__items') {
            return $this->internalChildnodes;
        } elseif ($name == '__reference') {
            return $this->internalReference;
        } else {
            // not found
            return null;
        }

    }

    /**
     * reflect default setter to internal child nodes
     * @param $name|string property name
     * @param $value|string property value
     */
    public function __set($name, $value)
    {
        if (array_key_exists($name, $this->internalChildnodes)) {
            $this->internalChildnodes[$name]->setValue($value);
        }
    }

    public function __toString()
    {
        return $this->internalValue;
    }

    /**
     * default setter
     * @param $value set field value
     */
    public function setValue($value)
    {
        $this->internalValue = $value;
    }

    /**
     * @return array child items
     */
    public function getChildren()
    {
        return $this->internalChildnodes;
    }

    /**
     * set Default field value
     * @param $value default value
     */
    public function setDefault($value)
    {
        $this->internalValue = $value;
    }

    /**
     * @param $msg validation message (on failure)
     */
    public function setValidationMessage($msg)
    {
        $this->internalValidationMessage = $msg;
    }

    /**
     * @return array returns validators for this field type (empty if none)
     */
    public function getValidators()
    {
        return array();
    }

}