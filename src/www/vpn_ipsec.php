<?php

/*
	Copyright (C) 2014-2015 Deciso B.V.
	Copyright (C) 2003-2005 Manuel Kasper <mk@neon1.net>.
	Copyright (C) 2008 Shrew Soft Inc
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
require_once("filter.inc");
require_once("vpn.inc");
require_once("services.inc");
require_once("pfsense-utils.inc");
require_once("interfaces.inc");

if (!isset($config['ipsec']) || !is_array($config['ipsec'])) {
    $config['ipsec'] = array();
}
if (!isset($config['ipsec']['phase1'])) {
    $config['ipsec']['phase1'] = array();
}
if (!isset($config['ipsec']['phase2'])) {
    $config['ipsec']['phase2'] = array();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $a_phase1 = &$config['ipsec']['phase1'];
    $a_phase2 = &$config['ipsec']['phase2'];
    if (isset($_POST['apply'])) {
        $retval = vpn_ipsec_configure();
        /* reload the filter in the background */
        filter_configure();
        $savemsg = get_std_save_message();
        if ($retval >= 0) {
            if (is_subsystem_dirty('ipsec')) {
                clear_subsystem_dirty('ipsec');
            }
        }
    } elseif (isset($_POST['submit'])) {
        $config['ipsec']['enable'] = !empty($_POST['enable']) ? true : false;
        write_config();
        vpn_ipsec_configure();
        header("Location: vpn_ipsec.php");
        exit;
    } elseif (isset($_POST['del_x'])) {
        /* delete selected p1 entries */
        if (isset($_POST['p1entry']) && count($_POST['p1entry'])) {
            foreach ($_POST['p1entry'] as $p1entrydel) {
                unset($config['ipsec']['phase1'][$p1entrydel]);
            }
            if (write_config()) {
                mark_subsystem_dirty('ipsec');
            }
            header("Location: vpn_ipsec.php");
            exit;
        }
    } elseif (isset($_POST['delp2_x'])) {
        /* delete selected p2 entries */
        if (isset($_POST['p2entry']) && count($_POST['p2entry'])) {
            foreach ($_POST['p2entry'] as $p2entrydel) {
                unset($config['ipsec']['phase2'][$p2entrydel]);
            }
            if (write_config()) {
                mark_subsystem_dirty('ipsec');
            }
            header("Location: vpn_ipsec.php");
            exit;
        }
    } else {
      // move, delete, toggle items by id.
      //
      /* yuck - IE won't send value attributes for image buttons,
      while Mozilla does - so we use .x/.y to find move button clicks instead... */
        unset($delbtn, $delbtnp2, $movebtn, $movebtnp2, $togglebtn, $togglebtnp2);
        foreach ($_POST as $pn => $pd) {
            if (preg_match("/del_(\d+)_x/", $pn, $matches)) {
                $delbtn = $matches[1];
            } elseif (preg_match("/delp2_(\d+)_x/", $pn, $matches)) {
                $delbtnp2 = $matches[1];
            } elseif (preg_match("/move_(\d+)_x/", $pn, $matches)) {
                $movebtn = $matches[1];
            } elseif (preg_match("/movep2_(\d+)_x/", $pn, $matches)) {
                $movebtnp2 = $matches[1];
            } elseif (preg_match("/toggle_(\d+)_x/", $pn, $matches)) {
                $togglebtn = $matches[1];
            } elseif (preg_match("/togglep2_(\d+)_x/", $pn, $matches)) {
                $togglebtnp2 = $matches[1];
            }
        }
        $save = 1;

      /* move selected p1 entries before this */
        if (isset($movebtn) && isset($_POST['p1entry']) && count($_POST['p1entry'])) {
            $a_phase1_new = array();

            /* copy all p1 entries < $movebtn and not selected */
            for ($i = 0; $i < $movebtn; $i++) {
                if (!in_array($i, $_POST['p1entry'])) {
                    $a_phase1_new[] = $a_phase1[$i];
                }
            }

            /* copy all selected p1 entries */
            for ($i = 0; $i < count($a_phase1); $i++) {
                if ($i == $movebtn) {
                    continue;
                }
                if (in_array($i, $_POST['p1entry'])) {
                    $a_phase1_new[] = $a_phase1[$i];
                }
            }

            /* copy $movebtn p1 entry */
            if ($movebtn < count($a_phase1)) {
                $a_phase1_new[] = $a_phase1[$movebtn];
            }

            /* copy all p1 entries > $movebtn and not selected */
            for ($i = $movebtn+1; $i < count($a_phase1); $i++) {
                if (!in_array($i, $_POST['p1entry'])) {
                    $a_phase1_new[] = $a_phase1[$i];
                }
            }
            if (count($a_phase1_new) > 0) {
                $a_phase1 = $a_phase1_new;
            }

        } elseif (isset($movebtnp2) && isset($_POST['p2entry']) && count($_POST['p2entry'])) {
            /* move selected p2 entries before this */
            $a_phase2_new = array();

            /* copy all p2 entries < $movebtnp2 and not selected */
            for ($i = 0; $i < $movebtnp2; $i++) {
                if (!in_array($i, $_POST['p2entry'])) {
                    $a_phase2_new[] = $a_phase2[$i];
                }
            }

            /* copy all selected p2 entries */
            for ($i = 0; $i < count($a_phase2); $i++) {
                if ($i == $movebtnp2) {
                    continue;
                }
                if (in_array($i, $_POST['p2entry'])) {
                    $a_phase2_new[] = $a_phase2[$i];
                }
            }

            /* copy $movebtnp2 p2 entry */
            if ($movebtnp2 < count($a_phase2)) {
                $a_phase2_new[] = $a_phase2[$movebtnp2];
            }

            /* copy all p2 entries > $movebtnp2 and not selected */
            for ($i = $movebtnp2+1; $i < count($a_phase2); $i++) {
                if (!in_array($i, $_POST['p2entry'])) {
                    $a_phase2_new[] = $a_phase2[$i];
                }
            }
            if (count($a_phase2_new) > 0) {
                $a_phase2 = $a_phase2_new;
            }

        } elseif (isset($togglebtn)) {
            if (isset($a_phase1[$togglebtn]['disabled'])) {
                unset($a_phase1[$togglebtn]['disabled']);
            } else {
                $a_phase1[$togglebtn]['disabled'] = true;
            }

        } elseif (isset($togglebtnp2)) {
            if (isset($a_phase2[$togglebtnp2]['disabled'])) {
                unset($a_phase2[$togglebtnp2]['disabled']);
            } else {
                $a_phase2[$togglebtnp2]['disabled'] = true;
            }

        } elseif (isset($delbtn)) {
            /* remove static route if interface is not WAN */
            if ($a_phase1[$delbtn]['interface'] <> "wan") {
                mwexec("/sbin/route delete -host {$a_phase1[$delbtn]['remote-gateway']}");
            }

            /* remove all phase2 entries that match the ikeid */
            $ikeid = $a_phase1[$delbtn]['ikeid'];
            foreach ($a_phase2 as $p2index => $ph2tmp) {
                if ($ph2tmp['ikeid'] == $ikeid) {
                    unset($a_phase2[$p2index]);
                }
            }

            unset($a_phase1[$delbtn]);

        } elseif (isset($delbtnp2)) {
            unset($a_phase2[$delbtnp2]);

        } else {
            $save = 0;
        }

        if ($save === 1) {
            if (write_config()) {
                mark_subsystem_dirty('ipsec');
            }
        }
        header("Location: vpn_ipsec.php");
        exit;
    }

}

// form data
$pconfig = $config['ipsec'];
$pconfig['enable'] = isset($config['ipsec']['enable']);
legacy_html_escape_form_data($pconfig);

$pgtitle = array(gettext('VPN'), gettext('IPsec'), gettext('Tunnel Settings'));
$shortcut_section = 'ipsec';

include("head.inc");

?>

<body>

<?php include("fbegin.inc"); ?>

<script type="text/javascript">
//<![CDATA[
function show_phase2(id, buttonid) {
	document.getElementById(buttonid).innerHTML='';
	document.getElementById(id).style.display = "block";
	var visible = id + '-visible';
	document.getElementById(visible).value = "1";
}
//]]>
</script>

<form action="vpn_ipsec.php" method="post">
	<section class="page-content-main">
		<div class="container-fluid">
			<div class="row">
				<?php
                if (isset($savemsg)) {
                    print_info_box($savemsg);
                }
                if ($pconfig['enable'] && is_subsystem_dirty('ipsec')) {
                    print_info_box_np(gettext("The IPsec tunnel configuration has been changed") . ".<br />" . gettext("You must apply the changes in order for them to take effect."));
                }
                ?>
			    <section class="col-xs-12">
					 <div class="tab-content content-box col-xs-12">
							<div class="table-responsive">
							  <table class="table table-striped">
                  <thead>
									<tr>
										<td>&nbsp;</td>
										<td>&nbsp;</td>
										<td><?=gettext("IKE"); ?></td>
										<td><?=gettext("Remote Gateway"); ?></td>
										<td><?=gettext("Mode"); ?></td>
										<td><?=gettext("P1 Protocol"); ?></td>
										<td><?=gettext("P1 Transforms"); ?></td>
										<td><?=gettext("P1 Description"); ?></td>
										<td>
										</td>
									</tr>
                  </thead>
                  <tbody>
<?php
                $i = 0;
foreach ($pconfig['phase1'] as $ph1ent) :
?>
  <tr id="fr<?=$i;
?>" ondblclick="document.location='vpn_ipsec_phase1.php?p1index=<?=$i;?>'">
    <td>
      <input type="checkbox" name="p1entry[]" value="<?=$i;?>"/>
    </td>
    <td>
      <button name="toggle_<?=$i;
?>_x" title="<?=gettext("click to toggle enabled/disabled status");?>" type="submit" class="btn btn-<?= isset($ph1ent['disabled'])? "default":"success"?> btn-xs">
        <span class="glyphicon glyphicon-play"></span>
      </button>
    </td>
    <td>
        <?=empty($ph1ent['iketype']) || $ph1ent['iketype'] == "ikev1"?"V1":"V2";?>
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
}
?>
        <?=htmlspecialchars($if);?>
        <?=!isset($ph1ent['mobile'])?
        $ph1ent['remote-gateway']
        :
        "<strong>" . gettext("Mobile Client") . "</strong>";
        ?>
    </td>
    <td><?=$ph1ent['mode'];?></td>
                    <td>
                        <?=$p1_ealgos[$ph1ent['encryption-algorithm']['name']]['name'];?>
<?php
if (!empty($ph1ent['encryption-algorithm']['keylen'])) {
    if ($ph1ent['encryption-algorithm']['keylen']=="auto") {
        echo " (" . gettext("auto") . ")";
    } else {
        echo " ({$ph1ent['encryption-algorithm']['keylen']} " . gettext("bits") . ")";
    }
}
?>
    </td>
    <td>
        <?=$p1_halgos[$ph1ent['hash-algorithm']];?>
    </td>
    <td>
        <?=$ph1ent['descr'];?>&nbsp;
    </td>
    <td>
      <button name="move_<?=$i;
?>_x" title="<?=gettext("move selected entries before this");?>" type="submit" class="btn btn-default btn-xs">
          <span class="glyphicon glyphicon-arrow-left"></span>
      </button>
      <a href="vpn_ipsec_phase1.php?p1index=<?=$i;
?>" title="<?=gettext("edit phase1 entry"); ?>" class="btn btn-default btn-xs" alt="edit">
          <span class="glyphicon glyphicon-pencil"></span>
      </a><br/>
      <button name="del_<?=$i;?>_x"
          title="<?=gettext("delete phase1 entry");?>"
          type="submit"
          onclick="return confirm('<?=gettext("Do you really want to delete this phase1 and all associated phase2 entries?"); ?>')"
          class="btn btn-default btn-xs">
          <span class="glyphicon glyphicon-remove"></span>
      </button>
<?php                 if (!isset($ph1ent['mobile'])) :
?>
                      <a href="vpn_ipsec_phase1.php?dup=<?=$i;
?>" title="<?=gettext("copy phase1 entry"); ?>" class="btn btn-default btn-xs" alt="add">
                                      <span class="glyphicon glyphicon-plus"></span>
                      </a>
<?php                 endif;
?>
    </td>
  </tr>
  <tr>
    <td colspan="9">
<?php
    $phase2count=0;
foreach ($pconfig['phase2'] as $ph2ent) {
    if ($ph2ent['ikeid'] != $ph1ent['ikeid']) {
        continue;
    }
    $phase2count++;
}
    $fr_prefix = "frp2{$i}";
?>
      <div id="shph2but-<?=$i?>">
        <button class="btn btn-xs" type="button" onclick="show_phase2('tdph2-<?=$i?>','shph2but-<?=$i?>')">
          <i class="fa fa-plus"></i> <?php printf(gettext("Show %s Phase-2 entries"), $phase2count); ?>
        </button>
      </div>
      <div id="tdph2-<?=$i?>" style="display:none">
        <table class="table table-striped table-condensed">
          <thead>
            <tr>
              <td>&nbsp;</td>
              <td>&nbsp;</td>
              <td><?=gettext("Mode"); ?></td>
              <td><?=gettext("Local Subnet"); ?></td>
              <td><?=gettext("Remote Subnet"); ?></td>
              <td><?=gettext("P2 Protocol"); ?></td>
              <td><?=gettext("P2 Transforms"); ?></td>
              <td><?=gettext("P2 Auth Methods"); ?></td>
              <td class ="list">&nbsp;</td>
            </tr>
          </thead>
          <tbody>
<?php
          $j = 0;
foreach ($pconfig['phase2'] as $ph2index => $ph2ent) :
    if ($ph2ent['ikeid'] != $ph1ent['ikeid']) {
        continue;
    }

?>
  <tr id="<?=$fr_prefix . $j;
?>" ondblclick="document.location='vpn_ipsec_phase2.php?p2index=<?=$ph2ent['uniqid'];?>'">
    <td>
      <input type="checkbox" name="p2entry[]" value="<?=$ph2index;?>"/>
    </td>
    <td>
      <button name="togglep2_<?=$ph2index;
?>_x" title="<?=gettext("click to toggle enabled/disabled status");
?>" type="submit" class="btn btn-<?= isset($ph2ent['disabled'])?"default":"success";?> btn-xs">
        <span class="glyphicon glyphicon-play"></span>
      </button>
    </td>
    <td> <?=$ph2ent['mode'];?> </td>
<?php
if (($ph2ent['mode'] == "tunnel") || ($ph2ent['mode'] == "tunnel6")) :
?>
<td>
    <?=ipsec_idinfo_to_text($ph2ent['localid']); ?>
</td>
<td>
    <?=ipsec_idinfo_to_text($ph2ent['remoteid']); ?>
</td>
<?php                                                                         else :
?>                            <td>&nbsp;</td>
                              <td>&nbsp;</td>
<?php
endif;
?>
    <td><?=$p2_protos[$ph2ent['protocol']];?> </td>
    <td>
<?php
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
?>
    </td>
    <td>
<?php
if (!empty($ph2ent['hash-algorithm-option']) && is_array($ph2ent['hash-algorithm-option'])) {
    foreach ($ph2ent['hash-algorithm-option'] as $k => $ph2ha) {
        if ($k) {
            echo ", ";
        }
        echo $p2_halgos[$ph2ha];
    }
}
?>
    </td>
    <td>
        <button name="movep2_<?=$j;?>_x"
            title="<?=gettext("move selected entries before this");?>"
            type="submit"
            class="btn btn-default btn-xs">
            <span class="glyphicon glyphicon-arrow-left"></span>
        </button>
        <a href="vpn_ipsec_phase2.php?p2index=<?=$ph2ent['uniqid'];
?>" title="<?=gettext("edit phase2 entry"); ?>" alt="edit" class="btn btn-default btn-xs">
          <span class="glyphicon glyphicon-pencil"></span>
        </a>
        <button name="delp2_<?=$ph2index;
?>_x" title="<?=gettext("delete phase2 entry");?>"
          type="submit"
          onclick="return confirm('<?=gettext("Do you really want to delete this phase2 entry?"); ?>')"
          class="btn btn-default btn-xs">
          <span class="glyphicon glyphicon-remove"><span>
        </button>
        <a href="vpn_ipsec_phase2.php?dup=<?=$ph2ent['uniqid'];
?>" title="<?=gettext("add a new Phase 2 based on this one"); ?>" alt="add" class="btn btn-default btn-xs">
          <span class="glyphicon glyphicon-plus"></span>
        </a>
    </td>
  </tr>
<?php
    $j++;
endforeach;
?>
            <tr>
              <td colspan="8"></td>
              <td>
<?php                           if ($j > 0) :
?>

                                <button name="movep2_<?=$j;?>_x" type="submit"
                                  title="<?=gettext("move selected phase2 entries to end");?>"
                                  class="btn btn-default btn-xs">
                                  <span class="glyphicon glyphicon-arrow-down"></span>
                                </button>
<?php                                 endif;
?>
                <a href="vpn_ipsec_phase2.php?ikeid=<?=$ph1ent['ikeid'];
?><?= isset($ph1ent['mobile'])?"&amp;mobile=true":"";?>" class="btn btn-default btn-xs">
                  <span title="<?=gettext("add phase2 entry"); ?>" alt="add" class="glyphicon glyphicon-plus"></span>
                </a>
<?php                                 if ($j > 0) :
?>
                                <button name="delp2_x" type="submit" title="<?=gettext("delete selected phase2 entries");?>"
                                  onclick="return confirm('<?=gettext("Do you really want to delete the selected phase2 entries?");?>')"
                                  class="btn btn-default btn-xs">
                                  <span class="glyphicon glyphicon-remove"></span>
                                </button>
<?php                                 endif;
?>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </td>
  </tr>
<?php
  $i++;
endforeach;  // $a_phase1 as $ph1ent
?>
					        <tr>
						        <td colspan="8"> </td>
                    <td>
                      <button name="move_<?=$i;?>_x"
											type="submit"
											title="<?=gettext("move selected phase1 entries to end");?>"
											class="btn btn-default btn-xs">
											<span class="glyphicon glyphicon-arrow-down"></span>
										</button>
                      <a href="vpn_ipsec_phase1.php" title="<?=gettext("add new phase1");?>" alt="add" class="btn btn-default btn-xs">
											<span class="glyphicon glyphicon-plus"></span>
										</a>
                      <button
											name="del_x"
											type="submit"
											title="<?=gettext("delete selected phase1 entries");?>"
											onclick="return confirm('<?=gettext("Do you really want to delete the selected phase1 entries?");?>')"
											class="btn btn-default btn-xs">
											<span class="glyphicon glyphicon-remove"></span>
										</button>
                    </td>
                  </tr>
                  <tr>
                    <td colspan=9>
                      <input name="enable" type="checkbox" id="enable" value="yes" <?=!empty($pconfig['enable']) ? "checked=\"checked\"":"";?>/>
                      <strong><?=gettext("Enable IPsec"); ?></strong>
                    </td>
                  </tr>
                  <tr>
                    <td colspan=9>
                      <input name="submit" type="submit" class="btn btn-primary" value="<?=gettext("Save"); ?>" />
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </section>
      </div>
    </div>
  </section>
</form>
<?php include("foot.inc");
