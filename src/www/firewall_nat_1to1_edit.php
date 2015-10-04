<?php

/*
  Copyright (C) 2014 Deciso B.V.
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
require_once("pfsense-utils.inc");

/**
 * fetch list of selectable networks to use in form
 */
function formNetworks() {
    $networks = array();
    $networks["any"] = gettext("any");
    $networks["pptp"] = gettext("PPTP clients");
    $networks["pppoe"] = gettext("PPPoE clients");
    $networks["l2tp"] = gettext("L2TP clients");
    foreach (get_configured_interface_with_descr() as $ifent => $ifdesc) {
        $networks[$ifent] = htmlspecialchars($ifdesc) . " " . gettext("net");
        $networks[$ifent."ip"] = htmlspecialchars($ifdesc). " ". gettext("address");
    }
    return $networks;
}

/**
 * build array with interface options for this form
 */
function formInterfaces() {
    global $config;
    $interfaces = array();
    foreach ( get_configured_interface_with_descr(false, true) as $if => $ifdesc)
        $interfaces[$if] = $ifdesc;

    if (isset($config['l2tp']['mode']) && $config['l2tp']['mode'] == "server")
        $interfaces['l2tp'] = "L2TP VPN";

    if (isset($config['pptpd']['mode']) && $config['pptpd']['mode'] == "server")
        $interfaces['pptp'] = "PPTP VPN";

    if (is_pppoe_server_enabled())
        $interfaces['pppoe'] = "PPPoE VPN";

    /* add ipsec interfaces */
    if (isset($config['ipsec']['enable']) || isset($config['ipsec']['client']['enable']))
        $interfaces["enc0"] = "IPsec";

    /* add openvpn/tun interfaces */
    if (isset($config['openvpn']['openvpn-server']) || isset($config['openvpn']['openvpn-client'])) {
      $interfaces['openvpn'] = 'OpenVPN';
    }
    return $interfaces;
}

/**
 * obscured by clouds, is_specialnet uses this.. so let's hide it in here.
 * let's kill this another day.
 */
$specialsrcdst = explode(" ", "any pptp pppoe l2tp openvpn");
$ifdisp = get_configured_interface_with_descr();
foreach ($ifdisp as $kif => $kdescr) {
    $specialsrcdst[] = "{$kif}";
    $specialsrcdst[] = "{$kif}ip";
}

if (!isset($config['nat']['onetoone'])) {
    $config['nat']['onetoone'] = array();
}
$a_1to1 = &$config['nat']['onetoone'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // input record id, if valid
    if (isset($_GET['dup']) && isset($a_1to1[$_GET['dup']]))  {
        $configId = $_GET['dup'];
    } elseif (isset($_GET['id']) && isset($a_1to1[$_GET['id']])) {
        $id = $_GET['id'];
        $configId = $id;
    }

    $pconfig = array();
    // set defaults
    $pconfig['interface'] = "wan";
    $pconfig['src'] = 'lan';
    $pconfig['dst'] = 'any';
    if (isset($configId)) {
        // copy settings from config
        foreach (array('disabled','interface','external','descr','natreflection') as $fieldname) {
          if (isset($a_1to1[$id][$fieldname])) {
              $pconfig[$fieldname] = $a_1to1[$id][$fieldname];
          } else {
              $pconfig[$fieldname] = null;
          }
        }
        // read settings with some kind of logic
        address_to_pconfig(
          $a_1to1[$id]['source'], $pconfig['src'],
          $pconfig['srcmask'], $pconfig['srcnot'],
          $pconfig['__unused__'],$pconfig['__unused__']
        );

        address_to_pconfig(
          $a_1to1[$id]['destination'], $pconfig['dst'],
          $pconfig['dstmask'], $pconfig['dstnot'],
          $pconfig['__unused__'],$pconfig['__unused__']
        );
    } else {
        // init form data on new
        foreach (array('disabled','interface','external','descr','natreflection'
                      ,'src','srcmask','srcnot','dst','dstmask','dstnot'
                    ) as $fieldname) {
            if (!isset($pconfig[$fieldname])) {
                $pconfig[$fieldname] =  null;
            }
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input_errors = array();
    $pconfig = $_POST;
    // input record id, if valid
    if (isset($_POST['id']) && isset($a_1to1[$_POST['id']])) {
        $id = $_POST['id'];
    }

    // trim input
    foreach (array('external','src','dst') as $fieldname) {
        if (isset($pconfig[$fieldname])) {
            $pconfig[$fieldname] = trim($pconfig[$fieldname]);
        }
    }

    // validate input
    foreach ($pconfig as $key => $value) {
        if($value <> htmlentities($value))
            $input_errors[] = sprintf(gettext("Invalid characters detected (%s).  Please remove invalid characters and save again."),htmlentities($value));
    }

    /* input validation */
    $reqdfields = explode(" ", "interface external src dst");
    $reqdfieldsn = array(gettext("Interface"), gettext("External subnet"), gettext("Source address"), gettext("Destination address"));
    do_input_validation($pconfig, $reqdfields, $reqdfieldsn, $input_errors);


    /* For external, user can enter only ip's */
    if (!empty($pconfig['external']) && !is_ipaddr($_POST['external'])) {
        $input_errors[] = gettext("A valid external subnet must be specified.");
    }
    /* For src, user can enter only ip's or networks */
    if (!is_specialnet($pconfig['src']) && !is_ipaddroralias($pconfig['src'])) {
        $input_errors[] = sprintf(gettext("%s is not a valid source IP address or alias."), $pconfig['src']);
    }
    if (!empty($pconfig['srcmask']) && !is_numericint($pconfig['srcmask'])) {
        $input_errors[] = gettext("A valid source bit count must be specified.");
    }
    /* For dst, user can enter ip's, networks or aliases */
    if (!is_specialnet($pconfig['dst']) && !is_ipaddroralias($pconfig['dst'])) {
        $input_errors[] = sprintf(gettext("%s is not a valid destination IP address or alias."), $pconfig['dst']);
    }
    if (!empty($pconfig['dstmask']) && !is_numericint($pconfig['dstmask'])) {
        $input_errors[] = gettext("A valid destination bit count must be specified.");
    }

    if (count($input_errors) == 0) {
        $natent = array();
        // 1-on-1 copy
        $natent['external'] = $pconfig['external'];
        $natent['descr'] = $pconfig['descr'];
        $natent['interface'] = $pconfig['interface'];

        // copy form data with some kind of logic in it
        $natent['disabled'] = isset($_POST['disabled']) ? true:false;
        pconfig_to_address($natent['source'], $pconfig['src'],
          $pconfig['srcmask'], !empty($pconfig['srcnot']));

        pconfig_to_address($natent['destination'], $pconfig['dst'],
          $pconfig['dstmask'], !empty($pconfig['dstnot']));

        if (isset($pconfig['natreflection'] ) && ($pconfig['natreflection'] == "enable" || $pconfig['natreflection'] == "disable")) {
            $natent['natreflection'] = $pconfig['natreflection'];
        }

        // save data
        if (isset($id)) {
            $a_1to1[$id] = $natent;
        } else {
            $a_1to1[] = $natent;
        }

        if (write_config()) {
            mark_subsystem_dirty('natconf');
        }
        header("Location: firewall_nat_1to1.php");
        exit;
    }
}

legacy_html_escape_form_data($pconfig);
$pgtitle = array(gettext("Firewall"),gettext("NAT"),gettext("1:1"),gettext("Edit"));
include("head.inc");
?>

<body>
  <script type="text/javascript">
  $( document ).ready(function() {

    // select / input combination, link behaviour
    // when the data attribute "data-other" is selected, display related input item(s)
    // push changes from input back to selected option value
    $('[for!=""][for]').each(function(){
        var refObj = $("#"+$(this).attr("for"));
        if (refObj.is("select")) {
            // connect on change event to select box (show/hide)
            refObj.change(function(){
              if ($(this).find(":selected").attr("data-other") == "true") {
                  // show related controls
                  $('*[for="'+$(this).attr("id")+'"]').each(function(){
                    if ($(this).hasClass("selectpicker")) {
                      $(this).selectpicker('show');
                    } else {
                      $(this).removeClass("hidden");
                    }
                  });
              } else {
                  // hide related controls
                  $('*[for="'+$(this).attr("id")+'"]').each(function(){
                    if ($(this).hasClass("selectpicker")) {
                      $(this).selectpicker('hide');
                    } else {
                      $(this).addClass("hidden");
                    }
                  });
              }
            });
            // update initial
            refObj.change();

            // connect on change to input to save data to selector
            if ($(this).attr("name") == undefined) {
              $(this).change(function(){
                  var otherOpt = $('#'+$(this).attr('for')+' > option[data-other="true"]') ;
                  otherOpt.attr("value",$(this).val());
              });
            }
        }
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
?>
        <section class="col-xs-12">
          <div class="content-box">
            <form action="firewall_nat_1to1_edit.php" method="post" name="iform" id="iform">
              <div class="table-responsive">
                <table class="table table-striped">
                  <tr>
                    <td valign="top"><?=gettext("Edit NAT 1:1 entry"); ?></td>
                    <td align="right">
                      <small><?=gettext("full help"); ?> </small>
                      <i class="fa fa-toggle-off text-danger"  style="cursor: pointer;" id="show_all_help_page" type="button"></i></a>
                    </td>
                  </tr>
                  <tr>
                    <td><a id="help_for_disabled" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Disabled"); ?></td>
                    <td>
                      <input name="disabled" type="checkbox" id="disabled" value="yes" <?= !empty($pconfig['disabled']) ? "checked=\"checked\"" : ""; ?> />
                      <div class="hidden" for="help_for_disabled">
                        <strong><?=gettext("Disable this rule"); ?></strong><br />
                        <?=gettext("Set this option to disable this rule without removing it from the list."); ?>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td><a id="help_for_interface" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Interface"); ?></td>
                    <td>
                      <div class="input-group">
                        <select name="interface" class="selectpicker" data-width="auto" data-live-search="true">
  <?php
                          foreach (formInterfaces() as $iface => $ifacename): ?>
                          <option value="<?=$iface;?>" <?= $iface == $pconfig['interface'] ? "selected=\"selected\"" : ""; ?>>
                            <?=htmlspecialchars($ifacename);?>
                          </option>
                          <?php endforeach; ?>
                        </select>
                      </div>
                      <div class="hidden" for="help_for_interface">
                        <?=gettext("Choose which interface this rule applies to"); ?>.<br />
                        <?=gettext("Hint: in most cases, you'll want to use WAN here"); ?>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td><a id="help_for_external" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("External subnet IP"); ?></td>
                    <td>
                      <input name="external" type="text" value="<?=$pconfig['external'];?>" />
                      <br />
                      <div class="hidden" for="help_for_external">
                        <?=gettext("Enter the external (usually on a WAN) subnet's starting address for the 1:1 mapping.  ");?><br />
                        <?=gettext("The subnet mask from the internal address below will be applied to this IP address."); ?><br />
                        <?=gettext("Hint: this is generally an address owned by the router itself on the selected interface."); ?>
                      </div>
                    </td>
                  </tr>
                  <tr>
                      <td><a id="help_for_src_invert" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Internal IP") . " / ".gettext("Invert");?> </td>
                      <td>
                        <input name="srcnot" type="checkbox" id="srcnot" value="yes" <?= !empty($pconfig['srcnot']) ? "checked=\"checked\"" : "";?> />
                        <div class="hidden" for="help_for_src_invert">
                          <?=gettext("Use this option to invert the sense of the match."); ?>
                        </div>
                      </td>
                  </tr>
                  <tr>
                      <td><a id="help_for_src" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Internal IP"); ?></td>
                      <td>
                        <table class="table table-condensed">
                          <tr>
                            <td>
                              <select name="src" id="src" class="selectpicker" data-live-search="true" data-size="5" data-width="auto">
                                <option data-other=true value="<?=$pconfig['src'];?>" <?=!is_specialnet($pconfig['src']) ? "selected=\"selected\"" : "";?>><?=gettext("Single host or Network"); ?></option>
                                <optgroup label="<?=gettext("Aliases");?>">
  <?php                        foreach (legacy_list_aliases("network") as $alias):
  ?>
                                  <option value="<?=$alias['name'];?>" <?=$alias['name'] == $pconfig['src'] ? "selected=\"selected\"" : "";?>><?=htmlspecialchars($alias['name']);?></option>
  <?php                          endforeach; ?>
                                </optgroup>
                                <optgroup label="<?=gettext("Networks");?>">
  <?php                          foreach (formNetworks() as $ifent => $ifdesc):
  ?>
                                  <option value="<?=$ifent;?>" <?= $pconfig['src'] == $ifent ? "selected=\"selected\"" : ""; ?>><?=$ifdesc;?></option>
  <?php                            endforeach; ?>
                              </optgroup>
                            </select>
                          </td>
                        </tr>
                        <tr>
                          <td>
                            <div class="input-group">
                            <!-- updates to "other" option in  src -->
                            <input type="text" for="src" value="<?=$pconfig['src'];?>" aria-label="<?=gettext("Source address");?>"/>
                            <select name="srcmask" class="selectpicker" data-size="5" id="srcmask"  data-width="auto" for="src" >
                            <?php for ($i = 32; $i > 0; $i--): ?>
                              <option value="<?=$i;?>" <?= $i == $pconfig['srcmask'] ? "selected=\"selected\"" : ""; ?>><?=$i;?></option>
                            <?php endfor; ?>
                            </select>
                          </div>
                          </td>
                        </tr>
                      </table>
                      <div class="hidden" for="help_for_src">
                        <?=gettext("Enter the internal (LAN) subnet for the 1:1 mapping. The subnet size specified for the internal subnet will be applied to the external subnet."); ?>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td> <a id="help_for_dst_invert" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Destination") . " / ".gettext("Invert");?> </td>
                    <td>
                      <input name="dstnot" type="checkbox" id="srcnot" value="yes" <?= !empty($pconfig['dstnot']) ? "checked=\"checked\"" : "";?> />
                      <div class="hidden" for="help_for_dst_invert">
                        <?=gettext("Use this option to invert the sense of the match."); ?>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td><a id="help_for_dst" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Destination"); ?></td>
                    <td>
                      <table class="table table-condensed">
                        <tr>
                          <td>
                            <select name="dst" id="dst" class="selectpicker" data-live-search="true" data-size="5" data-width="auto">
                              <option data-other=true value="<?=$pconfig['dst'];?>" <?=!is_specialnet($pconfig['dst']) ? "selected=\"selected\"" : "";?>><?=gettext("Single host or Network"); ?></option>
                              <optgroup label="<?=gettext("Aliases");?>">
  <?php                        foreach (legacy_list_aliases("network") as $alias):
  ?>
                                <option value="<?=$alias['name'];?>" <?=$alias['name'] == $pconfig['dst'] ? "selected=\"selected\"" : "";?>><?=htmlspecialchars($alias['name']);?></option>
  <?php                          endforeach; ?>
                              </optgroup>
                              <optgroup label="<?=gettext("Networks");?>">
  <?php                          foreach (formNetworks() as $ifent => $ifdesc):
  ?>
                                <option value="<?=$ifent;?>" <?= $pconfig['dst'] == $ifent ? "selected=\"selected\"" : ""; ?>><?=$ifdesc;?></option>
  <?php                            endforeach; ?>
                              </optgroup>
                            </select>
                          </td>
                        </tr>
                        <tr>
                          <td>
                            <div class="input-group">
                            <!-- updates to "other" option in  src -->
                            <input type="text" for="dst" value="<?= !is_specialnet($pconfig['dst']) ? $pconfig['dst'] : "";?>" aria-label="<?=gettext("Destination address");?>"/>
                            <select name="dstmask" class="selectpicker" data-size="5" id="dstmask"  data-width="auto" for="dst" >
                            <?php for ($i = 32; $i > 0; $i--): ?>
                              <option value="<?=$i;?>" <?= $i == $pconfig['dstmask'] ? "selected=\"selected\"" : ""; ?>><?=$i;?></option>
                            <?php endfor; ?>
                            </select>
                          </div>
                          </td>
                        </tr>
                      </table>
                      <div class="hidden" for="help_for_dst">
                        <?=gettext("The 1:1 mapping will only be used for connections to or from the specified destination."); ?><br />
                        <?=gettext("Hint: this is usually 'any'."); ?>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td><a id="help_for_descr" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Description"); ?></td>
                    <td>
                      <input name="descr" type="text" class="formfld unknown" id="descr" size="40" value="<?=$pconfig['descr'];?>" />
                      <div class="hidden" for="help_for_descr">
                        <?=gettext("You may enter a description here " ."for your reference (not parsed)."); ?>
                      </div>
                  </tr>
                  <tr>
                    <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("NAT reflection"); ?></td>
                    <td>
                      <select name="natreflection" class="selectpicker">
                      <option value="default" <?=$pconfig['natreflection'] != "enable" && $pconfig['natreflection'] != "disable" ? "selected=\"selected\"" : ""; ?>><?=gettext("Use system default"); ?></option>
                      <option value="enable" <?=$pconfig['natreflection'] == "enable" ? "selected=\"selected\"" : ""; ?>><?=gettext("Enable"); ?></option>
                      <option value="disable" <?=$pconfig['natreflection'] == "disable" ? "selected=\"selected\"" : ""; ?>><?=gettext("Disable"); ?></option>
                      </select>
                    </td>
                  </tr>
                  <tr>
                    <td>&nbsp;</td>
                    <td>
                      <input name="Submit" type="submit" class="btn btn-primary" value="<?=gettext("Save"); ?>" />
                      <input type="button" class="btn btn-default" value="<?=gettext("Cancel");?>" onclick="window.location.href='<?=(isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '/firewall_nat_1to1.php');?>'" />
                      <?php if (isset($id)): ?>
                      <input name="id" type="hidden" value="<?=$id;?>" />
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
