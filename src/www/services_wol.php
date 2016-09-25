<?php

/*
    Copyright (C) 2014-2015 Deciso B.V.
    Copyright (C) 2003-2004 Manuel Kasper <mk@neon1.net>.
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
require_once("interfaces.inc");

if (empty($config['wol']['wolentry']) || !is_array($config['wol']['wolentry'])) {
    $config['wol'] = array();
    $config['wol']['wolentry'] = array();
}
$a_wol = &$config['wol']['wolentry'];


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // delete entry
    if (isset($_POST['act']) && $_POST['act'] == "del" && isset($_POST['id'])) {
        if (!empty($a_wol[$_POST['id']])) {
            unset($a_wol[$_POST['id']]);
            write_config();
        }
        exit;
    } elseif (isset($_POST['act']) && $_POST['act'] == "wakeall") {
        $savemsg = "";
        $result = array();
        foreach ($a_wol as $wolent) {
            $if = $wolent['interface'];
            $ipaddr = get_interface_ip($if);
            if (!is_ipaddr($ipaddr)) {
                continue;
            }
            $bcip = escapeshellarg(gen_subnet_max($ipaddr, get_interface_subnet($if)));
            /* Execute wol command and check return code. */
            if (!mwexec("/usr/local/bin/wol -i {$bcip} ". escapeshellarg($wolent['mac']))) {
                $result[] = sprintf(gettext('Sent magic packet to %s (%s).'), htmlspecialchars($wolent['mac']), $wolent['descr']);
            } else {
                $result[] =  sprintf(gettext('Please check the %ssystem log%s, the wol command for %s (%s) did not complete successfully.'), '<a href="/ui/syslog/logview/showlog/system">', '</a>', $wolent['descr'], htmlspecialchars($wolent['mac']));
            }
        }
        echo json_encode($result);
        exit;
    } elseif (isset($_POST['mac'])) {
        /* input validation */
        if (empty($_POST['mac']) || !is_macaddr($_POST['mac'])) {
            $input_errors[] = gettext("A valid MAC address must be specified.");
        }
        if (empty($_POST['if'])) {
            $input_errors[] = gettext("A valid interface must be specified.");
        } else {
            $ipaddr = get_interface_ip($_POST['if']);
            if (!is_ipaddr($ipaddr)) {
                $input_errors[] = gettext("A valid ip could not be found!");
            }
        }

        if (count($input_errors) == 0) {
            /* determine broadcast address */
            $bcip = escapeshellarg(gen_subnet_max($ipaddr, get_interface_subnet($_POST['if'])));
            /* Execute wol command and check return code. */
            if(!mwexec("/usr/local/bin/wol -i {$bcip} " . escapeshellarg($_POST['mac']))) {
                $savemsg = sprintf(gettext('Sent magic packet to %s.'), $_POST['mac']);
            } else {
                $savemsg = sprintf(gettext('Please check the %ssystem log%s, the wol command for %s did not complete successfully.'), '<a href="/ui/syslog/logview/showlog/system">', '</a>', $_POST['mac']);
            }
        }
    }
}

include("head.inc");
?>

<body>
  <script type="text/javascript">
  $( document ).ready(function() {
    // delete host action
    $(".act_delete_entry").click(function(event){
      event.preventDefault();
      var id = $(this).data("id");
      // delete single
      BootstrapDialog.show({
        type:BootstrapDialog.TYPE_DANGER,
        title: "<?= gettext("Wake on LAN");?>",
        message: "<?=gettext("Do you really want to delete this entry?");?>",
        buttons: [{
                  label: "<?= gettext("No");?>",
                  action: function(dialogRef) {
                      dialogRef.close();
                  }}, {
                  label: "<?= gettext("Yes");?>",
                  action: function(dialogRef) {
                    $.post(window.location, {act: 'del', id:id}, function(data) {
                        location.reload();
                    });
                }
              }]
      });
    });
    $("#act_wake_all").click(function(event){
        event.preventDefault();
        $.post(window.location, {act: 'wakeall'}, function(data) {
          BootstrapDialog.show({
            type:BootstrapDialog.TYPE_INFO,
            title: "<?= gettext("Result");?>",
            message: JSON.parse(data).join('<br/>')
          });
        });
    });
  });
  </script>
<?php include("fbegin.inc"); ?>
  <section class="page-content-main">
    <div class="container-fluid">
      <div class="row">
        <?php if (isset($input_errors) && count($input_errors) > 0) print_input_errors($input_errors); ?>
        <?php if (isset($savemsg)) print_info_box($savemsg); ?>
        <section class="col-xs-12">
          <div class="content-box">
            <div class="content-box-main">
              <form method="post" name="iform" id="iform">
                <div class="table-responsive">
                  <table class="table table-striped opnsense_standard_table_form">
                    <thead>
                      <tr>
                        <td width="22%">
                          <strong><?=gettext("Wake on LAN");?></strong>
                        </td>
                        <td width="78%" align="right">
                          <small><?=gettext("full help"); ?> </small>
                          <i class="fa fa-toggle-off text-danger"  style="cursor: pointer;" id="show_all_help_page"></i>
                          &nbsp;&nbsp;
                        </td>
                      </tr>
                    </thead>
                    <tbody>
                      <tr>
                        <td><a id="help_for_interface" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Interface");?></td>
                        <td>
                          <select name="if" class="selectpicker">
<?php
                          if (!empty($_POST['if'])) {
                              $if = $_POST['if'];
                          } elseif (!empty($_GET['if'])) {
                              $if = $_GET['if'];
                          } else {
                              $if = null;
                          }
                          foreach (get_configured_interface_with_descr() as $iface => $ifacename): ?>
                            <option value="<?=$iface;?>" <?=$iface == $if ? "selected=\"selected\"" : ""; ?>>
                              <?=htmlspecialchars($ifacename);?>
                            </option>
<?php
                          endforeach; ?>
                          </select>
                          <div class="hidden" for="help_for_interface">
                            <?=gettext("Choose which interface the host to be woken up is connected to.");?>
                          </div>
                        </td>
                      </tr>
                      <tr>
                        <td><a id="help_for_mac" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("MAC address");?></td>
                        <td>
                          <input name="mac" type="text" id="mac" value="<?=!empty($_GET['mac']) ? htmlspecialchars(strtolower(str_replace("-", ":", $_GET['mac']))) : "";?>" />
                          <div class="hidden" for="help_for_mac">
                            <?=sprintf(gettext("Enter a MAC address %sin the following format: xx:xx:xx:xx:xx:xx%s"),'<strong>','</strong>');?>
                          </div>
                        </td>
                      </tr>
                      <tr>
                        <td>&nbsp;</td>
                        <td>
                          <input name="Submit" type="submit" class="btn btn-primary" value="<?=gettext("Send");?>" />
                        </td>
                      </tr>
                    </tbody>
                    <tfoot>
                        <tr>
                          <td colspan="2">
                            <?=gettext("Wake all clients at once:");?>
                            <button type="button" class="btn btn-default btn-xs" id="act_wake_all">
                              <span class="glyphicon glyphicon-time"></span>
                            </button>
                            <?=gettext("Or Click the MAC address to wake up an individual device:");?>
                          </td>
                        </tr>
                    </tfoot>
                  </table>
                </div>
              </form>
            </div>
          </div>
        </section>
        <section class="col-xs-12">
          <div class="content-box">
            <div class="content-box-main ">
              <div class="table-responsive">
                <table class="table table-striped">
                  <thead>
                    <tr>
                      <td><?=gettext("Interface");?></td>
                      <td><?=gettext("MAC address");?></td>
                      <td><?=gettext("Description");?></td>
                      <td>
                        <a href="services_wol_edit.php" class="btn btn-default btn-xs"><span class="glyphicon glyphicon-plus"></span></a>
                      </td>
                    </tr>
                  </thead>
                  <tbody>
<?php
                  $i = 0;
                  foreach ($a_wol as $wolent): ?>
                    <tr>
                      <td>
                        <?=htmlspecialchars(convert_friendly_interface_to_friendly_descr($wolent['interface']));?>
                      </td>
                      <td>
                        <a href="?mac=<?=$wolent['mac'];?>&amp;if=<?=$wolent['interface'];?>"><?=strtolower($wolent['mac']);?></a>
                      </td>
                      <td>
                        <?=htmlspecialchars($wolent['descr']);?>
                      </td>
                      <td>
                        <a href="services_wol_edit.php?id=<?=$i;?>" class="btn btn-default btn-xs"><span class="glyphicon glyphicon-pencil"></span></a>
                        <button data-id="<?=$i;?>" type="button" class="act_delete_entry btn btn-xs btn-default"><span class="fa fa-trash text-muted"></span></button>
                      </td>
                    </tr>
<?php
                    $i++;
                  endforeach; ?>
                  </tbody>
                  <tfoot>
                    <tr>
                      <td colspan="4">
                        <span class="text-danger"><strong><?=gettext("Note:");?><br /></strong></span>
                        <?= gettext('This service can be used to wake up (power on) computers by sending special "Magic Packets". The NIC in the computer that is to be woken up must support Wake on LAN and has to be configured properly (WOL cable, BIOS settings).');?>
                      </td>
                    </tr>
                  </tfoot>
                </table>
              </div>
            </div>
          </div>
        </section>
      </div>
    </div>
  </section>
<?php include("foot.inc"); ?>
