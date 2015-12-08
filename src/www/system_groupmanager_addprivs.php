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

function cpusercmp($a, $b)
{
    return strcasecmp($a['name'], $b['name']);
}

require_once("guiconfig.inc");


if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($config['system']['group'][$_GET['groupid']])) {
        $groupid = $_GET['groupid'];
        $a_group = & $config['system']['group'][$groupid];
    } else {
        header("Location: system_groupmanager.php");
        exit;
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($config['system']['group'][$_POST['groupid']])) {
        $groupid = $_POST['groupid'];
        $a_group = & $config['system']['group'][$groupid];

        $input_errors = array();
        $pconfig = $_POST;

        /* input validation */
        $reqdfields = explode(" ", "sysprivs");
        $reqdfieldsn = array(gettext("Selected priveleges"));

        do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);

        if (count($input_errors) == 0) {
            if (!is_array($pconfig['sysprivs'])) {
                $pconfig['sysprivs'] = array();
            }

            if (!isset($a_group['priv']) || !count($a_group['priv'])) {
                $a_group['priv'] = $pconfig['sysprivs'];
            } else {
                $a_group['priv'] = array_merge($a_group['priv'], $pconfig['sysprivs']);
            }

            if (is_array($a_group['member'])) {
                foreach ($a_group['member'] as $uid) {
                    $user = getUserEntryByUID($uid);
                    if ($user) {
                        local_user_set($user);
                    }
                }
            }

            if (isset($config['system']['group']) && is_array($config['system']['group'])) {
                usort($config['system']['group'], "cpusercmp");
            }

            write_config();
            header("Location: system_groupmanager.php?act=edit&groupid={$groupid}");
            exit;
        }
    } else {
        header("Location: system_groupmanager.php");
        exit;
    }
}

if (!isset($a_group['priv']) || !is_array($a_group['priv'])) {
    $a_group['priv'] = array();
}

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
          <form method="post" name="iform">
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
                  <input name="groupid" type="hidden" value="<?=$groupid;?>" />
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
