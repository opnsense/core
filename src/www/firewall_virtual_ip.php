<?php

/*
    Copyright (C) 2014-2015 Deciso B.V.
    Copyright (C) 2005 Bill Marquette <bill.marquette@gmail.com>.
    Copyright (C) 2003-2005 Manuel Kasper <mk@neon1.net>.
    Copyright (C) 2004-2005 Scott Ullrich <geekgod@pfsense.com>.
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
require_once("filter.inc");

/**
 * delete virtual ip
 */
function deleteVIPEntry($id) {
    global $config;
    $input_errors = array();
    $a_vip = &$config['virtualip']['vip'];
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
        $is_ipv6 = true;
        $subnet = gen_subnetv6($a_vip[$id]['subnet'], $a_vip[$id]['subnet_bits']);
        $if_subnet_bits = get_interface_subnetv6($a_vip[$id]['interface']);
        $if_subnet = gen_subnetv6(get_interface_ipv6($a_vip[$id]['interface']), $if_subnet_bits);
    } else {
        $is_ipv6 = false;
        $subnet = gen_subnet($a_vip[$id]['subnet'], $a_vip[$id]['subnet_bits']);
        $if_subnet_bits = get_interface_subnet($a_vip[$id]['interface']);
        $if_subnet = gen_subnet(get_interface_ip($a_vip[$id]['interface']), $if_subnet_bits);
    }

    $subnet .= "/" . $a_vip[$id]['subnet_bits'];
    $if_subnet .= "/" . $if_subnet_bits;

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

    if ($a_vip[$id]['mode'] == "ipalias") {
        $subnet = gen_subnet($a_vip[$id]['subnet'], $a_vip[$id]['subnet_bits']) . "/" . $a_vip[$id]['subnet_bits'];
        $found_if = false;
        $found_carp = false;
        $found_other_alias = false;

        if ($subnet == $if_subnet)
          $found_if = true;

        $vipiface = $a_vip[$id]['interface'];
        foreach ($a_vip as $vip_id => $vip) {
            if ($vip_id != $id) {
                if ($vip['interface'] == $vipiface && ip_in_subnet($vip['subnet'], $subnet)) {
                    if ($vip['mode'] == "carp") {
                        $found_carp = true;
                    } else if ($vip['mode'] == "ipalias") {
                        $found_other_alias = true;
                    }
                }
            }
        }
        if ($found_carp === true && $found_other_alias === false && $found_if === false) {
            $input_errors[] = gettext("This entry cannot be deleted because it is still referenced by a CARP IP with the description") . " {$vip['descr']}.";
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

/**
 * redirect user if config may not be saved.
 */
function redirectReadOnlyUser() {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    $user = getUserEntry($_SESSION['Username']);
    if (is_array($user) && userHasPrivilege($user, "user-config-readonly")) {
        header("Location: firewall_virtual_ip.php");
        exit;
    }
    session_write_close();
}


if (!isset($config['virtualip']['vip'])) {
    $config['virtualip']['vip'] = array();
}
$a_vip = &$config['virtualip']['vip'];

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
        redirectReadOnlyUser();
        $input_errors = deleteVIPEntry($id);
        if (count($input_errors) == 0) {
            write_config();
            header("Location: firewall_virtual_ip.php");
            exit;
        }
    }  elseif (isset($pconfig['act']) && $pconfig['act'] == 'move' && isset($pconfig['rule']) && count($pconfig['rule']) > 0) {
        redirectReadOnlyUser();
        // move selected rules
        if (!isset($id)) {
            // if rule not set/found, move to end
            $id = count($a_vip);
        }
        $a_vip = legacy_move_config_list_items($a_vip, $id,  $pconfig['rule']);
        write_config();
        header("Location: firewall_virtual_ip.php");
        exit;
    }
}

include("head.inc");

$main_buttons = array(
    array('href'=>'firewall_virtual_ip_edit.php', 'label'=>gettext('Add')),
);

?>
<body>
  <script type="text/javascript">
  $( document ).ready(function() {
    // link delete buttons
    $(".act_delete").click(function(){
      var id = $(this).attr("id").split('_').pop(-1);
      // delete single
      BootstrapDialog.show({
        type:BootstrapDialog.TYPE_INFO,
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

    // link move buttons
    $(".act_move").click(function(){
      var id = $(this).attr("id").split('_').pop(-1);
      $("#id").val(id);
      $("#action").val("move");
      $("#iform").submit();
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
            <div class="content-box-main content-box">
              <form action="firewall_virtual_ip.php" method="post" name="iform" id="iform">
                <input type="hidden" id="id" name="id" value="" />
                <input type="hidden" id="action" name="act" value="" />
                <div class="table-responsive">
                  <table class="table table-striped">
                    <thead>
                      <tr>
                        <td></td>
                        <td><?=gettext("Virtual IP address");?></td>
                        <td><?=gettext("Interface");?></td>
                        <td><?=gettext("Type");?></td>
                        <td><?=gettext("Description");?></td>
                        <td></td>
                      </tr>
                    </thead>
                    <tbody>
<?php
                  $interfaces = get_configured_interface_with_descr(false, true);
                  $interfaces['lo0'] = "Localhost";
                  $i = 0;
                  foreach ($a_vip as $vipent):
                    if(!empty($vipent['subnet']) || !empty($vipent['range']) || !empty($vipent['subnet_bits']) || (isset($vipent['range']['from']) && !empty($vipent['range']['from']))): ?>
                      <tr ondblclick="document.location='firewall_virtual_ip_edit.php?id=<?=$i;?>';">
                        <td>
                          <input type="checkbox" name="rule[]" value="<?=$i;?>"  />
                        </td>
                        <td>
                          <?=($vipent['type'] == "single" || $vipent['type'] == "network") && !empty($vipent['subnet_bits']) ? $vipent['subnet']."/".$vipent['subnet_bits'] : "";?>
                          <?=$vipent['type'] == "range" ? $vipent['range']['from'] . "-" .  $vipent['range']['to'] : "";?>
                          <?=$vipent['mode'] == "carp" ?  " (vhid {$vipent['vhid']})" : "";?>
                        </td>
                        <td>
                          <?=htmlspecialchars($interfaces[$vipent['interface']]);?>
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
                          <a id="move_<?=$i;?>" name="move_<?=$i;?>_x" data-toggle="tooltip" data-placement="left" title="<?=gettext("move selected virtual ip before this rule");?>" class="act_move btn btn-default btn-xs">
                            <span class="glyphicon glyphicon-arrow-left"></span>
                          </a>
                          <a href="firewall_virtual_ip_edit.php?id=<?=$i;?>" data-toggle="tooltip" data-placement="left" title="<?=gettext("edit this virtual ip");?>" class="btn btn-default btn-xs">
                            <span class="glyphicon glyphicon-pencil"></span>
                          </a>
                          <a id="del_<?=$i;?>" title="<?=gettext("delete this virtual ip"); ?>" data-toggle="tooltip"  class="act_delete btn btn-default btn-xs">
                            <span class="glyphicon glyphicon-remove"></span>
                          </a>
                          <a href="firewall_virtual_ip_edit.php?dup=<?=$i;?>" class="btn btn-default btn-xs" data-toggle="tooltip" data-placement="left" title="<?=gettext("add new rule based on this one");?>">
                            <span class="glyphicon glyphicon-plus"></span>
                          </a>
                        </td>
                      </tr>
<?php
                      endif;
                      $i++;
                    endforeach;
                      ?>
                    <?php ?>
                    <tr>
                      <td colspan="5"></td>
                      <td>
                        <a type="submit" id="move_<?=$i;?>" name="move_<?=$i;?>_x" data-toggle="tooltip" data-placement="left" title="<?=gettext("move selected rules to end");?>" class="act_move btn btn-default btn-xs">
                          <span class="glyphicon glyphicon-arrow-left"></span>
                        </a>
                        <a href="firewall_virtual_ip_edit.php" class="btn btn-default btn-xs" data-toggle="tooltip" data-placement="left" title="<?=gettext("add new rule");?>">
                          <span class="glyphicon glyphicon-plus"></span>
                        </a>
                      </td>
                    </tr>
                    </tbody>
                  </table>
                </div>
                <div class="container-fluid">
                  <p><span class="vexpl"><span class="text-danger"><strong><?=gettext("Note:");?><br />
                  </strong></span><?=gettext("The virtual IP addresses defined on this page may be used in");?><a href="firewall_nat.php"> <?=gettext("NAT"); ?> </a><?=gettext("mappings.");?><br />
                  <?=gettext("You can check the status of your CARP Virtual IPs and interfaces ");?><a href="carp_status.php"><?=gettext("here");?></a>.</span></p>
                </div>
              </form>
            </div>
          </section>
        </div>
      </div>
    </section>

<?php include("foot.inc"); ?>
