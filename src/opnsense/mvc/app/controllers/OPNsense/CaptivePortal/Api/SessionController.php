<?php
/**
 *    Copyright (C) 2015-2016 Deciso B.V.
 *    Copyright (C) 2016 Fabian Franz
 *    Copyright (C) 2016 @zvs44
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
    
    /**
     * logon client to zone, must use post type of request
     * @param int|string zone id number
     * @return array
     */
    public function connectAction($zoneid = 0)
    {
        if(!$this->request->isPost())
        {
            return array("clientState" => 'Error', "errorMessage" => "not a POST request");
        }
        // get username, IP and auth server from post
        $username = $this->request->getPost("user", "striptags", null);
        $client_ip = $this->request->getPost("ip", "striptags", null);
        $timeout = $this->request->getPost("timeout", "striptags", null);
        // If an explicit client IP wasn't provided
        if(!$client_ip)
        {
            return array("clientState" => 'Error', "errorMessage" => "No client ip given");
        }
        
        $mdlCP = new CaptivePortal();
        $cpZone = $mdlCP->getByZoneID($zoneid);
        if ($cpZone == null) {
            return array("clientState" => 'Error', "errorMessage" => "Zone not found");
        }
        // is there already a session for this user?
        $clientSession = $mdlCP->clientSession((string)$cpZone->zoneid, $client_ip);
        if ($clientSession['clientState'] == 'AUTHORIZED') {
            // already authorized, return current session
            return $clientSession;
        }
        // allow client to this captiveportal zone
        $backend = new Backend();
        $CPsession = $backend->configdpRun(
            "captiveportal allow", array((string)$cpZone->zoneid, $username, $client_ip, null, 'json')
        );
        
        // Attempt to decode the session data returned from the config daemon
        $CPsession = json_decode($CPsession, true);
        
        // Push session restrictions, if they apply
        if ($CPsession != null && array_key_exists('sessionId', $CPsession) && $timeout) {
            // If a timeout has been specified in the POST request that we received then apply it
            $backend->configdpRun(
                "captiveportal set session_restrictions",
                array((string)$cpZone->zoneid,
                    $CPsession['sessionId'],
                    $timeout
                    )
            );
        }
        
        if ($CPsession != null) {
            // Return the details on the newly-established session
            return $CPsession;
        }
        // configd returned something other than json
        return array("clientState" => 'UNKNOWN', "ipAddress" => $client_ip);
    }
}
