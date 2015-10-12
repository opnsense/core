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
namespace OPNsense\CaptivePortal\Api;

use \OPNsense\Base\ApiControllerBase;
use \OPNsense\Core\Backend;
use \OPNsense\CaptivePortal\CaptivePortal;

/**
 * Class ServiceController
 * @package OPNsense\CaptivePortal
 */
class ServiceController extends ApiControllerBase
{

    /**
     * reconfigure captive portal
     */
    public function reconfigureAction()
    {
        if ($this->request->isPost()) {
            // close session for long running action
            $this->sessionClose();

            $backend = new Backend();
            // the ipfw rules need to know about all the zones, so we need to reload ipfw for the portal to work
            $backend->configdRun("template reload OPNsense.IPFW");
            $bckresult = trim($backend->configdRun("ipfw reload"));
            if ($bckresult == "OK") {
                // generate captive portal config
                $bckresult = trim($backend->configdRun("template reload OPNsense.Captiveportal"));
                if ($bckresult == "OK") {
                    $mdlCP = new CaptivePortal();
                    if ($mdlCP->isEnabled()) {
                        $bckresult = trim($backend->configdRun("captiveportal restart"));
                        if ($bckresult == "OK") {
                            $status = "ok";
                        } else {
                            $status = "error reloading captive portal";
                        }
                    } else {
                        $backend->configdRun("captiveportal restart");
                        $status = "ok";
                    }
                } else {
                    $status = "error reloading captive portal template";
                }
            } else {
                $status = "error reloading captive portal rules (".$bckresult.")";
            }

            return array("status" => $status);
        } else {
            return array("status" => "failed");
        }
    }
}
