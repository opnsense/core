<?php
/*
	Copyright (C) 2014-2015 Deciso B.V.
	Copyright (C) 2007 Scott Ullrich <sullrich@gmail.com>
	All rights reserved.

    Redistribution and use in source and binary forms, with or without
    modification, are permitted provided that the following conditions are met:

    1. Redistributions of source code must retain the above copyright notice,
       this list of conditions and the following disclaimer.

    2. Redistributions in binary form must reproduce the above copyright
       notice, this list of conditions and the following disclaimer in the
       documentation and/or other materials provided with the distribution.

    THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
    INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
    AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
    AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
    OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
    SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
    INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
    CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
    ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
    POSSIBILITY OF SUCH DAMAGE.
*/

require_once("guiconfig.inc");
require_once("auth.inc");
include('head.inc');


if (isset($config['system']['authserver'][0]['host'])) {
    $auth_server = $config['system']['authserver'][0]['host'];
    $authserver = $_GET['authserver'];
    $authcfg = auth_get_authserver($authserver);

    $ldap_auth = new OPNsense\Auth\LDAP($authcfg['ldap_basedn'],  $authcfg['ldap_protver']);
    ldap_setup_caenv($authcfg);
    $ldap_is_connected = $ldap_auth->connect($authcfg['ldap_full_url'], $authcfg['ldap_binddn'], $authcfg['ldap_bindpw']);
}


?>

<body>
	<form method="post" name="iform" id="iform">

<?php

if (!$authcfg) {
    printf(gettext("Could not find settings for %s%s"), htmlspecialchars($authserver), "<p/>");
} else {
    echo "<table class='table table-striped'>";

    echo "<tr><th colspan='2'>".sprintf(gettext("Testing %s LDAP settings... One moment please..."), $g['product_name'])."</th></tr>";
    echo "<tr><td>" . gettext("Attempting connection to") . " " . $authserver . "</td>";
    if ($ldap_is_connected) {
        echo "<td><font color='green'>OK</font></td></tr>";
            echo "<tr><td>" . gettext("Attempting to fetch Organizational Units from") . " " . $authserver . "</td>";
            $ous = $ldap_auth->listOUs();
            if (count($ous)>1) {
                echo "<td><font color=green>OK</font></td></tr>";
                echo "<tr><td>".gettext("Organization units found") . "</td><td><font color=green>".count($ous)."</font></td></tr>";
                foreach ($ous as $ou) {
                    echo "<tr><td colspan='2'>" . $ou . "</td></tr>";
                }
            } else {
                echo "<td><font color='red'>" . gettext("failed") . "</font></td></tr>";
            }
        } else {
            echo "<td><font color='red'>" . gettext("failed") . "</font></td></tr>";
        }
}

?>
	<tr>
		<td colspan="2" align="right">
			<input type="Button" value="<?=gettext("Close"); ?>" class="btn btn-default" onClick='Javascript:window.close();'>
		</td>
	</tr>
	</table>
	</form>
</body>
</html>
