<?php

/*
 * Copyright (C) 2015-2022 Deciso B.V.
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * 1. Redistributions of source code must retain the above copyright notice,
 *    this list of conditions and the following disclaimer.
 *
 * 2. Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in the
 *    documentation and/or other materials provided with the distribution.
 *
 * THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
 * INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 * AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 * AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 * OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 * INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 */

namespace OPNsense\CaptivePortal\Api;

use OPNsense\Base\ApiControllerBase;
use OPNsense\Core\Backend;
use OPNsense\Auth\AuthenticationFactory;
use OPNsense\CaptivePortal\CaptivePortal;

/**
 * Class AccessController
 * @package OPNsense\CaptivePortal
 */
class AccessController extends ApiControllerBase
{
    /**
     * request client session data
     * @param string $zoneid captive portal zone
     * @return array
     * @throws \OPNsense\Base\ModelException
     */
    private function clientSession(string $zoneid)
    {
        $backend = new Backend();
        $allClientsRaw = $backend->configdpRun("captiveportal list_clients", [$zoneid]);
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
        $result = ['clientState' => "NOT_AUTHORIZED", "ipAddress" => $this->getClientIp()];
        $mdlCP = new CaptivePortal();
        $cpZone = $mdlCP->getByZoneID($zoneid);
        if ($cpZone != null && (string)$cpZone->extendedPreAuthData == '1') {
            $arps = json_decode($backend->configdRun("interface list arp json"), true);
            if ($arps != null) {
                foreach ($arps as $arp) {
                    if (!empty($arp['ip'] && $arp['ip'] == $result['ipAddress'])) {
                        $result['macAddress'] = $arp['mac'];
                    }
                }
            }
        }
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
        // determine original sender of this request
        if (
            $this->request->getHeader('X-Forwarded-For') != "" &&
            explode('.', $this->request->getClientAddress())[0] == '127'
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
     * @return void
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
     * @param int|string $zoneid zone id number
     * @return array
     * @throws \OPNsense\Base\ModelException
     */
    public function logonAction($zoneid = 0)
    {
        $clientIp = $this->getClientIp();
        if ($this->request->isOptions()) {
            // return empty result on CORS preflight
            return [];
        } elseif ($this->request->isPost()) {
            // init variables for authserver object and name
            $authServer = null;
            $authServerName = "";

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
                        if ($authServer != null) {
                            // try this auth method
                            $isAuthenticated = $authServer->authenticate(
                                $userName,
                                $this->request->getPost("password")
                            );

                            // check group when group enforcement is set
                            if ($isAuthenticated && (string)$cpZone->authEnforceGroup != "") {
                                $isAuthenticated = $authServer->groupAllowed($userName, $cpZone->authEnforceGroup);
                            }

                            if ($isAuthenticated) {
                                // stop trying, when authenticated
                                break;
                            }
                        }
                    }
                } else {
                    // no authentication needed, set username to "anonymous@ip"
                    $userName = "anonymous@" . $clientIp;
                    $isAuthenticated = true;
                }

                if ($isAuthenticated) {
                    $this->getLogger("captiveportal")->info("AUTH " . $userName .  " (" . $clientIp . ") zone " . $zoneid);
                    // when authenticated, we have $authServer available to request additional data if needed
                    $clientSession = $this->clientSession($cpZone->zoneid);
                    if ($clientSession['clientState'] == 'AUTHORIZED') {
                        // already authorized, return current session
                        return $clientSession;
                    } else {
                        // allow client to this captiveportal zone
                        $backend = new Backend();
                        $CPsession = $backend->configdpRun(
                            "captiveportal allow",
                            [
                                (string)$cpZone->zoneid,
                                $userName,
                                $clientIp,
                                $authServerName
                            ]
                        );
                        $CPsession = json_decode($CPsession, true);
                        // push session restrictions, if they apply
                        if ($CPsession != null && array_key_exists('sessionId', $CPsession) && $authServer != null) {
                            $authProps = $authServer->getLastAuthProperties();
                            // when adding more client/session restrictions, extend next code
                            // (currently only time is restricted)
                            if (array_key_exists('session_timeout', $authProps) || $cpZone->alwaysSendAccountingReqs == '1') {
                                $backend->configdpRun(
                                    "captiveportal set session_restrictions",
                                    array((string)$cpZone->zoneid,
                                        $CPsession['sessionId'],
                                        $authProps['session_timeout'] ?? null,
                                        )
                                );
                            }
                        }
                        if ($CPsession != null) {
                            // only return session if configd return a valid json response, otherwise fallback to
                            // returning "UNKNOWN"
                            return $CPsession;
                        }
                    }
                } else {
                    $this->getLogger("captiveportal")->info("DENY " . $userName .  " (" . $clientIp . ") zone " . $zoneid);
                    return ["clientState" => 'NOT_AUTHORIZED', "ipAddress" => $clientIp];
                }
            }
        }

        return ["clientState" => 'UNKNOWN', "ipAddress" => $clientIp];
    }


    /**
     * logoff client
     * @param int|string $zoneid zone id number
     * @return array
     * @throws \OPNsense\Base\ModelException
     */
    public function logoffAction($zoneid = 0)
    {
        if ($this->request->isOptions()) {
            // return empty result on CORS preflight
            return [];
        } else {
            $clientSession = $this->clientSession((string)$zoneid);
            if (
                $clientSession['clientState'] == 'AUTHORIZED' &&
                $clientSession['authenticated_via'] != '---ip---' &&
                $clientSession['authenticated_via'] != '---mac---'
            ) {
                // you can only disconnect a connected client
                $backend = new Backend();
                $statusRAW = $backend->configdpRun("captiveportal disconnect", [$clientSession['sessionId']]);
                $status = json_decode($statusRAW, true);
                if ($status != null) {
                    $this->getLogger("captiveportal")->info(
                        "LOGOUT " . $clientSession['userName'] .  " (" . $this->getClientIp() . ") zone " . $zoneid
                    );
                    return $status;
                }
            }
        }
        return ["clientState" => "UNKNOWN", "ipAddress" => $this->getClientIp()];
    }

    /**
     * retrieve session info
     * @param int|string $zoneid zone id number
     * @return array
     * @throws \OPNsense\Base\ModelException
     */
    public function statusAction($zoneid = 0)
    {
        if ($this->request->isOptions()) {
            // return empty result on CORS preflight
            return [];
        } elseif ($this->request->isPost() || $this->request->isGet()) {
            $clientSession = $this->clientSession((string)$zoneid);
            return $clientSession;
        }
    }
}
