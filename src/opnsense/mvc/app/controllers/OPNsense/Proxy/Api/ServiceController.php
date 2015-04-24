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
namespace OPNsense\Proxy\Api;

use \OPNsense\Base\ApiControllerBase;
use \OPNsense\Core\Backend;
use \OPNsense\Proxy\Proxy;

/**
 * Class ServiceController
 * @package OPNsense\Proxy
 */
class ServiceController extends ApiControllerBase
{
    /**
     * start proxy service
     * @return array
     */
    public function startAction()
    {
        $backend = new Backend();
        $response = $backend->configdRun("proxy start",true);
        return array("response" => $response);
    }

    /**
     * stop proxy service
     * @return array
     */
    public function stopAction()
    {
        $backend = new Backend();
        $response = $backend->configdRun("proxy stop");
        return array("response" => $response);
    }

    /**
     * restart proxy service
     * @return array
     */
    public function restartAction()
    {
        $backend = new Backend();
        $response = $backend->configdRun("proxy restart");
        return array("response" => $response);
    }

    /**
     * retrieve status of squid proxy
     * @return array
     * @throws \Exception
     */
    public function statusAction()
    {
        $backend = new Backend();
        $response = $backend->configdRun("proxy status");

        if (strpos($response, "not running") > 0) {
            $status = "stopped";
        } elseif (strpos($response, "is running") > 0) {
            $status = "running";
        } else {
            $status = "unkown";
        }


        return array("status" => $status);
    }

    /**
     * reconfigure squid, generate config and reload
     */
    public function reconfigureAction()
    {
        // close session for long running action
        session_write_close();

        $mdlProxy = new Proxy();
        $backend = new Backend();

        $runStatus = $this->statusAction();

        // stop squid when disabled
        if ($runStatus['status'] == "running" && $mdlProxy->general->enabled->__toString() == 0) {
            $this->stopAction();
        }

        // generate template
        $backend->configdRun("template reload OPNsense.Proxy");

        // (res)start daemon
        if ($mdlProxy->general->enabled->__toString() == 1) {
            if ($runStatus['status'] == "running") {
                $backend->configdRun("proxy reconfigure");
            } else {
                $this->startAction();
            }
        }

        return array("status" => "ok");
    }
}
