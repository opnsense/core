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
namespace OPNsense\TrafficShaper\Api;

use \OPNsense\Base\ApiControllerBase;
use \OPNsense\Core\Config;
use \OPNsense\TrafficShaper\TrafficShaper;
use \OPNsense\Base\UIModelGrid;

/**
 * Class SettingsController Handles settings related API actions for the Traffic Shaper
 * @package OPNsense\Proxy
 */
class SettingsController extends ApiControllerBase
{
    /**
     * validate and save model after update or insertion.
     * Use the reference node and tag to rename validation output for a specific node to a new offset, which makes
     * it easier to reference specific uuids without having to use them in the frontend descriptions.
     * @param $mdlShaper
     * @param $node reference node, to use as relative offset
     * @param $reference reference for validation output, used to rename the validation output keys
     * @return array result / validation output
     */
    private function save($mdlShaper, $node = null, $reference = null)
    {
        $result = array("result"=>"failed","validations" => array());
        // perform validation
        $valMsgs = $mdlShaper->performValidation();
        foreach ($valMsgs as $field => $msg) {
            // replace absolute path to attribute for relative one at uuid.
            if ($node != null) {
                $fieldnm = str_replace($node->__reference, $reference, $msg->getField());
                if ($fieldnm != $msg->getField()) {
                    // only collect validation errors for the item we're currently editing.
                    $result["validations"][$fieldnm] = $msg->getMessage();
                }
            } else {
                $result["validations"][$msg->getField()] = $msg->getMessage();
            }
        }

        // serialize model to config and save when there are no validation errors
        if (count($result['validations']) == 0) {
            // we've already performed a validation, prevent issues from other items in the model reflecting back to us.
            $mdlShaper->serializeToConfig($disable_validation = true);

            // save config if validated correctly
            Config::getInstance()->save();
            $result = array("result" => "saved");
        }

        return $result;
    }

    /**
     * retrieve pipe settings or return defaults
     * @param $uuid item unique id
     * @return array
     */
    public function getPipeAction($uuid = null)
    {
        $mdlShaper = new TrafficShaper();
        if ($uuid != null) {
            $node = $mdlShaper->getNodeByReference('pipes.pipe.'.$uuid);
            if ($node != null) {
                // return node
                return array("pipe" => $node->getNodes());
            }
        } else {
            // generate new node, but don't save to disc
            $node = $mdlShaper->pipes->pipe->add() ;
            return array("pipe" => $node->getNodes());
        }
        return array();
    }

    /**
     * update pipe with given properties
     * @param $uuid item unique id
     * @return array
     */
    public function setPipeAction($uuid)
    {
        if ($this->request->isPost() && $this->request->hasPost("pipe")) {
            $mdlShaper = new TrafficShaper();
            if ($uuid != null) {
                $node = $mdlShaper->getNodeByReference('pipes.pipe.'.$uuid);
                if ($node != null) {
                    $node->setNodes($this->request->getPost("pipe"));
                    return $this->save($mdlShaper, $node, "pipe");
                }
            }
        }
        return array("result"=>"failed");
    }

    /**
     * add new pipe and set with attributes from post
     * @return array
     */
    public function addPipeAction()
    {
        $result = array("result"=>"failed");
        if ($this->request->isPost() && $this->request->hasPost("pipe")) {
            $mdlShaper = new TrafficShaper();
            $node = $mdlShaper->addPipe();
            $node->setNodes($this->request->getPost("pipe"));
            $node->origin = "TrafficShaper"; // set origin to this component.
            return $this->save($mdlShaper, $node, "pipe");
        }
        return $result;
    }

    /**
     * delete pipe by uuid
     * @param $uuid item unique id
     * @return array status
     */
    public function delPipeAction($uuid)
    {
        $result = array("result"=>"failed");
        if ($this->request->isPost()) {
            $mdlShaper = new TrafficShaper();
            if ($uuid != null) {
                if ($mdlShaper->pipes->pipe->del($uuid)) {
                    // if item is removed, serialize to config and save
                    $mdlShaper->serializeToConfig($disable_validation = true);
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
     * search traffic shaper pipes
     * @return array
     */
    public function searchPipesAction()
    {
        if ($this->request->isPost()) {
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
            $fields = array("number", "bandwidth","bandwidthMetric","description","mask","origin");
            $mdlShaper = new TrafficShaper();
            $grid = new UIModelGrid($mdlShaper->pipes->pipe);
            return $grid->fetch($fields, $itemsPerPage, $currentPage, $sortBy, $sortDescending, $searchPhrase);
        } else {
            return array();
        }

    }

    /**
     * search traffic shaper rules
     * @return array
     */
    public function searchRulesAction()
    {
        if ($this->request->isPost()) {
            // fetch query parameters
            $itemsPerPage = $this->request->getPost('rowCount', 'int', 9999);
            $currentPage = $this->request->getPost('current', 'int', 1);
            $sortBy = array("sequence");
            $sortDescending = false;

            if ($this->request->hasPost('sort') && is_array($this->request->getPost("sort"))) {
                $sortBy = array_keys($this->request->getPost("sort"));
                if ($this->request->getPost("sort")[$sortBy[0]] == "desc") {
                    $sortDescending = true;
                }
            }

            $searchPhrase = $this->request->getPost('searchPhrase', 'string', '');

            // create model and fetch query resuls
            $fields = array("interface", "proto","source","destination","description","origin","sequence","target");
            $mdlShaper = new TrafficShaper();
            $grid = new UIModelGrid($mdlShaper->rules->rule);
            return $grid->fetch($fields, $itemsPerPage, $currentPage, $sortBy, $sortDescending, $searchPhrase);
        } else {
            return array();
        }
    }

    /**
     * retrieve rule settings or return defaults for new rule
     * @param $uuid item unique id
     * @return array
     */
    public function getRuleAction($uuid = null)
    {
        $mdlShaper = new TrafficShaper();
        if ($uuid != null) {
            $node = $mdlShaper->getNodeByReference('rules.rule.'.$uuid);
            if ($node != null) {
                // return node
                return array("rule" => $node->getNodes());
            }
        } else {
            // generate new node, but don't save to disc
            $node = $mdlShaper->rules->rule->add() ;
            return array("rule" => $node->getNodes());
        }
        return array();
    }

    /**
     * update rule with given properties
     * @param $uuid item unique id
     * @return array
     */
    public function setRuleAction($uuid)
    {
        if ($this->request->isPost() && $this->request->hasPost("rule")) {
            $mdlShaper = new TrafficShaper();
            if ($uuid != null) {
                $node = $mdlShaper->getNodeByReference('rules.rule.'.$uuid);
                if ($node != null) {
                    $node->setNodes($this->request->getPost("rule"));
                    return $this->save($mdlShaper, $node, "rule");
                }
            }
        }
        return array("result"=>"failed");
    }

    /**
     * add new rule and set with attributes from post
     * @return array
     */
    public function addRuleAction()
    {
        $result = array("result"=>"failed");
        if ($this->request->isPost() && $this->request->hasPost("rule")) {
            $mdlShaper = new TrafficShaper();
            $node = $mdlShaper->rules->rule->add();
            $node->setNodes($this->request->getPost("rule"));
            $node->origin = "TrafficShaper"; // set origin to this component.
            return $this->save($mdlShaper, $node, "rule");
        }
        return $result;
    }

    /**
     * delete rule by uuid
     * @param $uuid item unique id
     * @return array status
     */
    public function delRuleAction($uuid)
    {
        $result = array("result"=>"failed");
        if ($this->request->isPost()) {
            $mdlShaper = new TrafficShaper();
            if ($uuid != null) {
                if ($mdlShaper->rules->rule->del($uuid)) {
                    // if item is removed, serialize to config and save
                    $mdlShaper->serializeToConfig($disable_validation = true);
                    Config::getInstance()->save();
                    $result['result'] = 'deleted';
                } else {
                    $result['result'] = 'not found';
                }
            }
        }
        return $result;
    }

}
