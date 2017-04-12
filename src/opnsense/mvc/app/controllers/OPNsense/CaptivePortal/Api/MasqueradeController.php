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
class MasqueradeController extends ApiControllerBase
{
    /**
     * request client session data
     * @param $zoneid captive portal zone
     * @param $clientIp IP of the client we are interested in, or will be determined using getClientIp()
     * @return array
     */
    private function clientSession($zoneid, $clientIp = null)
    {
        // If we've not been passed a client IP then work it out
        if($clientIp == null)
        {
            $clientIp = $this->getClientIp();
        }
        
        $backend = new Backend();
        $allClientsRaw = $backend->configdpRun(
            "captiveportal list_clients",
            array($zoneid, 'json')
        );
        $allClients = json_decode($allClientsRaw, true);
        if ($allClients != null) {
            // search for client by ip address
            foreach ($allClients as $connectedClient) {
                if ($connectedClient['ipAddress'] == $clientIp) {
                    // client is authorized in this zone according to our administration
                    $connectedClient['clientState'] = 'AUTHORIZED';
                    return $connectedClient;
                }
            }
        }

        // return Unauthorized including authentication requirements
        $result = array('clientState' => "NOT_AUTHORIZED", "ipAddress" => $clientIp);
        $mdlCP = new CaptivePortal();
        $cpZone = $mdlCP->getByZoneID($zoneid);
        if ($cpZone != null && trim((string)$cpZone->authservers) == "") {
            // no authentication needed, logon without username/password
            $result['authType'] = 'none';
        } else {
            $result['authType'] = 'normal';
        }
        return $result;
    }

    /**
     * determine client's ip address
     */
    private function getClientIp()
    {
        // determine orginal sender of this request
        $trusted_proxy = array(); // optional, not implemented
        if ($this->request->getHeader('X-Forwarded-For') != "" &&
            (
            explode('.', $this->request->getClientAddress())[0] == '127' ||
            in_array($this->request->getClientAddress(), $trusted_proxy)
            )
        ) {
            // use X-Forwarded-For header to determine real client
            return $this->request->getHeader('X-Forwarded-For');
        } else {
            // client accesses the Api directly
            return $this->request->getClientAddress();
        }
    }

    /**
     * logon client to zone, must use post type of request
     * @param int|string zone id number
     * @return array
     */
    public function logonAction($zoneid = 0)
    {
        // get username, IP and auth server from post
        $userName = $this->request->getPost("user", "striptags", null);
        $clientIp = $this->request->getPost("ip", "striptags", null);
        $authServer = $this->request->getPost("server", "striptags", null);
        $timeout = $this->request->getPost("timeout", "striptags", null);
        
        // If an explicit client IP wasn't provided
        if(!$clientIp)
        {
            $clientIp = $this->getClientIp();
        }
        
        $mdlCP = new CaptivePortal();
        $cpZone = $mdlCP->getByZoneID($zoneid);
        if ($cpZone != null) {
            // is there already a session for this user?
            $clientSession = $this->clientSession((string)$cpZone->zoneid, $clientIp);
            if ($clientSession['clientState'] == 'AUTHORIZED') {
                // already authorized, return current session
                return $clientSession;
            } else {
                // allow client to this captiveportal zone
                $backend = new Backend();
                $CPsession = $backend->configdpRun(
                    "captiveportal allow",
                    array(
                        (string)$cpZone->zoneid,
                        $userName,
                        $clientIp,
                        $authServer,
                        'json'
                    )
                );
                
                // Attempt to decode the session data returned from the config daemon
                $CPsession = json_decode($CPsession, true);
                
                // Push session restrictions, if they apply
                if ($CPsession != null && array_key_exists('sessionId', $CPsession)) {
                    // If a timeout has been specified in the POST request that we received then apply it
                    if($timeout) {
                        $backend->configdpRun(
                            "captiveportal set session_restrictions",
                            array((string)$cpZone->zoneid,
                                $CPsession['sessionId'],
                                $timeout
                                )
                        );
                    }
                }
                
                if ($CPsession != null) {
                    // Return the details on the newly-established session
                    return $CPsession;
                } else {
                    // configd returned something other than json
                    return array("clientState" => 'UNKNOWN', "ipAddress" => $clientIp);
                }
            }
        }
    }
}
