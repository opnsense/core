<?php

/*
  Copyright (C) 2014-2015 Deciso B.V.
  Copyright (C) 2006 Daniel S. Haischt.
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

function admusercmp($a, $b)
{
    return strcasecmp($a['name'], $b['name']);
}

function sort_user_privs($privs) {
    // Privileges to place first, to redirect properly.
    $priority_privs = array("page-dashboard-all", "page-system-login/logout");

    $fprivs = array_intersect($privs, $priority_privs);
    $sprivs  = array_diff($privs, $priority_privs);

    return array_merge($fprivs, $sprivs);
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($_GET['userid']) && isset($config['system']['user'][$_GET['userid']]['name'])) {
        $userid = $_GET['userid'];
    } else {
        redirectHeader("system_usermanager.php");
        exit;
    }
    $a_user = & $config['system']['user'][$userid];
    if (!isset($a_user['priv']) || !is_array($a_user['priv'])) {
        $a_user['priv'] = array();
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['userid']) && isset($config['system']['user'][$_POST['userid']]['name'])) {
        $userid = $_POST['userid'];
        $input_errors = array();
        $pconfig = $_POST;

        /* input validation */
        $reqdfields = explode(" ", "sysprivs");
        $reqdfieldsn = array(gettext("Selected priveleges"));

        do_input_validation($pconfig, $reqdfields, $reqdfieldsn, $input_errors);

        if (count($input_errors) == 0) {
            $a_user = & $config['system']['user'][$userid];
            if (!is_array($pconfig['sysprivs'])) {
                $pconfig['sysprivs'] = array();
            }

            if (!isset($a_user['priv']) || !count($a_user['priv'])) {
                $a_user['priv'] = $pconfig['sysprivs'];
            } else {
                $a_user['priv'] = array_merge($a_user['priv'], $pconfig['sysprivs']);
            }

            $a_user['priv'] = sort_user_privs($a_user['priv']);
            local_user_set($a_user);
            $retval = write_config();
            $savemsg = get_std_save_message();

            redirectHeader("system_usermanager.php?act=edit&userid=".$userid."&savemsg=".$savemsg);
            exit;
        }
    } else {
        redirectHeader("system_usermanager.php");
        exit;
    }
}

$pgtitle = array(gettext('System'), gettext('Users'),gettext('Privileges'));

include("head.inc");
?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC" >
<?php include("fbegin.inc"); ?>
<script type="text/javascript">
    $( document ).ready(function() {
        $("#sysprivs").change(function(){
            $("#pdesc").html($(this).find(':selected').data('descr'));
        });
    });
</script>

<section class="page-content-main">
  <div class="container-fluid">
    <div class="row">
<?php
    if (isset($input_errors) && count($input_errors) > 0) {
        print_input_errors($input_errors);
    }
?>
      <section class="col-xs-12">
        <div class="tab-content content-box col-xs-12">
          <form action="system_usermanager_addprivs.php" method="post" name="iform">
            <table class="table table-striped">
              <tr>
                <td width="22%"><?=gettext("System Privileges");?></td>
                <td width="78%">
                  <select name="sysprivs[]" id="sysprivs" class="formselect" multiple="multiple" size="35">
<?php
                  foreach ($priv_list as $pname => $pdata) :
                      if (in_array($pname, $a_user['priv'])) {
                          continue;
                      }
?>
                    <option data-descr="<?=!empty($pdata['descr']) ? $pdata['descr'] : "";?>" value="<?=$pname;?>">
                      <?=$pdata['name'];?>
                    </option>
<?php
                  endforeach; ?>
                  </select>
                  <br />
                  <?=gettext("Hold down CTRL (pc)/COMMAND (mac) key to select multiple items");?>
                </td>
              </tr>
              <tr>
                <td><?=gettext("Description");?></td>
                <td id="pdesc">
                  <em><?=gettext("Select a privilege from the list above for a description"); ?></em>
                </td>
              </tr>
              <tr>
                <td>&nbsp;</td>
                <td>
                  <input type="submit" class="btn btn-primary" value="<?=gettext("Save");?>" />
                  <input class="btn btn-default" type="button" value="<?=gettext("Cancel");?>" onclick="history.back()" />
                  <input name="userid" type="hidden" value="<?=$userid;?>" />
                </td>
              </tr>
            </table>
          </form>
        </div>
      </section>
    </div>
  </div>
</section>
<?php include("foot.inc"); ?>
