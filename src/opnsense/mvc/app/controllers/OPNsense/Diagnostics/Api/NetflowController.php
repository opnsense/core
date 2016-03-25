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

use \OPNsense\Base\ApiControllerBase;
use \OPNsense\Diagnostics\Netflow;
use \OPNsense\Core\Config;
use \OPNsense\Core\Backend;

/**
 * Class NetflowController
 * @package OPNsense\Netflow
 */
class NetflowController extends ApiControllerBase
{
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
     * @throws \Phalcon\Validation\Exception
     */
    public function setconfigAction()
    {
        $result = array("result"=>"failed");
        if ($this->request->hasPost("netflow")) {
            // load model and update with provided data
            $mdlNetflow = new Netflow();
            $mdlNetflow->setNodes($this->request->getPost("netflow"));

            // perform validation
            $validations = $mdlNetflow->validate();
            if (count($validations)) {
                $result['validations'] = array();
                foreach ($validations as $valkey => $validation) {
                    $result['validations']['netflow.'.$valkey] = $validation;
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
            $backend->configdRun("template reload OPNsense.Netflow");
            // restart netflow, by calling stop (which will always stop the collectors) and start
            // (which will only start if there are collectors configured)
            $backend->configdRun("netflow stop");
            $backend->configdRun("netflow start");
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
    public function cache_statsAction()
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
