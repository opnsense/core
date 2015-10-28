<?php

/*
    Copyright (C) 2014-2015 Deciso B.V.
    Copyright (C) 2010 Ermal Luçi
    Copyright (C) 2008 Shrew Soft Inc.
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


$auth_server_types = array(
    'ldap' => "LDAP",
    'radius' => "Radius",
    'voucher' => "Voucher"
);


if (!isset($config['system']['authserver'])) {
    $config['system']['authserver'] = array();
}

if (empty($config['ca']) || !is_array($config['ca'])) {
    $config['ca'] = array();
}

$a_servers = auth_get_authserver_list();
$a_server = array();
foreach ($a_servers as $servers) {
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
    if ($act == "new") {
        $pconfig['ldap_protver'] = 3;
        $pconfig['radius_srvcs'] = "both";
        $pconfig['radius_auth_port'] = "1812";
        $pconfig['radius_acct_port'] = "1813";
        $pconfig['type'] = 'ldap';
    } elseif ($act == "edit" && isset($id)) {
        $pconfig['type'] = $a_server[$id]['type'];
        $pconfig['name'] = $a_server[$id]['name'];

        if ($pconfig['type'] == "ldap") {
            $pconfig['ldap_caref'] = $a_server[$id]['ldap_caref'];
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
      if ($pconfig['type'] == "ldap") {
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
      }

      if ($pconfig['type'] == "radius") {
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

      do_input_validation($pconfig, $reqdfields, $reqdfieldsn, $input_errors);

      if (!empty($pconfig['ldap_host']) && preg_match("/[^a-zA-Z0-9\.\-_]/", $pconfig['ldap_host'])) {
          $input_errors[] = gettext("The host name contains invalid characters.");
      }
      if (!empty($pconfig['radius_host']) && preg_match("/[^a-zA-Z0-9\.\-_]/", $pconfig['radius_host'])) {
          $input_errors[] = gettext("The host name contains invalid characters.");
      }

      if (auth_get_authserver($pconfig['name']) && !isset($id)) {
          $input_errors[] = gettext("An authentication server with the same name already exists.");
      }

      if (($pconfig['type'] == "radius") && isset($pconfig['radius_timeout']) && !empty($pconfig['radius_timeout']) && (!is_numeric($pconfig['radius_timeout']) || (is_numeric($pconfig['radius_timeout']) && ($pconfig['radius_timeout'] <= 0)))) {
          $input_errors[] = gettext("RADIUS Timeout value must be numeric and positive.");
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

          if ($server['type'] == "ldap") {
              if (!empty($pconfig['ldap_caref'])) {
                  $server['ldap_caref'] = $pconfig['ldap_caref'];
              }
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
          }

          if (isset($id) && isset($config['system']['authserver'][$id])) {
              $config['system']['authserver'][$id] = $server;
          } else {
              $config['system']['authserver'][] = $server;
          }

          write_config();
          redirectHeader("system_authservers.php");
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
        $savemsg = gettext("Authentication Server")." {$serverdeleted} ".
                    gettext("deleted")."<br />";
        write_config($savemsg);
        redirectHeader("system_authservers.php");
    }

}
$pgtitle = array(gettext('System'), gettext('Users'), gettext('Servers'));
$shortcut_section = "authentication";

// list of all possible fields for auth item (used for form init)
$all_authfields = array('type','name','ldap_caref','ldap_host','ldap_port','ldap_urltype','ldap_protver','ldap_scope',
        'ldap_basedn','ldap_authcn','ldap_extended_query','ldap_binddn','ldap_bindpw','ldap_attr_user','radius_host',
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

$main_buttons = array(
    array('label'=>'Add server', 'href'=>'system_authservers.php?act=new'),
);

?>


<body>

<script type="text/javascript">
//<![CDATA[
function select_clicked() {
    if (document.getElementById("ldap_port").value == '' ||
        document.getElementById("ldap_host").value == '' ||
        document.getElementById("ldap_scope").value == '' ||
        document.getElementById("ldap_basedn").value == '' ) {
          alert("<?=gettext("Please fill the required values.");?>");
          return;
        }
          var url = 'system_usermanager_settings_ldapacpicker.php?';
          url += 'port=' + document.getElementById("ldap_port").value;
          url += '&host=' + document.getElementById("ldap_host").value;
          url += '&scope=' + document.getElementById("ldap_scope").value;
          url += '&basedn=' + document.getElementById("ldap_basedn").value;
          url += '&binddn=' + document.getElementById("ldap_binddn").value;
          url += '&bindpw=' + document.getElementById("ldap_bindpw").value;
          url += '&urltype=' + document.getElementById("ldap_urltype").value;
          url += '&proto=' + document.getElementById("ldap_protver").value;
          url += '&authcn=' + document.getElementById("ldapauthcontainers").value;
          <?php if (count($config['ca']) > 0) :
?>
          url += '&cert=' + document.getElementById("ldap_caref").value;
          <?php
else :?>
          url += '&cert=';
          <?php
endif; ?>
        var oWin = window.open(url,"OPNsense","width=620,height=400,top=150,left=150");
        if (oWin==null || typeof(oWin)=="undefined")
			alert("<?=gettext('Popup blocker detected.  Action aborted.');?>");
}

$( document ).ready(function() {
    $("#type").change(function(){
        $(".auth_radius").addClass('hidden');
        $(".auth_ldap").addClass('hidden');
        $(".auth_voucher").addClass('hidden');
        if ($("#type").val() == 'ldap') {
            $(".auth_ldap").removeClass('hidden');
        } else if ($("#type").val() == 'radius') {
            $(".auth_radius").removeClass('hidden');
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
              $("#ldap_attr_user").val('samAccountName');
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
          type:BootstrapDialog.TYPE_INFO,
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
    $("#type").change();
});
//]]>
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
            <form id="iform" name="iform" action="system_authservers.php" method="post">
              <table class="table table-striped">
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
                    <select name='type' id='type' class="formselect selectpicker" data-style="btn-default">
<?php
                    foreach ($auth_server_types as $typename => $typedesc) :
?>
                      <option value="<?=$typename;?>"><?=$typedesc;?></option>
<?php
                    endforeach; ?>
                    </select>
<?php
else :
?>
                    <strong><?=$auth_server_types[$pconfig['type']];?></strong>
                    <input name='type' type='hidden' id='type' value="<?=$pconfig['type'];?>"/>
<?php
endif; ?>
                  </td>
                </tr>
                <!-- LDAP -->
                <tr class="auth_ldap hidden">
                  <td><a id="help_for_ldap_host" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Hostname or IP address");?></td>
                  <td>
                    <input name="ldap_host" type="text" id="ldap_host" size="20" value="<?=$pconfig['ldap_host'];?>"/>
                    <div class="hidden" for="help_for_ldap_host">
                      <?= gettext("NOTE: When using SSL, this hostname MUST match the Common Name (CN) of the LDAP server's SSL Certificate."); ?>
                    </div>
                  </td>
                </tr>
                <tr class="auth_ldap hidden">
                  <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Port value");?></td>
                  <td>
                    <input name="ldap_port" type="text" id="ldap_port" size="5" value="<?=$pconfig['ldap_port'];?>"/>
                  </td>
                </tr>
                <tr class="auth_ldap hidden">
                  <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Transport");?></td>
                  <td>
                    <select name='ldap_urltype' id='ldap_urltype' class="formselect selectpicker" data-style="btn-default">
                      <option value="TCP - Standard" data-port="389" <?=$pconfig['ldap_urltype'] == "TCP - Standard" ? "selected=\"selected\"" : "";?>>
                        <?=gettext("TCP - Standard");?>
                      </option>
                      <option value="SSL - Encrypted" data-port="636" <?=$pconfig['ldap_urltype'] == "SSL - Encrypted" ? "selected=\"selected\"" : "";?>>
                        <?=gettext("SSL - Encrypted");?>
                      </option>
                    </select>
                  </td>
                </tr>
                <tr class="auth_ldap hidden">
                  <td><a id="help_for_ldap_caref" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Peer Certificate Authority"); ?></td>
                  <td>
<?php
                    if (count($config['ca'])) :?>
                    <select id='ldap_caref' name='ldap_caref' class="formselect selectpicker" data-style="btn-default">
<?php
                    foreach ($config['ca'] as $ca) :
?>
                      <option value="<?=$ca['refid'];?>" <?=$pconfig['ldap_caref'] == $ca['refid'] ? "selected=\"selected\"" : "";?>><?=$ca['descr'];?></option>
<?php
                    endforeach; ?>
                    </select>
                    <div class="hidden" for="help_for_ldap_caref">
                      <span><?=gettext("This option is used if 'SSL Encrypted' option is choosen.");?> <br />
                      <?=gettext("It must match with the CA in the AD otherwise problems will arise.");?></span>
                    </div>
<?php
                    else :?>
                    <b><?=gettext('No Certificate Authorities defined.');?></b> <br /><?=gettext('Create one under');?> <a href="system_camanager.php"><?=gettext('System: Certificates');?></a>.
<?php
                    endif; ?>
                  </td>
                </tr>
                <tr class="auth_ldap hidden">
                  <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Protocol version");?></td>
                  <td>
                    <select name='ldap_protver' id='ldap_protver' class="formselect selectpicker" data-style="btn-default">
                      <option value="2" <?=$pconfig['ldap_protver'] == 2 ? "selected=\"selected\"" : "";?>>2</option>
                      <option value="3" <?=$pconfig['ldap_protver'] == 3 ? "selected=\"selected\"" : "";?>>3</option>
                    </select>
                  </td>
                </tr>
                <tr class="auth_ldap hidden">
                  <td><a id="help_for_ldap_binddn" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Bind credentials");?></td>
                  <td>
                    <?=gettext("User DN:");?><br/>
                    <input name="ldap_binddn" type="text" id="ldap_binddn" size="40" value="<?=$pconfig['ldap_binddn'];?>"/>
                    <?=gettext("Password:");?><br/>
                    <input name="ldap_bindpw" type="password" class="formfld pwd" id="ldap_bindpw" size="20" value="<?=$pconfig['ldap_bindpw'];?>"/><br />
                    <div class="hidden" for="help_for_ldap_binddn">
                      <?=gettext("Leave empty to use anonymous binds to resolve distinguished names");?>
                    </div>
                  </td>
                </tr>
                <tr class="auth_ldap hidden">
                  <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Search scope");?></td>
                  <td>
                    <div>
                      <?=gettext("Level:");?><br/>
                      <select name='ldap_scope' id='ldap_scope' class="formselect selectpicker" data-style="btn-default">
                          <option value="one" <?=$pconfig['ldap_scope'] == 'one' ?  "selected=\"selected\"" : "";?>>
                              <?=gettext('One Level');?>
                          </option>
                          <option value="subtree" <?=$pconfig['ldap_scope'] == 'subtree' ?  "selected=\"selected\"" : "";?>>
                              <?=gettext('Entire Subtree');?>
                          </option>
                      </select>
                    </div>
                    <div>
                      <?=gettext("Base DN:");?><br/>
                      <input name="ldap_basedn" type="text" id="ldap_basedn" size="40" value="<?=$pconfig['ldap_basedn'];?>"/>
                    </div>
                  </td>
                </tr>
                <tr class="auth_ldap hidden">
                  <td><a id="help_for_ldapauthcontainers" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Authentication containers");?></td>
                  <td>
                    <ul class="list-inline">
                    <li><input name="ldapauthcontainers" type="text" id="ldapauthcontainers" size="40" value="<?=$pconfig['ldap_authcn'];?>"/></li>
                    <li><input type="button" onclick="select_clicked();" class="btn btn-default" value="<?=gettext("Select");?>" /></li>
                    </ul>
                    <br/>
                    <div class="hidden" for="help_for_ldapauthcontainers">
                        <br/><?=gettext("Note: Semi-Colon separated. This will be prepended to the search base dn above or you can specify full container path containing a dc= component.");?>
                        <br /><?=gettext("Example:");?> CN=Users;DC=example,DC=com
                        <br /><?=gettext("Example:");?> OU=Staff;OU=Freelancers
                    </div>
                  </td>
                </tr>
                <tr class="auth_ldap hidden">
                  <td><a id="help_for_ldap_extended_query" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Extended Query");?></td>
                  <td>
                    <input name="ldap_extended_query" type="text" id="ldap_extended_query" size="40" value="<?=$pconfig['ldap_extended_query'];?>"/>
                    <div class="hidden" for="help_for_ldap_extended_query">
                      <?=gettext("Example:");?> &amp;(objectClass=inetOrgPerson)(mail=*@example.com)
                    </div>
                  </td>
                </tr>
<?php if (!isset($id)) :
?>
                <tr class="auth_ldap hidden">
                  <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Initial Template");?></td>
                  <td>
                    <select name='ldap_tmpltype' id='ldap_tmpltype' class="formselect selectpicker" data-style="btn-default">
                      <option value="open"><?=gettext('OpenLDAP');?></option>
                      <option value="msad"><?=gettext('Microsoft AD');?></option>
                      <option value="edir"><?=gettext('Novell eDirectory');?></option>
                    </select>
                  </td>
                </tr>
<?php
endif; ?>
                <tr class="auth_ldap hidden">
                  <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("User naming attribute");?></td>
                  <td>
                    <input name="ldap_attr_user" type="text" id="ldap_attr_user" size="20" value="<?=$pconfig['ldap_attr_user'];?>"/>
                  </td>
                </tr>
                <!-- RADIUS -->
                <tr class="auth_radius hidden">
                  <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Hostname or IP address");?></td>
                  <td>
                    <input name="radius_host" type="text" id="radius_host" size="20" value="<?=$pconfig['radius_host'];?>"/>
                  </td>
                </tr>
                <tr class="auth_radius hidden">
                  <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Shared Secret");?></td>
                  <td>
                    <input name="radius_secret" type="password" class="formfld pwd" id="radius_secret" size="20" value="<?=$pconfig['radius_secret'];?>"/>
                  </td>
                </tr>
                <tr class="auth_radius hidden">
                  <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Services offered");?></td>
                  <td>
                    <select name='radius_srvcs' id='radius_srvcs' class="formselect selectpicker" data-style="btn-default">
                      <option value="both" <?=$pconfig['radius_srvcs'] == 'both' ? "selected=\"selected\"" :"";?>>
                        <?=gettext('Authentication and Accounting');?>
                      </option>
                      <option value="auth" <?=$pconfig['radius_srvcs'] == 'auth' ? "selected=\"selected\"" :"";?>>
                        <?=gettext('Authentication');?>
                      </option>
                    </select>
                  </td>
                </tr>
                <tr id="radius_auth" class="auth_radius hidden">
                  <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Authentication port value");?></td>
                  <td>
                    <input name="radius_auth_port" type="text" id="radius_auth_port" size="5" value="<?=$pconfig['radius_auth_port'];?>"/>
                  </td>
                </tr>
                <tr id="radius_acct" class="auth_radius hidden">
                  <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Accounting port value");?></td>
                  <td>
                    <input name="radius_acct_port" type="text" id="radius_acct_port" size="5" value="<?=$pconfig['radius_acct_port'];?>"/>
                  </td>
                </tr>
                <tr class="auth_radius hidden">
                  <td><a id="help_for_radius_timeout" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Authentication Timeout");?></td>
                  <td>
                    <input name="radius_timeout" type="text" id="radius_timeout" size="20" value="<?=$pconfig['radius_timeout'];?>"/>
                    <div class="hidden" for="help_for_radius_timeout">
                      <br /><?= gettext("This value controls how long, in seconds, that the RADIUS server may take to respond to an authentication request.") ?>
                      <br /><?= gettext("If left blank, the default value is 5 seconds.") ?>
                      <br /><br /><?= gettext("NOTE: If you are using an interactive two-factor authentication system, increase this timeout to account for how long it will take the user to receive and enter a token.") ?>
                    </div>
                  </td>
                </tr>
                <tr>
                  <td>&nbsp;</td>
                  <td>
                    <input id="submit" name="save" type="submit" class="btn btn-primary" value="<?=gettext("Save");?>" />
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
            <table class="table table-striped table-sort">
              <thead>
                <tr>
                  <th><?=gettext("Server Name");?></th>
                  <th width="25%"><?=gettext("Type");?></th>
                  <th width="35%"><?=gettext("Host Name");?></th>
                  <th width="10%" class="list"></th>
                </tr>
              </thead>
              <tfoot>
                <tr>
                  <td colspan="4">
                    <p>
                      <?=gettext("Additional authentication servers can be added here.");?>
                    </p>
                  </td>
                </tr>
              </tfoot>
              <tbody>
<?php
$i = 0;
              foreach ($a_server as $server) :
?>
                <tr>
                  <td><?=$server['name']?></td>
                  <td><?=!empty($auth_server_types[$server['type']]) ? $auth_server_types[$server['type']] : "";;?></td>
                  <td><?=$server['host'];?></td>
                  <td>
                    <?php if ($i < (count($a_server) - 1)) :
?>
                    <a href="system_authservers.php?act=edit&amp;id=<?=$i;?>" class="btn btn-default btn-xs">
                      <span class="glyphicon glyphicon-pencil"></span>
                    </a>
                    &nbsp;
                    <a id="del_<?=$i;?>" title="<?=gettext("delete this server"); ?>" data-toggle="tooltip"  class="act_delete btn btn-default btn-xs">
                      <span class="glyphicon glyphicon-remove"></span>
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
