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
    function: implements base model to bind config and definition to object

*/

namespace OPNsense\Base;

use OPNsense\Base\FieldTypes\ArrayField;
use OPNsense\Core\Config;

abstract class BaseModel
{
    /**
     * @var null|BaseField internal model data structure, should contain Field type objects
     */
    private $internalData = null;

    /**
     * place where the real data in the config.xml should live
     * @var string
     */
    private $internal_mountpoint = '';


    private $internalConfigHandle = null;

    /**
     * If the model needs a custom initializer, override this init() method
     * Default behaviour is to do nothing in this init.
     */
    protected function init()
    {
        return ;
    }

    /**
     * @param $xml|SimpleXMLElement model xml data (from items section)
     * @param $config_data|SimpleXMLElement (current) config data
     * @param $internal_data output structure using FieldTypes, rootnode is internalData
     * @throws ModelException parse error
     */
    private function parseXml($xml, &$config_data, &$internal_data)
    {
        foreach ($xml->children() as $xmlNode) {
            $tagName = $xmlNode->getName();
            // every item results in a Field type object, the first step is to determine which object to create
            // based on the input model spec
            $fieldObject = null ;
            $classname = "OPNsense\\Base\\FieldTypes\\".$xmlNode->attributes()["type"];
            if (class_exists($classname)) {
                // construct field type object
                $field_rfcls = new \ReflectionClass($classname);
                if (!$field_rfcls->getParentClass()->name == 'OPNsense\Base\FieldTypes\BaseField') {
                    // class found, but of wrong type. raise an exception.
                    throw new ModelException("class ".$field_rfcls->name." of wrong type in model definition");
                }
            } else {
                // no type defined, so this must be a standard container (without content)
                $field_rfcls = new \ReflectionClass('OPNsense\Base\FieldTypes\ContainerField');
            }

            // generate full object name ( section.section.field syntax ) and create new Field
            if ($internal_data->__reference == "") {
                $new_ref = $tagName;
            } else {
                $new_ref = $internal_data->__reference . "." . $tagName;
            }
            $fieldObject = $field_rfcls->newInstance($new_ref);

            // now add content to this model (recursive)
            if ($fieldObject->isContainer() == false) {
                $internal_data->addChildNode($tagName, $fieldObject);
                if ($xmlNode->count() > 0) {
                    // if fieldtype contains properties, try to call the setters
                    foreach ($xmlNode->children() as $fieldMethod) {
                        $param_value = $fieldMethod->__toString() ;
                        $method_name = "set".$fieldMethod->getName();
                        if ($field_rfcls->hasMethod($method_name)) {
                            $fieldObject->$method_name($param_value);
                        }
                    }
                }
                if ($config_data != null && isset($config_data->$tagName)) {
                    // set field content from config (if available)
                    $fieldObject->setValue($config_data->$tagName->__toString());
                }

            } else {
                // add new child node container, always try to pass config data
                if ($config_data != null && isset($config_data->$tagName)) {
                    $config_section_data = $config_data->$tagName;
                } else {
                    $config_section_data = null ;
                }

                if ($fieldObject instanceof ArrayField) {
                    // handle Array types, recurring items
                    if ($config_section_data != null) {
                        $counter = 0 ;
                        foreach ($config_section_data as $conf_section) {
                            $child_node = new ArrayField($fieldObject->__reference . "." . ($counter++));
                            $this->parseXml($xmlNode, $conf_section, $child_node);
                            $fieldObject->addChildNode(null, $child_node);
                        }
                    } else {
                        $child_node = new ArrayField($fieldObject->__reference . ".0");
                        $child_node->setInternalEmptyStatus(true);
                        $this->parseXml($xmlNode, $config_section_data, $child_node);
                        $fieldObject->addChildNode(null, $child_node);
                    }
                } else {
                    $this->parseXml($xmlNode, $config_section_data, $fieldObject);
                }

                $internal_data->addChildNode($xmlNode->getName(), $fieldObject);
            }

        }

    }

    /**
     * Construct new model type, using it's own xml template
     * @throws ModelException if the model xml is not found or invalid
     */
    public function __construct()
    {
        // setup config handle to singleton config singleton
        $this->internalConfigHandle = Config::getInstance();

        // init new root node, all details are linked to this
        $this->internalData = new FieldTypes\ContainerField();

        // determine our caller's filename and try to find the model definition xml
        // throw error on failure
        $class_info = new \ReflectionClass($this);
        $model_filename = substr($class_info->getFileName(), 0, strlen($class_info->getFileName())-3) . "xml" ;
        if (!file_exists($model_filename)) {
            throw new ModelException('model xml '.$model_filename.' missing') ;
        }
        $model_xml = simplexml_load_file($model_filename);
        if ($model_xml === false) {
            throw new ModelException('model xml '.$model_filename.' not valid') ;
        }
        if ($model_xml->getName() != "model") {
            throw new ModelException('model xml '.$model_filename.' seems to be of wrong type') ;
        }

        // use an xpath expression to find the root of our model in the config.xml file
        // if found, convert the data to a simple structure (or create an empty array)
        $tmp_config_data = $this->internalConfigHandle->xpath($model_xml->mount);
        if ($tmp_config_data->length > 0) {
            $config_array = simplexml_import_dom($tmp_config_data->item(0)) ;
        } else {
            $config_array = array();
        }

        // We've loaded the model template, now let's parse it into this object
        $this->parseXml($model_xml->items, $config_array, $this->internalData) ;

        //print_r($this->internalData);

        // call Model initializer
        $this->init();
    }

    /**
     * reflect getter to internalData (ContainerField)
     * @param $name property name
     * @return mixed
     */
    public function __get($name)
    {
        return $this->internalData->$name;
    }

    /**
     * reflect setter to internalData (ContainerField)
     * @param $name property name
     * @param $value property value
     */
    public function __set($name, $value)
    {
        $this->internalData->$name = $value ;
    }

}