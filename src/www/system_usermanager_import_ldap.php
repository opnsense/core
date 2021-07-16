<?php

/*
 * Copyright (C) 2014-2015 Deciso B.V.
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

function add_local_user($username, $userdn, $userfullname, $useremail)
{
    global $config;

    foreach ($config['system']['user'] as &$user) {
        if ($user['name'] == $username && $user['name'] != 'root') {
          // link local user to remote server by updating user_dn
          $user['user_dn'] = $userdn;
          // trash user password when linking to ldap, avoid accidental login
          // using fall-back local password. User could still reset its
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
    $new_user['email'] = $useremail;
    local_user_set_password($new_user);
    $new_user['uid'] = $config['system']['nextuid']++;
    $config['system']['user'][] = $new_user;
    local_user_set($new_user);
}

$ldap_is_connected = false;
$ldap_users = array();
$ldap_server = array();
$authName = null;
$exit_form = false;

// XXX find first LDAP GUI auth server, better select later on
$servers = explode(',', $config['system']['webgui']['authmode']);
foreach ($servers as $server) {
    $authcfg = auth_get_authserver($server);
    if ($authcfg['type'] == 'ldap' || $authcfg['type'] == 'ldap-totp') {
        $authName = $server;
        $ldap_server = $authcfg;
        if (strstr($ldap_server['ldap_urltype'], "Standard") || strstr($ldap_server['ldap_urltype'], "StartTLS")) {
            $ldap_server['ldap_full_url'] = "ldap://";
        } else {
            $ldap_server['ldap_full_url'] = "ldaps://";
        }
        $ldap_server['ldap_full_url'] .= is_ipaddrv6($authcfg['host']) ? "[{$authcfg['host']}]" : $authcfg['host'];
        if (!empty($ldap_server['ldap_port'])) {
            $ldap_server['ldap_full_url'] .= ":{$authcfg['ldap_port']}";
        }
        break;
    }
}

if ($authName !== null) {
    // connect to ldap server
    $authenticator = (new OPNsense\Auth\AuthenticationFactory())->get($authName);
    // search ldap
    $ldap_is_connected = $authenticator->connect(
        $ldap_server['ldap_full_url'], $ldap_server['ldap_binddn'], $ldap_server['ldap_bindpw']
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
        $result = $authenticator->searchUsers('*', $ldap_server['ldap_attr_user'], $ldap_server['ldap_extended_query']);
        // actual form action, either save new accounts or list missing
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
          // create selected accounts
          $exit_form = true;
          if (isset($_POST['user_dn'])) {
              $update_count = 0;
              foreach ($result as $ldap_user ) {
                  foreach ($_POST['user_dn'] as $userDN) {
                      if ($userDN == $ldap_user['dn'] && !in_array($ldap_user['dn'], $confDNs)) {
                          // strip domain if it exists and cleanse ldap username to make sure it is a valid one for
                          // our system.
                          $username = explode('@', $ldap_user['name'])[0];
                          $username = substr(preg_replace("/[^a-zA-Z0-9\.\-_]/", "", $username),0 ,32);
                          add_local_user($username , $ldap_user['dn'], $ldap_user['fullname'], $ldap_user['email']);
                          $update_count++;
                      }
                  }
              }
              if ($update_count > 0){
                  // write config when changed
                  write_config();
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
 <script>
    // [de]select all
    $( document ).ready(function() {
        $("#select_all").click(function(event){
            $(".user_option").prop('checked', $(this).is(':checked'));
        });
    });

 </script>
<?php if ($exit_form) :
?>
  <script>
    // exit form and reload parent after save
    window.opener.location.href = window.opener.location.href;
  window.close();
  </script>
<?php elseif (!$ldap_is_connected) :
?>
  <p><?=gettext("Could not connect to the LDAP server. Please check your LDAP configuration.");?></p>
  <input type="button" class="btn btn-default" value="<?= html_safe(gettext('Close')) ?>" onClick="window.close();">
<?php
else :
?>
<form method="post">
  <table class="table table-striped">
    <thead>
      <tr>
        <th colspan="2"><?=gettext("Please select users to import:");?></th>
        <th><input type="checkbox" id="select_all"></th>
      </tr>
    </thead>
    <tbody>
<?php foreach ($ldap_users as $username => $userDN): ?>
        <tr><td><?=$username?></td><td><?=$userDN?></td><td> <input type="checkbox" value="<?=$userDN?>" id="user_dn" class="user_option" name="user_dn[]"></td></tr>
<?php endforeach ?>
      <tr>
        <td style="text-align:left" colspan="3">
          <input type="submit" class="btn btn-primary" value="<?= html_safe(gettext('Save')) ?>">
        </td>
      </tr>
    </tbody>
  </table>
  </form>
<?php
endif; ?>
<!-- bootstrap script -->
<script src="<?= cache_safe('/ui/js/bootstrap.min.js') ?>"></script>
<!-- Fancy select with search options -->
<script src="<?= cache_safe('/ui/js/bootstrap-select.min.js') ?>"></script>
 </body>
</html>
