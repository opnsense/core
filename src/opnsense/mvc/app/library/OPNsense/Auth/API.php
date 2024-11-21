<?php

/*
 * Copyright (C) 2015 Deciso B.V.
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

namespace OPNsense\Auth;

use OPNsense\Core\Config;
use OPNsense\Auth\User;

/**
 * Class API key/secret database connector (connect to legacy xml structure).
 * @package OPNsense\Auth
 */
class API extends Base implements IAuthConnector
{
    /**
     * @var array internal list of authentication properties
     */
    private $lastAuthProperties = [];

    /**
     * type name in configuration
     * @return string
     */
    public static function getType()
    {
        return 'api';
    }

    /**
     * set connector properties
     * @param array $config connection properties
     */
    public function setProperties($config)
    {
        // local api authenticator doesn't use any additional settings.
    }

    /**
     * unused
     * @return array mixed named list of authentication properties
     */
    public function getLastAuthProperties()
    {
        return $this->lastAuthProperties;
    }

    /**
     * authenticate user against local database (in config.xml)
     * @param string $username username to authenticate
     * @param string $password user password
     * @return bool authentication status
     */
    public function _authenticate($username, $password)
    {
        // reset auth properties
        $this->lastAuthProperties = [];

        // search local user in database
        $userinfo = (new User())->getApiKeySecret($username);

        if ($userinfo != null) {
            if (!empty($userinfo['disabled'])) {
                // disabled user
                return false;
            }
            if (
                !empty($userinfo['expires'])
                && strtotime("-1 day") > strtotime(date("m/d/Y", strtotime($userinfo['expires'])))
            ) {
                // expired user
                return false;
            }
            if (password_verify($password, $userinfo['secret'])) {
                // password ok, return successfully authentication
                $this->lastAuthProperties['username'] = $userinfo['name'];
                return true;
            }
        }

        return false;
    }

    /**
     * generate a new api key for an existing user, backwards compatibility stub
     * @param $username username
     * @return array|null apikey/secret pair
     */
    public function createKey($username)
    {
        Config::getInstance()->lock();
        $mdl = new \OPNsense\Auth\User();
        $user = $mdl->getUserByName($username);
        if ($user) {
            $tmp = $user->apikeys->add();
            if (!empty($tmp)) {
                $mdl->serializeToConfig(false, true);
                Config::getInstance()->save();
                return $tmp;
            }
        }
        Config::getInstance()->unlock();
        return false;
    }

    /**
     * remove user api key, backwards compatibility stub
     * @param string $username username
     * @param string $apikey api key
     * @return bool key found
     */
    public function dropKey($username, $apikey)
    {
        Config::getInstance()->lock();
        $mdl = new \OPNsense\Auth\User();
        $user = $mdl->getUserByName($username);
        if ($user) {
            if ($user->apikeys->del($apikey)) {
                $mdl->serializeToConfig(false, true);
                Config::getInstance()->save();
                return true;
            }
        }
        Config::getInstance()->unlock();
        return false;
    }
}
