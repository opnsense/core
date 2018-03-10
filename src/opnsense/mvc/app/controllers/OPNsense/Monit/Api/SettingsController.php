<?php

/**
 *    Copyright (C) 2017-2018 EURO-LOG AG
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

namespace OPNsense\Monit\Api;

use \OPNsense\Base\ApiControllerBase;
use \OPNsense\Core\Config;
use \OPNsense\Monit\Monit;
use \OPNsense\Base\UIModelGrid;

/**
 * Class SettingsController
 * @package OPNsense\Monit
 */
class SettingsController extends ApiControllerBase
{

    /**
     * list with valid model node types
     */
    private $nodeTypes = array('general', 'alert', 'service', 'test');

    /**
     * query monit settings
     * @param $nodeType
     * @param $uuid
     * @return result array
     */
    public function getAction($nodeType = null, $uuid = null)
    {
        $result = array("result" => "failed");
        if ($this->request->isGet() && $nodeType != null) {
            $this->validateNodeType($nodeType);
            $mdlMonit = new Monit();
            if ($nodeType == 'general') {
                $node = $mdlMonit->getNodeByReference($nodeType);
            } else {
                if ($uuid != null) {
                    $node = $mdlMonit->getNodeByReference($nodeType . '.' . $uuid);
                } else {
                    $node = $mdlMonit->$nodeType->Add();
                }
            }
            if ($node != null) {
                $result[$nodeType] = $node->getNodes();
                $result["result"] = "ok";
            }
        }
        return $result;
    }

    /**
     * set monit properties
     * @param $nodeType
     * @param $uuid
     * @return status array
     */
    public function setAction($nodeType = null, $uuid = null)
    {
        $result = array("result" => "failed", "validations" => array());
        if ($this->request->isPost() && $this->request->hasPost("monit") && $nodeType != null) {
            $this->validateNodeType($nodeType);
            $mdlMonit = new Monit();
            if ($nodeType == 'general') {
                $node = $mdlMonit->getNodeByReference($nodeType);
            } else {
                if ($uuid != null) {
                    $node = $mdlMonit->getNodeByReference($nodeType . '.' . $uuid);
                } else {
                    $node = $mdlMonit->$nodeType->Add();
                }
            }
            if ($node != null) {
                $monitInfo = $this->request->getPost("monit");

                // perform plugin specific validations
                if ($nodeType == 'service') {
                    switch ($monitInfo[$nodeType]['type']) {
                        case 'process':
                            if (empty($monitInfo[$nodeType]['pidfile']) && empty($monitInfo[$nodeType]['match'])) {
                                $result["validations"]['monit.service.pidfile'] = "Please set at least one of Pidfile or Match.";
                                $result["validations"]['monit.service.match'] = $result["validations"]['monit.service.pidfile'];
                            }
                            break;
                        case 'host':
                            if (empty($monitInfo[$nodeType]['address'])) {
                                $result["validations"]['monit.service.address'] = "Address is mandatory for 'Remote Host' checks.";
                            }
                            break;
                        case 'network':
                            if (empty($monitInfo[$nodeType]['address']) && empty($monitInfo[$nodeType]['interface'])) {
                                $result["validations"]['monit.service.address'] = "Please set at least one of Address or Interface.";
                                $result["validations"]['monit.service.interface'] = $result["validations"]['monit.service.address'];
                            }
                            break;
                        case 'system':
                            break;
                        default:
                            if (empty($monitInfo[$nodeType]['path'])) {
                                $result["validations"]['monit.service.path'] = "Path is mandatory.";
                            }
                    }
                }

                $node->setNodes($monitInfo[$nodeType]);
                $valMsgs = $mdlMonit->performValidation();
                foreach ($valMsgs as $field => $msg) {
                    $fieldnm = str_replace($node->__reference, "monit." . $nodeType, $msg->getField());
                    $result["validations"][$fieldnm] = $msg->getMessage();
                }
                if (empty($result["validations"])) {
                    unset($result["validations"]);
                    $result['result'] = 'ok';
                    $mdlMonit->serializeToConfig();
                    Config::getInstance()->save();
                    if ($nodeType == 'general' && $monitInfo['general']['enabled'] == '0') {
                        $svcMonit = new ServiceController();
                        $result = $svcMonit->stopAction();
                    }
                }
            }
        }
        return $result;
    }

    /**
     * delete monit settings
     * @param $nodeType
     * @param $uuid
     * @return status array
     */
    public function delAction($nodeType = null, $uuid = null)
    {
        $result = array("result" => "failed");
        if ($nodeType != null) {
            $this->validateNodeType($nodeType);
            if ($uuid != null) {
                $mdlMonit = new Monit();
                $node = $mdlMonit->getNodeByReference($nodeType . '.' . $uuid);
                if ($node != null) {
                    if ($mdlMonit->$nodeType->del($uuid) == true) {
                        // delete relations
                        if ($nodeType == 'test') {
                            $nodeName = $mdlMonit->getNodeByReference($nodeType . '.' . $uuid . '.name');
                            if ($nodeName != null) {
                                $nodeName = $nodeName->__toString();
                                $this->deleteRelations('service', 'tests', $uuid, 'test', $nodeName, $mdlMonit);
                            }
                        }
                        $mdlMonit->serializeToConfig();
                        Config::getInstance()->save();
                        $result["result"] = "ok";
                    }
                }
            }
        }
        return $result;
    }

    /**
     * toggle monit items (enable/disable)
     * @param $nodeType
     * @param $uuid
     * @return result array
     */
    public function toggleAction($nodeType = null, $uuid = null)
    {
        $result = array("result" => "failed");
        if ($this->request->isPost() && $nodeType != null) {
            $mdlMonit = new Monit();
            if ($uuid != null) {
                $node = $mdlMonit->getNodeByReference($nodeType . '.' . $uuid);
                if ($node != null) {
                    if ($node->enabled->__toString() == "1") {
                        $node->enabled = "0";
                    } else {
                        $node->enabled = "1";
                    }
                    $mdlMonit->serializeToConfig();
                    Config::getInstance()->save();
                    $svcMonit = new ServiceController();
                    $result= $svcMonit->reloadAction();
                } else {
                    $result['result'] = "not found";
                }
            } else {
                $result['result'] = "uuid not given";
            }
        }
        return $result;
    }

    /**
     * search monit settings
     * @param $nodeType
     * @return result array
     */
    public function searchAction($nodeType = null)
    {
        $this->sessionClose();
        if ($this->request->isPost() && $nodeType != null) {
            $this->validateNodeType($nodeType);
            $mdlMonit = new Monit();
            $grid = new UIModelGrid($mdlMonit->$nodeType);
            $fields = array();
            switch ($nodeType) {
                case 'alert':
                    $fields = array("enabled", "recipient", "noton", "events", "description");
                    break;
                case 'service':
                    $fields = array("enabled", "name", "type", "description");
                    break;
                case 'test':
                    $fields = array("name", "condition", "action");
                    break;
            }
            return $grid->fetchBindRequest($this->request, $fields);
        }
    }

    /**
     * import system notification settings
     * @return result array
     */
    public function notificationAction()
    {
        $result = array("result" => "failed");
        if ($this->request->isPost()) {
            $this->sessionClose();

            $cfg = Config::getInstance();
            $cfgObj = $cfg->object();
            $mdlMonit = new Monit();
            $node = $mdlMonit->getNodeByReference('general');
            $generalSettings = array();

            // inherit SMTP settings from System->Settings->Notifications
            if (!empty($cfgObj->notifications->smtp->ipaddress)) {
                $generalSettings['mailserver'] = $cfgObj->notifications->smtp->ipaddress;
            }
            if (!empty($cfgObj->notifications->smtp->port)) {
                $generalSettings['port'] = $cfgObj->notifications->smtp->port;
            }
            $generalSettings['username'] = $cfgObj->notifications->smtp->username;
            $generalSettings['password'] = $cfgObj->notifications->smtp->password;
            if ((!empty($cfgObj->notifications->smtp->tls) && $cfgObj->notifications->smtp->tls == 1)  ||
                (!empty($cfgObj->notifications->smtp->ssl) && $cfgObj->notifications->smtp->ssl == 1)) {
                $generalSettings['ssl'] = 1;
            } else {
                $generalSettings['ssl'] = 0;
            }

            // apply them
            $node->setNodes($generalSettings);
            $valMsgs = $mdlMonit->performValidation();
            foreach ($valMsgs as $field => $msg) {
                $fieldnm = str_replace($node->__reference, "monit.general.", $msg->getField());
                $result["validations"][$fieldnm] = $msg->getMessage();
            }
            if (empty($result["validations"])) {
                unset($result["validations"]);
                $result['result'] = 'ok';
                $mdlMonit->serializeToConfig();
                Config::getInstance()->save();
            }
        }
        return $result;
    }

    /**
     * validate nodeType
     * @param $nodeType
     * @throws \Exception
     */
    private function validateNodeType($nodeType = null)
    {
        if (array_search($nodeType, $this->nodeTypes) === false) {
            throw new \Exception('unknown nodeType: ' . $nodeType);
        }
    }

    /**
     * delete relations
     * @param $nodeType
     * @param $uuid
     * @param $relNodeType
     * @param &$mdlMonit
     * @throws \Exception
     */
    private function deleteRelations($nodeType = null, $nodeField = null, $relUuid = null, $relNodeType = null, $relNodeName = null, &$mdlMonit = null)
    {
        $nodes = $mdlMonit->$nodeType->getNodes();
        // get nodes with relations
        foreach ($nodes as $nodeUuid => $node) {
            // get relation uuids
            foreach ($node[$nodeField] as $fieldUuid => $field) {
                // remove uuid from field
                if ($fieldUuid == $relUuid) {
                    $refField = $nodeType . '.' . $nodeUuid . '.' . $nodeField;
                    $relNode = $mdlMonit->getNodeByReference($refField);
                    $nodeRels = str_replace($relUuid, '', $relNode->__toString());
                    $nodeRels = str_replace(',,', ',', $nodeRels);
                    $nodeRels = rtrim($nodeRels, ',');
                    $nodeRels = ltrim($nodeRels, ',');
                    $mdlMonit->setNodeByReference($refField, $nodeRels);
                    if ($relNode->isEmptyAndRequired()) {
                        $nodeName = $mdlMonit->getNodeByReference($nodeType . '.' . $nodeUuid . '.name')->__toString();
                        throw new \Exception("Cannot delete $relNodeType '$relNodeName' from $nodeType '$nodeName'");
                    }
                }
            }
        }
    }
}
