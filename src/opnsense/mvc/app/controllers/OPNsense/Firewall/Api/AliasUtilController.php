<?php

/*
 * Copyright (C) 2018 Deciso B.V.
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

namespace OPNsense\Firewall\Api;

use OPNsense\Base\ApiControllerBase;
use OPNsense\Core\Backend;
use OPNsense\Firewall\Alias;
use OPNsense\Core\Config;

/**
 * @package OPNsense\Firewall
 */
class AliasUtilController extends ApiControllerBase
{
    /**
     * @var null|BaseModel model object to work on
     */
    private $modelHandle = null;

    /**
     * Get (or create) model object
     * @return null|BaseModel
     */
    private function getModel()
    {
        if ($this->modelHandle == null) {
            $this->modelHandle = new Alias();
        }
        return $this->modelHandle;
    }

    /**
     * fetch alias by name
     * @param string $name name to list
     */
    private function getAlias($name)
    {
        foreach ($this->getModel()->aliases->alias->iterateItems() as $key => $alias) {
            if ((string)$alias->name == $name) {
                return $alias;
            }
        }
        return null;
    }

    /**
     * list active alias tables
     * @return array alias names
     */
    public function aliasesAction()
    {
        $this->sessionClose();
        $backend = new Backend();
        $result = json_decode($backend->configdRun("filter list tables json"));
        if ($result !== null) {
            // return sorted (case insensitive)
            natcasesort($result);
            $result = array_values($result);
        }
        return $result;
    }

    /**
     * list alias table
     * @param string $alias name to list
     * @return array alias contents
     * @throws \Exception
     */
    public function listAction($alias)
    {
        $this->sessionClose();

        $itemsPerPage = intval($this->request->getPost('rowCount', 'int', 9999));
        $currentPage = intval($this->request->getPost('current', 'int', 1));
        $offset = ($currentPage - 1) * $itemsPerPage;

        $backend = new Backend();
        $entries = json_decode($backend->configdpRun("filter list table", array($alias, "json")), true);
        $entry_keys = array_keys($entries);

        if ($this->request->hasPost('searchPhrase') && $this->request->getPost('searchPhrase') !== '') {
            $searchPhrase = $this->request->getPost('searchPhrase');
            $entry_keys = array_filter($entry_keys, function ($value) use ($searchPhrase) {
                return strpos($value, $searchPhrase) !== false;
            });
        }

        if (
            $this->request->hasPost('sort') &&
            is_array($this->request->getPost('sort')) &&
            array_key_exists('ip', $this->request->getPost('sort')) &&
            $this->request->getPost('sort')['ip'] === 'desc'
        ) {
            rsort($entry_keys);
        } else {
            sort($entry_keys);
        }

        $formatted = array_map(function ($value) use (&$entries) {
            $item = ['ip' => $value];
            foreach ($entries[$value] as $ekey => $evalue) {
                $item[$ekey] = $evalue;
            }
            return $item;
        }, array_slice($entry_keys, $offset, $itemsPerPage));

        if (
            $this->request->hasPost('sort') &&
            is_array($this->request->getPost('sort')) &&
            !array_key_exists('ip', $this->request->getPost('sort'))
        ) {
            $sortcolumn = array_key_first($this->request->getPost('sort'));
            $sort_order = $this->request->getPost('sort')[$sortcolumn];
            if (!empty(array_column($formatted, $sortcolumn))) {
                array_multisort(array_column($formatted, $sortcolumn), $sort_order == 'asc' ? SORT_ASC : SORT_DESC, $formatted);
            }
        }

        return [
            'total' => count($entry_keys),
            'rowCount' => $itemsPerPage,
            'current' => $currentPage,
            'rows' => $formatted,
        ];
    }

    /**
     * update bogons table
     * @return array status
     */
    public function update_bogonsAction()
    {
        $this->sessionClose();
        $backend = new Backend();
        $backend->configdRun('filter update bogons');
        return array('status' => 'done');
    }

    /**
     * flush alias table
     * @param string $alias name to flush
     * @return array status
     */
    public function flushAction($alias)
    {
        if ($this->request->isPost()) {
            $this->sessionClose();
            $backend = new Backend();
            $backend->configdpRun("filter delete table", array($alias, "ALL"));
            return array("status" => "done");
        } else {
            return array("status" => "failed");
        }
    }

    /**
     * delete item from alias table
     * @param string $alias name
     * @return array status
     */
    public function deleteAction($alias)
    {
        $this->sessionClose();
        if ($this->request->isPost() && $this->request->hasPost("address")) {
            Config::getInstance()->lock();
            $address = $this->request->getPost("address");
            $cnfAlias = $this->getAlias($alias);
            if ($cnfAlias !== null && in_array($cnfAlias->type, array('host', 'network'))) {
                // update local administration, remove address when found for static types
                // XXX: addresses from "pfctl -t xxx -T show" don't always match our input, we probably need a
                //      better address matching at some point in time.
                $items = !empty((string)$cnfAlias->content) ? explode("\n", $cnfAlias->content) : array();
                if (strpos($address, "/") === false) {
                    $address_mask = $address . "/" . (strpos($address, ":") ? '128' : '32');
                } else {
                    $address_mask = $address;
                }
                $is_found = false;
                foreach (array($address_mask, $address) as $item) {
                    $index = array_search($item, $items);
                    if ($index !== false) {
                        unset($items[$index]);
                        $is_found = true;
                    }
                }
                if ($is_found) {
                    $cnfAlias->content = implode("\n", $items);
                    $this->getModel()->serializeToConfig();
                    Config::getInstance()->save();
                    // flush to disk,
                    (new Backend())->configdRun('template reload OPNsense/Filter');
                }
            }

            $backend = new Backend();
            $backend->configdpRun("filter delete table", array($alias, $address));
            return array("status" => "done");
        } else {
            return array("status" => "failed");
        }
    }

    /**
     * add item to alias table
     * @param string $alias name
     * @return array status
     */
    public function addAction($alias)
    {
        $this->sessionClose();
        if ($this->request->isPost() && $this->request->hasPost("address")) {
            Config::getInstance()->lock();
            $address = $this->request->getPost("address");
            if (preg_match("/[^0-9a-f\:\.\/_]/", $address)) {
                return array("status" => "not_an_address");
            }
            $cnfAlias = $this->getAlias($alias);
            if ($cnfAlias !== null && in_array($cnfAlias->type, array('host', 'network'))) {
                // update local administration, add address when not found for static types
                $items = !empty((string)$cnfAlias->content) ? explode("\n", $cnfAlias->content) : array();
                if (strpos($address, "/") === false && $cnfAlias->type == 'network') {
                    // add mask
                    $address .= "/" . (strpos($address, ":") ? '128' : '32');
                }
                if (!array_search($address, $items)) {
                    $items[] = $address;
                    $cnfAlias->content = implode("\n", $items);
                    $this->getModel()->serializeToConfig();
                    Config::getInstance()->save();
                    // flush to disk,
                    (new Backend())->configdRun('template reload OPNsense/Filter');
                }
            }
            if ($cnfAlias !== null) {
                // only allow additions to known aliases
                $backend = new Backend();
                $backend->configdpRun("filter add table", array($alias, $address));
                return array("status" => "done");
            } else {
                return array("status" => "failed", "status_msg" => sprintf("non existing alias %s", $alias));
            }
        } else {
            return array("status" => "failed");
        }
    }

    /**
     * API handler to look up in which rules an IP is used (either explicitly or included in a range).
     *
     * @return array Array with indexes 'status' (whether the call succeeded) and 'matches' (which rules match this IP,
     *               only present if the call was succesful.)
     * @throws \Exception
     */
    public function find_referencesAction()
    {
        $this->sessionClose();
        if ($this->request->isPost() && $this->request->hasPost('ip')) {
            $ip = $this->request->getPost('ip');
            if (preg_match("/[^0-9a-f\:\.\/_]/", $ip)) {
                return ['status' => 'Not an IP address!'];
            }

            $backend = new Backend();
            return json_decode($backend->configdpRun('filter find_table_references', [$ip]), true);
        } else {
            return ['status' => 'IP parameter not specified!'];
        }
    }
}
