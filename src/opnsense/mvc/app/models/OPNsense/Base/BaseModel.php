<?php

/*
 * Copyright (C) 2015-2020 Deciso B.V.
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

namespace OPNsense\Base;

use Exception;
use OPNsense\Base\FieldTypes\ContainerField;
use OPNsense\Core\Config;
use Phalcon\Logger;
use Phalcon\Logger\Adapter\Syslog;
use Phalcon\Validation;
use Phalcon\Messages\Messages;
use ReflectionClass;
use ReflectionException;
use SimpleXMLElement;

/**
 * Class BaseModel implements base model to bind config and definition to object.
 * Derive from BaseModel to create usable models.
 * Every model definition should include a class (derived from BaseModel) and a xml model to define the data (model.xml)
 *
 * See the HelloWorld model for a full implementation.
 * (https://github.com/opnsense/plugins/tree/master/devel/helloworld/src/opnsense/mvc/app/models/OPNsense/HelloWorld)
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
     * this models version number, defaults to 0.0.0 (no version)
     * @var string
     */
    private $internal_model_version = "0.0.0";

    /**
     * prefix for migration files, default is M (e.g. M1_0_0.php equals version 1.0.0)
     * when models share a namespace, they should be allowed to use their own unique prefix
     * @var string
     */
    private $internal_model_migration_prefix = "M";

    /**
     * model version in config.xml
     * @var null
     */
    private $internal_current_model_version = null;

    /**
     * cache classes
     * @var null
     */
    private static $internalCacheReflectionClasses = null;

    /**
     * If the model needs a custom initializer, override this init() method
     * Default behaviour is to do nothing in this init.
     */
    protected function init()
    {
        return;
    }

    /**
     * parse option data for model setter.
     * @param $xmlNode
     * @return array|string
     */
    private function parseOptionData($xmlNode)
    {
        if ($xmlNode->count() == 0) {
            $result = (string)$xmlNode;
        } else {
            $result = array();
            foreach ($xmlNode->children() as $childNode) {
                // item keys can be overwritten using value attributes
                if (!isset($childNode->attributes()['value'])) {
                    $itemKey = (string)$childNode->getName();
                } else {
                    $itemKey = (string)$childNode->attributes()['value'];
                }
                $result[$itemKey] = $this->parseOptionData($childNode);
            }
        }
        return $result;
    }

    /**
     * fetch reflection class (cached by field type)
     * @param string $classname classname to construct
     * @return BaseField type class
     * @throws ModelException when unable to parse field type
     * @throws ReflectionException when unable to create class
     */
    private function getNewField($classname)
    {
        if (self::$internalCacheReflectionClasses === null) {
            self::$internalCacheReflectionClasses = array();
        }
        $classname_idx = str_replace("\\", "_", $classname);
        if (!isset(self::$internalCacheReflectionClasses[$classname_idx])) {
            $is_derived_from_basefield = false;
            if (class_exists($classname)) {
                $field_rfcls = new ReflectionClass($classname);
                $check_derived = $field_rfcls->getParentClass();
                while ($check_derived != false) {
                    if ($check_derived->name == 'OPNsense\Base\FieldTypes\BaseField') {
                        $is_derived_from_basefield = true;
                        break;
                    }
                    $check_derived = $check_derived->getParentClass();
                }
            }
            if (!$is_derived_from_basefield) {
                // class found, but of wrong type. raise an exception.
                throw new ModelException("class " . $field_rfcls->name . " of wrong type in model definition");
            }
            self::$internalCacheReflectionClasses[$classname_idx] = $field_rfcls;
        }
        return self::$internalCacheReflectionClasses[$classname_idx];
    }

    /**
     * parse model and config xml to object model using types in FieldTypes
     * @param SimpleXMLElement $xml model xml data (from items section)
     * @param SimpleXMLElement $config_data (current) config data
     * @param BaseField $internal_data output structure using FieldTypes,rootnode is internalData
     * @throws ModelException parse error
     * @throws ReflectionException
     */
    private function parseXml(&$xml, &$config_data, &$internal_data)
    {
        // copy xml tag attributes to Field
        if ($config_data != null) {
            foreach ($config_data->attributes() as $AttrKey => $AttrValue) {
                $internal_data->setAttributeValue($AttrKey, $AttrValue->__toString());
            }
        }

        // iterate model children
        foreach ($xml->children() as $xmlNode) {
            $tagName = $xmlNode->getName();
            // every item results in a Field type object, the first step is to determine which object to create
            // based on the input model spec
            $xmlNodeType = $xmlNode->attributes()["type"];
            if (!empty($xmlNodeType)) {
                // construct field type object
                if (strpos($xmlNodeType, "\\") !== false) {
                    // application specific field type contains path separator
                    if (strpos($xmlNodeType, ".\\") === 0) {
                        // use current namespace (.\Class)
                        $namespace = explode("\\", get_class($this));
                        array_pop($namespace);
                        $namespace = implode("\\", $namespace);
                        $classname = str_replace(".\\", $namespace . "\\FieldTypes\\", (string)$xmlNodeType);
                    } else {
                        $classname = (string)$xmlNodeType;
                    }
                    $field_rfcls = $this->getNewField($classname);
                } else {
                    // standard field type
                    $field_rfcls = $this->getNewField("OPNsense\\Base\\FieldTypes\\" . $xmlNodeType);
                }
            } else {
                // no type defined, so this must be a standard container (without content)
                $field_rfcls = $this->getNewField('OPNsense\Base\FieldTypes\ContainerField');
            }

            // generate full object name ( section.section.field syntax ) and create new Field
            if ($internal_data->__reference == "") {
                $new_ref = $tagName;
            } else {
                $new_ref = $internal_data->__reference . "." . $tagName;
            }
            $fieldObject = $field_rfcls->newInstance($new_ref, $tagName);
            $fieldObject->setParentModel($this);

            // now add content to this model (recursive)
            if ($fieldObject->isContainer() == false) {
                $internal_data->addChildNode($tagName, $fieldObject);
                if ($xmlNode->count() > 0) {
                    // if fieldtype contains properties, try to call the setters
                    foreach ($xmlNode->children() as $fieldMethod) {
                        $method_name = "set" . $fieldMethod->getName();
                        if ($field_rfcls->hasMethod($method_name)) {
                            $fieldObject->$method_name($this->parseOptionData($fieldMethod));
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
                    $config_section_data = null;
                }

                if ($fieldObject->isArrayType()) {
                    // handle Array types, recurring items
                    $node_count = 0;
                    if ($config_section_data != null) {
                        foreach ($config_section_data as $conf_section) {
                            if ($conf_section->count() == 0) {
                                // skip empty nodes: prevents legacy empty tags from being treated as invalid content items
                                // (migration will drop these anyways)
                                continue;
                            }
                            $node_count++;
                            // Array items are identified by a UUID, read from attribute or create a new one
                            if (isset($conf_section->attributes()->uuid)) {
                                $tagUUID = $conf_section->attributes()['uuid']->__toString();
                            } else {
                                $tagUUID = $internal_data->generateUUID();
                            }

                            // iterate array items from config data
                            $child_node = $fieldObject->newContainerField(
                                $fieldObject->__reference . "." . $tagUUID,
                                $tagName
                            );
                            $this->parseXml($xmlNode, $conf_section, $child_node);
                            if (!isset($conf_section->attributes()->uuid)) {
                                // if the node misses a uuid, copy it to this nodes attributes
                                $child_node->setAttributeValue('uuid', $tagUUID);
                            }
                            $fieldObject->addChildNode($tagUUID, $child_node);
                        }
                    }
                    if ($node_count == 0) {
                        // There's no content in config.xml for this array node.
                        $tagUUID = $internal_data->generateUUID();
                        $child_node = $fieldObject->newContainerField(
                            $fieldObject->__reference . "." . $tagUUID,
                            $tagName
                        );
                        $child_node->setInternalIsVirtual();
                        $this->parseXml($xmlNode, $config_section_data, $child_node);
                        $fieldObject->addChildNode($tagUUID, $child_node);
                    }
                } else {
                    // All other node types (Text,Email,...)
                    $this->parseXml($xmlNode, $config_section_data, $fieldObject);
                }

                // add object as child to this node
                $internal_data->addChildNode($xmlNode->getName(), $fieldObject);
            }
        }
    }

    /**
     * Construct new model type, using its own xml template
     * @throws ModelException if the model xml is not found or invalid
     * @throws ReflectionException
     */
    public function __construct()
    {
        // setup config handle to singleton config singleton
        $internalConfigHandle = Config::getInstance();

        // init new root node, all details are linked to this
        $this->internalData = new ContainerField();

        // determine our caller's filename and try to find the model definition xml
        // throw error on failure
        $class_info = new ReflectionClass($this);
        $model_filename = substr($class_info->getFileName(), 0, strlen($class_info->getFileName()) - 3) . "xml";
        if (!file_exists($model_filename)) {
            throw new ModelException('model xml ' . $model_filename . ' missing');
        }
        $model_xml = simplexml_load_file($model_filename);
        if ($model_xml === false) {
            throw new ModelException('model xml ' . $model_filename . ' not valid');
        }
        if ($model_xml->getName() != "model") {
            throw new ModelException('model xml ' . $model_filename . ' seems to be of wrong type');
        }
        $this->internal_mountpoint = $model_xml->mount;

        if (!empty($model_xml->version)) {
            $this->internal_model_version = (string)$model_xml->version;
        }

        if (!empty($model_xml->migration_prefix)) {
            $this->internal_model_migration_prefix = (string)$model_xml->migration_prefix;
        }

        // use an xpath expression to find the root of our model in the config.xml file
        // if found, convert the data to a simple structure (or create an empty array)
        $tmp_config_data = $internalConfigHandle->xpath($model_xml->mount);
        if ($tmp_config_data->length > 0) {
            $config_array = simplexml_import_dom($tmp_config_data->item(0));
        } else {
            $config_array = array();
        }

        // We've loaded the model template, now let's parse it into this object
        $this->parseXml($model_xml->items, $config_array, $this->internalData);
        // root may contain a version, store if found
        if (empty($config_array)) {
            // new node, reset
            $this->internal_current_model_version = "0.0.0";
        } elseif (!empty($config_array->attributes()['version'])) {
            $this->internal_current_model_version = (string)$config_array->attributes()['version'];
        }

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
        $this->internalData->$name = $value;
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
     * get nodes as array structure
     * @return array
     */
    public function getNodes()
    {
        return $this->internalData->getNodes();
    }

    /**
     * structured setter for model
     * @param array $data named array
     * @return void
     * @throws Exception
     */
    public function setNodes($data)
    {
        return $this->internalData->setNodes($data);
    }

    /**
     * iterate (non virtual) child nodes
     * @return mixed
     */
    public function iterateItems()
    {
        return $this->internalData->iterateItems();
    }

    /**
     * validate full model using all fields and data in a single (1 deep) array
     * @param bool $validateFullModel validate full model or only changed fields
     * @return Group
     */
    public function performValidation($validateFullModel = false)
    {
        // create a Phalcon validator and collect all model validations
        $validation = new Validation();
        $validation_data = array();
        $all_nodes = $this->internalData->getFlatNodes();

        foreach ($all_nodes as $key => $node) {
            if ($validateFullModel || $node->isFieldChanged()) {
                $node_validators = $node->getValidators();
                foreach ($node_validators as $item_validator) {
                    if (is_a($item_validator, "OPNsense\\Base\\Constraints\\BaseConstraint")) {
                        $target_key = $item_validator->getOption("node")->__reference;
                        $validation->add($target_key, $item_validator);
                    } else {
                        $validation->add($key, $item_validator);
                    }
                }
                if (count($node_validators) > 0) {
                    $validation_data[$key] = $node->__toString();
                }
            }
        }

        if (count($validation_data) > 0) {
            $messages = $validation->validate($validation_data);
        } else {
            $messages = new Messages();
        }

        return $messages;
    }

    /**
     * perform a validation on changed model fields, using the (renamed) internal reference as a source pointer
     * for the requestor to identify its origin
     * @param null|string $sourceref source reference, for example model.section
     * @param string $targetref target reference, for example section. used as prefix if no source given
     * @return array list of validation errors, indexed by field reference
     */
    public function validate($sourceref = null, $targetref = "")
    {
        $result = array();
        $valMsgs = $this->performValidation();
        foreach ($valMsgs as $msg) {
            // replace absolute path to attribute for relative one at uuid.
            if ($sourceref != null) {
                $fieldnm = str_replace($sourceref, $targetref, $msg->getField());
                $result[$fieldnm] = $msg->getMessage();
            } else {
                $fieldnm = $targetref . $msg->getField();
                $result[$fieldnm] = $msg->getMessage();
            }
        }
        return $result;
    }

    /**
     * render xml document from model including all parent nodes.
     * (parent nodes are included to ease testing)
     *
     * @return SimpleXMLElement xml representation of the model
     */
    public function toXML()
    {
        // calculate root node from mountpoint
        $xml_root_node = "";
        $str_parts = explode("/", str_replace("//", "/", $this->internal_mountpoint));
        for ($i = 0; $i < count($str_parts); $i++) {
            if ($str_parts[$i] != "") {
                $xml_root_node .= "<" . $str_parts[$i] . ">";
            }
        }
        for ($i = count($str_parts) - 1; $i >= 0; $i--) {
            if ($str_parts[$i] != "") {
                $xml_root_node .= "</" . $str_parts[$i] . ">";
            }
        }

        $xml = new SimpleXMLElement($xml_root_node);
        $this->internalData->addToXMLNode($xml->xpath($this->internal_mountpoint)[0]);
        // add this model's version to the newly created xml structure
        if (!empty($this->internal_current_model_version)) {
            $xml->xpath($this->internal_mountpoint)[0]->addAttribute('version', $this->internal_current_model_version);
        }

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
        for ($i = 0; $i < count($str_parts) - 1; $i++) {
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
        foreach ($toDom->childNodes as $childNode) {
            if ($childNode->nodeName == $fromDom->nodeName) {
                $toDom->removeChild($childNode);
            }
        }
        $toDom->appendChild($toDom->ownerDocument->importNode($fromDom, true));
    }

    /**
     * validate model and serialize data to config singleton object.
     *
     * @param bool $validateFullModel by default we only validate the fields we have changed
     * @param bool $disable_validation skip validation, be careful to use this!
     * @throws Validation\Exception validation errors
     */
    public function serializeToConfig($validateFullModel = false, $disable_validation = false)
    {
        // create logger to save possible consistency issues to
        $logger = new Logger(
            'messages',
            [
                'main' => new Syslog("config", ['option' => LOG_PID, 'facility' => LOG_LOCAL2])
            ]
        );

        // Perform validation, collect all messages and raise exception if validation is not disabled.
        // If for some reason the developer chooses to ignore the errors, let's at least log there something
        // wrong in this model.
        $messages = $this->performValidation($validateFullModel);
        if ($messages->count() > 0) {
            $exception_msg = "";
            foreach ($messages as $msg) {
                $exception_msg_part = "[" . get_class($this) . ":" . $msg->getField() . "] ";
                $exception_msg_part .= $msg->getMessage();
                $exception_msg .= "$exception_msg_part\n";
                // always log validation errors
                $logger->error($exception_msg_part);
            }
            if (!$disable_validation) {
                throw new Validation\Exception($exception_msg);
            }
        }
        $this->internalSerializeToConfig();
    }

    /**
     * find node by reference starting at the root node
     * @param string $reference node reference (point separated "node.subnode.subsubnode")
     * @return BaseField|null field node by reference (or null if not found)
     */
    public function getNodeByReference($reference)
    {
        $parts = explode(".", $reference);

        $node = $this->internalData;
        while (count($parts) > 0) {
            $childName = array_shift($parts);
            if ($node->hasChild($childName)) {
                $node = $node->getChild($childName);
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
        $node = $this->getNodeByReference($reference);
        if ($node != null) {
            $node->setValue($value);
            return true;
        } else {
            return false;
        }
    }

    /**
     * Execute model version migrations
     * Every model may contain a migrations directory containing BaseModelMigration descendants, which
     * are executed in order of version number.
     *
     * The BaseModelMigration class should be named with the corresponding version
     * prefixed with an M and . replaced by _ for example : M1_0_1 equals version 1.0.1
     *
     * @return bool status (true-->success, false-->failed)
     * @throws ReflectionException
     */
    public function runMigrations()
    {
        if (version_compare($this->internal_current_model_version, $this->internal_model_version, '<')) {
            $upgradePerfomed = false;
            $migObjects = array();
            $logger = new Syslog("config", array('option' => LOG_PID, 'facility' => LOG_LOCAL2));
            $class_info = new ReflectionClass($this);
            // fetch version migrations
            $versions = array();
            // set default migration for current model version
            $versions[$this->internal_model_version] = __DIR__ . "/BaseModelMigration.php";
            $migprefix = $this->internal_model_migration_prefix;
            foreach (glob(dirname($class_info->getFileName()) . "/Migrations/{$migprefix}*.php") as $filename) {
                $version = str_replace('_', '.', explode('.', substr(basename($filename), strlen($migprefix)))[0]);
                $versions[$version] = $filename;
            }

            uksort($versions, "version_compare");
            foreach ($versions as $mig_version => $filename) {
                if (
                    version_compare($this->internal_current_model_version, $mig_version, '<') &&
                    version_compare($this->internal_model_version, $mig_version, '>=')
                ) {
                    // execute upgrade action
                    if (!strstr($filename, '/tests/app')) {
                        $mig_classname = explode('.', explode('/mvc/app/models', $filename)[1])[0];
                    } else {
                        // unit tests use a different namespace for their models
                        $mig_classname = "/tests" . explode('.', explode('/mvc/tests/app/models', $filename)[1])[0];
                    }
                    $mig_classname = str_replace('/', '\\', $mig_classname);
                    // Phalcon's autoloader uses _ as a directory locator, we need to import these files ourselves
                    require_once $filename;
                    $mig_class = new ReflectionClass($mig_classname);
                    $chk_class = empty($mig_class->getParentClass()) ? $mig_class :  $mig_class->getParentClass();
                    if ($chk_class->name == 'OPNsense\Base\BaseModelMigration') {
                        $migobj = $mig_class->newInstance();
                        try {
                            $migobj->run($this);
                            $migObjects[] = $migobj;
                            $upgradePerfomed = true;
                        } catch (Exception $e) {
                            $logger->error("failed migrating from version " .
                                $this->internal_current_model_version .
                                " to " . $mig_version . " in " .
                                $class_info->getName() .
                                " [skipping step]");
                        }
                        $this->internal_current_model_version = $mig_version;
                    }
                }
            }
            // serialize to config after last migration step, keep the config data static as long as not all
            // migrations have completed.
            if ($upgradePerfomed) {
                try {
                    $this->serializeToConfig();
                    foreach ($migObjects as $migobj) {
                        $migobj->post($this);
                    }
                } catch (Exception $e) {
                    $logger->error("Model " . $class_info->getName() . " can't be saved, skip ( " . $e . " )");
                    return false;
                }
            }

            return true;
        }
        return false;
    }

    /**
     * return current version number
     * @return null|string
     */
    public function getVersion()
    {
        return $this->internal_current_model_version;
    }
}
