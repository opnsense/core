<?php

/**
 *    Copyright (C) 2017 Deciso B.V.
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
 */

namespace OPNsense\Diagnostics\Api;

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
            "interface_name" => $interfaces,
            "dir" => ["in", "out"],
            "action" => ["pass", "block"]
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
}
