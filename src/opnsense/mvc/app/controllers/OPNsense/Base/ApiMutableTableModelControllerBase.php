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

/**
 * Class ApiMutableTableModelControllerBase, inherit this class to implement
 * an API that exposes a model for tables.
 * @package OPNsense\Base
 */
abstract class ApiMutableTableModelControllerBase extends ApiMutableModelControllerBase
{
    // FIXME What about validation?

    static protected $modelPathPrefix = '';
    static protected $gridFields = array();
    static protected $gridDefaultSort = null;
    private function getNodes() {
        $ref = static::$modelPathPrefix . static::$internalModelName;
        return $this->getModel()->getNodeByReference($ref);
    }
    private function getNodeByUUID($uuid) {
        $nodes = $this->getNodes();
        return !empty($nodes) ? $nodes->$uuid : null;
    }

    /**
     * retrieve item or return defaults
     * @param $uuid item unique id
     * @return array
     */
    public function getItemAction($uuid = null)
    {
        $mdl = $this->getModel();
        if ($uuid != null) {
            $node = $this->getNodeByUUID($uuid);
            if ($node != null) {
                // return node
                return array(static::$internalModelName => $node->getNodes());
            }
        } else {
            // generate new node, but don't save to disc
            $node = getNodes()->add();
            return array(static::$internalModelName => $node->getNodes());
        }
        return array();
    }

    /**
     * update item with given properties
     * @param $uuid item unique id
     * @return array
     */
    public function setItemAction($uuid)
    {
        if ($this->request->isPost() && $this->request->hasPost(static::$internalModelName)) {
            $mdl = $this->getModel();
            if ($uuid != null) {
                $node = $this->getNodeByUUID($uuid);
                if ($node != null) {
                    $node->setNodes($this->request->getPost(static::$internalModelName));
                    return $this->save($mdl, $node, static::$internalModelName);
                }
            }
        }
        return array("result"=>"failed");
    }

    /**
     * add new item and set with attributes from post
     * @return array
     */
    public function addItemAction()
    {
        $result = array("result"=>"failed");
        if ($this->request->isPost() && $this->request->hasPost(static::$internalModelName)) {
            $mdl = $this->getModel();
            // FIXME Is this correct?
            $node = $this->getNodes()->add();
            $node->setNodes($this->request->getPost(static::$internalModelName));
            return $this->save($mdl, $node, static::$internalModelName);
        }
        return $result;
    }

    /**
     * delete item by uuid
     * @param $uuid item unique id
     * @return array status
     */
    public function delItemAction($uuid)
    {
        $result = array("result"=>"failed");
        if ($this->request->isPost()) {
            $mdl = $this->getModel();
            if ($uuid != null) {
                if (getNodes()->del($uuid)) {
                    // if item is removed, serialize to config and save
                    $mdl->serializeToConfig();
                    Config::getInstance()->save();
                    $result['result'] = 'deleted';
                } else {
                    $result['result'] = 'not found';
                }
            }
        }
        return $result;
    }

    /**
     * toggle item by uuid (enable/disable)
     * @param $uuid item unique id
     * @param $enabled desired state enabled(1)/disabled(1), leave empty for toggle
     * @return array status
     */
    public function toggleItemAction($uuid, $enabled = null)
    {
        $result = array("result" => "failed");
        if ($this->request->isPost()) {
            $mdl = $this->getModel();
            if ($uuid != null) {
                $node = $mdl->getNodeByUUID($uuid);
                if ($node != null) {
                    if ($enabled == "0" || $enabled == "1") {
                        $node->enabled = (string)$enabled;
                    } elseif ($node->enabled->__toString() == "1") {
                        $node->enabled = "0";
                    } else {
                        $node->enabled = "1";
                    }
                    $result['result'] = $node->enabled;
                    // if item has toggled, serialize to config and save
                    $mdl->serializeToConfig();
                    Config::getInstance()->save();
                }
            }
        }
        return $result;
    }

    /**
     * search items
     * @return array
     */
    public function searchItemsAction()
    {
        $this->sessionClose();
        $mdl = $this->getModel();
        $grid = new UIModelGrid($this->getNodes());
        return $grid->fetchBindRequest($this->request, static::$gridFields, static::$gridDefaultSort);
    }
}
