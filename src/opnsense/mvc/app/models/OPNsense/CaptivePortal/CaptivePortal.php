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
namespace OPNsense\CaptivePortal;

use OPNsense\Base\BaseModel;
use \OPNsense\Core\Backend;

/**
 * Class CaptivePortal
 * @package OPNsense\CaptivePortal
 */
class CaptivePortal extends BaseModel
{
    /**
     * request client session data
     * @param $zoneid captive portal zone
     * @param $client_ip the ip to look for
     * @return array
     */
    public function clientSession($zoneid,$client_ip)
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
                if ($connectedClient['ipAddress'] == $client_ip) {
                    // client is authorized in this zone according to our administration
                    $connectedClient['clientState'] = 'AUTHORIZED';
                    return $connectedClient;
                }
            }
        }

        // return Unauthorized including authentication requirements
        $result = array('clientState' => "NOT_AUTHORIZED", "ip_address" => $client_ip);
        $cpZone = $this->getByZoneID($zoneid);
        if ($cpZone != null && trim((string)$cpZone->authservers) == "") {
            // no authentication needed, logon without username/password
            $result['authType'] = 'none';
        } else {
            $result['authType'] = 'normal';
        }
        return $result;
    }
    /**
     * retrieve zone by number
     * @param $zoneid zone number
     * @return null|BaseField zone details
     */
    public function getByZoneID($zoneid)
    {
        foreach ($this->zones->zone->__items as $zone) {
            if ((string)$zoneid === (string)$zone->zoneid) {
                return $zone;
            }
        }
        return null;
    }

    /**
     * check if module is enabled
     * @return bool is the captive portal enabled (1 or more active zones)
     */
    public function isEnabled()
    {
        foreach ($this->zones->zone->__items as $zone) {
            if ((string)$zone->enabled == "1") {
                return true;
            }
        }
        return false;
    }

    /**
     * find ttemplate by name or return a new object
     * @param $name template name
     * @return mixed
     */
    public function getTemplateByName($name)
    {
        foreach ($this->templates->template->__items as $template) {
            if ((string)$template->name === $name) {
                return $template;
            }
        }
        $newItem = $this->templates->template->Add();
        $newItem->name = $name;
        $newItem->fileid = uniqid();
        return $newItem;
    }
}
