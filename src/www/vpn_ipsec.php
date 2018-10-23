<?php

/*
    Copyright (C) 2014-2016 Deciso B.V.
    Copyright (C) 2003-2005 Manuel Kasper <mk@neon1.net>
    Copyright (C) 2008 Shrew Soft Inc. <mgrooms@shrew.net>
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
require_once("system.inc");
require_once("filter.inc");
require_once("plugins.inc.d/ipsec.inc");
require_once("services.inc");
require_once("interfaces.inc");

/*
 *  Return phase2 idinfo in text format
 */
function ipsec_idinfo_to_text(& $idinfo) {
    global $config;

    switch ($idinfo['type']) {
        case "address":
            return $idinfo['address'];
            break; /* NOTREACHED */
        case "network":
            return $idinfo['address']."/".$idinfo['netbits'];
            break; /* NOTREACHED */
        case "mobile":
            return gettext("Mobile Client");
            break; /* NOTREACHED */
        case "none":
            return gettext("None");
            break; /* NOTREACHED */
        default:
            if (!empty($config['interfaces'][$idinfo['type']])) {
                return convert_friendly_interface_to_friendly_descr($idinfo['type']);
            } else {
                return strtoupper($idinfo['type']);
            }
            break; /* NOTREACHED */
    }
}

$a_phase1 = &config_read_array('ipsec', 'phase1');
$a_phase2 = &config_read_array('ipsec', 'phase2');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['apply'])) {
        ipsec_configure_do();
        filter_configure();
        $savemsg = get_std_save_message();
        clear_subsystem_dirty('ipsec');
    } elseif (isset($_POST['save'])) {
        if (!empty($_POST['enable'])) {
            $config['ipsec']['enable'] = true;
        } elseif (isset($config['ipsec']['enable'])) {
            unset($config['ipsec']['enable']);
        }
        write_config();
        ipsec_configure_do();
        filter_configure();
        clear_subsystem_dirty('ipsec');
        header(url_safe('Location: /vpn_ipsec.php'));
        exit;
    } elseif (!empty($_POST['act']) && $_POST['act'] == "delphase1" ) {
        $del_items = array();
        if (isset($_POST['id']) && isset($config['ipsec']['phase1'][$_POST['id']])){
            $del_items[] = $_POST['id'];
        } elseif (empty($_POST['id']) && isset($_POST['p1entry']) && count($_POST['p1entry'])) {
            $del_items = $_POST['p1entry'];
        }

        foreach ($del_items as $p1entrydel) {
            /* remove static route if interface is not WAN */
            if ($a_phase1[$p1entrydel]['interface'] <> "wan") {
                /* XXX does this even apply? only use of system.inc at the top! */
                system_host_route($a_phase1[$p1entrydel]['remote-gateway'], $a_phase1[$p1entrydel]['remote-gateway'], true, false);
            }
            /* remove all phase2 entries that match the ikeid */
            $ikeid = $a_phase1[$p1entrydel]['ikeid'];
            foreach ($a_phase2 as $p2index => $ph2tmp) {
                if ($ph2tmp['ikeid'] == $ikeid) {
                    unset($a_phase2[$p2index]);
                }
            }
            unset($config['ipsec']['phase1'][$p1entrydel]);
        }

        write_config();
        mark_subsystem_dirty('ipsec');
        header(url_safe('Location: /vpn_ipsec.php'));
        exit;
    } elseif (!empty($_POST['act']) && $_POST['act'] == "delphase2" ) {
        if (isset($_POST['id']) && isset($config['ipsec']['phase2'][$_POST['id']])){
            unset($config['ipsec']['phase2'][$_POST['id']]);
        } elseif (empty($_POST['id']) && isset($_POST['p2entry']) && count($_POST['p2entry'])) {
            foreach ($_POST['p2entry'] as $p1entrydel) {
                unset($config['ipsec']['phase2'][$p1entrydel]);
            }
        }
        write_config();
        mark_subsystem_dirty('ipsec');
        header(url_safe('Location: /vpn_ipsec.php'));
        exit;
    } elseif (!empty($_POST['act']) && $_POST['act'] == "movep1" ) {
        // move phase 1 records
        if (isset($_POST['p1entry']) && count($_POST['p1entry']) > 0) {
            // if rule not set/found, move to end
            if (!isset($_POST['id']) || !isset($a_phase1[$_POST['id']])) {
                $id = count($a_phase1);
            } else {
                $id = $_POST['id'];
            }
            $a_phase1 = legacy_move_config_list_items($a_phase1, $id,  $_POST['p1entry']);
        }
        write_config();
        mark_subsystem_dirty('ipsec');
        header(url_safe('Location: /vpn_ipsec.php'));
        exit;
    } elseif (!empty($_POST['act']) && $_POST['act'] == "movep2" ) {
        // move phase 2 records
        if (isset($_POST['p2entry']) && count($_POST['p2entry']) > 0) {
            // if rule not set/found, move to end
            if (!isset($_POST['id']) || !isset($a_phase2[$_POST['id']])) {
                $id = count($a_phase2);
            } else {
                $id = $_POST['id'];
            }
            $a_phase2 = legacy_move_config_list_items($a_phase2, $id,  $_POST['p2entry']);
        }
        write_config();
        mark_subsystem_dirty('ipsec');
        header(url_safe('Location: /vpn_ipsec.php'));
        exit;
    } elseif (!empty($_POST['act']) && $_POST['act'] == "togglep1" && isset($a_phase1[$_POST['id']]) ) {
        // toggle phase 1 record
        if (isset($a_phase1[$_POST['id']]['disabled'])) {
            unset($a_phase1[$_POST['id']]['disabled']);
        } else {
            $a_phase1[$_POST['id']]['disabled'] = true;
        }
        write_config();
        mark_subsystem_dirty('ipsec');
        header(url_safe('Location: /vpn_ipsec.php'));
        exit;
    } elseif (!empty($_POST['act']) && $_POST['act'] == "togglep2" && isset($a_phase2[$_POST['id']]) ) {
        // toggle phase 2 record
        if (isset($a_phase2[$_POST['id']]['disabled'])) {
            unset($a_phase2[$_POST['id']]['disabled']);
        } else {
            $a_phase2[$_POST['id']]['disabled'] = true;
        }
        write_config();
        mark_subsystem_dirty('ipsec');
        header(url_safe('Location: /vpn_ipsec.php'));
        exit;
    }
}

// form data
legacy_html_escape_form_data($a_phase1);
legacy_html_escape_form_data($a_phase2);

$service_hook = 'strongswan';

include("head.inc");

$dhgroups = array(
    0 => gettext('off'),
    1 => '1 (768 bits)',
    2 => '2 (1024 bits)',
    5 => '5 (1536 bits)',
    14 => '14 (2048 bits)',
    15 => '15 (3072 bits)',
    16 => '16 (4096 bits)',
    17 => '17 (6144 bits)',
    18 => '18 (8192 bits)',
    19 => '19 (NIST EC 256 bits)',
    20 => '20 (NIST EC 384 bits)',
    21 => '21 (NIST EC 521 bits)',
    22 => '22 (1024(sub 160) bits)',
    23 => '23 (2048(sub 224) bits)',
    24 => '24 (2048(sub 256) bits)',
    28 => '28 (Brainpool EC 256 bits)',
    29 => '29 (Brainpool EC 384 bits)',
    30 => '30 (Brainpool EC 512 bits)',
);

?>
<body>
<script>
$( document ).ready(function() {
    // link move/toggle buttons (phase 1 and phase 2)
    $(".act_move").click(function(event){
      event.preventDefault();
      $("#id").val($(this).data("id"));
      $("#action").val($(this).data("act"));
      $("#iform").submit();
    });


    // link delete phase 1 buttons
    $(".act_delete_p1").click(function(event){
      event.preventDefault();
      var id = $(this).data("id");
      if (id != 'x') {
        // delete single
        BootstrapDialog.show({
          type:BootstrapDialog.TYPE_DANGER,
          title: "<?= gettext("IPSEC");?>",
          message: "<?=gettext("Do you really want to delete this phase1 and all associated phase2 entries?"); ?>",
          buttons: [{
                    label: "<?= gettext("No");?>",
                    action: function(dialogRef) {
                        dialogRef.close();
                    }}, {
                    label: "<?= gettext("Yes");?>",
                    action: function(dialogRef) {
                      $("#id").val(id);
                      $("#action").val("delphase1");
                      $("#iform").submit()
                  }
                }]
      });
      } else {
        // delete selected
        BootstrapDialog.show({
          type:BootstrapDialog.TYPE_DANGER,
          title: "<?= gettext("IPSEC");?>",
          message: "<?=gettext("Do you really want to delete the selected phase1 entries?");?>",
          buttons: [{
                    label: "<?= gettext("No");?>",
                    action: function(dialogRef) {
                        dialogRef.close();
                    }}, {
                    label: "<?= gettext("Yes");?>",
                    action: function(dialogRef) {
                      $("#id").val("");
                      $("#action").val("delphase1");
                      $("#iform").submit()
                  }
                }]
        });
      }
    });

    // link delete phase 2 buttons
    $(".act_delete_p2").click(function(event){
      event.preventDefault();
      var id = $(this).data("id");
      if (id != 'x') {
        // delete single
        BootstrapDialog.show({
          type:BootstrapDialog.TYPE_DANGER,
          title: "<?= gettext("IPSEC");?>",
          message: "<?=gettext("Do you really want to delete this phase2 entry?"); ?>",
          buttons: [{
                    label: "<?= gettext("No");?>",
                    action: function(dialogRef) {
                        dialogRef.close();
                    }}, {
                    label: "<?= gettext("Yes");?>",
                    action: function(dialogRef) {
                      $("#id").val(id);
                      $("#action").val("delphase2");
                      $("#iform").submit()
                  }
                }]
      });
      } else {
        // delete selected
        BootstrapDialog.show({
          type:BootstrapDialog.TYPE_DANGER,
          title: "<?= gettext("IPSEC");?>",
          message: "<?=gettext("Do you really want to delete the selected phase2 entries?");?>",
          buttons: [{
                    label: "<?= gettext("No");?>",
                    action: function(dialogRef) {
                        dialogRef.close();
                    }}, {
                    label: "<?= gettext("Yes");?>",
                    action: function(dialogRef) {
                      $("#id").val("");
                      $("#action").val("delphase2");
                      $("#iform").submit()
                  }
                }]
        });
      }
    });

    // show phase 2 entries
    $(".act_show_p2").click(function(){
        $("#tdph2-"+$(this).data("id")).show();
        $("#shph2but-"+$(this).data("id")).hide();
    });
});
</script>

<?php include("fbegin.inc"); ?>
<section class="page-content-main">
  <div class="container-fluid">
    <div class="row">
<?php
      if (isset($savemsg)) {
          print_info_box($savemsg);
      }
      if (is_subsystem_dirty('ipsec')) {
          print_info_box_apply(gettext("The IPsec tunnel configuration has been changed.") . "<br />" . gettext("You must apply the changes in order for them to take effect."));
      }?>
      <section class="col-xs-12">
        <form method="post" name="iform" id="iform">
          <input type="hidden" id="id" name="id" value="" />
          <input type="hidden" id="action" name="act" value="" />
           <div class="tab-content content-box col-xs-12">
              <div class="table-responsive">
                <table class="table table-striped">
                  <thead>
                  <tr>
                    <td>&nbsp;</td>
                    <td>&nbsp;</td>
                    <td class="hidden-xs"><?=gettext("Type"); ?></td>
                    <td><?=gettext("Remote Gateway"); ?></td>
                    <td class="hidden-xs"><?=gettext("Mode"); ?></td>
                    <td class="hidden-xs"><?=gettext("Phase 1 Proposal"); ?></td>
                    <td class="hidden-xs"><?=gettext("Authentication"); ?></td>
                    <td><?=gettext("Description"); ?></td>
                    <td class="text-nowrap"></td>
                  </tr>
                  </thead>
                  <tbody>
<?php
                  $i = 0;
                  foreach ($a_phase1 as $ph1ent) :?>
                    <tr>
                      <td>
                        <input type="checkbox" name="p1entry[]" value="<?=$i;?>"/>
                      </td>
                      <td>
                        <button data-id="<?=$i; ?>" data-act="togglep1" type="submit"
                            type="submit" class="act_move btn btn-<?= isset($ph1ent['disabled'])? "default":"success"?> btn-xs"
                            title="<?=(isset($ph1ent['disabled'])) ? gettext("Enable phase 1 entry") : gettext("Disable phase 1 entry");?>" data-toggle="tooltip">
                          <i class="fa fa-play fa-fw"></i>
                        </button>
                      </td>
                      <td class="hidden-xs">
                          <?=empty($ph1ent['protocol']) || $ph1ent['protocol'] == "inet" ? "IPv4" : "IPv6"; ?>
                          <?php $ph1ent_type=array("ikev1" => "IKE", "ikev2" => "IKEv2", "ike" => "auto"); ?>
                          <?=!empty($ph1ent['iketype']) &&  isset($ph1ent_type[$ph1ent['iketype']]) ? $ph1ent_type[$ph1ent['iketype']] :"" ;?>
                      </td>
                      <td>
<?php
                        if (!empty($ph1ent['interface'])) {
                            $iflabels = get_configured_interface_with_descr();

                            $carplist = get_configured_carp_interface_list();
                            foreach ($carplist as $cif => $carpip) {
                                $iflabels[$cif] = $carpip." (".get_vip_descr($carpip).")";
                            }

                            $aliaslist = get_configured_ip_aliases_list();
                            foreach ($aliaslist as $aliasip => $aliasif) {
                                $iflabels[$aliasip] = $aliasip." (".get_vip_descr($aliasip).")";
                            }

                            $grouplist = return_gateway_groups_array();
                            foreach ($grouplist as $name => $group) {
                                if ($group[0]['vip'] <> "") {
                                    $vipif = $group[0]['vip'];
                                } else {
                                    $vipif = $group[0]['int'];
                                }
                                $iflabels[$name] = "GW Group {$name}";
                            }
                            $if = $iflabels[$ph1ent['interface']];
                        } else {
                            $if = "WAN";
                        }?>
                        <?=htmlspecialchars($if);?>
                        <?=!isset($ph1ent['mobile'])?
                        $ph1ent['remote-gateway']
                        :
                        "<strong>" . gettext("Mobile Client") . "</strong>";
                        ?>
                      </td>
                      <td class="hidden-xs">
                        <?=htmlspecialchars($ph1ent['mode']);?>
                      </td>
                      <td class="hidden-xs">
                        <?=$p1_ealgos[$ph1ent['encryption-algorithm']['name']]['name'];?>
<?php
                        if (!empty($ph1ent['encryption-algorithm']['keylen'])) {
                            if ($ph1ent['encryption-algorithm']['keylen']=="auto") {
                                echo " (" . gettext("auto") . ")";
                            } else {
                                echo " ({$ph1ent['encryption-algorithm']['keylen']}&nbsp;" . gettext("bits") . ")";
                            }
                        }?> +

                        <?=strtoupper($ph1ent['hash-algorithm']);?>
<?php if (!empty($ph1ent['dhgroup'])): ?>
                          + <?=gettext("DH Group"); ?>&nbsp;<?= $ph1ent['dhgroup'] ?>
<?php endif ?>
                      </td>
                      <td class="hidden-xs">
                          <?= html_safe($p1_authentication_methods[$ph1ent['authentication_method']]['name']) ?>
                      </td>
                      <td>
                        <?= $ph1ent['descr'] ?>
                      </td>
                      <td class="text-nowrap">
                        <button data-id="<?=$i; ?>" data-act="movep1" type="submit" class="act_move btn btn-default btn-xs"
                          title="<?=gettext("Move selected entries before this");?>" data-toggle="tooltip">
                          <i class="fa fa-arrow-left fa-fw"></i>
                        </button>
                        <a href="vpn_ipsec_phase1.php?p1index=<?=$i; ?>" class="btn btn-default btn-xs"
                          title="<?= html_safe(gettext('Edit')) ?>" data-toggle="tooltip">
                          <i class="fa fa-pencil fa-fw"></i>
                        </a>
                        <button data-id="<?=$i; ?>" title="<?= html_safe(gettext('Delete')) ?>" data-toggle="tooltip"
                          type="submit" class="act_delete_p1 btn btn-default btn-xs">
                          <i class="fa fa-trash fa-fw"></i>
                        </button>
<?php                   if (!isset($ph1ent['mobile'])):
?>
                        <a href="vpn_ipsec_phase1.php?dup=<?=$i; ?>" class="btn btn-default btn-xs"
                          title="<?= html_safe(gettext('Clone')) ?>" data-toggle="tooltip">
                          <i class="fa fa-clone fa-fw"></i>
                        </a>
<?php
                        endif ?>
                      </td>
                    </tr>
                    <tr>
                      <td colspan="9">
<?php
                        $phase2count=0;
                        foreach ($a_phase2 as $ph2ent) {
                            if ($ph2ent['ikeid'] != $ph1ent['ikeid']) {
                                continue;
                            }
                            $phase2count++;
                        }?>
                        <div id="shph2but-<?=$i?>">
                          <button class="act_show_p2 btn btn-xs" type="button" data-id="<?=$i?>">
                            <i class="fa fa-plus"></i> <?= sprintf(gettext('Show %s Phase-2 entries'), $phase2count) ?>
                          </button>
                        </div>
                        <div id="tdph2-<?=$i?>" style="display:none">
                          <table class="table table-striped table-condensed">
                            <thead>
                              <tr>
                                <td>&nbsp;</td>
                                <td>&nbsp;</td>
                                <td class="hidden-xs"><?=gettext("Type"); ?></td>
                                <td><?=gettext("Local Subnet"); ?></td>
                                <td><?=gettext("Remote Subnet"); ?></td>
                                <td class="hidden-xs"><?=gettext("Encryption Protocols"); ?></td>
                                <td class="hidden-xs"><?=gettext("Authenticity Protocols"); ?></td>
                                <td class="hidden-xs"><?=gettext("PFS"); ?></td>
                                <td>&nbsp;</td>
                              </tr>
                            </thead>
                            <tbody>
<?php
                            $j = 0;
                            foreach ($a_phase2 as $ph2index => $ph2ent) :
                                if ($ph2ent['ikeid'] != $ph1ent['ikeid']) {
                                    continue;
                                }?>
                              <tr>
                                <td>
                                  <input type="checkbox" name="p2entry[]" value="<?=$ph2index;?>"/>
                                </td>
                                <td>
                                  <button data-id="<?=$ph2index; ?>" data-act="togglep2" type="submit"
                                      title="<?=(isset($ph2ent['disabled'])) ? gettext("Enable phase 2 entry") : gettext("Disable phase 2 entry"); ?>" data-toggle="tooltip"
                                      class="act_move btn btn-<?= isset($ph2ent['disabled'])?"default":"success";?> btn-xs">
                                    <i class="fa fa-play fa-fw"></i>
                                  </button>
                                </td>
                                <td class="hidden-xs">
                                  <?=$p2_protos[$ph2ent['protocol']];?>
                                  <?=isset($ph2ent['mode']) ? array_search($ph2ent['mode'], array("IPv4 tunnel" => "tunnel", "IPv6 tunnel" => "tunnel6", "transport" => "transport")) : ""; ?>
                                </td>
<?php
                                if (($ph2ent['mode'] == "tunnel") || ($ph2ent['mode'] == "tunnel6")) :?>
                                <td>
                                  <?=ipsec_idinfo_to_text($ph2ent['localid']); ?>
                                </td>
                                <td>
                                  <?=ipsec_idinfo_to_text($ph2ent['remoteid']); ?>
                                </td>
<?php
                                else :?>
                                <td>&nbsp;</td>
                                <td>&nbsp;</td>
<?php
                                endif;?>
                                <td class="hidden-xs">
<?php
                                if (!empty($ph2ent['encryption-algorithm-option'])) {
                                  foreach ($ph2ent['encryption-algorithm-option'] as $k => $ph2ea) {
                                      if ($k > 0) {
                                          echo ", ";
                                      }
                                      echo $p2_ealgos[$ph2ea['name']]['name'];
                                      if (!empty($ph2ea['keylen'])) {
                                          if ($ph2ea['keylen']=="auto") {
                                              echo " (" . gettext("auto") . ")";
                                          } else {
                                              echo " ({$ph2ea['keylen']} " . gettext("bits") . ")";
                                          }
                                      }
                                  }
                                }?>
                                </td>
                                <td class="hidden-xs">
<?php
                                  if (!empty($ph2ent['hash-algorithm-option']) && is_array($ph2ent['hash-algorithm-option'])) {
                                      foreach ($ph2ent['hash-algorithm-option'] as $k => $ph2ha) {
                                          if ($k) {
                                              echo ", ";
                                          }
                                          echo $p2_halgos[$ph2ha];
                                      }
                                  }?>
                                </td>
<?php
                                if (isset($ph2ent['pfsgroup'])): ?>
                                <td class="hidden-xs"><?=gettext("Group"); ?> <?=$dhgroups[$ph2ent['pfsgroup']];?> </td>
<?php
                                else: ?>
                                <td class="hidden-xs"><?=gettext("off"); ?></td>
<?php
                                endif; ?>
                                <td class="text-nowrap">
                                  <button data-id="<?=$j; ?>" data-act="movep2" type="submit" class="act_move btn btn-default btn-xs"
                                    title="<?=gettext("Move selected entries before this");?>" data-toggle="tooltip">
                                    <i class="fa fa-arrow-left fa-fw"></i>
                                  </button>
                                  <a href="vpn_ipsec_phase2.php?p2index=<?=$ph2ent['uniqid']; ?>"
                                    title="<?= html_safe(gettext('Edit')) ?>" data-toggle="tooltip"
                                    class="btn btn-default btn-xs">
                                    <i class="fa fa-pencil fa-fw"></i>
                                  </a>
                                  <button data-id="<?=$ph2index; ?>" type="submit" class="act_delete_p2 btn btn-default btn-xs"
                                    title="<?= html_safe(gettext('Delete')) ?>" data-toggle="tooltip">
                                    <i class="fa fa-trash fa-fw"></i>
                                  </button>
                                  <a href="vpn_ipsec_phase2.php?dup=<?=$ph2ent['uniqid']; ?>" class="btn btn-default btn-xs"
                                    title="<?= html_safe(gettext('Clone')) ?>" data-toggle="tooltip">
                                    <i class="fa fa-clone fa-fw"></i>
                                  </a>
                                </td>
                              </tr>
<?php
                              $j++;
                              endforeach;?>
                              <tr>
                                <td colspan="4" class="hidden-xs"></td>
                                <td colspan="4"></td>
                                <td class="text-nowrap">
<?php
                                if ($j > 0) :?>

                                  <button data-id="<?=$j+1; ?>" data-act="movep2" type="submit" class="act_move btn btn-default btn-xs"
                                    title="<?=gettext("Move selected phase 2 entries to end");?>" data-toggle="tooltip">
                                    <i class="fa fa-arrow-down fa-fw"></i>
                                  </button>
                                  <button data-id="x" type="submit" title="<?=gettext("delete selected phase 2 entries");?>" data-toggle="tooltip"
                                    class="act_delete_p2 btn btn-default btn-xs">
                                    <i class="fa fa-trash fa-fw"></i>
                                  </button>
<?php
                                endif;?>
                                  <a href="vpn_ipsec_phase2.php?ikeid=<?=$ph1ent['ikeid']; ?><?= isset($ph1ent['mobile'])?"&amp;mobile=true":"";?>" class="btn btn-default btn-xs"
                                    title="<?=gettext("add phase 2 entry"); ?>" data-toggle="tooltip">
                                    <i class="fa fa-plus fa-fw"></i>
                                  </a>
                                </td>
                              </tr>
                            </tbody>
                          </table>
                        </div>
                      </td>
                    </tr>
<?php
                    $i++;
                    endforeach;?>
                    <tr>
                      <td colspan="4" class="hidden-xs"></td>
                      <td colspan="4"> </td>
                      <td class="text-nowrap">
                        <button
                          type="submit"
                          data-id="<?=$i;?>"
                          data-act="movep1"
                          title="<?=gettext("Move selected phase 1 entries to end");?>"
                          data-toggle="tooltip"
                          class="act_move btn btn-default btn-xs">
                          <i class="fa fa-arrow-down fa-fw"></i>
                        </button>
                          <button data-id=""
                          type="submit"
                          title="<?=gettext("delete selected phase 1 entries");?>"
                          data-toggle="tooltip"
                          class="act_delete_p1 btn btn-default btn-xs">
                          <i class="fa fa-trash fa-fw"></i>
                        </button>
                        <a href="vpn_ipsec_phase1.php" title="<?=gettext("add new phase 1 entry");?>" data-toggle="tooltip"
                          class="btn btn-default btn-xs">
                          <i class="fa fa-plus fa-fw"></i>
                        </a>
                      </td>
                    </tr>
                    <tr>
                      <td colspan=9>
                        <input name="enable" type="checkbox" id="enable" value="yes" <?=!empty($config['ipsec']['enable']) ? "checked=\"checked\"":"";?>/>
                        <strong><?=gettext("Enable IPsec"); ?></strong>
                      </td>
                    </tr>
                    <tr>
                      <td colspan=9>
                        <input type="submit" name="save" class="btn btn-primary" value="<?=gettext("Save"); ?>" />
                      </td>
                    </tr>
                </tbody>
              </table>
            </div>
          </div>
        </form>
      </section>
    </div>
  </div>
</section>
<?php include("foot.inc");
