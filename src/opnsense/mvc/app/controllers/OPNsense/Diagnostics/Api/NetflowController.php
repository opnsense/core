<?php

/**
 *    Copyright (C) 2016 Deciso B.V.
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

namespace OPNsense\Diagnostics\Api;

use OPNsense\Base\ApiControllerBase;
use OPNsense\Diagnostics\Netflow;
use OPNsense\Core\Config;
use OPNsense\Core\Backend;

/**
 * Class NetflowController
 * @package OPNsense\Netflow
 */
class NetflowController extends ApiControllerBase
{
    /**
     *
     */
    public function isEnabledAction()
    {
        $result = array('netflow' => 0, "local" => 0);
        $mdlNetflow = new Netflow();
        if ((string)$mdlNetflow->capture->targets != "" && (string)$mdlNetflow->capture->interfaces != "") {
            $result['netflow'] = 1;
            if ((string)$mdlNetflow->collect->enable == 1) {
                $result['local'] = 1;
            }
        }
        return $result;
    }

    /**
     * retrieve Netflow settings
     * @return array
     */
    public function getconfigAction()
    {
        $result = array();
        if ($this->request->isGet()) {
            $mdlNetflow = new Netflow();
            $result['netflow'] = $mdlNetflow->getNodes();
        }
        return $result;
    }

    /**
     * update netflow configuration fields
     * @return array
     * @throws \OPNsense\Base\ValidationException
     */
    public function setconfigAction()
    {
        $result = array("result" => "failed");
        if ($this->request->hasPost("netflow")) {
            // load model and update with provided data
            $mdlNetflow = new Netflow();
            $mdlNetflow->setNodes($this->request->getPost("netflow"));
            if ((string)$mdlNetflow->collect->enable == 1) {
                // add localhost (127.0.0.1:2056) as target if local capture is configured
                if (strpos((string)$mdlNetflow->capture->targets, "127.0.0.1:2056") === false) {
                    if ((string)$mdlNetflow->capture->targets != "") {
                        $targets = explode(",", (string)$mdlNetflow->capture->targets);
                    } else {
                        $targets = array();
                    }
                    $targets[] = "127.0.0.1:2056";
                    $mdlNetflow->capture->targets = implode(',', $targets);
                }
            }

            // perform validation
            $validations = $mdlNetflow->validate();
            if (count($validations)) {
                $result['validations'] = array();
                foreach ($validations as $valkey => $validation) {
                    $result['validations']['netflow.' . $valkey] = $validation;
                }
            } else {
                // serialize model to config and save
                $mdlNetflow->serializeToConfig();
                Config::getInstance()->save();
                $result["result"] = "saved";
            }
        }
        return $result;
    }

    /**
     * configure start/stop netflow
     * @return array
     */
    public function reconfigureAction()
    {
        if ($this->request->isPost()) {
            // close session for long running action
            $this->sessionClose();

            // reconfigure netflow
            $backend = new Backend();
            $backend->configdRun('template reload OPNsense/Netflow');
            // restart netflow, by calling stop (which will always stop the collectors) and start
            // (which will only start if there are collectors configured)
            $backend->configdRun("netflow stop");
            $backend->configdRun("netflow start");
            $mdlNetflow = new Netflow();
            if ((string)$mdlNetflow->collect->enable == 1) {
                // don't try to restart the collector, to avoid data loss on reconfigure
                $response = $backend->configdRun("netflow collect status");
                if (strpos($response, "not running") > 0) {
                    $backend->configdRun("netflow collect start");
                }
                // aggregation process maybe restarted at all time
                $backend->configdRun("netflow aggregate restart");
            } else {
                // stop collector and aggregator
                $backend->configdRun("netflow collect stop");
                $backend->configdRun("netflow aggregate stop");
            }
            return array("status" => "ok");
        } else {
            return array("status" => "error");
        }
    }

    /**
     * request netflow status
     * @return array
     */
    public function statusAction()
    {
        $backend = new Backend();
        $status = trim($backend->configdRun("netflow status"));
        if (strpos($status, "netflow is active") !== false) {
            // active, return status active + number of configured collectors
            $collectors = trim(explode(')', explode(':', $status)[1])[0]);
            return array("status" => "active", "collectors" => $collectors);
        } else {
            // inactive
            return array("status" => "inactive");
        }
    }

    /**
     * Retrieve netflow cache statistics
     * @return array cache statistics per netgraph node
     */
    public function cacheStatsAction()
    {
        $backend = new Backend();
        $response = $backend->configdRun("netflow cache stats json");
        $stats = json_decode($response, true);
        if ($stats != null) {
            return $stats;
        } else {
            return array();
        }
    }
}
