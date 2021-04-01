<?php

/*
 * Copyright (C) 2014-2015 Deciso B.V.
 * Copyright (C) 2005 Bill Marquette <bill.marquette@gmail.com>
 * Copyright (C) 2003-2005 Manuel Kasper <mk@neon1.net>
 * Copyright (C) 2004-2005 Scott Ullrich <sullrich@gmail.com>
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
require_once("interfaces.inc");
require_once("filter.inc");

/**
 * delete virtual ip
 */
function deleteVIPEntry($id) {
    global $config;
    $input_errors = array();
    $a_vip = &config_read_array('virtualip', 'vip');
    /* make sure no inbound NAT mappings reference this entry */
    if (isset($config['nat']['rule'])) {
        foreach ($config['nat']['rule'] as $rule) {
            if(!empty($rule['destination']['address'])) {
                if ($rule['destination']['address'] == $a_vip[$id]['subnet']) {
                    $input_errors[] = gettext("This entry cannot be deleted because it is still referenced by at least one NAT mapping.");
                    break;
                }
            }
        }
    }

    if (is_ipaddrv6($a_vip[$id]['subnet'])) {
        $if_subnet = find_interface_networkv6(get_real_interface($a_vip[$id]['interface'], 'inet6'));
        $subnet = gen_subnetv6($a_vip[$id]['subnet'], $a_vip[$id]['subnet_bits']);
        $is_ipv6 = true;
    } else {
        $if_subnet = find_interface_network(get_real_interface($a_vip[$id]['interface']));
        $subnet = gen_subnet($a_vip[$id]['subnet'], $a_vip[$id]['subnet_bits']);
        $is_ipv6 = false;
    }

    $subnet .= "/" . $a_vip[$id]['subnet_bits'];

    if (isset($config['gateways']['gateway_item'])) {
        foreach($config['gateways']['gateway_item'] as $gateway) {
            if ($a_vip[$id]['interface'] != $gateway['interface'])
                continue;
            if ($is_ipv6 && $gateway['ipprotocol'] == 'inet')
                continue;
            if (!$is_ipv6 && $gateway['ipprotocol'] == 'inet6')
                continue;
            if (ip_in_subnet($gateway['gateway'], $if_subnet))
                continue;

            if (ip_in_subnet($gateway['gateway'], $subnet)) {
                $input_errors[] = gettext("This entry cannot be deleted because it is still referenced by at least one Gateway.");
                break;
            }
        }
    }

    if (count($input_errors) == 0) {
        // Special case since every proxyarp vip is handled by the same daemon.
        if ($a_vip[$id]['mode'] == "proxyarp") {
            $viface = $a_vip[$id]['interface'];
            unset($a_vip[$id]);
            interface_proxyarp_configure($viface);
        } else {
            interface_vip_bring_down($a_vip[$id]);
            unset($a_vip[$id]);
        }
        if (count($config['virtualip']['vip']) == 0) {
            unset($config['virtualip']['vip']);
        }
    }
    return $input_errors;
}

$a_vip = &config_read_array('virtualip', 'vip');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pconfig = $_POST;
    if (isset($pconfig['id']) && isset($a_vip[$pconfig['id']])) {
        // id found and valid
        $id = $pconfig['id'];
    }
    if (isset($pconfig['apply'])) {
        if (file_exists('/tmp/.firewall_virtual_ip.apply')) {
            $toapplylist = unserialize(file_get_contents('/tmp/.firewall_virtual_ip.apply'));
            foreach ($toapplylist as $vid => $ovip) {
                if (!empty($ovip)) {
                    interface_vip_bring_down($ovip);
                }
                if (!empty($a_vip[$vid])) {
                    switch ($a_vip[$vid]['mode']) {
                      case "ipalias":
                          interface_ipalias_configure($a_vip[$vid]);
                          break;
                      case "proxyarp":
                          interface_proxyarp_configure($a_vip[$vid]['interface']);
                          break;
                      case "carp":
                          interface_carp_configure($a_vip[$vid]);
                          break;
                      default:
                      break;
                    }
                }
            }
            @unlink('/tmp/.firewall_virtual_ip.apply');
        }
        filter_configure();
        $savemsg = get_std_save_message();
        clear_subsystem_dirty('vip');
    } elseif (isset($pconfig['act']) && $pconfig['act'] == 'del' && isset($id)) {
        $input_errors = deleteVIPEntry($id);
        if (count($input_errors) == 0) {
            write_config();
            header(url_safe('Location: /firewall_virtual_ip.php'));
            exit;
        }
    }  elseif (isset($pconfig['act']) && $pconfig['act'] == 'del_x' && isset($pconfig['rule']) && count($pconfig['rule']) > 0) {
        // delete selected VIPs, sort rule in reverse order to delete the highest item sequences first
        foreach (array_reverse($pconfig['rule']) as $ruleId) {
            if (isset($a_vip[$ruleId])) {
                deleteVIPEntry($ruleId);
            }
        }
        write_config();
        header(url_safe('Location: /firewall_virtual_ip.php'));
        exit;
    }  elseif (isset($pconfig['act']) && $pconfig['act'] == 'move' && isset($pconfig['rule']) && count($pconfig['rule']) > 0) {
        // move selected rules
        if (!isset($id)) {
            // if rule not set/found, move to end
            $id = count($a_vip);
        }
        $a_vip = legacy_move_config_list_items($a_vip, $id,  $pconfig['rule']);
        write_config();
        header(url_safe('Location: /firewall_virtual_ip.php'));
        exit;
    }
}

include("head.inc");

?>
<body>
  <script>
  $( document ).ready(function() {
    // link delete buttons
    $(".act_delete").click(function(){
      var id = $(this).attr("id").split('_').pop(-1);
      // delete single
      BootstrapDialog.show({
        type:BootstrapDialog.TYPE_DANGER,
        title: "<?= gettext("Virtual IP");?>",
        message: "<?=gettext("Do you really want to delete this entry?");?>",
        buttons: [{
                  label: "<?= gettext("No");?>",
                  action: function(dialogRef) {
                      dialogRef.close();
                  }}, {
                  label: "<?= gettext("Yes");?>",
                  action: function(dialogRef) {
                    $("#id").val(id);
                    $("#action").val("del");
                    $("#iform").submit()
                }
              }]
      });
    });

    $("#del_x").click(function(){
      BootstrapDialog.show({
        type:BootstrapDialog.TYPE_DANGER,
        title: "<?= gettext("Rules");?>",
        message: "<?=gettext("Do you really want to delete the selected Virtual IPs?");?>",
        buttons: [{
                  label: "<?= gettext("No");?>",
                  action: function(dialogRef) {
                      dialogRef.close();
                  }}, {
                  label: "<?= gettext("Yes");?>",
                  action: function(dialogRef) {
                    $("#id").val("");
                    $("#action").val("del_x");
                    $("#iform").submit()
                }
              }]
      });
    });

    // link move buttons
    $(".act_move").click(function(){
      var id = $(this).attr("id").split('_').pop(-1);
      $("#id").val(id);
      $("#action").val("move");
      $("#iform").submit();
    });

    // select All
    $("#selectAll").click(function(){
        $(".rule_select").prop("checked", $(this).prop("checked"));
    });
  });
  </script>
  <?php include("fbegin.inc"); ?>
  <section class="page-content-main">
    <div class="container-fluid">
      <div class="row">
        <?php
          if (isset($input_errors) && count($input_errors) > 0)
            print_input_errors($input_errors);
          else
          if (isset($savemsg))
            print_info_box($savemsg);
          else
          if (is_subsystem_dirty('vip'))
            print_info_box_apply(gettext("The VIP configuration has been changed.")."<br />".gettext("You must apply the changes in order for them to take effect."));
        ?>
        <section class="col-xs-12">
          <div class="content-box tab-content">
            <form method="post" name="iform" id="iform">
              <input type="hidden" id="id" name="id" value="" />
              <input type="hidden" id="action" name="act" value="" />
              <table class="table table-striped">
                <thead>
                  <tr>
                    <td><input type="checkbox" id="selectAll"></td>
                    <td><?=gettext("Virtual IP address");?></td>
                    <td><?=gettext("Interface");?></td>
                    <td><?=gettext("Type");?></td>
                    <td><?=gettext("Description");?></td>
                    <td>
                      <a href="firewall_virtual_ip_edit.php" class="btn btn-primary btn-xs" data-toggle="tooltip" title="<?= html_safe(gettext('Add')) ?>">
                        <i class="fa fa-plus fa-fw"></i> <?= $button['label'] ?>
                      </a>
                      <a type="submit" id="move_<?=$i;?>" name="move_<?=$i;?>_x" data-toggle="tooltip" title="<?= html_safe(gettext("Move selected virtual IPs to end")) ?>" class="act_move btn btn-default btn-xs">
                        <i class="fa fa-arrow-left fa-fw"></i>
                      </a>
                      <a id="del_x" title="<?= html_safe(gettext('delete selected virtual IPs')) ?>" data-toggle="tooltip" class="btn btn-default btn-xs">
                        <i class="fa fa-trash fa-fw"></i>
                      </a>
                    </td>
                  </tr>
                </thead>
                <tbody>
<?php
                  $interfaces = legacy_config_get_interfaces(array('virtual' => false));
                  $interfaces['lo0'] = array('descr' => 'Loopback');
                  $i = 0;
                  foreach ($a_vip as $vipent):
                    if(!empty($vipent['subnet']) || !empty($vipent['range']) || !empty($vipent['subnet_bits']) || (isset($vipent['range']['from']) && !empty($vipent['range']['from']))): ?>
                  <tr ondblclick="document.location='firewall_virtual_ip_edit.php?id=<?=$i;?>';">
                    <td>
                      <input class="rule_select" type="checkbox" name="rule[]" value="<?=$i;?>"  />
                    </td>
                    <td>
                      <?=($vipent['type'] == "single" || $vipent['type'] == "network") && !empty($vipent['subnet_bits']) ? $vipent['subnet']."/".$vipent['subnet_bits'] : "";?>
                      <?=$vipent['type'] == "range" ? $vipent['range']['from'] . "-" .  $vipent['range']['to'] : "";?>
                      <?=$vipent['mode'] == "carp" ?  " (vhid {$vipent['vhid']} , freq. {$vipent['advbase']} / {$vipent['advskew']})" : "";?>
                      <?=$vipent['mode'] != "carp" && !empty($vipent['vhid']) ? " (vhid {$vipent['vhid']})" : "";?>
                    </td>
                    <td>
                      <?= htmlspecialchars($interfaces[$vipent['interface']]['descr']) ?>
                    </td>
                    <td>
                      <?=$vipent['mode'] == "proxyarp" ? "Proxy ARP" : "";?>
                      <?=$vipent['mode'] == "carp" ? "CARP" : "";?>
                      <?=$vipent['mode'] == "other" ? "Other" : "";?>
                      <?=$vipent['mode'] == "ipalias" ? "IP Alias" :"";?>
                    </td>
                    <td>
                      <?=htmlspecialchars($vipent['descr']);?>
                    </td>
                    <td>
                      <a id="move_<?=$i;?>" name="move_<?=$i;?>_x" data-toggle="tooltip" title="<?= html_safe(gettext("Move selected virtual IPs before this entry")) ?>" class="act_move btn btn-default btn-xs">
                        <span class="fa fa-arrow-left fa-fw"></span>
                      </a>
                      <a href="firewall_virtual_ip_edit.php?id=<?=$i;?>" data-toggle="tooltip" title="<?= html_safe(gettext('Edit')) ?>" class="btn btn-default btn-xs">
                        <span class="fa fa-pencil fa-fw"></span>
                      </a>
                      <a id="del_<?=$i;?>" title="<?= html_safe(gettext('Delete')) ?>" data-toggle="tooltip"  class="act_delete btn btn-default btn-xs">
                        <span class="fa fa-trash fa-fw"></span>
                      </a>
                      <a href="firewall_virtual_ip_edit.php?dup=<?=$i;?>" class="btn btn-default btn-xs" data-toggle="tooltip" title="<?= html_safe(gettext('Clone')) ?>">
                        <span class="fa fa-clone fa-fw"></span>
                      </a>
                    </td>
                  </tr>
<?php
                      endif;
                      $i++;
                    endforeach;
                      ?>
                </tbody>
              </table>
            </form>
          </div>
        </section>
      </div>
    </div>
  </section>

<?php include("foot.inc"); ?>
