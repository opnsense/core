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

require_once("config.inc");
require_once("auth.inc");
require_once("xmlrpc.inc");

/**
 * do a basic authentication, uses $_SERVER['HTTP_AUTHORIZATION'] to validate user.
 * @param string $http_auth_header content of the Authorization HTTP header
 * @return bool
 */
function http_basic_auth($http_auth_header)
{
    $tags=explode(" ", $http_auth_header) ;
    if (count($tags) >= 2) {
        $userinfo= explode(":", base64_decode($tags[1])) ;
        if (count($userinfo)>=2) {
            if (authenticate_user($userinfo[0], $userinfo[1])) {
                $aclObj = new \OPNsense\Core\ACL();
                return $aclObj->isPageAccessible($userinfo[0], "/xmlrpc.php");
            }
        }
    }

    // not authenticated
    return false;
}


/**
 *   Simple XML-RPC server using IXR_Library
 */
if (!isset($_SERVER['HTTP_AUTHORIZATION']) ||               // check for an auth header
    !http_basic_auth($_SERVER['HTTP_AUTHORIZATION']) ||     // user authentication failure (basic auth)
    $_SERVER['SERVER_ADDR'] == $_SERVER['REMOTE_ADDR']      // do not accept request from server's own address
) {
    // Authentication failure, bail out.
    $xml = <<<EOD
<methodResponse>
<params>
    <param>
      <value>Authentication failed</value>
    </param>
  </params>
</methodResponse>
EOD;

    $xml = '<?xml version="1.0"?>'."\n".$xml;
    $length = strlen($xml);
    header('Connection: close');
    header('Content-Length: '.$length);
    header('Content-Type: text/xml');
    header('Date: '.date('r'));
    echo $xml;
} else {
    $server = new XMLRPCServer();
    $server->start();
}
