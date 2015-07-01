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
use \OPNsense\Base\ApiControllerBase;
use \OPNsense\Base\Filters\QueryFilter;
use \OPNsense\Core\Backend;
use \OPNsense\IDS\IDS;
use \OPNsense\Core\Config;

/**
 * Class SettingsController Handles settings related API actions for the IDS module
 * @package OPNsense\IDS
 */
class SettingsController extends ApiControllerBase
{
    private $idsModel = null;

    /**
     * get ids model
     * @return null|IDS
     */
    public function getModel()
    {
        if ($this->idsModel == null) {
            $this->idsModel = new IDS();
        }

        return $this->idsModel;
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


            // fetch query parameters
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
     * @param $sid rule identifier
     * @return array|mixed
     */
    public function getRuleInfoAction($sid)
    {
        // request list of installed rules
        $backend = new Backend();
        $response = $backend->configdpRun("ids query rules", array(1, 0,'sid/'.$sid));
        $data = json_decode($response, true);

        if ($data != null && array_key_exists("rows", $data) && count($data['rows'])>0) {
            $row = $data['rows'][0];
            // set current enable status (default + registered offset)
            $row['enabled_default'] = $row['enabled'];
            $row['enabled'] = $this->getModel()->getRuleStatus($row['sid'], $row['enabled']);
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
     * list all installable rules including current status
     * @return array|mixed
     * @throws \Exception
     */
    public function listInstallableRulesetsAction()
    {
        $backend = new Backend();
        $response = $backend->configdRun("ids list installablerulesets");
        $data = json_decode($response, true);
        if ($data != null && array_key_exists("items", $data)) {
            $result = array("items"=>array());
            ksort($data['items']);
            foreach ($data['items'] as $filename => $fileinfo) {
                $item = array();
                $item['description'] = $fileinfo['description'];
                $item['filename'] = $fileinfo['filename'];

                // format timestamps
                if ($fileinfo['modified_local'] == null) {
                    $item['modified_local'] = null ;
                } else {
                    $item['modified_local'] = date('Y/m/d G:i', $fileinfo['modified_local']) ;
                }
                // retrieve status from model
                $item['enabled'] = (string)$this->getModel()->getFileNode($fileinfo['filename'])->enabled;
                $result['rows'][] = $item;
            }
            $result['rowCount'] = count($result['rows']);
            $result['total'] = count($result['rows']);
            $result['current'] = 1;
            return $result;
        } else {
            return array();
        }
    }

    /**
     * toggle usage of rule file or set enabled / disabled depending on parameters
     * @param $filename (target) rule file name
     * @param $enabled desired state enabled(1)/disabled(1), leave empty for toggle
     * @return array status 0/1 or error
     * @throws \Exception
     * @throws \Phalcon\Validation\Exception
     */
    public function toggleInstalledRulesetAction($filename, $enabled = null)
    {
        $result = array("status" => "none");
        if ($this->request->isPost()) {
            $backend = new Backend();
            $response = $backend->configdRun("ids list installablerulesets");
            $data = json_decode($response, true);
            if ($data != null && array_key_exists("items", $data) && array_key_exists($filename, $data['items'])) {
                $node = $this->getModel()->getFileNode($filename);
                if ($enabled == "0" || $enabled == "1") {
                    $node->enabled = (string)$enabled;
                } elseif ((string)$node->enabled == "1") {
                    $node->enabled = "0";
                } else {
                    $node->enabled = "1";
                }
                $result['status'] = $node->enabled;
                $this->getModel()->serializeToConfig();
                Config::getInstance()->save();
            } else {
                $result['status'] = "error";
            }
        }
        return $result;
    }

    /**
     * toggle rule enable status
     * @param $sid
     * @return array
     */
    public function toggleRuleAction($sid)
    {
        $ruleinfo = $this->getRuleInfoAction($sid);
        if (count($ruleinfo) > 0) {
            if ($ruleinfo['enabled_default'] != $ruleinfo['enabled']) {
                // if we're switching back to default, remove alter rule
                $this->getModel()->removeRule($sid) ;
            } elseif ($ruleinfo['enabled'] == 1) {
                $this->getModel()->disableRule($sid) ;
            } else {
                $this->getModel()->enableRule($sid) ;
            }
            $this->getModel()->serializeToConfig();
            Config::getInstance()->save();
        }
        return array();
    }

    /**
     * retrieve IDS settings
     * @return array IDS settings
     */
    public function getAction()
    {
        // define list of configurable settings
        $settingsNodes = array('general');
        $result = array();
        if ($this->request->isGet()) {
            $mdlIDS = $this->getModel();
            $result['ids'] = array();
            foreach ($settingsNodes as $key) {
                $result['ids'][$key] = $mdlIDS->$key->getNodes();
            }
        }
        return $result;
    }

    /**
     * update IDS settings
     * @return array status
     */
    public function setAction()
    {
        $result = array("result"=>"failed");
        if ($this->request->isPost()) {
            // load model and update with provided data
            $mdlIDS = $this->getModel();
            $mdlIDS->setNodes($this->request->getPost("ids"));

            // perform validation
            $valMsgs = $mdlIDS->performValidation();
            foreach ($valMsgs as $field => $msg) {
                if (!array_key_exists("validations", $result)) {
                    $result["validations"] = array();
                }
                $result["validations"]["ids.".$msg->getField()] = $msg->getMessage();
            }

            // serialize model to config and save
            if ($valMsgs->count() == 0) {
                $mdlIDS->serializeToConfig();
                Config::getInstance()->save();
                $result["result"] = "saved";
            }
        }
        return $result;
    }
}
