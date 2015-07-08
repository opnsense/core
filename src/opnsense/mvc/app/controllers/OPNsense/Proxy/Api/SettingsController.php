<?php
/**
 *    Copyright (C) 2015 J. Schellevis - Deciso B.V.
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
namespace OPNsense\Proxy\Api;

use \OPNsense\Base\ApiControllerBase;
use \OPNsense\Proxy\Proxy;
use \OPNsense\Cron\Cron;
use \OPNsense\Core\Config;
use \OPNsense\Base\UIModelGrid;

/**
 * Class SettingsController
 * @package OPNsense\Proxy
 */
class SettingsController extends ApiControllerBase
{
    /**
     * retrieve proxy settings
     * @return array
     */
    public function getAction()
    {
        $result = array();
        if ($this->request->isGet()) {
            $mdlProxy = new Proxy();
            $result['proxy'] = $mdlProxy->getNodes();
        }

        return $result;
    }


    /**
     *
     * @return array
     * @throws \Phalcon\Validation\Exception
     */
    public function setAction()
    {
        $result = array("result"=>"failed");
        if ($this->request->hasPost("proxy")) {
            // load model and update with provided data
            $mdlProxy = new Proxy();
            $mdlProxy->setNodes($this->request->getPost("proxy"));

            // perform validation
            $valMsgs = $mdlProxy->performValidation();
            foreach ($valMsgs as $field => $msg) {
                if (!array_key_exists("validations", $result)) {
                    $result["validations"] = array();
                }
                $result["validations"]["proxy.".$msg->getField()] = $msg->getMessage();
            }

            // serialize model to config and save
            if ($valMsgs->count() == 0) {
                $mdlProxy->serializeToConfig();
                $cnf = Config::getInstance();
                $cnf->save();
                $result["result"] = "saved";
            }

        }

        return $result;

    }

    /**
     *
     * search remote blacklists
     * @return array
     */
    public function searchRemoteBlacklistsAction()
    {
        $this->sessionClose();
        // fetch query parameters
        $itemsPerPage = $this->request->getPost('rowCount', 'int', 9999);
        $currentPage = $this->request->getPost('current', 'int', 1);
        $sortBy = array("filename");
        $sortDescending = false;

        if ($this->request->hasPost('sort') && is_array($this->request->getPost("sort"))) {
            $sortBy = array_keys($this->request->getPost("sort"));
            if ($this->request->getPost("sort")[$sortBy[0]] == "desc") {
                $sortDescending = true;
            }
        }

        $searchPhrase = $this->request->getPost('searchPhrase', 'string', '');

        // create model and fetch query resuls
        $fields = array(
            "enabled",
            "filename",
            "url",
            "description"
        );
        $mdlProxy = new Proxy();
        $grid = new UIModelGrid($mdlProxy->forward->acl->remoteACLs->blacklists->blacklist);

        return $grid->fetch($fields, $itemsPerPage, $currentPage, $sortBy, $sortDescending, $searchPhrase);
    }

    /**
     * retrieve remote blacklist settings or return defaults
     * @param $uuid item unique id
     * @return array
     */
    public function getRemoteBlacklistAction($uuid = null)
    {
        $mdlProxy = new Proxy();
        if ($uuid != null) {
            $node = $mdlProxy->getNodeByReference('forward.acl.remoteACLs.blacklists.blacklist.' . $uuid);
            if ($node != null) {
                // return node
                return array("blacklist" => $node->getNodes());
            }
        } else {
            // generate new node, but don't save to disc
            $node = $mdlProxy->forward->acl->remoteACLs->blacklists->blacklist->add();
            return array("blacklist" => $node->getNodes());
        }
        return array();
    }


    public function setRemoteBlacklistAction($uuid)
    {
        if ($this->request->isPost() && $this->request->hasPost("blacklist")) {
            $mdlProxy = new Proxy();
            if ($uuid != null) {
                $node = $mdlProxy->getNodeByReference('forward.acl.remoteACLs.blacklists.blacklist.' . $uuid);
                if ($node != null) {
                    $result = array("result" => "failed", "validations" => array());
                    $blacklistInfo = $this->request->getPost("blacklist");

                    $node->setNodes($blacklistInfo);
                    $valMsgs = $mdlProxy->performValidation();
                    foreach ($valMsgs as $field => $msg) {
                        $fieldnm = str_replace($node->__reference, "blacklist", $msg->getField());
                        $result["validations"][$fieldnm] = $msg->getMessage();
                    }

                    if (count($result['validations']) == 0) {
                        // save config if validated correctly
                        $mdlProxy->serializeToConfig();
                        Config::getInstance()->save();
                        $result = array("result" => "saved");
                    }
                    return $result;
                }
            }
        }
        return array("result" => "failed");
    }

    /**
     * add new blacklist and set with attributes from post
     * @return array
     */
    public function addRemoteBlacklistAction()
    {
        $result = array("result" => "failed");
        if ($this->request->isPost() && $this->request->hasPost("blacklist")) {
            $result = array("result" => "failed", "validations" => array());
            $mdlProxy = new Proxy();
            $node = $mdlProxy->forward->acl->remoteACLs->blacklists->blacklist->Add();
            $node->setNodes($this->request->getPost("blacklist"));
            $valMsgs = $mdlProxy->performValidation();

            foreach ($valMsgs as $field => $msg) {
                $fieldnm = str_replace($node->__reference, "blacklist", $msg->getField());
                $result["validations"][$fieldnm] = $msg->getMessage();
            }

            if (count($result['validations']) == 0) {
                // save config if validated correctly
                $mdlProxy->serializeToConfig();
                Config::getInstance()->save();
                $result = array("result" => "saved");
            }
            return $result;
        }
        return $result;
    }

    /**
     * delete blacklist by uuid
     * @param $uuid item unique id
     * @return array status
     */
    public function delRemoteBlacklistAction($uuid)
    {

        $result = array("result" => "failed");

        if ($this->request->isPost()) {
            $mdlProxy = new Proxy();
            if ($uuid != null) {
                if ($mdlProxy->forward->acl->remoteACLs->blacklists->blacklist->del($uuid)) {
                    // if item is removed, serialize to config and save
                    $mdlProxy->serializeToConfig();
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
     * toggle blacklist by uuid (enable/disable)
     * @param $uuid item unique id
     * @return array status
     */
    public function toggleRemoteBlacklistAction($uuid)
    {

        $result = array("result" => "failed");

        if ($this->request->isPost()) {
            $mdlProxy = new Proxy();
            if ($uuid != null) {
                $node = $mdlProxy->getNodeByReference('forward.acl.remoteACLs.blacklists.blacklist.' . $uuid);
                if ($node != null) {
                    if ($node->enabled->__toString() == "1") {
                        $result['result'] = "Disabled";
                        $node->enabled = "0";
                    } else {
                        $result['result'] = "Enabled";
                        $node->enabled = "1";
                    }
                    // if item has toggled, serialize to config and save
                    $mdlProxy->serializeToConfig();
                    Config::getInstance()->save();
                }
            }
        }
        return $result;
    }

    /**
     * create new cron item for remote acl or return already available one
     * @return array status action
     */
    public function fetchRBCronAction()
    {
        $result = array("result" => "failed");

        if ($this->request->isPost()) {
            $mdlProxy = new Proxy();
            if ((string)$mdlProxy->forward->acl->remoteACLs->UpdateCron == "") {
                $mdlCron = new Cron();
                // update cron relation (if this doesn't break consistency)
                $uuid = $mdlCron->newDailyJob("Proxy", "proxy fetchacls", "fetch proxy acls", "1");
                $mdlProxy->forward->acl->remoteACLs->UpdateCron = $uuid;

                if ($mdlCron->performValidation()->count() == 0) {
                    $mdlCron->serializeToConfig();
                    // save data to config, do not validate because the current in memory model doesn't know about the
                    // cron item just created.
                    $mdlProxy->serializeToConfig($validateFullModel = false, $disable_validation = true);
                    Config::getInstance()->save();
                    $result['result'] = "new";
                    $result['uuid'] = $uuid;
                } else {
                    $result['result'] = "unable to add cron";
                }
            } else {
                $result['result'] = "existing";
                $result['uuid'] = (string)$mdlProxy->forward->acl->remoteACLs->UpdateCron;
            }
        }

        return $result;
    }
}
