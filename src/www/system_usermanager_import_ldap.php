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

function add_local_user($username, $userdn, $userfullname)
{
    global $config;

    foreach ($config['system']['user'] as &$user) {
        if ($user['name'] == $username && $user['name'] != 'root') {
          // link local user to remote server by updating user_dn
          $user['user_dn'] = $userdn;
          // trash user password when linking to ldap, avoid accidental login
          // using fall-back local password. User could still reset it's
          // local password, but only by choice.
          local_user_set_password($user);
          local_user_set($user);
          return;
        }
    }
    // new user, add
    $new_user = array();
    $new_user['scope'] = 'user';
    $new_user['name'] = $username;
    $new_user['user_dn'] = $userdn;
    $new_user['descr'] = $userfullname;
    local_user_set_password($new_user);
    $new_user['uid'] = $config['system']['nextuid']++;
    $config['system']['user'][] = $new_user;
    local_user_set($new_user);
}

// attributes used in page
$ldap_users= array();
$ldap_is_connected = false;
$exit_form = false;

// find gui auth server
$authcfg = auth_get_authserver($config['system']['webgui']['authmode']);

if ($authcfg['type'] == 'ldap') {
    // setup peer ca
    ldap_setup_caenv($authcfg);
    // connect to ldap server
    $ldap_auth = new OPNsense\Auth\LDAP($authcfg['ldap_basedn'], $authcfg['ldap_protver']);
    $ldap_is_connected = $ldap_auth->connect($authcfg['ldap_full_url']
                        , $authcfg['ldap_binddn']
                        , $authcfg['ldap_bindpw']
                      );
    if ($ldap_is_connected) {
        // collect list of current ldap users from config
        $confDNs = array();
        foreach ($config['system']['user'] as $confUser) {
           if (!empty($confUser['user_dn'])) {
              $confDNs[] = trim($confUser['user_dn']);
           }
        }

        // search ldap
        $result = $ldap_auth->searchUsers("*"
                  , $authcfg['ldap_attr_user']
                  , $authcfg['ldap_extended_query']
                );

        // actual form action, either save new accounts or list missing
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
          // create selected accounts
          $exit_form = true;
          if (isset($_POST['user_dn'])) {
              $update_count = 0;
              foreach ($result as $ldap_user ) {
                  foreach ($_POST['user_dn'] as $userDN) {
                      if ($userDN == $ldap_user['dn'] && !in_array($ldap_user['dn'], $confDNs)) {
                          add_local_user($ldap_user['name'] , $ldap_user['dn'], $ldap_user['fullname']);
                          $update_count++;
                      }
                  }
                  if ($update_count > 0){
                      // write config when changed
                      write_config();
                  }
              }
          }
      } else {
          if (is_array($result)) {
              // list all missing accounts
              foreach ($result as $ldap_user ) {
                  if (!in_array($ldap_user['dn'], $confDNs)) {
                      $ldap_users[$ldap_user['name']] = $ldap_user['dn'];
                  }
              }
              ksort($ldap_users);
            }
        }
    }
}

include('head.inc');
?>

 <body>
<?php if ($exit_form) :
?>
  <script type="text/javascript">
    // exit form and reload parent after save
    window.opener.location.href = window.opener.location.href;
  window.close();
  </script>
<?php elseif (!$ldap_is_connected) :
?>
  <p><?=gettext("Could not connect to the LDAP server. Please check your LDAP configuration.");?></p>
  <input type='button' class="btn btn-default" value='<?=gettext("Close"); ?>' onClick="window.close();">
<?php
else :
?>
<form method="post">
  <table class="table table-striped">
    <tbody>
      <tr>
      <th colspan="3">
        <?=gettext("Please select users to import:");?>
      </th>
      </tr>
      <?php foreach ($ldap_users as $username => $userDN) :
?>
        <tr><td><?=$username?></td><td><?=$userDN?></td><td> <input type='checkbox' value="<?=$userDN?>" id='user_dn' name='user_dn[]'>  </td></tr>
      <?php endforeach;
?>
      <tr>
        <td style="text-align:left" colspan="3">
          <input type='submit' class="btn btn-primary" value='<?=gettext("Save");?>'>
        </td>
      </tr>
    </tbody>
  </table>
  </form>
<?php
endif; ?>
<!-- bootstrap script -->
<script type="text/javascript" src="/ui/js/bootstrap.min.js"></script>
<!-- Fancy select with search options -->
<script type="text/javascript" src="/ui/js/bootstrap-select.min.js"></script>
 </body>
</html>
