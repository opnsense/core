<?php
/**
 *    Copyright (C) 2016 IT-assistans Sverige AB
 *    Copyright (C) 2016 Deciso B.V.
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

use \OPNsense\Core\Config;

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
    static protected $internalModelName = null;

    /**
     * @var string model class name to use
     */
    static protected $internalModelClass = null;

    /**
     * @var null|BaseModel model object to work on
     */
    private $modelHandle = null;

    /**
     * validate on initialization
     * @throws Exception
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
     * retrieve model settings
     * @return array settings
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
     * override this to customize what part of the model gets exposed
     * @return array
     */
    protected function getModelNodes()
    {
        return $this->getModel()->getNodes();
    }

    /**
     * @return null|BaseModel
     */
    protected function getModel()
    {
        if ($this->modelHandle == null) {
            $this->modelHandle = (new \ReflectionClass(static::$internalModelClass))->newInstance();
        }

        return $this->modelHandle;
    }

    /**
     * validate and save model after update or insertion.
     * Use the reference node and tag to rename validation output for a specific node to a new offset, which makes
     * it easier to reference specific uuids without having to use them in the frontend descriptions.
     * @param $node reference node, to use as relative offset
     * @return array result / validation output
     */
    protected function validateAndSave($node = null)
    {
        $result = $this->validate();
        if (empty($result['result'])) {
            return $this->save();
        }
        return $result;
    }

    /**
     * validate this model
     * @param $node reference node, to use as relative offset
     * @return array result / validation output
     */
    protected function validate($node = null)
    {
        $result = array("result"=>"");
        // perform validation
        $valMsgs = $this->getModel()->performValidation();
        foreach ($valMsgs as $field => $msg) {
            if (!array_key_exists("validations", $result)) {
                $result["validations"] = array();
                $result["result"] = "failed";
            }
            // replace absolute path to attribute for relative one at uuid.
            if ($node != null) {
                $fieldnm = str_replace($node->__reference, static::$internalModelName, $msg->getField());
                $result["validations"][$fieldnm] = $msg->getMessage();
            } else {
                $result["validations"][static::$internalModelName.".".$msg->getField()] = $msg->getMessage();
            }
        }
        return $result;
    }

    /**
     * save model after update or insertion, validate() first to avoid raising exceptions
     * @return array result / validation output
     */
    protected function save()
    {
        $this->getModel()->serializeToConfig();
        Config::getInstance()->save();
        return array("result"=>"saved");
    }

    /**
     * hook to be overridden if the controller is to take an action when
     * setAction is called. This hook is called after a model has been
     * constructed and validated but before it serialized to the configuration
     * and written to disk
     * @param $mdl The validated model containing the new state of the model
     * @return Error message on error, or null/void on success
     */
    protected function setActionHook()
    {
    }

    /**
     * update model settings
     * @return array status / validation errors
     */
    public function setAction()
    {
        $result = array("result"=>"failed");
        if ($this->request->isPost()) {
            // load model and update with provided data
            $mdl = $this->getModel();
            $mdl->setNodes($this->request->getPost(static::$internalModelName));
            $result = $this->validate();
            if (empty($result['result'])) {
                $errorMessage = $this->setActionHook();
                if (!empty($errorMessage)) {
                    $result['error'] = $errorMessage;
                } else {
                    return $this->save();
                }
            }
        }
        return $result;
    }
}
