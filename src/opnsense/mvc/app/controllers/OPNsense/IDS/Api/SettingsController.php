<?php

/*
 * Copyright (C) 2015-2017 Deciso B.V.
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

namespace OPNsense\IDS\Api;

use Phalcon\Filter;
use OPNsense\Base\ApiMutableModelControllerBase;
use OPNsense\Base\Filters\QueryFilter;
use OPNsense\Core\Backend;
use OPNsense\Core\Config;
use OPNsense\Base\UIModelGrid;

/**
 * Class SettingsController Handles settings related API actions for the IDS module
 * @package OPNsense\IDS
 */
class SettingsController extends ApiMutableModelControllerBase
{
    protected static $internalModelName = 'ids';
    protected static $internalModelClass = '\OPNsense\IDS\IDS';

    /**
     * Query non layered model items
     * @return array plain model settings (non repeating items)
     * @throws \ReflectionException when not bound to model
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
     * Search installed ids rules
     * @return array query results
     * @throws \Exception when configd action fails
     * @throws \ReflectionException when not bound to model
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
                    $sortStr .= $filter->sanitize($sortKey, "query") . ' ' . $sortOrd . ' ';
                }
            } else {
                $sortStr = 'sid';
            }
            if ($this->request->getPost('searchPhrase', 'string', '') != "") {
                $searchTag = $filter->sanitize($this->request->getPost('searchPhrase'), "query");
                $searchPhrase = 'msg,source,sid/"*' . $searchTag . '"';
            } else {
                $searchPhrase = '';
            }

            // add metadata filters
            foreach ($_POST as $key => $value) {
                $key = $filter->sanitize($key, "string");
                $value = $filter->sanitize($value, "string");
                if (!in_array($key, ['current', 'rowCount', 'sort', 'searchPhrase'])) {
                    $searchPhrase .= " {$key}/\"{$value}\" ";
                }
            }

            // request list of installed rules
            $backend = new Backend();
            $response = $backend->configdpRun("ids query rules", array($itemsPerPage,
                ($currentPage - 1) * $itemsPerPage,
                $searchPhrase, $sortStr));

            $data = json_decode($response, true);

            if ($data != null && array_key_exists("rows", $data)) {
                $result = array();
                $result['rows'] = $data['rows'];
                $result['rowCount'] = empty($result['rows']) || !is_array($result['rows']) ? 0 : count($result['rows']);
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
     * Get rule information
     * @param string|null $sid rule identifier
     * @return array|mixed
     * @throws \Exception when configd action fails
     * @throws \ReflectionException when not bound to model
     */
    public function getRuleInfoAction($sid = null)
    {
        // request list of installed rules
        if (!empty($sid)) {
            $this->sessionClose();
            $backend = new Backend();
            $response = $backend->configdpRun("ids query rules", array(1, 0,'sid/' . $sid));
            $data = json_decode($response, true);
        } else {
            $data = null;
        }

        if ($data != null && array_key_exists("rows", $data) && !empty($data['rows'])) {
            $row = $data['rows'][0];
            // set current enable status (default + registered offset)
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
                        $item_html = str_replace("%url%", 'http://' . substr($ref, 4), $item_html);
                        $item_html = str_replace("%ref%", substr($ref, 4), $item_html);
                    } elseif (substr($ref, 0, 7) == "system,") {
                        $item_html = str_replace("%url%", substr($ref, 7), $item_html);
                        $item_html = str_replace("%ref%", substr($ref, 7), $item_html);
                    } elseif (substr($ref, 0, 8) == "bugtraq,") {
                        $item_html = str_replace("%url%", "http://www.securityfocus.com/bid/" .
                            substr($ref, 8), $item_html);
                        $item_html = str_replace("%ref%", "bugtraq " . substr($ref, 8), $item_html);
                    } elseif (substr($ref, 0, 4) == "cve,") {
                        $item_html = str_replace("%url%", "http://cve.mitre.org/cgi-bin/cvename.cgi?name=" .
                            substr($ref, 4), $item_html);
                        $item_html = str_replace("%ref%", substr($ref, 4), $item_html);
                    } elseif (substr($ref, 0, 7) == "nessus,") {
                        $item_html = str_replace("%url%", "http://cgi.nessus.org/plugins/dump.php3?id=" .
                            substr($ref, 7), $item_html);
                        $item_html = str_replace("%ref%", 'nessus ' . substr($ref, 7), $item_html);
                    } elseif (substr($ref, 0, 7) == "mcafee,") {
                        $item_html = str_replace("%url%", "http://vil.nai.com/vil/dispVirus.asp?virus_k=" .
                            substr($ref, 7), $item_html);
                        $item_html = str_replace("%ref%", 'macafee ' . substr($ref, 7), $item_html);
                    } else {
                        continue;
                    }
                    $row['reference_html'] .= $item_html . '<br/>';
                }
            }
            ksort($row);
            return $row;
        } else {
            return array();
        }
    }

    /**
     * List available rule metadata
     * @return array
     * @throws \Exception when configd action fails
     */
    public function listRuleMetadataAction()
    {
        $this->sessionClose();
        $response = (new Backend())->configdRun("ids list rulemetadata");
        $data = json_decode($response, true);
        if ($data != null) {
            $data['matched_policy'] = ['__manual__'];
            foreach ($this->getModel()->policies->policy->iterateItems() as $policy) {
                if (!empty((string)$policy->enabled) && !empty((string)$policy->description)) {
                    $data['matched_policy'][] = (string)$policy->description;
                }
            }
            return $data;
        } else {
            return array();
        }
    }

    /**
     * List all installable rules including configuration additions
     * @return array
     * @throws \Exception when configd action fails
     * @throws \ReflectionException when not bound to model
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
                    $item['documentation'] = "<a href='" . $item['documentation_url'] . "' target='_new'>";
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
                $result[] = $item;
            }
        }
        return $result;
    }

    /**
     * List ruleset properties
     * @return array result status
     * @throws \Exception when config actions fails
     * @throws \ReflectionException when not bound to model
     */
    public function getRulesetpropertiesAction()
    {
        $result = array('properties' => array());
        $this->sessionClose();
        $backend = new Backend();
        $response = $backend->configdRun("ids list installablerulesets");
        $data = json_decode($response, true);
        if ($data != null && isset($data["properties"])) {
            foreach ($data['properties'] as $key => $settings) {
                $result['properties'][$key] = !empty($settings['default']) ? $settings['default'] : "";
                foreach ($this->getModel()->fileTags->tag->iterateItems() as $tag) {
                    if ((string)$tag->property == $key) {
                        $result['properties'][(string)$tag->property] = (string)$tag->value;
                    }
                }
            }
        }
        return $result;
    }

    /**
     * Update ruleset properties
     * @return array result status
     * @throws \Exception when config action fails
     * @throws \Phalcon\Validation\Exception when field validations fail
     * @throws \ReflectionException when not bound to model
     */
    public function setRulesetpropertiesAction()
    {
        $result = array("result" => "failed");
        if ($this->request->isPost() && $this->request->hasPost("properties")) {
            // only update properties available in "ids list installablerulesets"
            $this->sessionClose();
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
                        foreach ($this->getModel()->fileTags->tag->iterateItems() as $tag) {
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
                if (!empty($validations)) {
                    $result['validations'] = $validations;
                } else {
                    $result = $this->save();
                }
            }
        }
        return $result;
    }

    /**
     * List all installable rules including current status
     * @return array|mixed list of items when $id is null otherwise the selected item is returned
     * @throws \Exception
     */
    public function listRulesetsAction()
    {
        $result = array();
        $this->sessionClose();
        $result['rows'] = $this->listInstallableRules();
        // sort by description
        usort($result['rows'], function ($item1, $item2) {
            return strcmp(strtolower($item1['description']), strtolower($item2['description']));
        });
        $result['rowCount'] = empty($result['rows']) ? 0 :  count($result['rows']);
        $result['total'] = empty($result['rows']) ? 0 : count($result['rows']);
        $result['current'] = 1;
        return $result;
    }

    /**
     * Get ruleset list info (file)
     * @param string $id list filename
     * @return array|mixed list details
     * @throws \Exception when configd action fails
     * @throws \ReflectionException when not bound to model
     */
    public function getRulesetAction($id)
    {
        $this->sessionClose();
        $rules = $this->listInstallableRules();
        foreach ($rules as $rule) {
            if ($rule['filename'] == $id) {
                return $rule;
            }
        }
        return array();
    }

    /**
     * Set ruleset attributes
     * @param $filename rule filename (key)
     * @return array result status
     * @throws \Exception when configd action fails
     * @throws \Phalcon\Validation\Exception when field validations fail
     * @throws \ReflectionException when not bound to model
     */
    public function setRulesetAction($filename)
    {
        $result = array("result" => "failed");
        if ($this->request->isPost()) {
            // we're only allowed to edit filenames which have an install ruleset, request valid ones from configd
            $this->sessionClose();
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
                if (!empty($validations)) {
                    $result['validations'] = $validations;
                } else {
                    $result = $this->save();
                }
            }
        }
        return $result;
    }

    /**
     * Toggle usage of rule file or set enabled / disabled depending on parameters
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
            $this->sessionClose();
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
                $this->save();
            }
        }
        return $result;
    }

    /**
     * Toggle rule enable status
     * @param string $sids unique id
     * @param string|int $enabled desired state enabled(1)/disabled(1), leave empty for toggle
     * @return array empty
     * @throws \Exception when configd action fails
     * @throws \Phalcon\Validation\Exception when field validations fail
     * @throws \ReflectionException when not bound to model
     */
    public function toggleRuleAction($sids, $enabled = null)
    {
        if ($this->request->isPost()) {
            $this->sessionClose();
            $update_count = 0;
            foreach (explode(",", $sids) as $sid) {
                $ruleinfo = $this->getRuleInfoAction($sid);
                $current_action = null;
                foreach ($ruleinfo['action'] as $key => $act) {
                    if (!empty($act['selected'])) {
                        $current_action = $key;
                    }
                }
                if (!empty($ruleinfo)) {
                    if ($enabled == null) {
                        // toggle state
                        if ($ruleinfo['enabled'] == 1) {
                            $new_state = 0;
                        } else {
                            $new_state = 1;
                        }
                    } elseif ($enabled == 1) {
                        $new_state = 1;
                    } elseif ($enabled == "alert") {
                        $current_action = "alert";
                        $new_state = 1;
                    } elseif ($enabled == "drop") {
                        $current_action = "drop";
                        $new_state = 1;
                    } else {
                        $new_state = 0;
                    }
                    if ($new_state == 1) {
                        $this->getModel()->enableRule($sid)->action = $current_action;
                    } else {
                        $this->getModel()->disableRule($sid)->action = $current_action;
                    }
                    $update_count++;
                }
            }
            if ($update_count > 0) {
                return $this->save();
            }
        }
        return array();
    }

    /**
     * Set rule action
     * @param $sid item unique id
     * @return array result status
     * @throws \Exception when configd action fails
     * @throws \Phalcon\Validation\Exception when field validations fail
     * @throws \ReflectionException when not bound to model
     */
    public function setRuleAction($sid)
    {
        $result = array("result" => "failed");
        if ($this->request->isPost() && $this->request->hasPost("action")) {
            $this->sessionClose();
            if ($this->request->hasPost('enabled')) {
                $this->toggleRuleAction($sid, $this->request->getPost("enabled", "int", null));
            }
            $ruleinfo = $this->getRuleInfoAction($sid);
            $newAction = $this->request->getPost("action", "striptags", null);
            if (!empty($ruleinfo)) {
                $mdlIDS = $this->getModel();
                $mdlIDS->setAction($sid, $newAction);
                $validations = $mdlIDS->validate();
                if (!empty($validations)) {
                    $result['validations'] = $validations;
                } else {
                    return $this->save();
                }
            }
        }
        return $result;
    }

    /**
     * Search user defined rules
     * @return array list of found user rules
     * @throws \ReflectionException when not bound to model
     */
    public function searchUserRuleAction()
    {
        return $this->searchBase("userDefinedRules.rule", array("enabled", "action", "description"), "description");
    }

    /**
     * Update user defined rules
     * @param string $uuid internal id
     * @return array save result + validation output
     * @throws \Phalcon\Validation\Exception when field validations fail
     * @throws \ReflectionException when not bound to model
     */
    public function setUserRuleAction($uuid)
    {
        return $this->setBase("rule", "userDefinedRules.rule", $uuid);
    }

    /**
     * Add new user defined rule
     * @return array save result + validation output
     * @throws \Phalcon\Validation\Exception when field validations fail
     * @throws \ReflectionException when not bound to model
     */
    public function addUserRuleAction()
    {
        return $this->addBase("rule", "userDefinedRules.rule");
    }

    /**
     * Get properties of user defined rule
     * @param null|string $uuid user rule internal id
     * @return array user defined properties
     * @throws \ReflectionException when not bound to model
     */
    public function getUserRuleAction($uuid = null)
    {
        return $this->getBase("rule", "userDefinedRules.rule", $uuid);
    }

    /**
     * Delete user rule item
     * @param string $uuid user rule internal id
     * @return array save status
     * @throws \Phalcon\Validation\Exception when field validations fail
     * @throws \ReflectionException when not bound to model
     */
    public function delUserRuleAction($uuid)
    {
        return  $this->delBase("userDefinedRules.rule", $uuid);
    }

    /**
     * Toggle user defined rule by uuid (enable/disable)
     * @param $uuid user defined rule internal id
     * @param $enabled desired state enabled(1)/disabled(1), leave empty for toggle
     * @return array save result
     * @throws \Phalcon\Validation\Exception when field validations fail
     * @throws \ReflectionException when not bound to model
     */
    public function toggleUserRuleAction($uuid, $enabled = null)
    {
        return $this->toggleBase("userDefinedRules.rule", $uuid, $enabled);
    }

    /**
     * Search policy
     * @return array list of found user rules
     * @throws \ReflectionException when not bound to model
     */
    public function searchPolicyAction()
    {
        return $this->searchBase("policies.policy", array("enabled", "prio", "description"), "description");
    }

    /**
     * Update policy
     * @param string $uuid internal id
     * @return array save result + validation output
     * @throws \Phalcon\Validation\Exception when field validations fail
     * @throws \ReflectionException when not bound to model
     */
    public function setPolicyAction($uuid)
    {
        return $this->setBase("policy", "policies.policy", $uuid);
    }

    /**
     * Add new policy
     * @return array save result + validation output
     * @throws \Phalcon\Validation\Exception when field validations fail
     * @throws \ReflectionException when not bound to model
     */
    public function addPolicyAction()
    {
        return $this->addBase("policy", "policies.policy");
    }

    /**
     * Get properties of a policy
     * @param null|string $uuid user rule internal id
     * @return array user defined properties
     * @throws \ReflectionException when not bound to model
     */
    public function getPolicyAction($uuid = null)
    {
        return $this->getBase("policy", "policies.policy", $uuid);
    }

    /**
     * Delete policy item
     * @param string $uuid user rule internal id
     * @return array save status
     * @throws \Phalcon\Validation\Exception when field validations fail
     * @throws \ReflectionException when not bound to model
     */
    public function delPolicyAction($uuid)
    {
        return  $this->delBase("policies.policy", $uuid);
    }

    /**
     * Toggle policy by uuid (enable/disable)
     * @param $uuid user defined rule internal id
     * @param $enabled desired state enabled(1)/disabled(1), leave empty for toggle
     * @return array save result
     * @throws \Phalcon\Validation\Exception when field validations fail
     * @throws \ReflectionException when not bound to model
     */
    public function togglePolicyAction($uuid, $enabled = null)
    {
        return $this->toggleBase("policies.policy", $uuid, $enabled);
    }

    /**
     * Search policy rule adjustment
     * @return array list of found user rules
     * @throws \ReflectionException when not bound to model
     */
    public function searchPolicyRuleAction()
    {
        return $this->searchBase("rules.rule", array("sid", "enabled", "action"), "sid");
    }

    /**
     * Update policy rule adjustment
     * @param string $uuid internal id
     * @return array save result + validation output
     * @throws \Phalcon\Validation\Exception when field validations fail
     * @throws \ReflectionException when not bound to model
     */
    public function setPolicyRuleAction($uuid)
    {
        return $this->setBase("rule", "rules.rule", $uuid);
    }

    /**
     * Add new policy rule adjustment
     * @return array save result + validation output
     * @throws \Phalcon\Validation\Exception when field validations fail
     * @throws \ReflectionException when not bound to model
     */
    public function addPolicyRuleAction()
    {
        return $this->addBase("rule", "rules.rule");
    }

    /**
     * Get properties of a policy rule adjustment
     * @param null|string $uuid internal id
     * @return array user defined properties
     * @throws \ReflectionException when not bound to model
     */
    public function getPolicyRuleAction($uuid = null)
    {
        return $this->getBase("rule", "rules.rule", $uuid);
    }

    /**
     * Delete policy rule adjustment item
     * @param string $uuid internal id
     * @return array save status
     * @throws \Phalcon\Validation\Exception when field validations fail
     * @throws \ReflectionException when not bound to model
     */
    public function delPolicyRuleAction($uuid)
    {
        return  $this->delBase("rules.rule", $uuid);
    }

    /**
     * Toggle policy rule adjustment by uuid (enable/disable)
     * @param $uuid user internal id
     * @param $enabled desired state enabled(1)/disabled(1), leave empty for toggle
     * @return array save result
     * @throws \Phalcon\Validation\Exception when field validations fail
     * @throws \ReflectionException when not bound to model
     */
    public function togglePolicyRuleAction($uuid, $enabled = null)
    {
        return $this->toggleBase("rules.rule", $uuid, $enabled);
    }

    /**
     * return then number of custom defined policy rules
     */
    public function checkPolicyRuleAction()
    {
        $result = ['status' => 'ok'];
        $mdlIDS = $this->getModel();
        $result['count'] = count(iterator_to_array($mdlIDS->rules->rule->iterateItems()));
        if ($result['count'] > 100) {
            // changing some rules by sid doesn't matter, a lot inflates the config.xml beyond reasonable limits.
            $result['status'] = 'warning';
            $result['message'] = sprintf(
                gettext("We strongly advise to use policies instead of " .
                "single rule based changes to limit the size of the configuration. " .
                "A list of all manual changes can be revised in the policy editor (available %s here %s)"),
                "<a href='/ui/ids/policy#rules'>",
                "</a>"
            );
        }
        // return unescaped content
        $this->response->setContentType('application/json', 'UTF-8');
        $this->response->setContent(json_encode($result));
    }
}
