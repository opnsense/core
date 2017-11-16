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
namespace OPNsense\IDS\Api;

use \Phalcon\Filter;
use \OPNsense\Base\ApiMutableModelControllerBase;
use \OPNsense\Base\Filters\QueryFilter;
use \OPNsense\Core\Backend;
use \OPNsense\Core\Config;
use \OPNsense\Base\UIModelGrid;

/**
 * Class SettingsController Handles settings related API actions for the IDS module
 * @package OPNsense\IDS
 */
class SettingsController extends ApiMutableModelControllerBase
{
    static protected $internalModelName = 'ids';
    static protected $internalModelClass = '\OPNsense\IDS\IDS';

    /**
     * @return array plain model settings (non repeating items)
     */
    protected function getModelNodes()
    {
        $settingsNodes = array('general');
        $result = array();
        $mdlIDS = $this->getModel();
        foreach ($settingsNodes as $key) {
            $result[$key] = $mdlIDS->$key->getNodes();
        }
        return $result;
    }

    /**
     * search installed ids rules
     * @return array
     */
    public function searchInstalledRulesAction()
    {
        if ($this->request->isPost()) {
            $this->sessionClose();
            // create filter to sanitize input data
            $filter = new Filter();
            $filter->add('query', new QueryFilter());


            // fetch query parameters (limit results to prevent out of memory issues)
            $itemsPerPage = $this->request->getPost('rowCount', 'int', 9999);
            $currentPage = $this->request->getPost('current', 'int', 1);

            if ($this->request->hasPost('sort') && is_array($this->request->getPost("sort"))) {
                $sortStr = '';
                $sortBy = array_keys($this->request->getPost("sort"));
                if ($this->request->getPost("sort")[$sortBy[0]] == "desc") {
                    $sortOrd = 'desc';
                } else {
                    $sortOrd = 'asc';
                }

                foreach ($sortBy as $sortKey) {
                    if ($sortStr != '') {
                        $sortStr .= ',';
                    }
                    $sortStr .= $filter->sanitize($sortKey, "query") . ' '. $sortOrd . ' ';
                }
            } else {
                $sortStr = 'sid';
            }
            if ($this->request->getPost('searchPhrase', 'string', '') != "") {
                $searchTag = $filter->sanitize($this->request->getPost('searchPhrase'), "query");
                $searchPhrase = 'msg,source,sid/"*'.$searchTag.'"';
            } else {
                $searchPhrase = '';
            }

            // add filter for classtype
            if ($this->request->getPost("classtype", "string", '') != "") {
                $searchTag = $filter->sanitize($this->request->getPost('classtype'), "query");
                $searchPhrase .= " classtype/".$searchTag.' ';
            }

            // add filter for action
            if ($this->request->getPost("action", "string", '') != "") {
                $searchTag = $filter->sanitize($this->request->getPost('action'), "query");
                $searchPhrase .= " installed_action/".$searchTag.' ';
            }

            // request list of installed rules
            $backend = new Backend();
            $response = $backend->configdpRun("ids query rules", array($itemsPerPage,
                ($currentPage-1)*$itemsPerPage,
                $searchPhrase, $sortStr));

            $data = json_decode($response, true);

            if ($data != null && array_key_exists("rows", $data)) {
                $result = array();
                $result['rows'] = $data['rows'];
                // update rule status with own administration
                foreach ($result['rows'] as &$row) {
                    $row['enabled_default'] = $row['enabled'];
                    $row['enabled'] = $this->getModel()->getRuleStatus($row['sid'], $row['enabled']);
                    $row['action'] = $this->getModel()->getRuleAction($row['sid'], $row['action'], true);
                }

                $result['rowCount'] = count($result['rows']);
                $result['total'] = $data['total_rows'];
                $result['parameters'] = $data['parameters'];
                $result['current'] = (int)$currentPage;
                return $result;
            } else {
                return array();
            }
        } else {
            return array();
        }
    }

    /**
     * get rule information
     * @param string|null $sid rule identifier
     * @return array|mixed
     */
    public function getRuleInfoAction($sid = null)
    {
        // request list of installed rules
        if (!empty($sid)) {
            $backend = new Backend();
            $response = $backend->configdpRun("ids query rules", array(1, 0,'sid/'.$sid));
            $data = json_decode($response, true);
        } else {
            $data = null;
        }

        if ($data != null && array_key_exists("rows", $data) && count($data['rows'])>0) {
            $row = $data['rows'][0];
            // set current enable status (default + registered offset)
            $row['enabled_default'] = $row['enabled'];
            $row['action_default'] = $row['action'];
            $row['enabled'] = $this->getModel()->getRuleStatus($row['sid'], $row['enabled']);
            $row['action'] = $this->getModel()->getRuleAction($row['sid'], $row['action']);
            //
            if (isset($row['reference']) && $row['reference'] != '') {
                // browser friendly reference data
                $row['reference_html'] = '';
                foreach (explode("\n", $row['reference']) as $ref) {
                    $ref = trim($ref);
                    $item_html = '<small><a href="%url%" target="_blank">%ref%</a></small>';
                    if (substr($ref, 0, 4) == 'url,') {
                        $item_html = str_replace("%url%", 'http://'.substr($ref, 4), $item_html);
                        $item_html = str_replace("%ref%", substr($ref, 4), $item_html);
                    } elseif (substr($ref, 0, 7) == "system,") {
                        $item_html = str_replace("%url%", substr($ref, 7), $item_html);
                        $item_html = str_replace("%ref%", substr($ref, 7), $item_html);
                    } elseif (substr($ref, 0, 8) == "bugtraq,") {
                        $item_html = str_replace("%url%", "http://www.securityfocus.com/bid/".
                            substr($ref, 8), $item_html);
                        $item_html = str_replace("%ref%", "bugtraq ".substr($ref, 8), $item_html);
                    } elseif (substr($ref, 0, 4) == "cve,") {
                        $item_html = str_replace("%url%", "http://cve.mitre.org/cgi-bin/cvename.cgi?name=".
                            substr($ref, 4), $item_html);
                        $item_html = str_replace("%ref%", substr($ref, 4), $item_html);
                    } elseif (substr($ref, 0, 7) == "nessus,") {
                        $item_html = str_replace("%url%", "http://cgi.nessus.org/plugins/dump.php3?id=".
                            substr($ref, 7), $item_html);
                        $item_html = str_replace("%ref%", 'nessus '.substr($ref, 7), $item_html);
                    } elseif (substr($ref, 0, 7) == "mcafee,") {
                        $item_html = str_replace("%url%", "http://vil.nai.com/vil/dispVirus.asp?virus_k=".
                            substr($ref, 7), $item_html);
                        $item_html = str_replace("%ref%", 'macafee '.substr($ref, 7), $item_html);
                    } else {
                        continue;
                    }
                    $row['reference_html'] .= $item_html.'<br/>';
                }
            }
            return $row;
        } else {
            return array();
        }
    }

    /**
     * list available classtypes
     * @return array
     * @throws \Exception
     */
    public function listRuleClasstypesAction()
    {
        $backend = new Backend();
        $response = $backend->configdRun("ids list classtypes");
        $data = json_decode($response, true);
        if ($data != null && array_key_exists("items", $data)) {
            return $data;
        } else {
            return array();
        }
    }

    /**
     * list all installable rules including configuration additions
     * @return array
     */
    private function listInstallableRules()
    {
        $result = array();
        $backend = new Backend();
        $response = $backend->configdRun("ids list installablerulesets");
        $data = json_decode($response, true);
        if ($data != null && array_key_exists("items", $data)) {
            ksort($data['items']);
            foreach ($data['items'] as $filename => $fileinfo) {
                $item = array();
                $item['description'] = $fileinfo['description'];
                $item['filename'] = $fileinfo['filename'];
                $item['documentation_url'] = $fileinfo['documentation_url'];
                if (!empty($fileinfo['documentation_url'])) {
                    $item['documentation'] = "<a href='".$item['documentation_url']."' target='_new'>";
                    $item['documentation'] .= $item['documentation_url'];
                    $item['documentation'] .= '</a>';
                } else {
                    $item['documentation'] = null;
                }

                // format timestamps
                if ($fileinfo['modified_local'] == null) {
                    $item['modified_local'] = null;
                } else {
                    $item['modified_local'] = date('Y/m/d G:i', $fileinfo['modified_local']);
                }
                // retrieve status from model
                $fileNode = $this->getModel()->getFileNode($fileinfo['filename']);
                $item['enabled'] = (string)$fileNode->enabled;
                $item['filter'] = $fileNode->filter->getNodeData(); // filter (option list)
                $item['filter_str'] = (string)$fileNode->filter; // filter current value
                $result[] = $item;
            }
        }
        return $result;
    }

    /**
     * list ruleset properties
     * @return array
     */
    public function getRulesetpropertiesAction()
    {
        $result = array('properties' => array());
        $backend = new Backend();
        $response = $backend->configdRun("ids list installablerulesets");
        $data = json_decode($response, true);
        if ($data != null && isset($data["properties"])) {
            foreach ($data['properties'] as $key => $settings) {
                $result['properties'][$key] = !empty($settings['default']) ? $settings['default'] : "";
                foreach ($this->getModel()->fileTags->tag->__items as $tag) {
                    if ((string)$tag->property == $key) {
                        $result['properties'][(string)$tag->property] = (string)$tag->value;
                    }
                }
            }
        }
        return $result;
    }

    /**
     * update ruleset properties
     * @return array
     */
    public function setRulesetpropertiesAction()
    {
        $result = array("result" => "failed");
        if ($this->request->isPost() && $this->request->hasPost("properties")) {
            // only update properties available in "ids list installablerulesets"
            $backend = new Backend();
            $response = $backend->configdRun("ids list installablerulesets");
            $data = json_decode($response, true);
            if ($data != null && isset($data["properties"])) {
                $setProperties = $this->request->getPost("properties");
                foreach ($setProperties as $key => $value) {
                    if (isset($data['properties'][$key])) {
                        if (!isset($result['fields'])) {
                            $result['fields'] = array(); // return updated fields
                        }
                        $result['fields'][] = $key;
                        $resultTag = null;
                        foreach ($this->getModel()->fileTags->tag->__items as $tag) {
                            if ((string)$tag->property == $key) {
                                $resultTag = $tag;
                                break;
                            }
                        }
                        if ($resultTag == null) {
                            $resultTag = $this->getModel()->fileTags->tag->Add();
                        }
                        $resultTag->property = (string)$key;
                        $resultTag->value = (string)$value;
                    }
                }
                $validations = $this->getModel()->validate();
                if (count($validations)) {
                    $result['validations'] = $validations;
                } else {
                    $this->getModel()->serializeToConfig();
                    Config::getInstance()->save();
                    $result["result"] = "saved";
                }
            }
        }
        return $result;
    }

    /**
     * list all installable rules including current status
     * @return array|mixed list of items when $id is null otherwise the selected item is returned
     * @throws \Exception
     */
    public function listRulesetsAction()
    {
        $result = array();
        $result['rows'] = $this->listInstallableRules();
        // sort by description
        usort($result['rows'], function ($item1, $item2) {
            return strcmp(strtolower($item1['description']), strtolower($item2['description']));
        });
        $result['rowCount'] = count($result['rows']);
        $result['total'] = count($result['rows']);
        $result['current'] = 1;
        return $result;
    }

    /**
     * get ruleset list info (file)
     * @param string $id list filename
     * @return array|mixed list details
     */
    public function getRulesetAction($id)
    {
        $rules = $this->listInstallableRules();
        foreach ($rules as $rule) {
            if ($rule['filename'] == $id) {
                return $rule;
            }
        }
        return array();
    }

    /**
     * set ruleset attributes
     * @param $filename rule filename (key)
     * @return array
     */
    public function setRulesetAction($filename)
    {
        $result = array("result" => "failed");
        if ($this->request->isPost()) {
            // we're only allowed to edit filenames which have an install ruleset, request valid ones from configd
            $backend = new Backend();
            $response = $backend->configdRun("ids list installablerulesets");
            $data = json_decode($response, true);
            if ($data != null && array_key_exists("items", $data) && array_key_exists($filename, $data['items'])) {
                // filename exists, input ruleset data
                $mdlIDS = $this->getModel();
                $node = $mdlIDS->getFileNode($filename);

                // send post attributes to model
                $node->setNodes($_POST);

                $validations = $mdlIDS->validate($node->__reference . ".", "");
                if (count($validations)) {
                    $result['validations'] = $validations;
                } else {
                    // serialize model to config and save
                    $mdlIDS->serializeToConfig();
                    Config::getInstance()->save();
                    $result["result"] = "saved";
                }
            }
        }
        return $result;
    }

    /**
     * toggle usage of rule file or set enabled / disabled depending on parameters
     * @param $filenames (target) rule file name, or list of filenames separated by a comma
     * @param $enabled desired state enabled(1)/disabled(1), leave empty for toggle
     * @return array status 0/1 or error
     * @throws \Exception
     * @throws \Phalcon\Validation\Exception
     */
    public function toggleRulesetAction($filenames, $enabled = null)
    {
        $update_count = 0;
        $result = array("status" => "none");
        if ($this->request->isPost()) {
            $backend = new Backend();
            $response = $backend->configdRun("ids list installablerulesets");
            $data = json_decode($response, true);
            foreach (explode(",", $filenames) as $filename) {
                if ($data != null && array_key_exists("items", $data) && array_key_exists($filename, $data['items'])) {
                    $node = $this->getModel()->getFileNode($filename);
                    if ($enabled == "0" || $enabled == "1") {
                        $node->enabled = (string)$enabled;
                    } elseif ((string)$node->enabled == "1") {
                        $node->enabled = "0";
                    } else {
                        $node->enabled = "1";
                    }
                    // only update result state if all items until now are ok
                    if ($result['status'] != 'error') {
                        $result['status'] = (string)$node->enabled;
                    }
                    $update_count++;
                } else {
                    $result['status'] = "error";
                }
            }
            if ($update_count > 0) {
                $this->getModel()->serializeToConfig();
                Config::getInstance()->save();
            }
        }
        return $result;
    }

    /**
     * toggle rule enable status
     * @param string $sids unique id
     * @param string|int $enabled desired state enabled(1)/disabled(1), leave empty for toggle
     * @return array empty
     */
    public function toggleRuleAction($sids, $enabled = null)
    {
        if ($this->request->isPost()) {
            $update_count = 0;
            foreach (explode(",", $sids) as $sid) {
                $ruleinfo = $this->getRuleInfoAction($sid);
                if (count($ruleinfo) > 0) {
                    if ($enabled == null) {
                        // toggle state
                        if ($ruleinfo['enabled'] == 1) {
                            $new_state = 0;
                        } else {
                            $new_state = 1;
                        }
                    } elseif ($enabled == 1) {
                        $new_state = 1;
                    } else {
                        $new_state = 0;
                    }

                    if ($ruleinfo['enabled_default'] == $new_state &&
                        array_key_exists($ruleinfo['action_default'], $ruleinfo['action']) &&
                        $ruleinfo['action'][$ruleinfo['action_default']]['selected'] == 1
                        ) {
                        // if we're switching back to default, remove alter rule
                        $this->getModel()->removeRule($sid);
                    } elseif ($new_state == 1) {
                        $this->getModel()->enableRule($sid);
                    } else {
                        $this->getModel()->disableRule($sid);
                    }
                    $update_count++;
                }
            }
            if ($update_count > 0) {
                $this->getModel()->serializeToConfig();
                Config::getInstance()->save();
            }
        }
        return array();
    }

    /**
     * set rule action
     * @param $sid item unique id
     * @return array
     */
    public function setRuleAction($sid)
    {
        $result = array("result" => "failed");
        if ($this->request->isPost() && $this->request->hasPost("action")) {
            if ($this->request->hasPost('enabled')) {
                $this->toggleRuleAction($sid, $this->request->getPost("enabled", "int", null));
            }
            $ruleinfo = $this->getRuleInfoAction($sid);
            $newAction = $this->request->getPost("action", "striptags", null);
            if (count($ruleinfo) > 0) {
                $mdlIDS = $this->getModel();
                if ($ruleinfo['enabled_default'] == $ruleinfo['enabled'] &&
                    $ruleinfo['action_default'] == $newAction
                    ) {
                    // if we're switching back to default, remove alter rule
                    $mdlIDS->removeRule($sid);
                } else {
                    $mdlIDS->setAction($sid, $newAction);
                }

                $validations = $mdlIDS->validate();
                if (count($validations)) {
                    $result['validations'] = $validations;
                } else {
                    $mdlIDS->serializeToConfig();
                    Config::getInstance()->save();
                    $result["result"] = "saved";
                }
            }
        }
        return $result;
    }

    /**
     * search user defined rules
     * @return array list of found user rules
     */
    public function searchUserRuleAction()
    {
        $this->sessionClose();
        $mdlIDS = $this->getModel();
        $grid = new UIModelGrid($mdlIDS->userDefinedRules->rule);
        return $grid->fetchBindRequest(
            $this->request,
            array("enabled", "action", "description"),
            "description"
        );
    }

    /**
     * update user defined rules
     * @param string $uuid internal id
     * @return array save result + validation output
     * @throws \Phalcon\Validation\Exception
     */
    public function setUserRuleAction($uuid)
    {
        $result = array("result"=>"failed");
        if ($this->request->isPost() && $this->request->hasPost("rule")) {
            $mdlIDS = $this->getModel();
            if ($uuid != null) {
                $node = $mdlIDS->getNodeByReference('userDefinedRules.rule.'.$uuid);
                if ($node != null) {
                    $node->setNodes($this->request->getPost("rule"));
                    $validations = $mdlIDS->validate($node->__reference, "rule");
                    if (count($validations)) {
                        $result['validations'] = $validations;
                    } else {
                        // serialize model to config and save
                        $mdlIDS->serializeToConfig();
                        Config::getInstance()->save();
                        $result["result"] = "saved";
                    }
                }
            }
        }
        return $result;
    }

    /**
     * add new user defined rule
     * @return array save result + validation output
     * @throws \Phalcon\Validation\Exception
     */
    public function addUserRuleAction()
    {
        $result = array("result"=>"failed");
        if ($this->request->isPost() && $this->request->hasPost("rule")) {
            $mdlIDS = $this->getModel();
            $node = $mdlIDS->userDefinedRules->rule->Add();
            $node->setNodes($this->request->getPost("rule"));
            $validations = $mdlIDS->validate($node->__reference, "rule");
            if (count($validations)) {
                $result['validations'] = $validations;
            } else {
                // serialize model to config and save
                $mdlIDS->serializeToConfig();
                Config::getInstance()->save();
                $result["result"] = "saved";
            }
        }
        return $result;
    }

    /**
     * get properties of user defined rule
     * @param null|string $uuid user rule internal id
     * @return array user defined properties
     */
    public function getUserRuleAction($uuid = null)
    {
        $mdlIDS = $this->getModel();
        if ($uuid != null) {
            $node = $mdlIDS->getNodeByReference('userDefinedRules.rule.'.$uuid);
            if ($node != null) {
                // return node
                return array("rule" => $node->getNodes());
            }
        } else {
            // generate new node, but don't save to disc
            $node = $mdlIDS->userDefinedRules->rule->add();
            return array("rule" => $node->getNodes());
        }
        return array();
    }

    /**
     * delete user rule item
     * @param string $uuid user rule internal id
     * @return array
     * @throws \Phalcon\Validation\Exception
     */
    public function delUserRuleAction($uuid)
    {
        $result = array("result"=>"failed");
        if ($this->request->isPost() && $uuid != null) {
            $mdlIDS = $this->getModel();
            if ($mdlIDS->userDefinedRules->rule->del($uuid)) {
                // if item is removed, serialize to config and save
                $mdlIDS->serializeToConfig();
                Config::getInstance()->save();
                $result['result'] = 'deleted';
            } else {
                $result['result'] = 'not found';
            }
        }
        return $result;
    }

    /**
     * toggle user defined rule by uuid (enable/disable)
     * @param $uuid user defined rule internal id
     * @param $enabled desired state enabled(1)/disabled(1), leave empty for toggle
     * @return array status
     */
    public function toggleUserRuleAction($uuid, $enabled = null)
    {
        $result = array("result" => "failed");
        if ($this->request->isPost() && $uuid != null) {
            $mdlIDS = $this->getModel();
            $node = $mdlIDS->getNodeByReference('userDefinedRules.rule.' . $uuid);
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
                $mdlIDS->serializeToConfig();
                Config::getInstance()->save();
            }
        }
        return $result;
    }
}
