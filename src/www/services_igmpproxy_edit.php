<?php

/*
    Copyright (C) 2014-2016 Deciso B.V.
    Copyright (C) 2009 Ermal Luçi
    Copyright (C) 2004 Scott Ullrich
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
require_once("services.inc");
require_once('plugins.inc.d/igmpproxy.inc');

if (!isset($config['igmpproxy']['igmpentry'])) {
    $config['igmpproxy']['igmpentry'] = array();
}
$a_igmpproxy = &$config['igmpproxy']['igmpentry'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($_GET['id']) && !empty($a_igmpproxy[$_GET['id']])) {
        $id = $_GET['id'];
    }
    $pconfig = array();
    foreach (array('ifname', 'threshold', 'type', 'address', 'descr') as $fieldname) {
        if (isset($id) && isset($a_igmpproxy[$id][$fieldname])) {
            $pconfig[$fieldname] = $a_igmpproxy[$id][$fieldname];
        } else {
            $pconfig[$fieldname] = null;
        }
    }
    $pconfig['networks_network'] = array();
    $pconfig['networks_mask'] = array();
    foreach (explode(" ", $pconfig['address']) as $entry) {
        $parts = explode('/', $entry);
        $pconfig['networks_network'][] = $parts[0];
        $pconfig['networks_mask'][] = $parts[1];
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['id']) && !empty($a_igmpproxy[$_POST['id']])) {
        $id = $_POST['id'];
    }
    $pconfig = $_POST;
    $input_errors = array();
    $pconfig['address'] = "";
    foreach ($pconfig['networks_network'] as $idx => $value) {
        if (!empty($value) && !empty($pconfig['networks_mask'][$idx])) {
            $pconfig['address'] .= " " . $value . "/" . $pconfig['networks_mask'][$idx];
        }
    }
    $pconfig['address'] = trim($pconfig['address']);
    if ($pconfig['type'] == "upstream") {
        foreach ($a_igmpproxy as $pid => $proxyentry) {
            if (isset($id) && $id == $pid) {
                continue;
            }
            if ($proxyentry['type'] == "upstream" && $proxyentry['ifname'] != $pconfig['interface']) {
                $input_errors[] = gettext("Only one 'upstream' interface can be configured.");
            }
        }
    }
    if (count($input_errors) == 0) {
        $igmpentry = array();
        $igmpentry['ifname'] = $pconfig['ifname'];
        $igmpentry['threshold'] = $pconfig['threshold'];
        $igmpentry['type'] = $pconfig['type'];
        $igmpentry['address'] = $pconfig['address'];
        $igmpentry['descr'] = $pconfig['descr'];

        if (isset($id)) {
            $a_igmpproxy[$id] = $igmpentry;
        } else {
            $a_igmpproxy[] = $igmpentry;
        }

        write_config();
        igmpproxy_configure_do();
        header(url_safe('Location: /services_igmpproxy.php'));
        exit;
    }
}

legacy_html_escape_form_data($pconfig);
include("head.inc");
?>

<body>
  <?php include("fbegin.inc"); ?>
  <script type="text/javascript">
    $( document ).ready(function() {
      /**
       *  Aliases
       */
      function removeRow() {
          if ( $('#networks_table > tbody > tr').length == 1 ) {
              $('#networks_table > tbody > tr:last > td > input').each(function(){
                $(this).val("");
              });
          } else {
              $(this).parent().parent().remove();
          }
      }
      // add new detail record
      $("#addNew").click(function(){
          // copy last row and reset values
          $('#networks_table > tbody').append('<tr>'+$('#networks_table > tbody > tr:last').html()+'</tr>');
          $('#networks_table > tbody > tr:last > td > input').each(function(){
            $(this).val("");
          });
          //  link network / cidr
          var item_cnt = $('#networks_table > tbody > tr').length;
          $('#networks_table > tbody > tr:last > td:eq(1) > input').attr('id', 'network_n'+item_cnt);
          $('#networks_table > tbody > tr:last > td:eq(2) > select').data('network-id', 'network_n'+item_cnt);
          $(".act-removerow").click(removeRow);
          // hookin ipv4/v6 for new item
          hook_ipv4v6('ipv4v6net', 'network-id');
      });
      $(".act-removerow").click(removeRow);
      // hook in, ipv4/ipv6 selector events
      hook_ipv4v6('ipv4v6net', 'network-id');
    });
  </script>

  <section class="page-content-main">
    <div class="container-fluid">
      <div class="row">
        <?php if (isset($input_errors) && count($input_errors) > 0) print_input_errors($input_errors); ?>
        <section class="col-xs-12">
          <div class="content-box">
              <form method="post" name="iform" id="iform">
                <div class="table-responsive">
                  <table class="table table-striped opnsense_standard_table_form">
                    <tr>
                      <td width="22%"><strong><?=gettext("IGMP Proxy Edit");?></strong></td>
                      <td width="78%" align="right">
                        <small><?=gettext("full help"); ?> </small>
                        <i class="fa fa-toggle-off text-danger"  style="cursor: pointer;" id="show_all_help_page" type="button"></i>
                      </td>
                    </tr>
                    <tr>
                      <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Interface");?></td>
                      <td> <select name="ifname" id="ifname" >
<?php
                        foreach (get_configured_interface_with_descr() as $ifnam => $ifdescr):?>
                          <option value="<?=$ifnam;?>" <?=$ifnam == $pconfig['ifname'] ? "selected=\"selected\"" :"";?>>
                            <?=htmlspecialchars($ifdescr);?>
                          </option>

<?php
                        endforeach;?>
                        </select>
                      </td>
                    </tr>
                    <tr>
                      <td><a id="help_for_descr" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Description");?></td>
                      <td>
                        <input name="descr" type="text" class="formfld unknown" id="descr" size="40" value="<?=$pconfig['descr'];?>" />
                        <div class="hidden" for="help_for_descr">
                          <?=gettext("You may enter a description here for your reference (not parsed).");?>
                        </div>
                      </td>
                    </tr>
                    <tr>
                      <td><a id="help_for_type" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Type");?></td>
                      <td>
                        <select name="type" class="formselect" id="type" >
                          <option value="upstream" <?=$pconfig['type'] == "upstream" ?  "selected=\"selected\"" : ""; ?>><?=gettext("Upstream Interface");?></option>
                          <option value="downstream" <?= $pconfig['type'] == "downstream" ? "selected=\"selected\"" : ""; ?>><?=gettext("Downstream Interface");?></option>
                        </select>
                        <div class="hidden" for="help_for_type">
                            <?=gettext("The upstream network interface is the outgoing interface which is".
                              " responsible for communicating to available multicast data sources.".
                              " There can only be one upstream interface.");?>
                          <br />
                          <?=gettext("Downstream network interfaces are the distribution interfaces to the".
                             " destination networks, where multicast clients can join groups and".
                             " receive multicast data. One or more downstream interfaces must be configured.");?>
                        </div>
                      </td>
                    </tr>
                    <tr>
                      <td><a id="help_for_threshold" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Threshold");?></td>
                      <td>
                        <input name="threshold" type="text" class="formfld unknown" id="threshold" value="<?=$pconfig['threshold'];?>" />
                        <div class="hidden" for="help_for_threshold">
                          <?=gettext("Defines the TTL threshold for the network interface. ".
                               "Packets with a lower TTL than the threshold value will be ignored. ".
                               "This setting is optional, and by default the threshold is 1.");?>
                        </div>
                      </td>
                    </tr>
                  <tr>
                    <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Network(s)");?></td>
                    <td>
                      <table class="table table-striped table-condensed" id="networks_table">
                        <thead>
                          <tr>
                            <th></th>
                            <th><?=gettext("Network"); ?></th>
                            <th><?=gettext("CIDR"); ?></th>
                          </tr>
                        </thead>
                        <tbody>
<?php
                        if (count($pconfig['networks_network']) == 0 ) {
                            $pconfig['networks_network'][] = "";
                            $pconfig['networks_mask'][] = "";
                        }
                        foreach($pconfig['networks_network'] as $item_idx => $network):?>
                          <tr>
                            <td>
                              <div style="cursor:pointer;" class="act-removerow btn btn-default btn-xs" alt="remove"><span class="glyphicon glyphicon-minus"></span></div>
                            </td>
                            <td>
                              <input name="networks_network[]" type="text" id="network_<?=$item_idx;?>" value="<?=$network;?>" />
                            </td>
                            <td>
                              <select name="networks_mask[]" data-network-id="network_<?=$item_idx;?>" class="ipv4v6net" id="mask<?=$item_idx;?>">
<?php
                                for ($i = 128; $i > 0; $i--):?>
                                <option value="<?=$i;?>" <?= $pconfig['networks_mask'][$item_idx] == $i ?  "selected=\"selected\"" : ""?>>
                                  <?=$i;?>
                                </option>
<?php
                                endfor;?>
                              </select>
                            </td>
                          </tr>
<?php
                        endforeach;?>
                        </tbody>
                        <tfoot>
                          <tr>
                            <td colspan="4">
                              <div id="addNew" style="cursor:pointer;" class="btn btn-default btn-xs" alt="add"><span class="glyphicon glyphicon-plus"></span></div>
                            </td>
                          </tr>
                        </tfoot>
                      </table>
                    </td>
                  </tr>
                  <tr>
                    <td></td>
                    <td>
                      <input id="submit" name="submit" type="submit" class="btn btn-primary" value="<?=gettext("Save");?>" />
                      <a href="services_igmpproxy.php"><input id="cancelbutton" name="cancelbutton" type="button" class="btn btn-default" value="<?=gettext("Cancel");?>" /></a>
                      <?php if (isset($id)): ?>
                      <input name="id" type="hidden" value="<?=htmlspecialchars($id);?>" />
                      <?php endif; ?>
                    </td>
                  </tr>
                </table>
              </div>
            </form>
          </div>
        </section>
      </div>
    </div>
  </section>
<?php include("foot.inc"); ?>
