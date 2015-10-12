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
namespace OPNsense\CaptivePortal\Api;

use \OPNsense\Base\ApiControllerBase;
use \OPNsense\Core\Config;
use \OPNsense\CaptivePortal\CaptivePortal;
use \OPNsense\Base\UIModelGrid;

/**
 * Class SettingsController Handles settings related API actions for Captive Portal
 * @package OPNsense\TrafficShaper
 */
class SettingsController extends ApiControllerBase
{
    /**
     * validate and save model after update or insertion.
     * Use the reference node and tag to rename validation output for a specific node to a new offset, which makes
     * it easier to reference specific uuids without having to use them in the frontend descriptions.
     * @param $mdl model reference
     * @param $node reference node, to use as relative offset
     * @param $reference reference for validation output, used to rename the validation output keys
     * @return array result / validation output
     */
    private function save($mdl, $node = null, $reference = null)
    {
        $result = array("result"=>"failed","validations" => array());
        // perform validation
        $valMsgs = $mdl->performValidation();
        foreach ($valMsgs as $field => $msg) {
            // replace absolute path to attribute for relative one at uuid.
            if ($node != null) {
                $fieldnm = str_replace($node->__reference, $reference, $msg->getField());
                $result["validations"][$fieldnm] = $msg->getMessage();
            } else {
                $result["validations"][$msg->getField()] = $msg->getMessage();
            }
        }

        // serialize model to config and save when there are no validation errors
        if (count($result['validations']) == 0) {
            // save config if validated correctly
            $mdl->serializeToConfig();

            Config::getInstance()->save();
            $result = array("result" => "saved");
        }

        return $result;
    }

    /**
     * retrieve zone settings or return defaults
     * @param $uuid item unique id
     * @return array
     */
    public function getZoneAction($uuid = null)
    {
        $mdlCP = new CaptivePortal();
        if ($uuid != null) {
            $node = $mdlCP->getNodeByReference('zones.zone.'.$uuid);
            if ($node != null) {
                // return node
                return array("zone" => $node->getNodes());
            }
        } else {
            // generate new node, but don't save to disc
            $node = $mdlCP->zones->zone->add() ;
            return array("zone" => $node->getNodes());
        }
        return array();
    }

    /**
     * update zone with given properties
     * @param $uuid item unique id
     * @return array
     */
    public function setZoneAction($uuid)
    {
        if ($this->request->isPost() && $this->request->hasPost("zone")) {
            $mdlCP = new CaptivePortal();
            if ($uuid != null) {
                $node = $mdlCP->getNodeByReference('zones.zone.'.$uuid);
                if ($node != null) {
                    $node->setNodes($this->request->getPost("zone"));
                    return $this->save($mdlCP, $node, "zone");
                }
            }
        }
        return array("result"=>"failed");
    }

    /**
     * add new zone and set with attributes from post
     * @return array
     */
    public function addZoneAction()
    {
        $result = array("result"=>"failed");
        if ($this->request->isPost() && $this->request->hasPost("zone")) {
            $mdlCP = new CaptivePortal();
            $node = $mdlCP->zones->zone->Add();
            $node->setNodes($this->request->getPost("zone"));
            return $this->save($mdlCP, $node, "zone");
        }
        return $result;
    }

    /**
     * delete zone by uuid
     * @param $uuid item unique id
     * @return array status
     */
    public function delZoneAction($uuid)
    {
        $result = array("result"=>"failed");
        if ($this->request->isPost()) {
            $mdlCP = new CaptivePortal();
            if ($uuid != null) {
                if ($mdlCP->zones->zone->del($uuid)) {
                    // if item is removed, serialize to config and save
                    $mdlCP->serializeToConfig();
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
     * toggle zone by uuid (enable/disable)
     * @param $uuid item unique id
     * @param $enabled desired state enabled(1)/disabled(1), leave empty for toggle
     * @return array status
     */
    public function toggleZoneAction($uuid, $enabled = null)
    {

        $result = array("result" => "failed");
        if ($this->request->isPost()) {
            $mdlCP = new CaptivePortal();
            if ($uuid != null) {
                $node = $mdlCP->getNodeByReference('zones.zone.' . $uuid);
                if ($node != null) {
                    if ($enabled == "0" || $enabled == "1") {
                        $node->enabled = (string)$enabled;
                    } elseif ((string)$node->enabled == "1") {
                        $node->enabled = "0";
                    } else {
                        $node->enabled = "1";
                    }
                    $result['result'] = $node->enabled;
                    // if item has toggled, serialize to config and save
                    $mdlCP->serializeToConfig();
                    Config::getInstance()->save();
                }
            }
        }
        return $result;
    }

    /**
     * search captive portal zones
     * @return array
     */
    public function searchZonesAction()
    {
        if ($this->request->isPost()) {
            $this->sessionClose();
            // fetch query parameters
            $itemsPerPage = $this->request->getPost('rowCount', 'int', 9999);
            $currentPage = $this->request->getPost('current', 'int', 1);
            $sortBy = array("number");
            $sortDescending = false;

            if ($this->request->hasPost('sort') && is_array($this->request->getPost("sort"))) {
                $sortBy = array_keys($this->request->getPost("sort"));
                if ($this->request->getPost("sort")[$sortBy[0]] == "desc") {
                    $sortDescending = true;
                }
            }

            $searchPhrase = $this->request->getPost('searchPhrase', 'string', '');

            // create model and fetch query resuls
            $fields = array("enabled", "description", "zoneid");
            $mdlCP = new CaptivePortal();
            $grid = new UIModelGrid($mdlCP->zones->zone);
            return $grid->fetch($fields, $itemsPerPage, $currentPage, $sortBy, $sortDescending, $searchPhrase);
        } else {
            return array();
        }
    }
}
