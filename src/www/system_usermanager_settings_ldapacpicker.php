<?php

/*
 * Copyright (C) 2014-2018 Deciso B.V.
 * Copyright (C) 2007 Scott Ullrich <sullrich@gmail.com>
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

require_once("guiconfig.inc");
require_once("auth.inc");

$result = array();

if (isset($_POST['basedn']) && isset($_POST['host'])) {
    $ldap_authcn = isset($_POST['authcn']) ? explode(";", $_POST['authcn']) : array();
    if (isset($_POST['urltype']) && (strstr($_POST['urltype'], "Standard") || strstr($_POST['urltype'], "StartTLS"))) {
        $ldap_full_url = "ldap://";
    } else {
        $ldap_full_url = "ldaps://";
    }
    $ldap_full_url .= is_ipaddrv6($_POST['host']) ? "[{$_POST['host']}]" : $_POST['host'];
    if (!empty($_POST['port'])) {
        $ldap_full_url .= ":{$_POST['port']}";
    }

    $ldap_auth = new OPNsense\Auth\LDAP($_POST['basedn'], isset($_POST['proto']) ? $_POST['proto'] : 3);
    $ldap_auth->setProperties(['ldap_urltype' => $_POST['urltype']]);
    $ldap_is_connected = $ldap_auth->connect(
        $ldap_full_url,
        !empty($_POST['binddn']) ? $_POST['binddn'] : null,
        !empty($_POST['bindpw']) ? $_POST['bindpw'] : null
    );

    $ous = false;
    if ($ldap_is_connected) {
        $ous = $ldap_auth->listOUs();
    }

    if ($ous !== false) {
        foreach ($ous as $ou) {
            $result[] = array("value" => $ou, "selected" => in_array($ou, $ldap_authcn));
        }
    }
}

echo json_encode($result);
