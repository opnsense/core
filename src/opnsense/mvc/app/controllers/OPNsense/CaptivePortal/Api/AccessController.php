<?php

/*
 * Copyright (C) 2015-2025 Deciso B.V.
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
    private array $arp = [];

    /**
     * request client session data
     * @param string $zoneid captive portal zone
     * @return array
     * @throws \OPNsense\Base\ModelException
     */
    protected function clientSession(string $zoneid)
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
            $mac = $this->getClientMac($result['ipAddress']);
            if (!empty($mac)) {
                $result['macAddress'] = $mac;
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
    protected function getClientIp()
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

    protected function getClientMac($ip)
    {
        $this->arp = empty($this->arp) ? json_decode((new Backend())->configdRun("interface list arp json"), true) : [];
        foreach ($this->arp as $arp) {
            if (!empty($arp['ip'] && $arp['ip'] == $ip)) {
                return $arp['mac'];
            }
        }
    }

    /**
     * retrieve session info
     * @param int|string $zoneid zone id number, provided for backwards compatibility
     * @return array
     * @throws \OPNsense\Base\ModelException
     *
     */
    public function statusAction($zoneid = 0)
    {
        if ($this->request->isOptions()) {
            // return empty result on CORS preflight
            return [];
        } elseif ($this->request->isPost() || $this->request->isGet()) {
            $clientSession = $this->clientSession($this->request->getHeader("zoneid"));
            return $clientSession;
        }
    }

    /**
     * RFC 8908: Captive Portal API status object
     *
     * The URI for this endpoint can be provisioned to the client
     * as defined by RFC 7710.
     *
     * Request and response must set media type as "application/captive+json".
     *
     * Response contains the following fields:
     * - captive: boolean: client is currently in a state of captivity.
     * - user-portal-url: string: URL to login web portal (must be HTTPS).
     * - seconds-remaining: number: seconds until session expires,
     *   only relevant if hardtimeout set.
     *
     * Fields not implemented here but possible in the future:
     * - venue-info-url: string: Information page (must be HTTPS)
     * - can-extend-session: boolean: hint that client system can access
     *   user-portal-url to extend session.
     * - bytes-remaining: number: no. of bytes after which session expires.
     *
     * Response must set Cache-Control to 'private' or 'no-store'
     */
    public function apiAction()
    {
        if (
            $this->request->isGet() &&
            $this->request->getHeader("accept") == "application/captive+json"
        ) {
            $result = [];
            $zoneId = $this->request->getHeader("zoneid");
            $clientSession = $this->clientSession($zoneId);
            $captive = $clientSession["clientState"] != "AUTHORIZED";
            $host = $this->request->getHeader('X-Forwarded-Host');

            $zone = (new \OPNsense\CaptivePortal\CaptivePortal())->getByZoneId($zoneId);

            if ($zone != null && !empty($clientSession['startTime'])) {
                $startTime = (int)$clientSession['startTime'];
                $secondsPassed = time() - $startTime;
                $remainingTimes = [];

                if (!empty((string)$zone->hardtimeout)) {
                    $timeout = (int)$zone->hardtimeout * 60;
                    if ($secondsPassed < $timeout) {
                        $remainingTimes[] = $timeout - $secondsPassed;
                    }
                }

                if (!empty($clientSession['acc_session_timeout'])) {
                    $timeout = (int)$clientSession['acc_session_timeout'];
                    if ($secondsPassed < $timeout) {
                        $remainingTimes[] = $timeout - $secondsPassed;
                    }
                }

                if (!empty($remainingTimes)) {
                    $result['seconds-remaining'] = min($remainingTimes);
                }
            }

            $this->response->setRawHeader("Cache-Control: private");
            $this->response->setContentType("application/captive+json");

            $result["captive"] = $captive;
            $result["user-portal-url"] = "https://{$host}/index.html";

            $this->response->setContent($result);

            return;
        }

        $this->response->setStatusCode(400);
        $this->response->setContentType('application/json', 'UTF-8');
        $this->response->setContent(['status'  => 400, 'message' => 'Bad request']);
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
     * @param int|string $zoneid zone id number, provided for backwards compatibility
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
            $zoneid = $this->request->getHeader("zoneid");

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
                            $isAuthenticated = $authServer->preauth([
                                'calling_station_id' => $this->getClientMac($this->getClientIp())
                            ])->authenticate(
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
     * @param int|string $zoneid zone id number, provided for backwards compatibility
     * @return array
     * @throws \OPNsense\Base\ModelException
     *
     */
    public function logoffAction($zoneid = 0)
    {
        if ($this->request->isOptions()) {
            // return empty result on CORS preflight
            return [];
        } else {
            $zoneid = $this->request->getHeader("zoneid");
            $clientSession = $this->clientSession((string)$zoneid);
            if (
                $clientSession['clientState'] == 'AUTHORIZED' &&
                $clientSession['authenticated_via'] != '---ip---' &&
                $clientSession['authenticated_via'] != '---mac---'
            ) {
                // you can only disconnect a connected client
                $backend = new Backend();
                $statusRAW = $backend->configdpRun("captiveportal disconnect", [$clientSession['sessionId'], "User-Request"]);
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
}
