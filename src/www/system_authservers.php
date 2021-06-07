<?php

/*
 * Copyright (C) 2014-2015 Deciso B.V.
 * Copyright (C) 2010 Ermal Luçi
 * Copyright (C) 2008 Shrew Soft Inc. <mgrooms@shrew.net>
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

$authFactory = new \OPNsense\Auth\AuthenticationFactory();
$authCNFOptions = $authFactory->listConfigOptions();

config_read_array('system', 'authserver');
config_read_array('ca');

$a_server = array();
foreach (auth_get_authserver_list() as $servers) {
    $a_server[] = $servers;
}

$act = null;
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // input record id, if valid
    if (isset($_GET['id']) && isset($a_server[$_GET['id']])) {
        $id = $_GET['id'];
    }
    if (isset($_GET['act'])) {
        $act = $_GET['act'];
    }
    $pconfig = array();
    $pconfig['ldap_sync_memberof_groups'] = array();
    if ($act == "new") {
        $pconfig['ldap_protver'] = 3;
        $pconfig['radius_srvcs'] = "both";
        $pconfig['radius_auth_port'] = "1812";
        $pconfig['radius_acct_port'] = "1813";
        $pconfig['type'] = 'ldap';
        // gather auth plugin defaults
        // the hotplug properties should be different per type, if not the default won't function correctly
        foreach ($authCNFOptions as $authType) {
            foreach ($authType['additionalFields'] as $fieldname => $field) {
                if (!empty($field['default']) && empty($pconfig[$fieldname])) {
                    $pconfig[$fieldname] = $field['default'];
                }
            }
        }
    } elseif ($act == "edit" && isset($id)) {
        $pconfig['type'] = $a_server[$id]['type'];
        $pconfig['name'] = $a_server[$id]['name'];

        if (in_array($pconfig['type'], array("ldap", "ldap-totp"))) {
            $pconfig['ldap_host'] = $a_server[$id]['host'];
            $pconfig['ldap_port'] = $a_server[$id]['ldap_port'];
            $pconfig['ldap_urltype'] = $a_server[$id]['ldap_urltype'];
            $pconfig['ldap_protver'] = $a_server[$id]['ldap_protver'];
            $pconfig['ldap_scope'] = $a_server[$id]['ldap_scope'];
            $pconfig['ldap_basedn'] = $a_server[$id]['ldap_basedn'];
            $pconfig['ldap_authcn'] = $a_server[$id]['ldap_authcn'];
            $pconfig['ldap_extended_query'] = $a_server[$id]['ldap_extended_query'];
            $pconfig['ldap_attr_user'] = $a_server[$id]['ldap_attr_user'];
            if (!empty($a_server[$id]['ldap_binddn'])) {
                $pconfig['ldap_binddn'] = $a_server[$id]['ldap_binddn'];
            }
            if (!empty($a_server[$id]['ldap_bindpw'])) {
                $pconfig['ldap_bindpw'] = $a_server[$id]['ldap_bindpw'];
            }
            $pconfig['ldap_read_properties'] = !empty($a_server[$id]['ldap_read_properties']);
            $pconfig['ldap_sync_memberof'] = !empty($a_server[$id]['ldap_sync_memberof']);
            if (!empty($a_server[$id]['ldap_sync_memberof_groups'])) {
                $pconfig['ldap_sync_memberof_groups'] = explode(",", $a_server[$id]['ldap_sync_memberof_groups']);
            }
        } elseif ($pconfig['type'] == "radius") {
            $pconfig['radius_host'] = $a_server[$id]['host'];
            $pconfig['radius_auth_port'] = $a_server[$id]['radius_auth_port'];
            $pconfig['radius_acct_port'] = $a_server[$id]['radius_acct_port'];
            $pconfig['radius_secret'] = $a_server[$id]['radius_secret'];
            $pconfig['radius_timeout'] = $a_server[$id]['radius_timeout'];

            if (!empty($pconfig['radius_auth_port']) &&
                !empty($pconfig['radius_acct_port'])) {
                $pconfig['radius_srvcs'] = "both";
            } else {
                $pconfig['radius_srvcs'] = "auth";
            }

            if (empty($pconfig['radius_auth_port'])) {
                $pconfig['radius_auth_port'] = 1812;
            }
        } elseif ($pconfig['type'] == 'local') {
            foreach (array('password_policy_duration', 'enable_password_policy_constraints',
                'password_policy_complexity', 'password_policy_length') as $fieldname) {
                if (!empty($config['system']['webgui'][$fieldname])) {
                    $pconfig[$fieldname] = $config['system']['webgui'][$fieldname];
                } else {
                    $pconfig[$fieldname] = null;
                }
            }
        }
        if (!empty($authCNFOptions[$pconfig['type']])) {
            foreach ($authCNFOptions[$pconfig['type']]['additionalFields'] as $fieldname => $field) {
                $pconfig[$fieldname] = $a_server[$id][$fieldname];
            }
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input_errors = array();
    $pconfig = $_POST;
    if (isset($pconfig['id']) && isset($a_server[$pconfig['id']])) {
        $id = $pconfig['id'];
    }
    if (isset($pconfig['act'])) {
        $act = $pconfig['act'];
    }
    if (isset($pconfig['save'])) {
      /* input validation */
      if (in_array($pconfig['type'], array("ldap", "ldap-totp"))) {
          $reqdfields = explode(" ", "name type ldap_host ldap_port ".
                          "ldap_urltype ldap_protver ldap_scope ".
                          "ldap_attr_user ldapauthcontainers");
          $reqdfieldsn = array(
              gettext("Descriptive name"),
              gettext("Type"),
              gettext("Hostname or IP"),
              gettext("Port value"),
              gettext("Transport"),
              gettext("Protocol version"),
              gettext("Search level"),
              gettext("User naming Attribute"),
              gettext("Authentication container"));

          if (!empty($pconfig['ldap_binddn']) && !empty($pconfig['ldap_bindpw'])) {
              $reqdfields[] = "ldap_binddn";
              $reqdfields[] = "ldap_bindpw";
              $reqdfieldsn[] = gettext("Bind user DN");
              $reqdfieldsn[] = gettext("Bind Password");
          }
      } elseif ($pconfig['type'] == "radius") {
          $reqdfields = explode(" ", "name type radius_host radius_srvcs");
          $reqdfieldsn = array(
              gettext("Descriptive name"),
              gettext("Type"),
              gettext("Hostname or IP"),
              gettext("Services"));

          if ($pconfig['radisu_srvcs'] == "both" ||
              $pconfig['radisu_srvcs'] == "auth") {
              $reqdfields[] = "radius_auth_port";
              $reqdfieldsn[] = gettext("Authentication port value");
          }

          if ($id == null) {
              $reqdfields[] = "radius_secret";
              $reqdfieldsn[] = gettext("Shared Secret");
          }
      }
      if (!empty($authCNFOptions[$pconfig['type']])) {
          foreach ($authCNFOptions[$pconfig['type']]['additionalFields'] as $fieldname => $field) {
              if (!empty($field['validate'])) {
                  foreach ($field['validate']($pconfig[$fieldname]) as $input_error) {
                      $input_errors[] = $input_error;
                  }
              }
          }
      }

      do_input_validation($pconfig, $reqdfields, $reqdfieldsn, $input_errors);

      if (!empty($pconfig['ldap_host']) && !(is_hostname($pconfig['ldap_host']) || is_ipaddr($pconfig['ldap_host']))) {
          $input_errors[] = gettext("The host name contains invalid characters.");
      }
      if (!empty($pconfig['radius_host']) && !(is_hostname($pconfig['radius_host']) || is_ipaddr($pconfig['radius_host']))) {
          $input_errors[] = gettext("The host name contains invalid characters.");
      }

      if (auth_get_authserver($pconfig['name']) && !isset($id)) {
          $input_errors[] = gettext("An authentication server with the same name already exists.");
      }

      if (($pconfig['type'] == "radius") && isset($pconfig['radius_timeout']) && !empty($pconfig['radius_timeout']) && (!is_numeric($pconfig['radius_timeout']) || (is_numeric($pconfig['radius_timeout']) && ($pconfig['radius_timeout'] <= 0)))) {
          $input_errors[] = gettext("RADIUS Timeout value must be numeric and positive.");
      }
      if (empty($pconfig['name'])) {
          $input_errors[] = gettext('A server name must be provided.');
      } elseif (strpos($pconfig['name'], ',') !== false) {
          $input_errors[] = gettext('Invalid server name given.');
      }

      if (count($input_errors) == 0) {
          $server = array();
          $server['refid'] = uniqid();
          if (isset($id)) {
              $server = $a_server[$id];
          } else {
              $server['type'] = $pconfig['type'];
              $server['name'] = $pconfig['name'];
          }

          if (in_array($server['type'], array("ldap", "ldap-totp"))) {
              $server['host'] = $pconfig['ldap_host'];
              $server['ldap_port'] = $pconfig['ldap_port'];
              $server['ldap_urltype'] = $pconfig['ldap_urltype'];
              $server['ldap_protver'] = $pconfig['ldap_protver'];
              $server['ldap_scope'] = $pconfig['ldap_scope'];
              $server['ldap_basedn'] = $pconfig['ldap_basedn'];
              $server['ldap_authcn'] = $pconfig['ldapauthcontainers'];
              $server['ldap_extended_query'] = $pconfig['ldap_extended_query'];
              $server['ldap_attr_user'] = $pconfig['ldap_attr_user'];
              if (!empty($pconfig['ldap_binddn']) && !empty($pconfig['ldap_bindpw']) ){
                  $server['ldap_binddn'] = $pconfig['ldap_binddn'];
                  $server['ldap_bindpw'] = $pconfig['ldap_bindpw'];
              } else {
                  if (isset($server['ldap_binddn'])) {
                      unset($server['ldap_binddn']);
                  }
                  if (isset($server['ldap_bindpw'])) {
                      unset($server['ldap_bindpw']);
                  }
              }
              $server['ldap_read_properties'] = !empty($pconfig['ldap_read_properties']);
              $server['ldap_sync_memberof'] = !empty($pconfig['ldap_sync_memberof']);
              $server['ldap_sync_memberof_groups'] = !empty($pconfig['ldap_sync_memberof_groups']) ? implode(",", $pconfig['ldap_sync_memberof_groups']) : array();
          } elseif ($server['type'] == "radius") {
              $server['host'] = $pconfig['radius_host'];

              if (!empty($pconfig['radius_secret'])) {
                  $server['radius_secret'] = $pconfig['radius_secret'];
              }

              if (!empty($pconfig['radius_timeout'])) {
                  $server['radius_timeout'] = $pconfig['radius_timeout'];
              } else {
                  $server['radius_timeout'] = 5;
              }

              if ($pconfig['radius_srvcs'] == "both") {
                  $server['radius_auth_port'] = $pconfig['radius_auth_port'];
                  $server['radius_acct_port'] = $pconfig['radius_acct_port'];
              }

              if ($pconfig['radius_srvcs'] == "auth") {
                  $server['radius_auth_port'] = $pconfig['radius_auth_port'];
                  unset($server['radius_acct_port']);
              }
          } elseif ($server['type'] == 'local') {
              foreach (array('password_policy_duration', 'enable_password_policy_constraints',
                  'password_policy_complexity', 'password_policy_length') as $fieldname) {
                  if (!empty($pconfig[$fieldname])) {
                      $config['system']['webgui'][$fieldname] = $pconfig[$fieldname];
                  } elseif (isset($config['system']['webgui'][$fieldname])) {
                      unset($config['system']['webgui'][$fieldname]);
                  }
              }
          }
          if (!empty($authCNFOptions[$server['type']])) {
              foreach ($authCNFOptions[$server['type']]['additionalFields'] as $fieldname => $field) {
                  $server[$fieldname] = $pconfig[$fieldname];
              }
          }

          if ($server['type'] != 'local') {
              if (isset($id) && isset($config['system']['authserver'][$id])) {
                  $config['system']['authserver'][$id] = $server;
              } else {
                  $config['system']['authserver'][] = $server;
              }
          }

          write_config();
          header(url_safe('Location: /system_authservers.php'));
          exit;
      } else {
          $act = "edit";
      }
    } elseif (isset($pconfig['act']) && $pconfig['act'] == 'del' && isset($id)) {
        /* Remove server from main list. */
        $serverdeleted = $a_server[$id]['name'];
        foreach ($config['system']['authserver'] as $k => $as) {
            if ($config['system']['authserver'][$k]['name'] == $serverdeleted) {
                unset($config['system']['authserver'][$k]);
            }
        }
        write_config(sprintf('Authentication server "%s" deleted.', $serverdeleted));
        header(url_safe('Location: /system_authservers.php'));
        exit;
    }
}

// list of all possible fields for auth item (used for form init)
$all_authfields = array(
    'type','name','ldap_host','ldap_port','ldap_urltype','ldap_protver','ldap_scope',
    'ldap_basedn','ldap_authcn','ldap_extended_query','ldap_binddn','ldap_bindpw','ldap_attr_user',
    'ldap_read_properties', 'ldap_sync_memberof', 'radius_host',
    'radius_auth_port','radius_acct_port','radius_secret','radius_timeout','radius_srvcs'
);

foreach ($all_authfields as $fieldname) {
    if (!isset($pconfig[$fieldname])) {
        $pconfig[$fieldname] = null;
    }
}

legacy_html_escape_form_data($pconfig);
legacy_html_escape_form_data($a_server);

include("head.inc");

$main_buttons = array();
if (!isset($_GET['act'])) {
    $main_buttons[] = array('label' => gettext('Add'), 'href' => 'system_authservers.php?act=new');
}

?>
<body>

<script>
$( document ).ready(function() {
    $("#type").change(function () {
        var type = $(this).val();
        if (type == 'Local Database') {
            type = 'local';
        }
        $('.auth_options').addClass('hidden');
        $('.auth_options :input').prop('disabled', true);
        $('.auth_' + type).removeClass('hidden');
        $('.auth_' + type + ' :input').prop('disabled', false);
        $('.selectpicker').selectpicker('refresh');
    });

    $("#enable_password_policy_constraints").change(function () {
        if ($("#enable_password_policy_constraints").prop('checked')) {
            $(".password_policy_constraints").show();
        } else {
            $(".password_policy_constraints").hide();
        }
    });

    $("#ldap_urltype").change(function(){
        $("#ldap_port").val($(this).find(':selected').data('port'));
    });

    $("#ldap_tmpltype").change(function(){
        switch ($("#ldap_tmpltype").val()) {
            case 'open':
            case 'edir':
              $("#ldap_attr_user").val('cn');
              break;
            case 'msad':
              $("#ldap_attr_user").val('sAMAccountName');
              break;
        }
    });

    $("#radius_srvcs").change(function(){
        switch ($("#radius_srvcs").val()) {
            case 'both': // both
              $("#radius_auth").removeClass('hidden');
              $("#radius_acct").removeClass('hidden');
              break;
            default: // authentication
              $("#radius_auth").removeClass('hidden');
              $("#radius_acct").addClass('hidden');
              break;
        }
    });

    $(".act_delete").click(function(){
        var id = $(this).attr("id").split('_').pop(-1);
        // delete single
        BootstrapDialog.show({
          type:BootstrapDialog.TYPE_DANGER,
          title: "<?= gettext("Server");?>",
          message: "<?=gettext("Do you really want to delete this Server?");?>",
          buttons: [{
                    label: "<?= gettext("No");?>",
                    action: function(dialogRef) {
                        dialogRef.close();
                    }}, {
                    label: "<?= gettext("Yes");?>",
                    action: function(dialogRef) {
                      $("#overview_id").val(id);
                      $("#overview_act").val("del");
                      $("#iform_overview").submit()
                  }
                }]
      });
    });

    // init
    $("#radius_srvcs").change();
    if ($("#ldap_port").val() == "") {
        $("#ldap_urltype").change();
    }
    if ($("#ldap_attr_user").val() == "") {
        $("#ldap_tmpltype").change();
    }
    $("#enable_password_policy_constraints").change();
    $("#type").change();

    $("#act_select").click(function() {
        var request_data = {
            'port': $("#ldap_port").val(),
            'host': $("#ldap_host").val(),
            'scope': $("#ldap_scope").val(),
            'basedn': $("#ldap_basedn").val(),
            'binddn': $("#ldap_binddn").val(),
            'bindpw': $("#ldap_bindpw").val(),
            'urltype': $("#ldap_urltype").val(),
            'proto': $("#ldap_protver").val(),
            'authcn': $("#ldapauthcontainers").val(),
        };
        //
        if ($("#ldap_port").val() == '' || $("#ldap_host").val() == '' || $("#ldap_scope").val() == '' || $("#ldap_basedn").val() == '') {
            BootstrapDialog.show({
              type: BootstrapDialog.TYPE_DANGER,
              title: "<?= gettext("Server");?>",
              message: "<?=gettext("Please fill the required values.");?>",
              buttons: [{
                        label: "<?= gettext("Close");?>",
                        action: function(dialogRef) {
                            dialogRef.close();
                        }
                    }]
            });
        } else {
            $.post('system_usermanager_settings_ldapacpicker.php', request_data, function(data) {
                var tbl = $("<table/>");
                var tbl_body = $("<tbody/>");
                if (data.length > 0) {
                    for (var i=0; i < data.length ; ++i) {
                       var tr = $("<tr/>");
                       tr.append($("<td/>").append(
                           $("<input type='checkbox' class='ldap_item_select'>")
                               .prop('checked', data[i].selected)
                               .prop('value', data[i].value)
                       ));
                       tr.append($("<td/>").text(data[i].value));
                       tbl_body.append(tr);
                    }
                } else {
                    tbl_body.append("<tr><td><?=gettext("No results. Check General log for details"); ?></td></tr>");
                }
                tbl.append(tbl_body);
                BootstrapDialog.show({
                  type: BootstrapDialog.TYPE_PRIMARY,
                  title: "<?=gettext("Please select which containers to Authenticate against:");?>",
                  message: tbl,
                  buttons: [{
                            label: "<?= gettext("Save");?>",
                            cssClass: 'btn-primary',
                            action: function(dialogRef) {
                                var values = $(".ldap_item_select:checked").map(function(){
                                    return $(this).val();
                                }).get().join(';');
                                $("#ldapauthcontainers").val(values);
                                dialogRef.close();
                            }}, {
                            label: "<?= gettext("Cancel");?>",
                            action: function(dialogRef) {
                                dialogRef.close();
                        }}]
                });
            }, "json");
        }
    });
    $("#ldap_read_properties").change(function(){
        if ($(this).is(":checked")) {
            $("#ldap_sync_memberof").prop('disabled', false);
            $("#ldap_sync_memberof_groups").prop('disabled', false);
        } else {
            $("#ldap_sync_memberof").prop('disabled', true);
            $("#ldap_sync_memberof_groups").prop('disabled', true);
        }
    });
    $("#ldap_read_properties").change();
});
</script>

<?php include("fbegin.inc");?>
  <section class="page-content-main">
    <div class="container-fluid">
      <div class="row">
<?php
      if (isset($input_errors) && count($input_errors) > 0) {
          print_input_errors($input_errors);
      }
?>
        <section class="col-xs-12">
          <div class="tab-content content-box col-xs-12 table-responsive">
            <?php if ($act == "new" || $act == "edit") :
?>
            <form id="iform" name="iform" method="post">
              <table class="table table-striped opnsense_standard_table_form">
                <tr>
                  <td style="width:22%"></td>
                  <td style="width:78%; text-align:right">
                    <small><?=gettext("full help"); ?> </small>
                    <i class="fa fa-toggle-off text-danger" style="cursor: pointer;" id="show_all_help_page"></i>
                  </td>
                </tr>
                <tr>
                  <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Descriptive name"); ?></td>
                  <td>
<?php if (!isset($id)) :
?>
                    <input name="name" type="text" size="20" value="<?=$pconfig['name'];?>"/>
<?php else :
?>
                    <strong><?=$pconfig['name'];?></strong>
                    <input name="name" type="hidden" value="<?=$pconfig['name'];?>"/>
<?php
endif; ?>
                  </td>
                </tr>
                <tr>
                  <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Type"); ?></td>
                  <td>
<?php if (!isset($id)) :
?>
                    <select name='type' id='type' class="selectpicker" data-style="btn-default">
<?php
                    foreach ($authCNFOptions as $typename => $authType) :?>
                      <option value="<?=$typename;?>" <?=$pconfig['type'] == $typename ? "selected=\"selected\"" : "";?> >
                        <?= !empty($authType['description']) ? $authType['description'] : $pconfig['name'] ?>
                      </option>
<?php
                    endforeach; ?>
                    </select>
<?php
else :
?>
                    <strong><?= !empty($authCNFOptions[$pconfig['type']]['description']) ? $authCNFOptions[$pconfig['type']]['description'] : $pconfig['name'] ?></strong>
                    <input name='type' type='hidden' id='type' value="<?=$pconfig['type'];?>"/>
<?php
endif; ?>
                  </td>
                </tr>
                <!-- Local Database -->
                <tr class="auth_local auth_options hidden">
                  <td><a id="help_for_enable_password_policy_constraints" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext('Policy'); ?></td>
                  <td>
                    <input id="enable_password_policy_constraints" name="enable_password_policy_constraints" type="checkbox" <?= empty($pconfig['enable_password_policy_constraints']) ? '' : 'checked="checked"';?> />
                    <?= gettext('Enable password policy constraints') ?>
                    <div class="hidden" data-for="help_for_enable_password_policy_constraints">
                      <?= gettext('Use hardened security policies for local accounts. Methods other than local these will usually be configured by the respective provider (e.g. LDAP, RADIUS, ...).');?>
                    </div>
                  </td>
                </tr>
                <tr class="auth_local auth_options password_policy_constraints hidden">
                  <td><a id="help_for_password_policy_duration" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext('Duration'); ?></td>
                  <td>
                    <select id="password_policy_duration" name="password_policy_duration" class="selectpicker" data-style="btn-default">
                      <option <?=empty($pconfig['password_policy_duration']) ? "selected=\"selected\"" : "";?> value="0"><?=gettext("Disable");?></option>
                      <option <?=$pconfig['password_policy_duration'] == '30' ? "selected=\"selected\"" : "";?> value="30"><?=sprintf(gettext("%d days"), "30");?></option>
                      <option <?=$pconfig['password_policy_duration'] == '90' ? "selected=\"selected\"" : "";?> value="90"><?=sprintf(gettext("%d days"), "90");?></option>
                      <option <?=$pconfig['password_policy_duration'] == '180' ? "selected=\"selected\"" : "";?> value="180"><?=sprintf(gettext("%d days"), "180");?></option>
                      <option <?=$pconfig['password_policy_duration'] == '360' ? "selected=\"selected\"" : "";?> value="360"><?=sprintf(gettext("%d days"), "360");?></option>
                    </select>
                    <div class="hidden" data-for="help_for_password_policy_duration">
                      <?= gettext("Password duration settings, the interval in days in which passwords stay valid. ".
                                  "When reached, the user will be forced to change his or her password before continuing.");?>
                    </div>
                  </td>
                </tr>
                <tr class="auth_local auth_options password_policy_constraints hidden">
                  <td><a id="help_for_password_policy_length" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext('Length'); ?></td>
                  <td>
                    <select id="password_policy_length" name="password_policy_length" class="selectpicker" data-style="btn-default">
                      <option <?=$pconfig['password_policy_length'] == '4' ? "selected=\"selected\"" : "";?> value="4">4</option>
                      <option <?=$pconfig['password_policy_length'] == '6' ? "selected=\"selected\"" : "";?> value="6">6</option>
                      <option <?=empty($pconfig['password_policy_length']) || $pconfig['password_policy_length'] == '8' ? "selected=\"selected\"" : "";?> value="8">8</option>
                      <option <?=$pconfig['password_policy_length'] == '10' ? "selected=\"selected\"" : "";?> value="10">10</option>
                      <option <?=$pconfig['password_policy_length'] == '12' ? "selected=\"selected\"" : "";?> value="12">12</option>
                      <option <?=$pconfig['password_policy_length'] == '14' ? "selected=\"selected\"" : "";?> value="14">14</option>
                      <option <?=$pconfig['password_policy_length'] == '16' ? "selected=\"selected\"" : "";?> value="16">16</option>
                    </select>
                    <div class="hidden" data-for="help_for_password_policy_length">
                      <?= gettext("Sets the minimum length for a password");?>
                    </div>
                  </td>
                </tr>
                <tr class="auth_local auth_options password_policy_constraints hidden">
                  <td><a id="help_for_password_policy_complexity" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext('Complexity'); ?></td>
                  <td>
                    <input id="password_policy_complexity" name="password_policy_complexity" type="checkbox" <?= empty($pconfig['password_policy_complexity']) ? '' : 'checked="checked"';?> />
                    <?= gettext('Enable complexity requirements') ?>
                    <div class="hidden" data-for="help_for_password_policy_complexity">
                      <?= gettext("Require passwords to meet complexity rules");?>
                    </div>
                  </td>
                </tr>
                <!-- LDAP -->
                <tr class="auth_ldap auth_ldap-totp auth_options hidden">
                  <td><a id="help_for_ldap_host" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Hostname or IP address");?></td>
                  <td>
                    <input name="ldap_host" type="text" id="ldap_host" size="20" value="<?=$pconfig['ldap_host'];?>"/>
                    <div class="hidden" data-for="help_for_ldap_host">
                      <?= gettext("NOTE: When using SSL, this hostname MUST match the Common Name (CN) of the LDAP server's SSL Certificate."); ?>
                    </div>
                  </td>
                </tr>
                <tr class="auth_ldap auth_ldap-totp auth_options hidden">
                  <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Port value");?></td>
                  <td>
                    <input name="ldap_port" type="text" id="ldap_port" size="5" value="<?=$pconfig['ldap_port'];?>"/>
                  </td>
                </tr>
                <tr class="auth_ldap auth_ldap-totp auth_options hidden">
                  <td><a id="help_for_ldap_urltype" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Transport");?></td>
                  <td>
                    <select name="ldap_urltype" id="ldap_urltype" class="selectpicker" data-style="btn-default">
                      <option value="TCP - Standard" data-port="389" <?=$pconfig['ldap_urltype'] == "TCP - Standard" ? "selected=\"selected\"" : "";?>>
                        <?=gettext("TCP - Standard");?>
                      </option>
                      <option value="StartTLS" data-port="389" <?=$pconfig['ldap_urltype'] == "StartTLS" ? "selected=\"selected\"" : "";?>>
                        <?=gettext("StartTLS");?>
                      </option>
                      <option value="SSL - Encrypted" data-port="636" <?=$pconfig['ldap_urltype'] == "SSL - Encrypted" ? "selected=\"selected\"" : "";?>>
                        <?=gettext("SSL - Encrypted");?>
                      </option>
                    </select>
                    <div class="hidden" data-for="help_for_ldap_urltype">
                        <?=gettext("When choosing StartTLS or SSL, please configure the required private CAs in System -> Trust");?>
                    </div>
                  </td>
                </tr>
                <tr class="auth_ldap auth_ldap-totp auth_options hidden">
                  <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Protocol version");?></td>
                  <td>
                    <select name="ldap_protver" id="ldap_protver" class="selectpicker" data-style="btn-default">
                      <option value="2" <?=$pconfig['ldap_protver'] == 2 ? "selected=\"selected\"" : "";?>>2</option>
                      <option value="3" <?=$pconfig['ldap_protver'] == 3 ? "selected=\"selected\"" : "";?>>3</option>
                    </select>
                  </td>
                </tr>
                <tr class="auth_ldap auth_ldap-totp auth_options hidden">
                  <td><a id="help_for_ldap_binddn" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Bind credentials");?></td>
                  <td>
                    <?=gettext("User DN:");?><br/>
                    <input name="ldap_binddn" type="text" id="ldap_binddn" size="40" value="<?=$pconfig['ldap_binddn'];?>"/>
                    <?=gettext("Password:");?><br/>
                    <input name="ldap_bindpw" type="password" id="ldap_bindpw" size="20" value="<?=$pconfig['ldap_bindpw'];?>"/><br />
                    <div class="hidden" data-for="help_for_ldap_binddn">
                      <?=gettext("Leave empty to use anonymous binds to resolve distinguished names");?>
                    </div>
                  </td>
                </tr>
                <tr class="auth_ldap auth_ldap-totp auth_options hidden">
                  <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Search scope");?></td>
                  <td>
                    <select name="ldap_scope" id="ldap_scope" class="selectpicker" data-style="btn-default">
                      <option value="one" <?=$pconfig['ldap_scope'] == 'one' ? "selected=\"selected\"" : "";?>>
                        <?=gettext('One Level');?>
                      </option>
                      <option value="subtree" <?=$pconfig['ldap_scope'] == 'subtree' ? "selected=\"selected\"" : "";?>>
                        <?=gettext('Entire Subtree');?>
                      </option>
                    </select>
                  </td>
                </tr>
                <tr class="auth_ldap auth_ldap-totp auth_options hidden">
                  <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Base DN");?></td>
                  <td>
                    <input name="ldap_basedn" type="text" id="ldap_basedn" size="40" value="<?=$pconfig['ldap_basedn'];?>"/>
                  </td>
                </tr>
                <tr class="auth_ldap auth_ldap-totp auth_options hidden">
                  <td><a id="help_for_ldapauthcontainers" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Authentication containers");?></td>
                  <td>
                    <ul class="list-inline">
                    <li><input name="ldapauthcontainers" type="text" id="ldapauthcontainers" size="40" value="<?=$pconfig['ldap_authcn'];?>"/></li>
                    <li><input type="button" id="act_select" class="btn btn-default" value="<?= html_safe(gettext('Select')) ?>" /></li>
                    </ul>
                    <br/>
                    <div class="hidden" data-for="help_for_ldapauthcontainers">
                        <br/><?= gettext('Semicolon-separated list of distinguished names containing DC= components.') ?>
                        <br/><?=gettext("Example:");?> OU=Freelancers,O=Company,DC=example,DC=com;CN=Users,OU=Staff,O=Company,DC=example,DC=com
                    </div>
                  </td>
                </tr>
                <tr class="auth_ldap auth_ldap-totp auth_options hidden">
                  <td><a id="help_for_ldap_extended_query" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Extended Query");?></td>
                  <td>
                    <input name="ldap_extended_query" type="text" id="ldap_extended_query" size="40" value="<?=$pconfig['ldap_extended_query'];?>"/>
                    <div class="hidden" data-for="help_for_ldap_extended_query">
                      <?=gettext("Example:");?> &amp;(objectClass=inetOrgPerson)(mail=*@example.com)
                    </div>
                  </td>
                </tr>
<?php if (!isset($id)) :
?>
                <tr class="auth_ldap auth_ldap-totp auth_options hidden">
                  <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Initial Template");?></td>
                  <td>
                    <select name="ldap_tmpltype" id="ldap_tmpltype" class="selectpicker" data-style="btn-default">
                      <option value="open"><?=gettext('OpenLDAP');?></option>
                      <option value="msad"><?=gettext('Microsoft AD');?></option>
                      <option value="edir"><?=gettext('Novell eDirectory');?></option>
                    </select>
                  </td>
                </tr>
<?php
endif; ?>
                <tr class="auth_ldap auth_ldap-totp auth_options hidden">
                  <td><a id="help_for_ldap_attr_user" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("User naming attribute");?></td>
                  <td>
                    <input name="ldap_attr_user" type="text" id="ldap_attr_user" size="20" value="<?=$pconfig['ldap_attr_user'];?>"/>
                    <div class="hidden" data-for="help_for_ldap_attr_user">
                      <?= gettext('Typically "cn" (OpenLDAP, Novell eDirectory), "sAMAccountName" (Microsoft AD)') ?>
                    </div>
                  </td>
                </tr>
                <tr class="auth_ldap auth_ldap-totp auth_options hidden">
                  <td><a id="help_for_ldap_read_properties" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext('Read properties'); ?></td>
                  <td>
                    <input id="ldap_read_properties" name="ldap_read_properties" type="checkbox" <?= empty($pconfig['ldap_read_properties']) ? '' : 'checked="checked"';?> />
                    <div class="hidden" data-for="help_for_ldap_read_properties">
                      <?= gettext("Normally the authentication only tries to bind to the remote server, ".
                                  "when this option is enabled also the objects properties are fetched, can be practical for debugging purposes.");?>
                    </div>
                  </td>
                </tr>
                <tr class="auth_ldap auth_ldap-totp auth_options hidden">
                  <td><a id="help_for_ldap_sync_memberof" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext('Synchronize groups'); ?></td>
                  <td>
                    <input id="ldap_sync_memberof" name="ldap_sync_memberof" type="checkbox" <?= empty($pconfig['ldap_sync_memberof']) ? '' : 'checked="checked"';?> />
                    <div class="hidden" data-for="help_for_ldap_sync_memberof">
                      <?= gettext("Synchronize groups specified by memberOf attribute after login, this option requires to enable read properties. ".
                                  "Groups will be extracted from the first CN= section and will only be considered when already existing in OPNsense. ".
                                  "Group memberships will be persisted in OPNsense. ".
                                  "Use the server test tool to check if memberOf is returned by your LDAP server before enabling.");?>
                    </div>
                  </td>
                </tr>
                <tr class="auth_ldap auth_ldap-totp auth_options hidden">
                  <td><a id="help_for_ldap_sync_memberof_groups" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext('Limit groups'); ?></td>
                  <td>
                    <select name='ldap_sync_memberof_groups[]' id="ldap_sync_memberof_groups" class="selectpicker" multiple="multiple">
<?php
                    foreach (config_read_array('system', 'group') as $group):
                        $selected = !empty($pconfig['ldap_sync_memberof_groups']) && in_array($group['name'], $pconfig['ldap_sync_memberof_groups']) ? 'selected="selected"' : ''; ?>
                      <option value="<?= $group['name'] ?>" <?= $selected ?>><?= $group['name'] ?></option>
<?php
                    endforeach; ?>
                    </select>
                    <div class="hidden" data-for="help_for_ldap_sync_memberof_groups">
                      <?= gettext("Limit the groups which may be used by ldap, keep empty to consider all local groups in OPNsense. ".
                                  "When groups are selected, you can assign unassigned groups to the user manually ");?>
                    </div>
                  </td>
                </tr>
                <!-- RADIUS -->
                <tr class="auth_radius auth_options hidden">
                  <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Hostname or IP address");?></td>
                  <td>
                    <input name="radius_host" type="text" id="radius_host" size="20" value="<?=$pconfig['radius_host'];?>"/>
                  </td>
                </tr>
                <tr class="auth_radius auth_options hidden">
                  <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Shared Secret");?></td>
                  <td>
                    <input name="radius_secret" type="password" id="radius_secret" size="20" value="<?=$pconfig['radius_secret'];?>"/>
                  </td>
                </tr>
                <tr class="auth_radius auth_options hidden">
                  <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Services offered");?></td>
                  <td>
                    <select name="radius_srvcs" id="radius_srvcs" class="selectpicker" data-style="btn-default">
                      <option value="both" <?=$pconfig['radius_srvcs'] == 'both' ? "selected=\"selected\"" :"";?>>
                        <?=gettext('Authentication and Accounting');?>
                      </option>
                      <option value="auth" <?=$pconfig['radius_srvcs'] == 'auth' ? "selected=\"selected\"" :"";?>>
                        <?=gettext('Authentication');?>
                      </option>
                    </select>
                  </td>
                </tr>
                <tr id="radius_auth" class="auth_radius auth_options hidden">
                  <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Authentication port value");?></td>
                  <td>
                    <input name="radius_auth_port" type="text" id="radius_auth_port" size="5" value="<?=$pconfig['radius_auth_port'];?>"/>
                  </td>
                </tr>
                <tr id="radius_acct" class="auth_radius auth_options hidden">
                  <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Accounting port value");?></td>
                  <td>
                    <input name="radius_acct_port" type="text" id="radius_acct_port" size="5" value="<?=$pconfig['radius_acct_port'];?>"/>
                  </td>
                </tr>
                <tr class="auth_radius auth_options hidden">
                  <td><a id="help_for_radius_timeout" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Authentication Timeout");?></td>
                  <td>
                    <input name="radius_timeout" type="text" id="radius_timeout" size="20" value="<?=$pconfig['radius_timeout'];?>"/>
                    <div class="hidden" data-for="help_for_radius_timeout">
                      <br /><?= gettext("This value controls how long, in seconds, that the RADIUS server may take to respond to an authentication request.") ?>
                      <br /><?= gettext("If left blank, the default value is 5 seconds.") ?>
                      <br /><br /><?= gettext("NOTE: If you are using an interactive two-factor authentication system, increase this timeout to account for how long it will take the user to receive and enter a token.") ?>
                    </div>
                  </td>
                </tr>
                <!-- pluggable options -->
<?php
                foreach ($authCNFOptions as $typename => $authtype):
                  if (!empty($authtype['additionalFields'])):
                    foreach ($authtype['additionalFields'] as $fieldname => $field):?>

                    <tr class="auth_options auth_<?=$typename;?> hidden">
                      <td>
<?php
                        if (!empty($field['help'])):?>
                        <a id="help_for_field_<?=$typename;?>_<?=$fieldname;?>" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a>
<?php
                        else:?>
                        <i class="fa fa-info-circle text-muted"></i>
<?php
                        endif;?>
                        <?=$field['name']; ?>
                      </td>
                      <td>
<?php
                        if ($field['type'] == 'text'):?>
                        <input name="<?=$fieldname;?>" type="text" value="<?=$pconfig[$fieldname];?>"/>
<?php
                        elseif ($field['type'] == 'dropdown'):?>
                        <select name="<?=$fieldname;?>" class="selectpicker" data-style="btn-default">
<?php
                          foreach ($field['options'] as $option => $optiontext):?>
                          <option value="<?=$option;?>" <?=(empty($pconfig[$fieldname]) && $field['default'] == $option) || $pconfig[$fieldname] == $option ? "selected=\"selected\"" : "";?> >
                            <?=$optiontext;?>
                          </option>
<?php
                          endforeach;?>
                        </select>
<?php
                        elseif ($field['type'] == 'checkbox'):?>
                        <input name="<?=$fieldname;?>" type="checkbox" value="1" <?=!empty($pconfig[$fieldname]) ? "checked=\"checked\"" : ""; ?>/>
<?php
                        endif;?>
                        <div class="hidden" data-for="help_for_field_<?=$typename;?>_<?=$fieldname;?>">
                          <?=$field['help'];?>
                        </div>
                      </td>
                    </tr>


<?php
                    endforeach;
                  endif;
                endforeach;?>
                <!-- /pluggable options -->
                <tr>
                  <td>&nbsp;</td>
                  <td>
                    <input id="submit" name="save" type="submit" class="btn btn-primary" value="<?=html_safe(gettext('Save'));?>" />
<?php if (isset($id)) :
?>
                    <input name="id" type="hidden" value="<?=htmlspecialchars($id);?>" />
<?php
endif;?>
                  </td>
                </tr>
              </table>
            </form>
<?php
else :
?>
          <form id="iform_overview" method="post">
            <input type="hidden" id="overview_id" name="id">
            <input type="hidden" id="overview_act" name="act">
            <table class="table table-striped">
              <thead>
                <tr>
                  <th><?=gettext("Server Name");?></th>
                  <th style="width:25%"><?=gettext("Type");?></th>
                  <th style="width:35%"><?=gettext("Host Name");?></th>
                  <th style="width:10%" class="text-nowrap"></th>
                </tr>
              </thead>
              <tbody>
<?php
              $i = 0;
              foreach ($a_server as $server): ?>
                <tr>
                  <td><?= $server['name'] ?></td>
                  <td><?= !empty($authCNFOptions[$server['type']]) ? $authCNFOptions[$server['type']]['description'] : $server['name'] ?></td>
                  <td><?= !empty($server['host']) ? $server['host'] : $config['system']['hostname'] ?></td>
                  <td class="text-nowrap">
                    <a href="system_authservers.php?act=edit&amp;id=<?=$i;?>" title="<?= html_safe(gettext('Edit')) ?>" data-toggle="tooltip" class="btn btn-default btn-xs">
                      <i class="fa fa-pencil fa-fw"></i>
                    </a>
                    <?php if ($i < (count($a_server) - 1)):
?>
                    <a id="del_<?=$i;?>" title="<?= html_safe(gettext('Delete')) ?>" data-toggle="tooltip" class="act_delete btn btn-default btn-xs">
                      <i class="fa fa-trash fa-fw"></i>
                    </a>
                  </td>
<?php
endif; ?>
                </tr>
<?php
                $i++;
              endforeach;?>
              </tbody>
            </table>
          </form>
<?php
endif; ?>
        </div>
      </section>
    </div>
  </div>
</section>
<?php include("foot.inc");
