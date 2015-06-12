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

use \OPNsense\Base\ApiControllerBase;
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
                    $sortStr .= $sortKey . ' '. $sortOrd . ' ';
                }
            } else {
                $sortStr = 'sid';
            }
            if ($this->request->getPost('searchPhrase', 'string', '') != "") {
                $searchPhrase = 'msg,classtype,source,sid/"%'.$this->request->getPost('searchPhrase', 'string', '').'"';
            } else {
                $searchPhrase = '';
            }

            // add filter for classtype
            if ($this->request->getPost("classtype", "string", '') != "") {
                $searchPhrase .= "classtype/".$this->request->getPost("classtype", "string", '').' ';
            }

            // request list of installed rules
            $backend = new Backend();
            $response = $backend->configdpRun("ids list installedrules", array($itemsPerPage,
                ($currentPage-1)*$itemsPerPage,
                $searchPhrase, $sortStr));
            $data = json_decode($response, true);

            if ($data != null && array_key_exists("rows", $data)) {
                $result = array();
                $result['rows'] = $data['rows'];
                // update rule status with own administration
                foreach ($result['rows'] as &$row) {
                    $row['enabled'] = $this->getModel()->getRuleStatus($row['sid'], $row['enabled']);
                }

                $result['rowCount'] = count($result['rows']);
                $result['total'] = $data['total_rows'];
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
        $response = $backend->configdpRun("ids list installedrules", array(1, 0,'sid/'.$sid));
        $data = json_decode($response, true);

        if ($data != null && array_key_exists("rows", $data) && count($data['rows'])>0) {
            $row = $data['rows'][0];
            // set current enable status (default + registered offset)
            $row['enabled'] = $this->getModel()->getRuleStatus($row['sid'], $row['enabled']);
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
     * @param $sid
     * @return array
     */
    public function toggleRuleAction($sid)
    {
        $ruleinfo = $this->getRuleInfoAction($sid);
        if (count($ruleinfo) > 0) {
            if ($ruleinfo['enabled'] == 1) {
                $this->getModel()->disableRule($sid) ;
            } else {
                $this->getModel()->enableRule($sid) ;
            }
            $this->getModel()->serializeToConfig();
            Config::getInstance()->save();
        }
        return array();
    }
}
