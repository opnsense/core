<?php

/*
 * Copyright (C) 2016 IT-assistans Sverige AB
 * Copyright (C) 2016 Deciso B.V.
 * Copyright (C) 2018 Fabian Franz
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

use OPNsense\Core\ACL;
use OPNsense\Core\Config;

/**
 * Class ApiMutableModelControllerBase, inherit this class to implement
 * an API that exposes a model with get and set actions.
 * You need to implement a method to create new blank model
 * objecs (newModelObject) as well as a method to return
 * the name of the model.
 * @package OPNsense\Base
 */
abstract class ApiMutableModelControllerBase extends ApiControllerBase
{
    /**
     * @var string this implementations internal model name to use (in set/get output)
     */
    protected static $internalModelName = null;

    /**
     * @var string model class name to use
     */
    protected static $internalModelClass = null;

    /**
     * @var bool use safe delete, search for references before allowing deletion
     */
    protected static $internalModelUseSafeDelete = false;

    /**
     * @var null|BaseModel model object to work on
     */
    private $modelHandle = null;

    /**
     * Validate on initialization
     * @throws \Exception when not bound to a model class or a set/get reference is missing
     */
    public function initialize()
    {
        parent::initialize();
        if (empty(static::$internalModelClass)) {
            throw new \Exception('cannot instantiate without internalModelClass defined.');
        }
        if (empty(static::$internalModelName)) {
            throw new \Exception('cannot instantiate without internalModelName defined.');
        }
    }

    /**
     * Check if item can be safely deleted if $internalModelUseSafeDelete is enabled.
     * Throws a user exception when the $uuid seems to be used in some other config section.
     * @param $uuid string uuid to check
     * @throws UserException containing additional information
     */
    private function checkAndThrowSafeDelete($uuid)
    {
        if (static::$internalModelUseSafeDelete) {
            $configObj = Config::getInstance()->object();
            $usages = array();
            // find uuid's in our config.xml
            foreach ($configObj->xpath("//text()[.='{$uuid}']") as $node) {
                $referring_node = $node->xpath("..")[0];
                if (!empty($referring_node->attributes()['uuid'])) {
                    // this looks like a model node, try to find module name (first tag with version attribute)
                    $item_path = array($referring_node->getName());
                    $item_uuid = $referring_node->attributes()['uuid'];
                    $parent_node = $referring_node;
                    do {
                        $parent_node = $parent_node->xpath("..");
                        $parent_node = $parent_node != null ? $parent_node[0] : null;
                        if ($parent_node != null) {
                            $item_path[] = $parent_node->getName();
                        }
                    } while ($parent_node != null && !isset($parent_node->attributes()['version']));
                    if ($parent_node != null) {
                        // construct usage info and add to usages if this looks like a model
                        $item_path = array_reverse($item_path);
                        $item_description = "";
                        foreach (["description", "descr", "name"] as $key) {
                            if (!empty($referring_node->$key)) {
                                $item_description = (string)$referring_node->$key;
                                break;
                            }
                        }
                        $usages[] = array(
                            "reference" =>  implode(".", $item_path) . "." . $item_uuid,
                            "module" => $item_path[0],
                            "description" => $item_description
                        );
                    }
                }
            }
            if (!empty($usages)) {
                // render exception message
                $message = "";
                foreach ($usages as $usage) {
                    $message .= sprintf(
                        gettext("%s - %s {%s}"),
                        $usage['module'],
                        $usage['description'],
                        $usage['reference']
                    ) . "\n";
                }
                throw new UserException($message, gettext("Item in use by"));
            }
        }
    }

    /**
     * Retrieve model settings
     * @return array settings
     * @throws \ReflectionException when not bound to a valid model
     */
    public function getAction()
    {
        // define list of configurable settings
        $result = array();
        if ($this->request->isGet()) {
            $result[static::$internalModelName] = $this->getModelNodes();
        }
        return $result;
    }

    /**
     * Override this to customize what part of the model gets exposed
     * @return array
     * @throws \ReflectionException
     */
    protected function getModelNodes()
    {
        return $this->getModel()->getNodes();
    }

    /**
     * Get (or create) model object
     * @return null|BaseModel
     * @throws \ReflectionException
     */
    protected function getModel()
    {
        if ($this->modelHandle == null) {
            $this->modelHandle = (new \ReflectionClass(static::$internalModelClass))->newInstance();
        }

        return $this->modelHandle;
    }

    /**
     * Validate and save model after update or insertion.
     * Use the reference node and tag to rename validation output for a specific node to a new offset, which makes
     * it easier to reference specific uuids without having to use them in the frontend descriptions.
     * @param string $node reference node, to use as relative offset
     * @param string $prefix prefix to use when $node is provided (defaults to static::$internalModelName)
     * @return array result / validation output
     * @throws \Phalcon\Validation\Exception on validation issues
     * @throws \ReflectionException when binding to the model class fails
     * @throws UserException when denied write access
     */
    protected function validateAndSave($node = null, $prefix = null)
    {
        $result = $this->validate($node, $prefix);
        if (empty($result['result'])) {
            $result = $this->save();
            if ($node !== null) {
                $attrs = $node->getAttributes();
                if (!empty($attrs['uuid'])) {
                    $result['uuid'] = $attrs['uuid'];
                }
            }
        }
        return $result;
    }

    /**
     * Validate this model
     * @param $node reference node, to use as relative offset
     * @param $prefix prefix to use when $node is provided (defaults to static::$internalModelName)
     * @return array result / validation output
     * @throws \ReflectionException when binding to the model class fails
     */
    protected function validate($node = null, $prefix = null)
    {
        $result = array("result" => "");
        $resultPrefix = empty($prefix) ? static::$internalModelName : $prefix;
        // perform validation
        $valMsgs = $this->getModel()->performValidation();
        foreach ($valMsgs as $field => $msg) {
            if (!array_key_exists("validations", $result)) {
                $result["validations"] = array();
                $result["result"] = "failed";
            }
            // replace absolute path to attribute for relative one at uuid.
            if ($node != null) {
                $fieldnm = str_replace($node->__reference, $resultPrefix, $msg->getField());
            } else {
                $fieldnm = $resultPrefix . "." . $msg->getField();
            }
            $msgText = $msg->getMessage();
            if (empty($result["validations"][$fieldnm])) {
                $result["validations"][$fieldnm] = $msgText;
            } elseif (!is_array($result["validations"][$fieldnm])) {
                // multiple validations, switch to array type output
                $result["validations"][$fieldnm] = array($result["validations"][$fieldnm]);
                if (!in_array($msgText, $result["validations"][$fieldnm])) {
                    $result["validations"][$fieldnm][] = $msgText;
                }
            } elseif (!in_array($msgText, $result["validations"][$fieldnm])) {
                $result["validations"][$fieldnm][] = $msgText;
            }
        }
        return $result;
    }

    /**
     * Save model after update or insertion, validate() first to avoid raising exceptions
     * @return array result / validation output
     * @throws \Phalcon\Validation\Exception on validation issues
     * @throws \ReflectionException when binding to the model class fails
     * @throws \OPNsense\Base\UserException when denied write access
     */
    protected function save()
    {
        if (!(new ACL())->hasPrivilege($this->getUserName(), 'user-config-readonly')) {
            $this->getModel()->serializeToConfig();
            Config::getInstance()->save();
            return array("result" => "saved");
        } else {
            // XXX remove user-config-readonly in some future release
            throw new UserException(
                sprintf("User %s denied for write access (user-config-readonly set)", $this->getUserName())
            );
        }
    }

    /**
     * Hook to be overridden if the controller is to take an action when
     * setAction is called. This hook is called after a model has been
     * constructed and validated but before it serialized to the configuration
     * and written to disk
     * @return string error message on error, or null/void on success
     */
    protected function setActionHook()
    {
    }

    /**
     * Update model settings
     * @return array status / validation errors
     * @throws \Phalcon\Validation\Exception on validation issues
     * @throws \ReflectionException when binding to the model class fails
     * @throws UserException when denied write access
     */
    public function setAction()
    {
        $result = array("result" => "failed");
        if ($this->request->isPost()) {
            // load model and update with provided data
            $mdl = $this->getModel();
            $mdl->setNodes($this->request->getPost(static::$internalModelName));
            $result = $this->validate();
            if (empty($result['result'])) {
                $hookErrorMessage = $this->setActionHook();
                if (!empty($hookErrorMessage)) {
                    $result['error'] = $hookErrorMessage;
                } else {
                    return $this->save();
                }
            }
        }
        return $result;
    }

    /**
     * Model search wrapper
     * @param string $path path to search, relative to this model
     * @param array $fields fieldnames to fetch in result
     * @param string|null $defaultSort default sort field name
     * @param null|function $filter_funct additional filter callable
     * @param int $sort_flags sorting behavior
     * @return array
     * @throws \ReflectionException when binding to the model class fails
     */
    public function searchBase($path, $fields, $defaultSort = null, $filter_funct = null, $sort_flags = SORT_NATURAL)
    {
        $this->sessionClose();
        $element = $this->getModel();
        foreach (explode('.', $path) as $step) {
            $element = $element->{$step};
        }
        $grid = new UIModelGrid($element);
        return $grid->fetchBindRequest(
            $this->request,
            $fields,
            $defaultSort,
            $filter_funct,
            $sort_flags
        );
    }

    /**
     * Model get wrapper, fetches an array item and returns it's contents
     * @param string $key_name result root key
     * @param string $path path to fetch, relative to our model
     * @param null|string $uuid node key
     * @return array
     * @throws \ReflectionException when binding to the model class fails
     */
    public function getBase($key_name, $path, $uuid = null)
    {
        $mdl = $this->getModel();
        if ($uuid != null) {
            $node = $mdl->getNodeByReference($path . '.' . $uuid);
            if ($node != null) {
                // return node
                return array($key_name => $node->getNodes());
            }
        } else {
            foreach (explode('.', $path) as $step) {
                $mdl = $mdl->{$step};
            }
            $node = $mdl->Add();
            return array($key_name => $node->getNodes());
        }
        return array();
    }

    /**
     * Model add wrapper, adds a new item to an array field using a specified post variable
     * @param string $post_field root key to retrieve item content from
     * @param string $path relative model path
     * @param array|null $overlay properties to overlay when available (call setNodes)
     * @return array
     * @throws \Phalcon\Validation\Exception on validation issues
     * @throws \ReflectionException when binding to the model class fails
     * @throws UserException when denied write access
     */
    public function addBase($post_field, $path, $overlay = null)
    {
        $result = array("result" => "failed");
        if ($this->request->isPost() && $this->request->hasPost($post_field)) {
            $mdl = $this->getModel();
            $tmp = $mdl;
            foreach (explode('.', $path) as $step) {
                $tmp = $tmp->{$step};
            }
            $node = $tmp->Add();
            $node->setNodes($this->request->getPost($post_field));
            if (is_array($overlay)) {
                $node->setNodes($overlay);
            }
            $result = $this->validate($node, $post_field);

            if (empty($result['validations'])) {
                // save config if validated correctly
                $this->save();
                $result = array(
                    "result" => "saved",
                    "uuid" => $node->getAttribute('uuid')
                );
            } else {
                $result["result"] = "failed";
            }
        }
        return $result;
    }

    /**
     * Model delete wrapper, removes an item specified by path and uuid
     * @param string $path relative model path
     * @param null|string $uuid node key
     * @return array
     * @throws \Phalcon\Validation\Exception on validation issues
     * @throws \ReflectionException when binding to the model class fails
     * @throws UserException when denied write access
     */
    public function delBase($path, $uuid)
    {
        $result = array("result" => "failed");

        if ($this->request->isPost()) {
            $this->checkAndThrowSafeDelete($uuid);
            Config::getInstance()->lock();
            $mdl = $this->getModel();
            if ($uuid != null) {
                $tmp = $mdl;
                foreach (explode('.', $path) as $step) {
                    $tmp = $tmp->{$step};
                }
                if ($tmp->del($uuid)) {
                    $this->save();
                    $result['result'] = 'deleted';
                } else {
                    $result['result'] = 'not found';
                }
            }
        }
        return $result;
    }

    /**
     * Model setter wrapper, sets the contents of an array item using this requests post variable and path settings
     * @param string $post_field root key to retrieve item content from
     * @param string $path relative model path
     * @param string $uuid node key
     * @param array|null $overlay properties to overlay when available (call setNodes)
     * @return array
     * @throws \Phalcon\Validation\Exception on validation issues
     * @throws \ReflectionException when binding to the model class fails
     * @throws UserException when denied write access
     */
    public function setBase($post_field, $path, $uuid, $overlay = null)
    {
        if ($this->request->isPost() && $this->request->hasPost($post_field)) {
            $mdl = $this->getModel();
            if ($uuid != null) {
                $node = $mdl->getNodeByReference($path . '.' . $uuid);
                if ($node != null) {
                    $node->setNodes($this->request->getPost($post_field));
                    if (is_array($overlay)) {
                        $node->setNodes($overlay);
                    }
                    $result = $this->validate($node, $post_field);
                    if (empty($result['validations'])) {
                        // save config if validated correctly
                        $this->save();
                        $result = array("result" => "saved");
                    } else {
                        $result["result"] = "failed";
                    }
                    return $result;
                }
            }
        }
        return array("result" => "failed");
    }

    /**
     * Generic toggle function, assumes our model item has an enabled boolean type field.
     * @param string $path relative model path
     * @param string $uuid node key
     * @param string $enabled desired state enabled(1)/disabled(0), leave empty for toggle
     * @return array
     * @throws \Phalcon\Validation\Exception on validation issues
     * @throws \ReflectionException when binding to the model class fails
     * @throws UserException when denied write access
     */
    public function toggleBase($path, $uuid, $enabled = null)
    {
        $result = array("result" => "failed");
        if ($this->request->isPost()) {
            $mdl = $this->getModel();
            if ($uuid != null) {
                $node = $mdl->getNodeByReference($path . '.' . $uuid);
                if ($node != null) {
                    $result['changed'] = true;
                    if ($enabled == "0" || $enabled == "1") {
                        $result['result'] = !empty($enabled) ? "Enabled" : "Disabled";
                        $result['changed'] = (string)$node->enabled !== (string)$enabled;
                        $node->enabled = (string)$enabled;
                    } elseif ($enabled !== null) {
                        // failed
                        $result['changed'] = false;
                    } elseif ((string)$node->enabled == "1") {
                        $result['result'] = "Disabled";
                        $node->enabled = "0";
                    } else {
                        $result['result'] = "Enabled";
                        $node->enabled = "1";
                    }
                    // if item has toggled, serialize to config and save
                    if ($result['changed']) {
                        $this->save();
                    }
                }
            }
        }
        return $result;
    }
}
