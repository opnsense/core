<?php

/*
 * Copyright (C) 2014-2015 Deciso B.V.
 * Copyright (C) 2008 Ermal Luçi
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

$a_gres = &config_read_array('gres', 'gre');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // read form data
    if (!empty($a_gres[$_GET['id']])) {
        $id = $_GET['id'];
    }
    $pconfig = array();
    // copy fields
    $copy_fields = array('if', 'greif', 'remote-addr', 'tunnel-remote-net', 'tunnel-local-addr', 'tunnel-remote-addr', 'descr');
    foreach ($copy_fields as $fieldname) {
        $pconfig[$fieldname] = isset($a_gres[$id][$fieldname]) ? $a_gres[$id][$fieldname] : null;
    }
    // bool fields
    $pconfig['link1'] = isset($a_gres[$id]['link1']);
    $pconfig['link2'] = isset($a_gres[$id]['link2']);
    $pconfig['link0'] = isset($a_gres[$id]['link0']);
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // validate / save form data
    if (!empty($a_gres[$_POST['id']])) {
        $id = $_POST['id'];
    }

    $input_errors = array();
    $pconfig = $_POST;

    /* input validation */
    $reqdfields = explode(" ", "if tunnel-remote-addr tunnel-remote-net tunnel-local-addr");
    $reqdfieldsn = array(gettext("Parent interface"),gettext("Local address"),gettext("Remote tunnel address"),gettext("Remote tunnel network"), gettext("Local tunnel address"));

    do_input_validation($pconfig, $reqdfields, $reqdfieldsn, $input_errors);

    if (!is_ipaddr($pconfig['tunnel-local-addr']) || !is_ipaddr($pconfig['tunnel-remote-addr']) || !is_ipaddr($pconfig['remote-addr'])) {
        $input_errors[] = gettext("The tunnel local and tunnel remote fields must have valid IP addresses.");
    }

    foreach ($a_gres as $gre) {
        if (isset($id) && $a_gres[$id] === $gre) {
            continue;
        }
        if ($gre['if'] == $pconfig['if'] && $gre['tunnel-remote-addr'] == $pconfig['tunnel-remote-addr']) {
            $input_errors[] = sprintf(gettext("A GRE tunnel with the network %s is already defined."),$gre['remote-network']);
            break;
        }
    }

    if (count($input_errors) == 0) {
        $gre = array();
        $copy_fields = array('if', 'greif', 'remote-addr', 'tunnel-remote-net', 'tunnel-local-addr', 'tunnel-remote-addr', 'descr');
        foreach ($copy_fields as $fieldname) {
            $gre[$fieldname] = isset($pconfig[$fieldname]) ? $pconfig[$fieldname] : null;
        }
        $gre['link1'] = isset($pconfig['link1']);
        $gre['link2'] = isset($pconfig['link2']);
        $gre['link0'] = isset($pconfig['link0']);


        $gre['greif'] = interface_gre_configure($gre);
        ifgroup_setup();
        if ($gre['greif'] == "" || !stristr($gre['greif'], "gre")) {
            $input_errors[] = gettext("Error occurred creating interface, please retry.");
        } else {
            if (isset($id)) {
                $a_gres[$id] = $gre;
            } else {
                $a_gres[] = $gre;
            }
            write_config();
            $confif = convert_real_interface_to_friendly_interface_name($gre['greif']);
            if ($confif != '') {
                interface_configure(false, $confif);
            }
            header(url_safe('Location: /interfaces_gre.php'));
            exit;
        }
    }
}

legacy_html_escape_form_data($pconfig);
include("head.inc");
?>

<body>
<script>
  $( document ).ready(function() {
    hook_ipv4v6('ipv4v6net', 'network-id');
  });
</script>
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
                    <td style="width:22%"><strong><?=gettext("GRE configuration");?></strong></td>
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
                      <select name="if" class="selectpicker" data-live-search="true">
<?php
                      $portlist = get_configured_interface_with_descr();
                      $carplist = get_configured_carp_interface_list();
                      $aliaslist = get_configured_ip_aliases_list();
                      foreach ($carplist as $cif => $carpip) {
                          $portlist[$cif] = $carpip." (".get_vip_descr($carpip).")";
                      }
                      foreach ($aliaslist as $aliasip => $aliasif) {
                          $portlist[$aliasip] = $aliasip." (".get_vip_descr($aliasip).")";
                      }

                      foreach ($portlist as $ifn => $ifinfo):?>
                        <option value="<?=$ifn;?>" <?=$ifn == $pconfig['if'] ? "selected=\"selected\"" : "";?>>
                            <?=htmlspecialchars($ifinfo);?>
                        </option>

<?php
                      endforeach;?>
                      </select>
                      <div class="hidden" data-for="help_for_if">
                          <?=gettext("The interface here serves as the local address to be used for the GRE tunnel.");?>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td><a id="help_for_remote-addr" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("GRE remote address");?></td>
                    <td>
                      <input name="remote-addr" type="text" value="<?=$pconfig['remote-addr'];?>" />
                      <div class="hidden" data-for="help_for_remote-addr">
                        <?=gettext("Peer address where encapsulated GRE packets will be sent.");?>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td><a id="help_for_tunnel-local-addr" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("GRE tunnel local address");?></td>
                    <td>
                      <input name="tunnel-local-addr" type="text" value="<?=$pconfig['tunnel-local-addr'];?>" />
                      <div class="hidden" data-for="help_for_tunnel-local-addr">
                        <?=gettext("Local GRE tunnel endpoint");?>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td><a id="help_for_tunnel-remote-addr" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("GRE tunnel remote address");?></td>
                    <td>
                      <table class="table table-condensed">
                        <tr>
                          <td style="width:285px">
                            <input name="tunnel-remote-addr" type="text" id="tunnel-remote-addr" value="<?=$pconfig['tunnel-remote-addr'];?>" />
                          </td>
                          <td>
                            <select name="tunnel-remote-net" data-network-id="tunnel-remote-addr" class="selectpicker ipv4v6net" id="tunnel-remote-net" data-width="auto">
<?php
                            for ($i = 128; $i > 0; $i--):?>
                              <option value="<?=$i;?>"  <?=$i == $pconfig['tunnel-remote-net'] ? "selected=\"selected\"" : "";?> >
                                  <?=$i;?>
                              </option>
<?php
                            endfor;?>
                            </select>
                          </td>
                        </tr>
                      </table>
                      <div class="hidden" data-for="help_for_tunnel-remote-addr">
                        <?=gettext("Remote GRE address endpoint. The subnet part is used for the determining the network that is tunneled.");?>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td><a id="help_for_link0" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Mobile tunnel");?></td>
                    <td>
                      <input name="link0" type="checkbox" id="link0" <?= !empty($pconfig['link0']) ? "checked=\"checked\"" : "";?> />
                      <div class="hidden" data-for="help_for_link0">
                        <?=gettext("Specify which encapsulation method the tunnel should use.");?>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td><a id="help_for_link1" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Route search type");?></td>
                    <td>
                      <input name="link1" type="checkbox" id="link1" <?= !empty($pconfig['link1']) ? "checked=\"checked\"" : "";?> />
                      <div class="hidden" data-for="help_for_link1">
                        <?=gettext("For correct operation, the GRE device needs a route to the destination ".
                       "that is less specific than the one over the tunnel. (Basically, there ".
                       "needs to be a route to the decapsulating host that does not run over the ".
                       "tunnel, as this would be a loop.");?>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td><a id="help_for_link2" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("WCCP version");?></td>
                    <td>
                      <input name="link2" type="checkbox" id="link2" <?= !empty($pconfig['link2']) ? "checked=\"checked\"" : "";?> />
                      <div class="hidden" data-for="help_for_link2">
                        <?=gettext("Check this box for WCCP encapsulation version 2, or leave unchecked for version 1.");?>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td><a id="help_for_descr" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Description"); ?></td>
                    <td>
                      <input name="descr" type="text" value="<?=$pconfig['descr'];?>" />
                      <div class="hidden" data-for="help_for_descr">
                        <?=gettext("You may enter a description here for your reference (not parsed)."); ?>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td style="width:22%">&nbsp;</td>
                    <td style="width:78%">
                      <input type="hidden" name="greif" value="<?=$pconfig['greif']; ?>" />
                      <input name="Submit" type="submit" class="btn btn-primary" value="<?=html_safe(gettext('Save'));?>" />
                      <input type="button" class="btn btn-default" value="<?=html_safe(gettext('Cancel'));?>" onclick="window.location.href='/interfaces_gre.php'" />
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
