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
 * Class SessionController
 * @package OPNsense\CaptivePortal
 */
class SessionController extends ApiControllerBase
{
    /**
     * list client sessions
     * @param int $zoneid zone number
     * @return array|mixed
     */
    public function listAction($zoneid = 0)
    {
        $mdlCP = new CaptivePortal();
        $cpZone = $mdlCP->getByZoneID($zoneid);
        if ($cpZone != null) {
            $backend = new Backend();
            $allClientsRaw = $backend->configdpRun(
                "captiveportal list_clients",
                array($cpZone->zoneid, 'json')
            );
            $allClients = json_decode($allClientsRaw, true);

            return $allClients;
        } else {
            // illegal zone, return empty response
            return array();
        }
    }

    /**
     * return list of available zones
     * @return array available zones
     */
    public function zonesAction()
    {
        $response = array();
        $mdlCP = new CaptivePortal();
        foreach ($mdlCP->zones->zone->__items as $zone) {
            $response[(string)$zone->zoneid] = (string)$zone->description;
        }
        asort($response);
        return $response;
    }

    /**
     * disconnect a client
     * @param string|int $zoneid zoneid
     * @return array|mixed
     */
    public function disconnectAction($zoneid = 0)
    {
        if ($this->request->isPost() && $this->request->hasPost('sessionId')) {
            $backend = new Backend();
            $statusRAW = $backend->configdpRun(
                "captiveportal disconnect",
                array($zoneid, $this->request->getPost('sessionId'), 'json')
            );
            $status = json_decode($statusRAW, true);
            if ($status != null) {
                return $status;
            } else {
                return array("status" => "Illegal response");
            }
        }
        return array();
    }
}
