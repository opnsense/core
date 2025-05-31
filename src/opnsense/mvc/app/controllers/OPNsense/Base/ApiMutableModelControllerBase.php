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
     * Message to append to configuration change event
     */
    protected $internalAuditMessage = null;

    /**
     * @var null|BaseModel model object to work on
     */
    private $modelHandle = null;

    /**
     * Message to use on save of this model
     */
    protected function setSaveAuditMessage($msg)
    {
        $this->internalAuditMessage = $msg;
    }

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

    public function isValidUUID($uuid)
    {
        if (
            !is_string($uuid) ||
            preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $uuid) !== 1
        ) {
            return false;
        }

        return true;
    }

    /**
     * check if a specific token is in use, either in a list of options (xxx,yyy) or as exact match
     * @param string $token token to search recursive
     * @param bool $contains exact match or in list
     * @param bool $only_mvc only report (versioned) models
     * @param array $exclude_refs exclude topics (for example the tokens original location)
     * @throws UserException containing additional information
     */
    protected function checkAndThrowValueInUse($token, $contains = true, $only_mvc = true, $exclude_refs = [])
    {
        if ($contains) {
            $xpath = "//text()[contains(.,'{$token}')]";
        } else {
            $xpath = "//*[text() = '{$token}']";
        }
        $usages = [];
        // find uuid's in our config.xml
        foreach (Config::getInstance()->object()->xpath($xpath) as $node) {
            $referring_node = $node->xpath("..")[0];
            $item_path = [$referring_node->getName()];
            // collect path, when it's a model stop at model root
            $parent_node = $referring_node;
            do {
                $parent_node = $parent_node->xpath("..");
                $parent_node = $parent_node != null ? $parent_node[0] : null;
                if ($parent_node != null) {
                    $item_path[] = $parent_node->getName();
                }
            } while ($parent_node != null && !isset($parent_node->attributes()['version']));
            $item_description = "";
            foreach (["description", "descr", "name"] as $key) {
                if (!empty($referring_node->$key)) {
                    $item_description = (string)$referring_node->$key;
                    break;
                }
            }
            $item_path = array_reverse($item_path);
            if ($parent_node == null) {
                /* chop root node when a legacy path */
                unset($item_path[0]);
            }
            $backref = implode(".", $item_path);
            if (
                $parent_node != null &&
                !empty($referring_node->attributes()['uuid']) &&
                !in_array($backref, $exclude_refs)
            ) {
                if ($parent_node != null) {
                    $usages[] = [
                        "reference" =>  $backref . "." .  $referring_node->attributes()['uuid'],
                        "module" => $item_path[0],
                        "description" => $item_description
                    ];
                }
            } elseif (!$only_mvc && !in_array($backref, $exclude_refs)) {
                $usages[] = [
                    "reference" => $backref,
                    "module" => $referring_node->getName(),
                    "description" => $item_description
                ];
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

    /**
     * Check if item can be safely deleted if $internalModelUseSafeDelete is enabled.
     * Throws a user exception when the $uuid seems to be used in some other config section.
     * @param $uuid string uuid to check
     * @throws UserException containing additional information
     */
    private function checkAndThrowSafeDelete($uuid)
    {
        if (static::$internalModelUseSafeDelete) {
            $this->checkAndThrowValueInUse($uuid);
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
        $result = [];
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
     * @throws \OPNsense\Base\ValidationException on validation issues
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
     * @param $node reference node, to use as relative offset, ignore validation issues outside this scope
     * @param $prefix prefix to use when $node is provided (defaults to static::$internalModelName)
     * @param bool $validateFullModel by default we only validate the fields we have changed
     * @return array result / validation output
     * @throws \ReflectionException when binding to the model class fails
     */
    protected function validate($node = null, $prefix = null, $validateFullModel = false)
    {
        $result = ["result" => ""];
        $resultPrefix = empty($prefix) ? static::$internalModelName : $prefix;
        $valMsgs = $this->getModel()->performValidation($validateFullModel);
        foreach ($valMsgs as $field => $msg) {
            if ($node != null) {
                /*
                 * Replace absolute path with attribute for relative one at 'uuid',
                 * ignore validation issues when triggered outside the node scope.
                 */
                if (strpos($msg->getField(), $node->__reference) === false) {
                    continue;
                }
                $fieldnm = str_replace($node->__reference, $resultPrefix, $msg->getField());
            } else {
                $fieldnm = $resultPrefix . "." . $msg->getField();
            }
            if (!array_key_exists("validations", $result)) {
                $result["validations"] = [];
                $result["result"] = "failed";
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
     * @param bool $validateFullModel by default we only validate the fields we have changed
     * @param bool $disable_validation skip validation, be careful to use this!
     * @return array result / validation output
     * @throws \OPNsense\Base\ValidationException on validation issues
     * @throws \ReflectionException when binding to the model class fails
     * @throws \OPNsense\Base\UserException when denied write access
     */
    protected function save($validateFullModel = false, $disable_validation = false)
    {
        if (!(new ACL())->hasPrivilege($this->getUserName(), 'user-config-readonly')) {
            if ($this->getModel()->serializeToConfig($validateFullModel, $disable_validation)) {
                if ($this->internalAuditMessage) {
                    Config::getInstance()->save(['description' => $this->internalAuditMessage]);
                } else {
                    /* default "endpoint made changes" message */
                    Config::getInstance()->save();
                }
            }
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
     * Hook to be overridden if the controller is to take an action when
     * setBase/addBase is called. This hook is called after a model has been
     * constructed and validated but before it serialized to the configuration
     * and written to disk
     * @throws UserException when action is not possible (and save should be aborted)
     */
    protected function setBaseHook($node)
    {
    }

    /**
     * Update model settings
     * @return array status / validation errors
     * @throws \OPNsense\Base\ValidationException on validation issues
     * @throws \ReflectionException when binding to the model class fails
     * @throws UserException when denied write access
     */
    public function setAction()
    {
        $result = ['result' => 'failed'];
        if ($this->request->isPost()) {
            Config::getInstance()->lock();
            // load model and update with provided data
            $mdl = $this->getModel();
            $mdl->setNodes($this->request->getPost(static::$internalModelName));
            $result = $this->validate();
            if (empty($result['result'])) {
                $hookErrorMessage = $this->setActionHook();
                if (!empty($hookErrorMessage)) {
                    $result['error'] = $hookErrorMessage;
                } else {
                    return $this->save(false, true);
                }
            }
        }
        return $result;
    }

    /**
     * Model search wrapper
     * @param string $path path to search, relative to this model
     * @param array|null $fields fieldnames to fetch in result, defaults to all fields
     * @param string|null $defaultSort default sort field name
     * @param null|function $filter_funct additional filter callable
     * @param int $sort_flags sorting behavior
     * @return array
     * @throws \ReflectionException when binding to the model class fails
     */
    public function searchBase(
        $path,
        $fields = null,
        $defaultSort = null,
        $filter_funct = null,
        $sort_flags = SORT_NATURAL | SORT_FLAG_CASE
    ) {
        $element = $this->getModel();
        foreach (explode('.', $path) as $step) {
            $element = $element->{$step};
        }

        if (
            empty($fields) && (
            is_a($element, "OPNsense\\Base\\FieldTypes\\ArrayField") ||
            is_subclass_of($element, "OPNsense\\Base\\FieldTypes\\ArrayField")
            )
        ) {
            $fields = [];
            foreach ($element->iterateItems() as $node) {
                foreach ($node->iterateItems() as $key => $value) {
                    $fields[] = $key;
                }
                break;
            }
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
        return [];
    }

    /**
     * Model add wrapper, adds a new item to an array field using a specified post variable
     * @param string $post_field root key to retrieve item content from
     * @param string $path relative model path
     * @param array|null $overlay properties to overlay when available (call setNodes)
     * @return array
     * @throws \OPNsense\Base\ValidationException on validation issues
     * @throws \ReflectionException when binding to the model class fails
     * @throws UserException when denied write access
     */
    public function addBase($post_field, $path, $overlay = null)
    {
        $result = ['result' => 'failed'];
        if ($this->request->isPost() && $this->request->hasPost($post_field)) {
            Config::getInstance()->lock();
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
                $this->setBaseHook($node);
                // save config if validated correctly
                $this->save(false, true);
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
     * @throws \OPNsense\Base\ValidationException on validation issues
     * @throws \ReflectionException when binding to the model class fails
     * @throws UserException when denied write access
     */
    public function delBase($path, $uuid)
    {
        $result = ['result' => 'failed'];

        if ($this->request->isPost()) {
            Config::getInstance()->lock();
            $this->checkAndThrowSafeDelete($uuid);
            $mdl = $this->getModel();
            if ($uuid != null) {
                $tmp = $mdl;
                foreach (explode('.', $path) as $step) {
                    $tmp = $tmp->{$step};
                }
                if ($tmp->del($uuid)) {
                    $this->save(false, true);
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
     * @throws \OPNsense\Base\ValidationException on validation issues
     * @throws \ReflectionException when binding to the model class fails
     * @throws UserException when denied write access
     */
    public function setBase($post_field, $path, $uuid, $overlay = null)
    {
        if ($this->request->isPost() && $this->request->hasPost($post_field) && $uuid != null) {
            Config::getInstance()->lock();
            $mdl = $this->getModel();
            $node = $mdl->getNodeByReference($path . '.' . $uuid);
            if ($node == null) {
                if (!$this->isValidUUID($uuid)) {
                    // invalid uuid, upsert not allowed
                    return ["result" => "failed"];
                }
                // set is an "upsert" operation, if we don't know the uuid, it's ok to create it.
                // this eases scriptable actions where a single unique entry should be pushed atomically to
                // multiple hosts.
                $node = $mdl->getNodeByReference($path);
                if ($node != null && $node->isArrayType()) {
                    $node = $node->Add();
                    $node->setAttributeValue("uuid", $uuid);
                }
            }
            if ($node != null) {
                $node->setNodes($this->request->getPost($post_field));
                if (is_array($overlay)) {
                    $node->setNodes($overlay);
                }
                $result = $this->validate($node, $post_field, true);
                if (empty($result['validations'])) {
                    $this->setBaseHook($node);
                    // save config if validated correctly
                    $this->save(false, true);
                    $result = ["result" => "saved"];
                } else {
                    $result["result"] = "failed";
                }
                return $result;
            }
        }
        return ["result" => "failed"];
    }

    /**
     * Generic toggle function, assumes our model item has an enabled boolean type field.
     * @param string $path relative model path
     * @param string $uuid node key
     * @param string $enabled desired state enabled(1)/disabled(0), leave empty for toggle
     * @return array
     * @throws \OPNsense\Base\ValidationException on validation issues
     * @throws \ReflectionException when binding to the model class fails
     * @throws UserException when denied write access
     */
    public function toggleBase($path, $uuid, $enabled = null)
    {
        $result = ['result' => 'failed'];
        if ($this->request->isPost()) {
            Config::getInstance()->lock();
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
                        $this->save(false, true);
                    }
                }
            }
        }
        return $result;
    }

    /**
     * Import csv data into ArrayField type
     * @param string $path model path, should point to an ArrayField type
     * @param string $payload csv data to import
     * @param array $keyfields fieldnames to use as key
     * @param function $data_callback inline data modification, used to match parsed csv data
     * @param function $node_callback to be called when the array node has been setup
     * @return array exceptions
     */
    protected function importCsv($path, $payload, $keyfields = [], $data_callback = null, $node_callback = null)
    {
        Config::getInstance()->lock();
        /* parse csv data to array structure */
        $data = [];
        $stream = fopen('php://temp', 'rw+');
        fwrite($stream, $payload);
        fseek($stream, 0);
        $heading = [];
        while (($line = fgetcsv($stream)) !== false) {
            if (empty($heading)) {
                $heading = $line;
            } else {
                $record = [];
                foreach ($line as $idx => $content) {
                    if (isset($heading[$idx])) {
                        $record[$heading[$idx]] = $content;
                    }
                }
                $data[] = $record;
            }
        }
        fclose($stream);

        /*
         * Try to import the offered data, when errors occur remove records and try again.
         * Always return validation items collected in the first run.
         **/
        $response = [];
        for ($i = 0; $i < 2; $i++) {
            $mdl = $this->getModel();
            $node = $mdl->getNodeByReference($path);
            if (
                is_a($node, "OPNsense\\Base\\FieldTypes\\ArrayField") ||
                is_subclass_of($node, "OPNsense\\Base\\FieldTypes\\ArrayField")
            ) {
                $result = $node->importRecordSet($data, $keyfields, $data_callback, $node_callback);
                $valmsgfields = [];
                foreach ($this->getModel()->performValidation() as $msg) {
                    if (str_starts_with($msg->getField(), $path) && !in_array($msg->getField(), $valmsgfields)) {
                        $tmp = explode('.', substr($msg->getField(), strlen($path) + 1));
                        $uuid = $tmp[0];
                        $fieldname = end($tmp);
                        $result['validations'][] = [
                            'sequence' => $result['uuids'][$uuid] ?? null,
                            'message' =>  $msg->getMessage(),
                            'field' => $fieldname
                        ];
                        $valmsgfields[] = $msg->getField();
                    }
                }
                // save first validation result
                $response = empty($response) ? $result : $response;
                // remove invalid records and reinitialize model (so next pass can update)
                if (!empty($result['validations'])) {
                    $this->modelHandle = null;
                    $error_keys = [];
                    foreach ($result['validations'] as $val) {
                        if ($val['sequence'] !== null) {
                            $error_keys[] = $val['sequence'];
                        }
                    }
                    foreach (array_reverse(array_unique($error_keys, SORT_NUMERIC)) as $err_key) {
                        unset($data[$err_key]);
                    }
                } else {
                    // save to config when there's anything left
                    if ($result['inserted'] > 0 || $result['updated'] > 0) {
                        $this->save(false, true);
                    }
                    break;
                }
            }
        }
        return $response + ['fieldnames' => $heading];
    }
}
