<?php

/*
 * Copyright (C) 2019 Deciso B.V.
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

/**
 * interface service, binds authenticator methods to consumers of those methods
 * @package OPNsense\Auth
 */
interface IService
{
    /**
     * return aliases for this service
     * pam supports "includes" to adapt generic templates, since we align to pam services it's practical to have a
     * similar method to extend defaults.
     *
     * An alias serves as a fallback, if there's a class defined handling the specific service it should take
     * precedence over the alias (handled by our authentication factory).
     *
     * @return  array of strings
     */
    public static function aliases();

    /**
     * return all configured / supported configurators for this service
     * @return array list of configured authenticators (defined in system->authserver)
     */
    public function supportedAuthenticators();

     /**
      * set the username for this service, in some scenarios this might be prefixed with some additional
      * logic to determine which authenticators are actually supported.
      * (in case one pam service has multiple real services assigned)
      * @param $username string
      */
    public function setUserName($username);

     /**
      * return the username for authentication.
      * @return string username
      */
    public function getUserName();

     /**
      * When authenticated, validate if this user is actually allowed to access the service, there might be
      * other constraints, such as required group memberships.
      * @return boolean is authenticated
      */
    public function checkConstraints();
}
