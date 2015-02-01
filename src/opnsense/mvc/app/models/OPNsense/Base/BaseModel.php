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

namespace OPNsense\Base;

use OPNsense\Base\FieldTypes\ArrayField;
use OPNsense\Base\FieldTypes\ContainerField;
use OPNsense\Core\Config;

/**
 * Class BaseModel implements base model to bind config and definition to object.
 * Derive from this class to create usable models.
 * Every model definition should include a class (derived from this) and a xml model to define the data (model.xml)
 *
 * See the Sample model for a full implementation.
 *
 * @package OPNsense\Base
 */
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


    /**
     * If the model needs a custom initializer, override this init() method
     * Default behaviour is to do nothing in this init.
     */
    protected function init()
    {
        return ;
    }

    /**
     * parse model and config xml to object model using types in FieldTypes
     * @param SimpleXMLElement $xml model xml data (from items section)
     * @param SimpleXMLElement $config_data (current) config data
     * @param BaseField $internal_data output structure using FieldTypes,rootnode is internalData
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
            $fieldObject = $field_rfcls->newInstance($new_ref, $tagName);

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
                            $child_node = new ContainerField($fieldObject->__reference . "." . ($counter++), $tagName);
                            $this->parseXml($xmlNode, $conf_section, $child_node);
                            $fieldObject->addChildNode(null, $child_node);
                        }
                    } else {
                        $child_node = new ContainerField($fieldObject->__reference . ".0", $tagName);
                        $child_node->setInternalIsVirtual();
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
        $internalConfigHandle = Config::getInstance();

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
        $this->internal_mountpoint = $model_xml->mount;

        // use an xpath expression to find the root of our model in the config.xml file
        // if found, convert the data to a simple structure (or create an empty array)
        $tmp_config_data = $internalConfigHandle->xpath($model_xml->mount);
        if ($tmp_config_data->length > 0) {
            $config_array = simplexml_import_dom($tmp_config_data->item(0)) ;
        } else {
            $config_array = array();
        }

        // We've loaded the model template, now let's parse it into this object
        $this->parseXml($model_xml->items, $config_array, $this->internalData) ;

        // trigger post loading event
        $this->internalData->eventPostLoading();

        // call Model initializer
        $this->init();
    }

    /**
     * reflect getter to internalData (ContainerField)
     * @param string $name property name
     * @return mixed
     */
    public function __get($name)
    {
        return $this->internalData->$name;
    }

    /**
     * reflect setter to internalData (ContainerField)
     * @param string $name property name
     * @param string $value property value
     */
    public function __set($name, $value)
    {
        $this->internalData->$name = $value ;
    }

    /**
     * forward to root node's getFlatNodes
     * @return array all children
     */
    public function getFlatNodes()
    {
        return $this->internalData->getFlatNodes();
    }

    /**
     * validate full model using all fields and data in a single (1 deep) array
     */
    public function performValidation()
    {
        // create a Phalcon validator and collect all model validations
        $validation = new \Phalcon\Validation();
        $validation_data = array();
        $all_nodes = $this->internalData->getFlatNodes();

        foreach ($all_nodes as $key => $node) {
            $node_validators = $node->getValidators();
            foreach ($node_validators as $item_validator) {
                $validation->add($key, $item_validator);
            }
            if (count($node_validators) > 0) {
                $validation_data[$key] = $node->__toString();
            }
        }

        if (count($validation_data) > 0) {
            $messages = $validation->validate($validation_data);
        } else {
            $messages = new \Phalcon\Validation\Message\Group();
        }

        return $messages;
    }

    /**
     * render xml document from model including all parent nodes.
     * (parent nodes are included to ease testing)
     *
     * @return \SimpleXMLElement xml representation of the model
     */
    public function toXML()
    {
        // calculate root node from mountpoint
        $xml_root_node = "";
        $str_parts = explode("/", str_replace("//", "/", $this->internal_mountpoint));
        for ($i=0; $i < count($str_parts); $i++) {
            if ($str_parts[$i] != "") {
                $xml_root_node .= "<".$str_parts[$i].">";
            }
        }
        for ($i=count($str_parts)-1; $i >= 0; $i--) {
            if ($str_parts[$i] != "") {
                $xml_root_node .= "</".$str_parts[$i].">";
            }
        }

        $xml = new \SimpleXMLElement($xml_root_node);
        $this->internalData->addToXMLNode($xml->xpath($this->internal_mountpoint)[0]);

        return $xml;

    }

    /**
     * serialize model singleton to config object
     */
    private function internalSerializeToConfig()
    {
        // setup config handle to singleton config singleton
        $internalConfigHandle = Config::getInstance();
        $config_xml = $internalConfigHandle->object();

        // serialize this model's data to xml
        $data_xml = $this->toXML();

        // Locate source node (in theory this must return a valid result, delivered by toXML).
        // Because toXML delivers the actual xml including the full path, we need to find the root of our data.
        $source_node = $data_xml->xpath($this->internal_mountpoint);

        // find parent of mountpoint (create if it doesn't exists)
        $target_node = $config_xml;
        $str_parts = explode("/", str_replace("//", "/", $this->internal_mountpoint));
        for ($i=0; $i < count($str_parts)-1; $i++) {
            if ($str_parts[$i] != "") {
                if (count($target_node->xpath($str_parts[$i])) == 0) {
                    $target_node = $target_node->addChild($str_parts[$i]);
                } else {
                    $target_node = $target_node->xpath($str_parts[$i])[0];
                }

            }
        }

        // copy model data into config
        $toDom = dom_import_simplexml($target_node);
        $fromDom = dom_import_simplexml($source_node[0]);

        // remove old model data and write new
        foreach ($toDom->getElementsByTagName($fromDom->nodeName) as $oldNode) {
            $toDom->removeChild($oldNode);
        }
        $toDom->appendChild($toDom->ownerDocument->importNode($fromDom, true));
    }

    /**
     * validate model and serialize data to config singleton object.
     *
     * @param bool $disable_validation skip validation, be careful to use this!
     * @throws \Phalcon\Validation\Exception validation errors
     */
    public function serializeToConfig($disable_validation = false)
    {
        if ($disable_validation == false) {
            // perform validation, collect all messages and raise exception
            $messages = $this->performValidation();
            if ($messages->count() > 0) {
                $exception_msg = "";
                foreach ($messages as $msg) {
                    $exception_msg .= "[".$msg-> getField()."] ".$msg->getMessage()."\n";
                }
                throw new \Phalcon\Validation\Exception($exception_msg);
            }
        }
        $this->internalSerializeToConfig();
    }

    /**
     * find node by reference starting at the root node
     * @param BaseField $node source node
     * @param string $reference node reference (point separated "node.subnode.subsubnode")
     * @return BaseField|null field node by reference (or null if not found)
     */
    public function getNodeByReference($reference)
    {
        $parts = explode(".", $reference);

        $node = $this->internalData;
        while (count($parts)>0) {
            $childName = array_shift($parts);
            if (array_key_exists($childName, $node->getChildren())) {
                $node = $node->getChildren()[$childName];
            } else {
                return null;
            }

        }
        return $node;
    }

    /**
     * set node value by name (if reference exists)
     * @param string $reference node reference (point separated "node.subnode.subsubnode")
     * @param string $value
     * @return bool value saved yes/no
     */
    public function setNodeByReference($reference, $value)
    {
        $node =$this->getNodeByReference($reference);
        if ($node != null) {
            $node->setValue($value);
            return true;
        } else {
            return false;
        }
    }
}
