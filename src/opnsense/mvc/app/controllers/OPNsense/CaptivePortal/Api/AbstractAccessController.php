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
use OPNsense\CaptivePortal\CaptivePortal;

/**
 * Class AbstractAccessController
 * @package OPNsense\CaptivePortal
 */
abstract class AbstractAccessController extends ApiControllerBase
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
}
