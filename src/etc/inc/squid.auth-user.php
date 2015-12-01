#!/usr/local/bin/php
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

require_once("config.inc");
require_once("auth.inc");

openlog("squid", LOG_ODELAY, LOG_AUTH);

$authFactory = new \OPNsense\Auth\AuthenticationFactory();

$f = fopen("php://stdin", "r");
while ($line = fgets($f)) {
    $fields = explode(' ', trim($line));
    $username = rawurldecode($fields[0]);
    $password = rawurldecode($fields[1]);

    $isAuthenticated = false;
    if (isset($config['OPNsense']['proxy']['forward']['authentication']['method'])) {
        foreach (explode(',',$config['OPNsense']['proxy']['forward']['authentication']['method']) as $authServerName) {
            $authServer = $authFactory->get(trim($authServerName));
            if ($authsrv == null) {
                // authenticator not found, use local
                $authServer = $authFactory->get('Local Database');
            }
            $isAuthenticated = $authServer->authenticate($username, $password);
            if ($isAuthenticated) {
                if (get_class($authServer) == "OPNsense\Auth\Local") {
                    // todo: user priv check needs a reload of squid, maybe it's better to move the token check to
                    //       the auth object.
                    //
                    // when using local authentication, check if user has role user-proxy-auth
                    $user = getUserEntry($username);
                    if (is_array($user) && userHasPrivilege($user, "user-proxy-auth")) {
                        break;
                    } else {
                        // log user auth failure
                        syslog(LOG_WARNING, "user '{$username}' cannot authenticate for squid because of missing user-proxy-auth role");
                        fwrite(STDOUT, "ERR\n");
                        $isAuthenticated = false;
                    }
                } else {
                    break;
                }
            }
        }
    }

    if ($isAuthenticated) {
        syslog(LOG_NOTICE, "user '{$username}' authenticated\n");
        fwrite(STDOUT, "OK\n");
    } else {
        syslog(LOG_WARNING, "user '{$username}' could not authenticate.\n");
        fwrite(STDOUT, "ERR\n");
    }
}

closelog();
