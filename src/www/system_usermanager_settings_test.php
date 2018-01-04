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
<html>
  <body>
    <table class='table table-striped'>
<?php
      if (empty($authcfg)):?>
      <tr>
        <td>
<?php
          printf(gettext("Could not find settings for %s%s"), htmlspecialchars($authserver), "<p/>");?>
        </td>
      </tr>
<?php
      else:?>
      <tr>
        <td colspan='2'>
          <?= gettext('Testing LDAP settings... One moment please...') ?>
        </td>
      </tr>
      <tr>
        <td><?=gettext("Attempting connection to") . " " . htmlspecialchars($authserver);?></td>
<?php
        if ($ldap_is_connected):?>
        <td>
          <font color='green'><?=gettext("OK");?></font></td>
      </tr>
      <tr>
        <td><?=gettext("Attempting to fetch Organizational Units from") . " " . htmlspecialchars($authserver);?></td>
<?php
          $ous = $ldap_auth->listOUs();
          if (count($ous)>1):?>
        <td><font color=green><?=gettext("OK");?></font></td>
      </tr>
      <tr>
        <td><?=gettext("Organization units found");?> </td>
        <td><font color=green><?=count($ous);?></font></td>
      </tr>
<?php
            foreach($ous as $ou):?>
      <tr><td colspan='2'><?=$ou;?></td></tr>
<?php
            endforeach;
          else:?>
        <td><font color='red'><?=gettext("failed");?></font></td>
      </tr>
<?php
          endif;?>
<?php
        else:?>
        <td><font color='red'><?=gettext("failed");?></font></td>
      </tr>
<?php
        endif;
      endif;?>
      <tr>
        <td colspan="2" style="text-align:right">
          <input type="Button" value="<?=gettext("Close"); ?>" class="btn btn-default" onClick='Javascript:window.close();'>
        </td>
      </tr>
    </table>
  </body>
</html>
