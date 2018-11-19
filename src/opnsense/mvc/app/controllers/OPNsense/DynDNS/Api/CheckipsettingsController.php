<?php

/**
 *    Copyright (C) 2015 Deciso B.V.
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
//namespace OPNsense\CheckIP\Api;
namespace OPNsense\DynDNS\Api;

use \OPNsense\Base\ApiMutableModelControllerBase;
use \OPNsense\Core\Config;
use \OPNsense\DynDNS\CheckIP;
use \OPNsense\Base\UIModelGrid;

/**
 * Class SettingsController
 * @package OPNsense\CheckIP
 */
class CheckIPSettingsController extends ApiMutableModelControllerBase
{

//    static protected $internalModelName = 'CheckIP';
//    static protected $internalModelClass = '\OPNsense\CheckIP\Service';
    static protected $internalModelName = 'DynDNS';
    static protected $internalModelClass = '\OPNsense\DynDNS\Service';

    /**
     * list with valid model node types
     */
    private $nodeTypes = array('service');

    /**
     * query CheckIP settings
     * @param $nodeType
     * @param $uuid
     * @return result array
     */
    public function getAction($nodeType = null, $uuid = null)
    {
        $result = array("result" => "failed");
        if ($this->request->isGet() && $nodeType != null) {
            $this->validateNodeType($nodeType);
//            $mdlCheckIPService = new Service();
            $mdlCheckIPService = new CheckIP();
            if ($uuid != null) {
                $node = $mdlCheckIPService->getNodeByReference($nodeType . '.' . $uuid);
            } else {
                $node = $mdlCheckIPService->$nodeType->Add();
            }
            if ($node != null) {
                $result['checkip'] = array($nodeType => $node->getNodes());
                $result['result'] = 'ok';
            }
        }
        return $result;
    }

    /**
     * set CheckIP properties
     * @param $nodeType
     * @param $uuid
     * @return status array
     */
    public function setAction($nodeType = null, $uuid = null)
    {
        $result = array("result" => "failed", "validations" => array());
        if ($this->request->isPost() && $this->request->hasPost("checkip") && $nodeType != null) {
            $this->validateNodeType($nodeType);
//            $mdlCheckIPService = new Service();
            $mdlCheckIPService = new CheckIP();
            if ($uuid != null) {
                $node = $mdlCheckIPService->getNodeByReference($nodeType . '.' . $uuid);
            } else {
                $node = $mdlCheckIPService->$nodeType->Add();
            }
            if ($node != null) {
                $checkIPInfo = $this->request->getPost("checkip");

                $node->setNodes($checkIPInfo[$nodeType]);
                $valMsgs = $mdlCheckIPService->performValidation();
                foreach ($valMsgs as $field => $msg) {
                    $fieldnm = str_replace($node->__reference, "checkip." . $nodeType, $msg->getField());
                    $result["validations"][$fieldnm] = $msg->getMessage();
                }
                if (empty($result["validations"])) {
                    unset($result["validations"]);
                    $result['result'] = 'ok';

                    // If enabling this node, disable all others.  Only one service is to be enabled as the default.
                    if ($node->default->__toString() == "1") {
                        $this->disableAll($mdlCheckIPService, $nodeType);  // first disable all
                        $node->default = "1";
                    }

                    $mdlCheckIPService->serializeToConfig();
                    Config::getInstance()->save();
                }
            }
        }
        return $result;
    }

    /**
     * delete CheckIP settings
     * @param $nodeType
     * @param $uuid
     * @return status array
     */
    public function delAction($nodeType = null, $uuid = null)
    {
        return $this->delBase('service', $uuid);
    }

    /**
     * toggle CheckIP items (enable/disable)
     * @param $nodeType
     * @param $uuid
     * @return result array
     */
    public function toggleAction($nodeType = null, $uuid = null)
    {
        $result = array("result" => "failed");
        if ($this->request->isPost() && $nodeType != null) {
//            $mdlCheckIPService = new Service();
            $mdlCheckIPService = new CheckIP();
            if ($uuid != null) {

                $node = $mdlCheckIPService->getNodeByReference($nodeType . '.' . $uuid);

                if ($node != null) {    // toggle the service
                    $default = $node->default->__toString();
                    $this->disableAll($mdlCheckIPService, $nodeType);  // first disable all
                    if (!$default) {    // toggle to default i.e. enabled state
                        $node->default = "1"; 
                    }
                } elseif ($uuid == 'FDS') {    // toggle the factory default service (inverted logic)
                    $default = $mdlCheckIPService->disable_factory_default_service->__toString();
                    $this->disableAll($mdlCheckIPService, $nodeType);  // first disable all
                    if ($default) {    // toggle to default i.e. enabled state
                        $mdlCheckIPService->disable_factory_default_service = "0";
                    }
                } else {
                    $result['result'] = "not found";
                }

                if ($result['result'] != 'not found') {
                    $mdlCheckIPService->serializeToConfig();
                    Config::getInstance()->save();
                    $result["result"] = "ok";
                }

            } else {
                $result['result'] = "uuid not given";
            }
        }
        return $result;
    }

    /**
     * search CheckIP settings
     * @param $nodeType
     * @return result array
     */
    public function searchAction($nodeType = null)
    {
        $this->sessionClose();
        if ($this->request->isPost() && $nodeType != null) {
            $this->validateNodeType($nodeType);
//            $mdlCheckIPService = new Service();
            $mdlCheckIPService = new CheckIP();

            $grid = new UIModelGrid($mdlCheckIPService->$nodeType);
            $fields = array("default", "name", "url", "regex", "username", "password", "verifysslpeer", "description");
            $grid = $grid->fetchBindRequest($this->request, $fields);

            // Include the factory default check IP service.
            $grid = $this->includeFDS($mdlCheckIPService, $grid);

            return $grid;
        }
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
     * is_default (is at least one CheckIP service enabled as the default; FDS inclusive)
     * @return result array
     */
    public function is_defaultAction($nodeType = null)
    {
        $is_default = false;
        if ($nodeType != null) {
//            $mdlCheckIPService = new Service();
            $mdlCheckIPService = new CheckIP();

            $nodes = $mdlCheckIPService->$nodeType->getNodes();
            foreach ($nodes as $nodeUuid => $node) {
                $node = $mdlCheckIPService->getNodeByReference($nodeType . '.' . $nodeUuid);
                $is_default = $node->default->__toString() == "1" ? true : $is_default;
            }
            $is_default = ($mdlCheckIPService->disable_factory_default_service->__toString() == "1") ? $is_default : true;
        }
        return array('result' => $is_default);
    }

    /**
     * disable all CheckIP service items; FDS inclusive
     * @param $nodeType
     * @param &$mdlCheckIPService
     */
    private function disableAll(&$mdlCheckIPService, $nodeType)
    {
        $nodes = $mdlCheckIPService->$nodeType->getNodes();
        foreach ($nodes as $nodeUuid => $node) {
            $node = $mdlCheckIPService->getNodeByReference($nodeType . '.' . $nodeUuid);
            $node->default = "0";
        }
        $mdlCheckIPService->disable_factory_default_service = "1";
    }

    /**
     * Include the factory default check IP service.
     */
    private function includeFDS($mdlCheckIPService, $grid)
    {
//        $mdlCheckIPFDS = new Factory_Default_Service();
        $mdlCheckIPFDS = new CheckIP();

        $fds = $mdlCheckIPFDS->factory_default_service->getNodes();
        $fds['default'] = ($mdlCheckIPService->disable_factory_default_service->__toString() == "1") ? "0" : "1";
        $fds['uuid'] = 'FDS';

        $grid['rows'][] = $fds;
        $grid['rowCount'] = $grid['rowCount'] + 1;
        $grid['total'] = $grid['total'] + 1;

        return $grid;
    }
}
