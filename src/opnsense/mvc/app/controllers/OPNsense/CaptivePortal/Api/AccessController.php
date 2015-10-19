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
use \OPNsense\Auth\AuthenticationFactory;
use \OPNsense\CaptivePortal\CaptivePortal;

/**
 * Class ServiceController
 * @package OPNsense\CaptivePortal
 */
class AccessController extends ApiControllerBase
{
    /**
     * request client session data
     * @param $zoneid captive portal zone
     * @return array
     */
    private function clientSession($zoneid)
    {
        $backend = new Backend();
        $allClientsRaw = $backend->configdpRun(
            "captiveportal list_clients",
            array($zoneid, 'json')
        );
        $allClients = json_decode($allClientsRaw, true);
        if ($allClients != null) {
            // search for client by ip address
            foreach ($allClients as $connectedClient) {
                if ($connectedClient['ipAddress'] == $this->getClientIp()) {
                    // client is authorized in this zone according to our administration
                    $connectedClient['clientState'] = 'AUTHORIZED';
                    return $connectedClient;
                }
            }
        }

        // return Unauthorized including authentication requirements
        $result = array('clientState' => "NOT_AUTHORIZED", "ipAddress" => $this->getClientIp());
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
     * determine clients ip address
     */
    private function getClientIp()
    {
        // determine orginal sender of this request
        $trusted_proxy = array("127.0.0.1");
        if ($this->request->getHeader('X-Forwarded-For') != "" &&
            in_array($this->request->getClientAddress(), $trusted_proxy)
        ) {
            // use X-Forwarded-For header to determine real client
            return $this->request->getHeader('X-Forwarded-For');
        } else {
            // client accesses the Api directly
            return $this->request->getClientAddress();
        }
    }

    /**
     * before routing event
     * @param Dispatcher $dispatcher
     * @return null|bool
     */
    public function beforeExecuteRoute($dispatcher)
    {
        // disable standard authentication in CaptivePortal Access API calls.
        // set CORS headers
        $this->response->setHeader("Access-Control-Allow-Origin", "*");
        $this->response->setHeader("Access-Control-Allow-Methods", "OPTIONS, GET, POST");
    }

    /**
     * logon client to zone, must use post type of request
     * @param string zone id number
     * @return array
     */
    public function logonAction($zoneid = 0)
    {
        $clientIp = $this->getClientIp();
        if ($this->request->isOptions()) {
            // return empty result on CORS preflight
            return array();
        } elseif ($this->request->isPost()) {
            // close session for long running action
            $this->sessionClose();

            // get username from post
            $userName = $this->request->getPost("user", "striptags", null);

            // search zone info, to retrieve list of authenticators
            $mdlCP = new CaptivePortal();
            $cpZone = $mdlCP->getByZoneID($zoneid);
            if ($cpZone != null) {
                if (trim((string)$cpZone->authservers) != "") {
                    // authenticate user
                    $isAuthenticated = false;
                    $authFactory = new AuthenticationFactory();
                    foreach (explode(',', (string)$cpZone->authservers) as $authServerName) {
                        $authServer = $authFactory->get(trim($authServerName));
                        // try this auth method
                        $isAuthenticated = $authServer->authenticate(
                            $userName,
                            $this->request->getPost("password", "string")
                        );

                        if ($isAuthenticated) {
                            // stop trying, when authenticated
                            break;
                        }
                    }
                } else {
                    // no authentication needed, set username to "anonymous@ip"
                    $userName = "anonymous@" . $clientIp;
                    $authServerName = "";
                    $isAuthenticated = true;
                }

                if ($isAuthenticated) {
                    // when authenticated, we have $authServer available to request additional data if needed
                    $clientSession = $this->clientSession((string)$cpZone->zoneid);
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
                                $authServerName,
                                'json'
                            )
                        );
                        $CPsession = json_decode($CPsession, true);
                        if ($CPsession != null) {
                            // only return session if configd return a valid json response, otherwise fallback to
                            // returning "UNKNOWN"
                            return $CPsession;
                        }
                    }
                } else {
                    return array("clientState" => 'NOT_AUTHORIZED', "ipAddress" => $clientIp);
                }
            }
        }

        return array("clientState" => 'UNKNOWN', "ipAddress" => $clientIp);
    }


    /**
     * logoff client
     * @param string zone id number
     * @return array
     */
    public function logoffAction($zoneid = 0)
    {
        if ($this->request->isOptions()) {
            // return empty result on CORS preflight
            return array();
        } else {
            $this->sessionClose();
            $clientSession = $this->clientSession((string)$zoneid);
            if ($clientSession['clientState'] == 'AUTHORIZED' &&
                $clientSession['authenticated_via'] != '---ip---' &&
                $clientSession['authenticated_via'] != '---mac---'
            ) {
                // you can only disconnect a connected client
                $backend = new Backend();
                $statusRAW = $backend->configdpRun(
                    "captiveportal disconnect",
                    array($zoneid, $clientSession['sessionId'], 'json')
                );
                $status = json_decode($statusRAW, true);
                if ($status != null) {
                    return $status;
                }
            }
        }
        return array("clientState" => "UNKNOWN", "ipAddress" => $this->getClientIp());
    }

    /**
     * retrieve session info
     * @param string zone id number
     * @return array
     */
    public function statusAction($zoneid = 0)
    {
        if ($this->request->isOptions()) {
            // return empty result on CORS preflight
            return array();
        } elseif ($this->request->isPost() || $this->request->isGet()) {
            $this->sessionClose();
            $clientSession = $this->clientSession((string)$zoneid);
            return $clientSession;
        }
    }
}
