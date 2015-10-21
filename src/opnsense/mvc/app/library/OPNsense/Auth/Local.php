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
 * Class Local user database connector (using legacy xml structure).
 * @package OPNsense\Auth
 */
class Local implements IAuthConnector
{
    /**
     * set connector properties
     * @param array $config connection properties
     */
    public function setProperties($config)
    {
        // local authenticator doesn't use any additional settings.
    }

    /**
     * authenticate user against local database (in config.xml)
     * @param $username username to authenticate
     * @param $password user password
     * @return bool authentication status
     */
    public function authenticate($username, $password)
    {
        // search local user in database
        $configObj = Config::getInstance()->object();
        $userObject = null;
        foreach ($configObj->system->children() as $key => $value) {
            if ($key == 'user' && !empty($value->name) && (string)$value->name == $username) {
                // user found, stop search
                $userObject = $value;
                break;
            }
        }

        if ($userObject != null) {
            if (isset($userObject->disabled)) {
                // disabled user
                return false;
            }
            if (!empty($userObject->expires)
                && strtotime("-1 day") > strtotime(date("m/d/Y", strtotime((string)$userObject->expires)))) {
                // expired user
                return false;
            }
            $passwd = crypt($password, (string)$userObject->password);
            if ($passwd == (string)$userObject->password) {
                // password ok, return successfully authentication
                return true;
            }
        }

        return false;
    }
}
