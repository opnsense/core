<?php

/*
 * Copyright (C) 2017-2021 Deciso B.V.
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

namespace OPNsense\Diagnostics\Api;

use Phalcon\Filter;
use OPNsense\Base\ApiControllerBase;
use OPNsense\Core\Backend;
use OPNsense\Core\Config;

/**
 * Class FirewallController
 * @package OPNsense\Diagnostics\Api
 */
class FirewallController extends ApiControllerBase
{

    /**
     * retrieve firewall log
     * @return array
     */
    public function logAction()
    {
        if ($this->request->isGet()) {
            $this->sessionClose(); // long running action, close session
            $digest = empty($this->request->get('digest')) ? "" : $this->request->get('digest');
            $limit = empty($this->request->get('limit')) ? 1000 : $this->request->get('limit');
            $backend = new Backend();
            $response = $backend->configdpRun("filter read log", array($limit, $digest));
            $logoutput = json_decode($response, true);
            return $logoutput;
        } else {
            return null;
        }
    }

    /**
     * retrieve firewall log filter choices
     * @return array
     */
    public function logFiltersAction()
    {
        $config = Config::getInstance()->object();
        $interfaces = [];
        if ($config->interfaces->count() > 0) {
            foreach ($config->interfaces->children() as $key => $node) {
                // XXX: Omit group types since they don't link to actual interfaces.
                if (isset($node->type) && (string)$node->type == 'group') {
                    continue;
                } elseif ((string)$node->if == 'openvpn') {
                    continue;
                }
                $interfaces[] = !empty((string)$node->descr) ? (string)$node->descr : $key;
            }
        }
        sort($interfaces, SORT_NATURAL | SORT_FLAG_CASE);
        return [
            'action' => ['pass', 'block', 'rdr', 'nat'], /* XXX binat is possible but not yet supported in rules */
            'interface_name' => $interfaces,
            'dir' => ['in', 'out'],
        ];
    }

    /**
     * retrieve firewall stats
     * @return array
     */
    public function statsAction()
    {
        if ($this->request->isGet()) {
            $this->sessionClose(); // long running action, close session
            $limit = empty($this->request->get('limit')) ? 5000 : $this->request->get('limit');
            $group_by = empty($this->request->get('group_by')) ? "interface" : $this->request->get('group_by');
            $records = json_decode((new Backend())->configdpRun("filter read log", array($limit)), true);
            $response = array();
            if (!empty($records)) {
                $tmp_stats = array();
                foreach ($records as $record) {
                    if (isset($record[$group_by])) {
                        if (!isset($tmp_stats[$record[$group_by]])) {
                            $tmp_stats[$record[$group_by]] = 0;
                        }
                        $tmp_stats[$record[$group_by]]++;
                    }
                }
                arsort($tmp_stats);
                $label_map = array();
                switch ($group_by) {
                    case 'interface':
                        $label_map["lo0"] = gettext("loopback");
                        if (Config::getInstance()->object()->interfaces->count() > 0) {
                            foreach (Config::getInstance()->object()->interfaces->children() as $k => $n) {
                                $label_map[(string)$n->if] = !empty((string)$n->descr) ? (string)$n->descr : $k;
                            }
                        }
                        break;
                    case 'proto':
                      // proto
                        break;
                }
                $recno = $top_cnt = 0;
                foreach ($tmp_stats as $key => $value) {
                    // top 10 + other
                    if ($recno < 10) {
                        $response[] = [
                            "label" => !empty($label_map[$key]) ? $label_map[$key] : $key,
                            "value" => $value
                        ];
                        $top_cnt += $value;
                    } else {
                        $response[] = ["label" => gettext("other"), "value" => count($records) - $top_cnt];
                        break;
                    }
                    $recno++;
                }
            }
            return $response;
        } else {
            return null;
        }
    }

    /**
     * query pf states
     */
    public function queryStatesAction()
    {
        if ($this->request->isPost()) {
            $this->sessionClose();
            $ifnames = [];
            $ifnames["lo0"] = gettext("loopback");
            if (Config::getInstance()->object()->interfaces->count() > 0) {
                foreach (Config::getInstance()->object()->interfaces->children() as $k => $n) {
                    $ifnames[(string)$n->if] = !empty((string)$n->descr) ? (string)$n->descr : $k;
                }
            }

            $filter = new Filter([
                'query' => function ($value) {
                    return preg_replace("/[^0-9,a-z,A-Z, ,\/,*,\-,_,.,\#]/", "", $value);
                }
            ]);
            $searchPhrase = '';
            $ruleId = '';
            $sortBy = '';
            $itemsPerPage = $this->request->getPost('rowCount', 'int', 9999);
            $currentPage = $this->request->getPost('current', 'int', 1);

            if ($this->request->getPost('ruleid', 'string', '') != '') {
                $ruleId = $filter->sanitize($this->request->getPost('ruleid'), 'query');
            }

            if ($this->request->getPost('searchPhrase', 'string', '') != '') {
                $searchPhrase = $filter->sanitize($this->request->getPost('searchPhrase'), 'query');
            }
            if ($this->request->has('sort') && is_array($this->request->getPost("sort"))) {
                $tmp = array_keys($this->request->getPost("sort"));
                $sortBy = $tmp[0] . " " . $this->request->getPost("sort")[$tmp[0]];
            }

            $response = (new Backend())->configdpRun('filter list states', [$searchPhrase, $itemsPerPage,
                ($currentPage - 1) * $itemsPerPage, $ruleId, $sortBy]);
            $response = json_decode($response, true);
            if ($response != null) {
                foreach ($response['details'] as &$row) {
                    $isipv4 = strpos($row['src_addr'], ':') === false;
                    $row['interface'] = !empty($ifnames[$row['iface']]) ? $ifnames[$row['iface']] : $row['iface'];
                }
                return [
                    'rows' => $response['details'],
                    'rowCount' => count($response['details']),
                    'total' => $response['total_entries'],
                    'current' => (int)$currentPage
                ];
            }
        }
        return [];
    }

    /**
     * delete / drop a specific state by state+creator id
     */
    public function delStateAction($stateid, $creatorid)
    {
        if ($this->request->isPost()) {
            $filter = new Filter([
                'hexval' => function ($value) {
                    return preg_replace("/[^0-9,a-f,A-F]/", "", $value);
                }
            ]);
            $response = (new Backend())->configdpRun("filter kill state", [
                $filter->sanitize($stateid, "hexval"),
                $filter->sanitize($creatorid, "hexval")
            ]);
            return [
                'result' => $response
            ];
        }
        return ['result' => ""];
    }

    /**
     * drop pf states by filter and/or rule id
     */
    public function killStatesAction()
    {
        if ($this->request->isPost()) {
            $filter = new Filter([
                'query' => function ($value) {
                    return preg_replace("/[^0-9,a-z,A-Z, ,\/,*,\-,_,.,\#]/", "", $value);
                },
                'hexval' => function ($value) {
                    return preg_replace("/[^0-9,a-f,A-F]/", "", $value);
                }
            ]);
            $ruleid = null;
            $filterString = null;
            if (!empty($this->request->getPost('filter'))) {
                $filterString = $filter->sanitize($this->request->getPost('filter'), 'query');
            }
            if (!empty($this->request->getPost('ruleid'))) {
                $ruleid = $filter->sanitize($this->request->getPost('ruleid'), 'hexval');
            }
            if ($filterString != null || $ruleid != null) {
                $response = (new Backend())->configdpRun("filter kill states", [$filterString, $ruleid]);
                $response = json_decode($response, true);
                if ($response != null) {
                    return ["result" => "ok", "dropped_states" => $response['dropped_states']];
                }
            }
        }
        return ["result" => "failed"];
    }

    /**
     * return rule'ids and descriptions from running config
     */
    public function listRuleIdsAction()
    {
        if ($this->request->isGet()) {
            $response = json_decode((new Backend())->configdpRun("filter list rule_ids"), true);
            if ($response != null) {
                return ["items" => $response];
            }
        }
        return ["items" => []];
    }

    /**
     * flush all pf states
     */
    public function flushStatesAction()
    {
        if ($this->request->isPost()) {
            (new Backend())->configdRun("filter flush states");
            return ["result" => "ok"];
        }
        return ["result" => "failed"];
    }

    /**
     * flush pf source tracking
     */
    public function flushSourcesAction()
    {
        if ($this->request->isPost()) {
            (new Backend())->configdRun("filter flush sources");
            return ["result" => "ok"];
        }
        return ["result" => "failed"];
    }
}
