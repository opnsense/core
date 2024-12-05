<?php

/**
 *    Copyright (C) 2015-2020 Deciso B.V.
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

use OPNsense\Base\ApiControllerBase;
use OPNsense\Core\Backend;
use OPNsense\TrafficShaper\TrafficShaper;

/**
 * Class ServiceController
 * @package OPNsense\TrafficShaper
 */
class ServiceController extends ApiControllerBase
{
    /**
     * reconfigure ipfw, generate config and reload
     */
    public function reconfigureAction()
    {
        if ($this->request->isPost()) {
            $backend = new Backend();
            $backend->configdRun('template reload OPNsense/IPFW');
            $bckresult = trim($backend->configdRun("ipfw reload"));
            if ($bckresult == "OK") {
                $status = "ok";
            } else {
                $status = "error reloading shaper (" . $bckresult . ")";
            }

            return array("status" => $status);
        } else {
            return array("status" => "failed");
        }
    }

    /**
     * flush all ipfw rules
     */
    public function flushreloadAction()
    {
        if ($this->request->isPost()) {
            $backend = new Backend();
            $status = trim($backend->configdRun("ipfw flush"));
            $status = trim($backend->configdRun("ipfw reload"));
            return array("status" => $status);
        } else {
            return array("status" => "failed");
        }
    }

    /**
     * fetch current statistics
     */
    public function statisticsAction()
    {
        $result = array("status" => "failed");
        if ($this->request->isGet()) {
            $ipfwstats = json_decode((new Backend())->configdRun("ipfw stats"), true);
            if ($ipfwstats != null) {
                // ipfw stats are structured as they would be using the various ipfw commands, let's reformat
                // into something easier to handle from the UI and attach model data.
                $result['status'] = "ok";
                $result['items'] = array();
                $pipenrs = array();
                if (!empty($ipfwstats['pipes'])) {
                    $shaperModel = new TrafficShaper();

                    // link pipe and queue descriptions and sort
                    foreach (['pipes', 'queues'] as $objectType) {
                        if ($objectType == 'pipes') {
                            $root = $shaperModel->pipes->pipe;
                        } else {
                            $root = $shaperModel->queues->queue;
                        }
                        $idfield = $objectType == 'queues' ? "flow_set_nr" : "pipe";
                        foreach ($ipfwstats[$objectType] as &$ipfwObject) {
                            $ipfwObject['description'] = "";
                            foreach ($root->iterateItems() as $node) {
                                if ((string)$node->number == $ipfwObject[$idfield]) {
                                      $ipfwObject['description'] = (string)$node->description;
                                      $ipfwObject['uuid'] = (string)$node->getAttribute('uuid');
                                      break;
                                }
                            }
                        }
                        uasort($ipfwstats[$objectType], function ($item1, $item2) {
                            return $item1['description'] <=> $item2['description'];
                        });
                    }

                    foreach ($ipfwstats['pipes'] as $pipeid => &$pipe) {
                        $pipenrs[] = $pipeid;
                        $item = $pipe;
                        $item['type'] = "pipe";
                        $item['id'] = $pipeid;
                        // move flows to "template" queue
                        $item['flows'] = [];
                        $result['items'][] = $item;
                        if (!empty($pipe['flowset'])) {
                            // template queues seem to be automatically attached to pipes
                            $item = $pipe['flowset'];
                            $item['type'] = "queue";
                            $item['template'] = true;
                            $item['pipe'] = $pipeid;
                            $item['id'] = $pipeid . "." . $item['flow_set_nr'];
                            $item['flows'] = $pipe['flows'];
                            $result['items'][] = $item;
                        }
                        foreach ($ipfwstats['queues'] as $queueid => $queue) {
                            if ($queue['sched_nr'] == $pipeid) {
                                // XXX: sched_nr seems to be the linking pin to pipe
                                $item = $queue;
                                $item['type'] = "queue";
                                $item['id'] = $pipeid . "." . $queueid;
                                $result['items'][] = $item;
                            }
                        }
                    }
                    // XXX: If not directly connected, we better still list the queues so we know what we miss.
                    //      current assumption is this doesn't happen on our setups, should be removed in the future
                    $stray_queues = false;
                    foreach ($ipfwstats['queues'] as $queueid => $queue) {
                        if (!in_array($queue['sched_nr'], $pipenrs)) {
                            if (!$stray_queues) {
                                $result['items'][] = [
                                    "type" => "unknown",
                                    "id" => "XXXXX"
                                ];
                                $stray_queues = true;
                            }
                            $item = $queue;
                            $item['type'] = "queue";
                            $item['id'] = "XXXXX." . $queueid;
                            $result['items'][] = $item;
                        }
                    }
                    // link rules (with statistics)
                    foreach ($result['items'] as &$item) {
                        $item['rules'] = [];
                        if ($item['type'] == 'pipe') {
                            continue;
                        }
                        $idfield = empty($item['template']) ? "flow_set_nr" : "pipe";
                        $rule_type = empty($item['template']) ? "queues" : "pipes";
                        if (!empty($ipfwstats['rules'][$rule_type])) {
                            foreach ($ipfwstats['rules'][$rule_type] as $rule) {
                                if ($item[$idfield] == $rule['attached_to']) {
                                    $rule['description'] = "";
                                    if ($rule['rule_uuid'] != null) {
                                        $node = $shaperModel->getNodeByReference("rules.rule.{$rule['rule_uuid']}");
                                        if ($node != null) {
                                            $rule['description'] = (string)$node->description;
                                        }
                                    }
                                    $item['rules'][] = $rule;
                                }
                            }
                        }
                    }
                }
            }
        }
        return $result;
    }
}
