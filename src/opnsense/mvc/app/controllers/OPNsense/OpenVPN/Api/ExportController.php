<?php
/**
 *    Copyright (C) 2018 Deciso B.V.
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
namespace OPNsense\OpenVPN\Api;

use \OPNsense\Base\ApiControllerBase;
use \OPNsense\Core\Config;

/**
 * Class ExportController handles client export functions
 * @package OPNsense\OpenVPN
 */
class ExportController extends ApiControllerBase
{
    /**
     * find configured servers
     * @param bool $active only active servers
     * @return \Generator
     */
    private function servers($active=true)
    {
        if (isset(Config::getInstance()->object()->openvpn)) {
            foreach (Config::getInstance()->object()->openvpn->children() as $key => $value) {
                if ($key == 'openvpn-server') {
                    if (empty($value->disable) || !$active) {
                        yield $value;
                    }
                }
            }
        }
    }

    /**
     * find server by vpnid
     * @param string $vpnid reference
     * @return mixed|null
     */
    private function findServer($vpnid)
    {
        foreach ($this->servers() as $server) {
            if ((string)$server->vpnid == $vpnid) {
                return $server;
            }
        }
        return null;
    }

    /**
     * list providers
     * @return array list of configured openvpn providers (servers)
     */
    public function providersAction()
    {
        $result = array();
        foreach ($this->servers() as $server) {
            $vpnid = (string)$server->vpnid;
            $result[$vpnid] = array();
            // visible name
            $result[$vpnid]["name"] = empty($server->description) ? "server" : (string)$server->description;
            $result[$vpnid]["name"] .= " " . $server->protocol . ":" . $server->local_port;
            // relevant properties
            $result[$vpnid]["mode"] = (string)$server->mode;
            $result[$vpnid]["vpnid"] = $vpnid;
        }
        return $result;
    }

    /**
     * list configured accounts
     * @param string $vpnid server handle
     * @return array list of configured accounts
     */
    public function accountsAction($vpnid)
    {
        $result = array();

        $server = $this->findServer($vpnid);
        if ($server !== null) {
            // collect certificates for this server's ca
            if (isset(Config::getInstance()->object()->cert)) {
                foreach (Config::getInstance()->object()->cert as $cert) {
                    if (isset($cert->refid) && isset($cert->caref) && (string)$server->caref == $cert->caref) {
                        $result[(string)$cert->refid] = array(
                            "description" => (string)$cert->descr,
                            "users" => array()
                        );
                    }
                }
            }
            // collect linked users
            foreach (Config::getInstance()->object()->system->user as $user) {
                if (isset($user->cert)) {
                    foreach ($user->cert as $cert) {
                        if (!empty($result[(string)$cert])) {
                            $result[(string)$cert]['users'][] = (string)$user->name;
                        }
                    }
                }
            }
        }
        return $result;
    }

    /**
     * list configured export options (client types)
     * @return array list of templates
     */
    public function templatesAction()
    {
        return array();
    }
}
