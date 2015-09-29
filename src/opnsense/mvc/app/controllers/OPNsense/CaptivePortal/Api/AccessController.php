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
                if ($connectedClient['ipAddress'] == $this->request->getClientAddress()) {
                    // client is authorized in this zone according to our administration
                    $connectedClient['clientState'] = 'AUTHORIZED';
                    return $connectedClient;
                }
            }
        }

        // return Unauthorized
        return array('clientState' => "NOT_AUTHORIZED", "ipAddress" => $this->request->getClientAddress());
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
        $this->response->setHeader("Access-Control-Allow-Methods", "POST");
    }

    /**
     * reconfigure captive portal
     * @param string zone id number
     * @return array
     */
    public function logonAction($zoneid = 0)
    {
        if ($this->request->isOptions()) {
            // return empty result on CORS preflight
            return array();
        } elseif (true || $this->request->isPost()) {
            // close session for long running action
            $this->sessionClose();

            // search zone info, to retrieve list of authenticators
            $mdlCP = new CaptivePortal();
            $cpZone = $mdlCP->getByZoneID($zoneid);
            if ($cpZone != null) {
                // authenticate user
                $isAuthenticated = false;
                $authFactory = new AuthenticationFactory();
                foreach (explode(',', (string)$cpZone->authservers) as $authServerName) {
                    $authServer = $authFactory->get(trim($authServerName));
                    // try this auth method
                    $isAuthenticated = $authServer->authenticate(
                        $this->request->getPost("user", "striptags"),
                        $this->request->getPost("password", "string")
                    );

                    if ($isAuthenticated) {
                        // stop trying, when authenticated
                        break;
                    }
                }

                if ($isAuthenticated) {
                    // when authenticated, we have $authServer available to request additional data if needed
                    $clientSession = $this->clientSession((string)$cpZone->zoneid);

                    if ($clientSession['clientState'] == 'AUTHORIZED') {
                        // already authorized, return current session
                        return $clientSession;
                    } else {
                        // allow client to
                        $backend = new Backend();
                        $CPsession = $backend->configdpRun(
                            "captiveportal allow",
                            array($zoneid, 'json')
                        );

                        return json_decode($CPsession);
                    }
                } else {
                    return array("clientState" => 'NOT_AUTHORIZED',
                        "ipAddress" => $this->request->getClientAddress()
                    );
                }
            }
        }

        return array("clientState" => 'UNKNOWN',
            "ipAddress" => $this->request->getClientAddress()
        );
    }
}
