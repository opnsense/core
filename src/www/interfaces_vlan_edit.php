<?php

/*
 * Copyright (C) 2014-2016 Deciso B.V.
 * Copyright (C) 2003-2004 Manuel Kasper <mk@neon1.net>
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
require_once("system.inc");
require_once("interfaces.inc");
require_once("filter.inc");

$a_vlans = &config_read_array('vlans', 'vlan');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // read form data
    if (!empty($a_vlans[$_GET['id']])) {
        $id = $_GET['id'];
    }
    $pconfig['if'] = isset($a_vlans[$id]['if']) ? $a_vlans[$id]['if'] : null;
    $pconfig['vlanif'] = isset($a_vlans[$id]['vlanif']) ? $a_vlans[$id]['vlanif'] : null;
    $pconfig['tag'] = isset($a_vlans[$id]['tag']) ? $a_vlans[$id]['tag'] : null;
    $pconfig['pcp'] = isset($a_vlans[$id]['pcp']) ? $a_vlans[$id]['pcp'] : 0;
    $pconfig['descr'] = isset($a_vlans[$id]['descr']) ? $a_vlans[$id]['descr'] : null;
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // validate / save form data
    if (!empty($a_vlans[$_POST['id']])) {
        $id = $_POST['id'];
    }

    $input_errors = array();
    $pconfig = $_POST;

    /* input validation */
    $reqdfields = explode(" ", "if tag");
    $reqdfieldsn = array(gettext("Parent interface"),gettext("VLAN tag"));

    do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);

    if ($pconfig['tag'] && (!is_numericint($pconfig['tag']) || ($pconfig['tag'] < '1') || ($pconfig['tag'] > '4094'))) {
        $input_errors[] = gettext("The VLAN tag must be an integer between 1 and 4094.");
    }

    if (isset($pconfig['pcp']) && (!is_numericint($pconfig['pcp']) || $pconfig['pcp'] < 0 || $pconfig['pcp'] > 7)) {
        $input_errors[] = gettext("The VLAN priority must be an integer between 0 and 7.");
    }

    if (!does_interface_exist($pconfig['if'])) {
        $input_errors[] = gettext("Interface supplied as parent is invalid");
    }

    if (isset($id) && $pconfig['tag'] && $pconfig['tag'] != $a_vlans[$id]['tag']) {
        if (!empty($a_vlans[$id]['vlanif']) && convert_real_interface_to_friendly_interface_name($a_vlans[$id]['vlanif']) != NULL) {
            $input_errors[] = gettext("Interface is assigned and you cannot change the VLAN tag while assigned.");
        }
    }

    foreach ($a_vlans as $vlan) {
        if (isset($id)  && $a_vlans[$id] === $vlan) {
            continue;
        }
        if (($vlan['if'] == $pconfig['if']) && ($vlan['tag'] == $_POST['tag'])) {
            $input_errors[] = sprintf(gettext("A VLAN with the tag %s is already defined on this interface."), $vlan['tag']);
            break;
        }
    }

    if (count($input_errors) == 0) {
        $confif = "";
        $vlan = array();
        $vlan['if'] = $_POST['if'];
        $vlan['tag'] = $_POST['tag'];
        $vlan['pcp'] = $pconfig['pcp'];
        $vlan['descr'] = $_POST['descr'];
        $vlan['vlanif'] = "{$_POST['if']}_vlan{$_POST['tag']}";
        if (isset($id)) {
            if (($a_vlans[$id]['if'] != $pconfig['if']) || ($a_vlans[$id]['tag'] != $pconfig['tag']) || ($a_vlans[$id]['pcp'] != $pconfig['pcp'])) {
                if (!empty($a_vlans[$id]['vlanif'])) {
                    $confif = convert_real_interface_to_friendly_interface_name($a_vlans[$id]['vlanif']);
                    legacy_interface_destroy($a_vlans[$id]['vlanif']);
                } else {
                    legacy_interface_destroy("{$a_vlans[$id]['if']}_vlan{$a_vlans[$id]['tag']}");
                    $confif = convert_real_interface_to_friendly_interface_name("{$a_vlans[$id]['if']}_vlan{$a_vlans[$id]['tag']}");
                }
                if ($confif != '') {
                    $config['interfaces'][$confif]['if'] = "{$_POST['if']}_vlan{$_POST['tag']}";
                }
                $vlan['vlanif'] = interface_vlan_configure($vlan);
            }
        } else {
            $vlan['vlanif'] = interface_vlan_configure($vlan);
        }
        ifgroup_setup();
        if ($vlan['vlanif'] == "" || !stristr($vlan['vlanif'], "vlan")) {
            $input_errors[] = gettext("Error occurred creating interface, please retry.");
        } else {
            if (isset($id)) {
                $a_vlans[$id] = $vlan;
            } else {
                $a_vlans[] = $vlan;
            }
            write_config();

            if ($confif != '') {
                interface_configure(false, $confif);
            }
            header(url_safe('Location: /interfaces_vlan.php'));
            exit;
        }
    }
}

include("head.inc");
legacy_html_escape_form_data($pconfig);
?>

<body>
<?php include("fbegin.inc"); ?>

<section class="page-content-main">
  <div class="container-fluid">
    <div class="row">
      <?php if (isset($input_errors) && count($input_errors) > 0) print_input_errors($input_errors); ?>
      <section class="col-xs-12">
        <div class="content-box">
          <div class="table-responsive">
            <form method="post" name="iform" id="iform">
              <table class="table table-striped opnsense_standard_table_form">
                <thead>
                  <tr>
                    <td style="width:22%"><strong><?=gettext("Interface VLAN Edit");?></strong></td>
                    <td style="width:78%; text-align:right">
                      <small><?=gettext("full help"); ?> </small>
                      <i class="fa fa-toggle-off text-danger"  style="cursor: pointer;" id="show_all_help_page"></i>
                      &nbsp;
                    </td>
                  </tr>
                </thead>
                <tbody>
                  <tr>
                    <td><a id="help_for_if" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Parent interface");?></td>
                    <td>
                      <select name="if" class="selectpicker">
<?php
                      $all_interfaces = legacy_config_get_interfaces(array('virtual' => false));
                      $all_interface_data = legacy_interfaces_details();
                      foreach ($all_interfaces as $intf) {
                          if (!empty($intf['if']) && !empty($all_interface_data[$intf['if']])) {
                              $all_interface_data[$intf['if']]['descr'] = $intf['descr'];
                          }
                      }
                      foreach ($all_interface_data as $ifn => $ifinfo):
                        if (strpos($ifn, "_vlan") > 1 || strpos($ifn, "lo") === 0 || strpos($ifn, "enc") === 0 ||
                              strpos($ifn, "pflog") === 0 || strpos($ifn, "pfsync") === 0 ||
                              strpos($ifn, "ipsec") === 0){
                            continue;
                        }?>

                        <option value="<?=$ifn;?>" <?=$ifn == $pconfig['if'] ? " selected=\"selected\"" : "";?>>
                          <?=htmlspecialchars($ifn);?>
                          ( <?= !empty($ifinfo['macaddr']) ? $ifinfo['macaddr'] :"" ;?> )
<?php
                          if (!empty($ifinfo['descr'])):?>
                          [<?=htmlspecialchars($ifinfo['descr']);?>]
<?php
                          endif;?>
                        </option>
<?php
                      endforeach;?>

                      </select>
                      <div class="hidden" data-for="help_for_if">
                        <?=gettext("Only VLAN capable interfaces will be shown.");?>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td><a id="help_for_tag" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("VLAN tag");?></td>
                    <td>
                      <input name="tag" type="text" value="<?=$pconfig['tag'];?>" />
                      <div class="hidden" data-for="help_for_tag">
                        <?=gettext("802.1Q VLAN tag (between 1 and 4094)");?>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td><a id="help_for_pcp" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("VLAN priority");?></td>
                    <td>
                      <select name="pcp">
<?php foreach (interfaces_vlan_priorities() as $pcp => $priority): ?>
                        <option value="<?=$pcp;?>"<?=($pconfig['pcp'] == $pcp ? ' selected="selected"' : '');?>><?=htmlspecialchars($priority);?></option>
<?php endforeach ?>
                      </select>
                      <div class="hidden" data-for="help_for_pcp">
                        <?=gettext('802.1Q VLAN PCP (priority code point)');?>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td><a id="help_for_descr" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Description"); ?></td>
                    <td>
                      <input name="descr" type="text" value="<?=$pconfig['descr'];?>" />
                      <div class="hidden" data-for="help_for_descr">
                        <?=gettext("You may enter a description here for your reference (not parsed).");?>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td style="width:22%">&nbsp;</td>
                    <td style="width:78%">
                      <input type="hidden" name="vlanif" value="<?=$pconfig['vlanif']; ?>" />
                      <input name="Submit" type="submit" class="btn btn-primary" value="<?=html_safe(gettext('Save'));?>" />
                      <input type="button" class="btn btn-default" value="<?=html_safe(gettext('Cancel'));?>" onclick="window.location.href='/interfaces_vlan.php'" />
                      <?php if (isset($id)): ?>
                      <input name="id" type="hidden" value="<?=$id;?>" />
                      <?php endif; ?>
                    </td>
                  </tr>
                </tbody>
              </table>
            </form>
          </div>
        </div>
      </section>
    </div>
  </div>
</section>
<?php include("foot.inc"); ?>
