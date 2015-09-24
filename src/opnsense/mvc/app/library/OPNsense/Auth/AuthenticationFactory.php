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

namespace OPNsense\Auth;

use OPNsense\Core\Config;

/**
 * Class AuthenticationFactory
 * @package OPNsense\Auth
 */
class AuthenticationFactory
{
    /**
     * search already known local userDN's into simple mapping if auth method is current standard method
     */
    private function fetchUserDNs($authserver = null)
    {
        $result = array();
        $configObj = Config::getInstance()->object();
        if ($authserver == null || (isset($configObj->system->webgui->authmode) &&
                (string)$configObj->system->webgui->authmode == $authserver)) {
            foreach ($configObj->system->children() as $key => $value) {
                if ($key == 'user' && !empty($value->user_dn)) {
                    $result[(string)$value->name] = (string)$value->user_dn;
                }
            }
        }
        return $result;
    }

    /**
     * request list of configured servers, the factory needs to be aware of it's options and settings to
     * be able to instantiate useful connectors.
     * @return array list of configured servers
     */
    public function listServers()
    {
        $servers = array();
        $servers['Local Database'] = array("name" => "Local Database", "type" => "local");
        $configObj = Config::getInstance()->object();
        foreach ($configObj->system->children() as $key => $value) {
            if ($key == 'authserver' && !empty($value->type) && !empty($value->name)) {
                $authServerSettings = array();
                foreach ($value as $itemKey => $itemValue) {
                    $authServerSettings[$itemKey] = (string)$itemValue;
                }
                $servers[$authServerSettings['name']] = $authServerSettings;
            }
        }

        return $servers;
    }

    /**
     * get new authenticator
     * @param $authserver authentication server name
     * @return IAuthConnector|null
     */
    public function get($authserver)
    {
        $localUserMap = array();
        $servers = $this->listServers();

        // create a new auth connector
        if (isset($servers[$authserver]['type'])) {
            switch ($servers[$authserver]['type']) {
                case 'local':
                    $authObject = new Local();
                    break;
                case 'ldap':
                    $authObject = new LDAP();
                    $localUserMap = $this->fetchUserDNs();
                    break;
                default:
                    $authObject = null;
            }
            if ($authObject != null) {
                $props = $servers[$authserver];
                // when a local user exist and has a different (distinguished) name on the authenticator we already
                // know of, we send the mapping to the authenticator as property "local_users".
                $props['local_users'] = $localUserMap;
                $authObject->setProperties($props);
                return $authObject;
            }
        }

        return null;
    }
}
