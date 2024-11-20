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
 * Class API key/secret database connector (connect to legacy xml structure).
 * @package OPNsense\Auth
 */
class API extends Base implements IAuthConnector
{
    /**
     * @var array internal list of authentication properties
     */
    private $lastAuthProperties = array();

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
     * generate a new api key for an existing user
     * @param $username username
     * @return array|null apikey/secret pair
     */
    public function createKey($username)
    {
        $configObj = Config::getInstance()->object();
        foreach ($configObj->system->children() as $key => $value) {
            if ($key == 'user' && (string)$username == (string)$value->name) {
                if (!isset($value->apikeys)) {
                    $apikeys = $value->addChild('apikeys');
                } else {
                    $apikeys = $value->apikeys;
                }
                $item = $apikeys->addChild('item');

                $newKey = base64_encode(random_bytes(60));
                $newSecret = base64_encode(random_bytes(60));

                $item->addChild('key', $newKey);
                $item->addChild('secret', crypt($newSecret, '$6$'));
                Config::getInstance()->save();
                $response = array('key' => $newKey, 'secret' => $newSecret);
                return $response;
            }
        }
        return null;
    }

    /**
     * remove user api key
     * @param string $username username
     * @param string $apikey api key
     * @return bool key found
     */
    public function dropKey($username, $apikey)
    {
        $configObj = Config::getInstance()->object();
        foreach ($configObj->system->children() as $key => $value) {
            if ($key == 'user' && (string)$username == (string)$value->name) {
                if (isset($value->apikeys)) {
                    $indx = 0;
                    foreach ($value->apikeys->children() as $apiNodeId => $apiNode) {
                        if ($apiNodeId == 'item' && (string)$apiNode->key == $apikey) {
                            unset($value->apikeys->item[$indx]);
                            Config::getInstance()->save();
                            return true;
                        }
                        $indx++;
                    }
                }
            }
        }
        return false;
    }

    /**
     * authenticate user against local database (in config.xml)
     * @param string $username username to authenticate
     * @param string $password user password
     * @return bool authentication status
     */
    public function authenticate($username, $password)
    {
        // reset auth properties
        $this->lastAuthProperties = array();

        // search local user in database
        $configObj = Config::getInstance()->object();
        $userObject = null;
        $apiKey = null;
        $apiSecret = null;
        foreach ($configObj->system->children() as $key => $value) {
            if ($key == 'user') {
                if (!empty($value->apikeys)) {
                    foreach ($value->apikeys->children() as $apikey) {
                        if (!empty($apikey->key) &&  (string)$apikey->key == $username) {
                            // api key found, stop search
                            $userObject = $value;
                            $apiSecret = (string)$apikey->secret;
                            break;
                        }
                    }
                }
            }
        }

        if ($userObject != null) {
            if (isset($userObject->disabled)) {
                // disabled user
                return false;
            }
            if (
                !empty($userObject->expires)
                && strtotime("-1 day") > strtotime(date("m/d/Y", strtotime((string)$userObject->expires)))
            ) {
                // expired user
                return false;
            }
            if (password_verify($password, $apiSecret)) {
                // password ok, return successfully authentication
                $this->lastAuthProperties['username'] = (string)$userObject->name;
                return true;
            }
        }

        return false;
    }
}
