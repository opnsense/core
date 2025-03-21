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

/**
 * Interface IAuthConnector for authenticator connectors
 * @package OPNsense\Auth
 */
interface IAuthConnector
{
    /**
     * set connector properties
     * @param array $config set configuration for this connector to use
     */
    public function setProperties($config);

    /**
     * after authentication, you can call this method to retrieve optional return data from the authenticator
     * @return mixed named list of authentication properties, may be returned by the authenticator
     */
    public function getLastAuthProperties();

    /**
     * after authentication, you can call this method to retrieve optional authentication errors
     * @return array of auth errors
     */
    public function getLastAuthErrors();

    /**
     * set session-specific pre-authentication metadata for the authenticator
     * @param array $config set configuration for this connector to use
     * @return IAuthConnector
     */
    public function preauth($config);

    /**
     * authenticate user
     * @param string $username username to authenticate
     * @param string $password user password
     * @return bool
     */
    public function authenticate($username, $password);
}
