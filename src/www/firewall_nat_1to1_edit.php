<?php

/*
    Copyright (C) 2014 Deciso B.V.
    Copyright (C) 2003-2004 Manuel Kasper <mk@neon1.net>
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


$a_1to1 = &config_read_array('nat', 'onetoone');

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
    $pconfig['type'] = 'binat';
    if (isset($configId)) {
        // copy settings from config
        foreach (array('disabled','interface','external','descr','natreflection', 'type', 'category') as $fieldname) {
          if (isset($a_1to1[$configId][$fieldname])) {
              $pconfig[$fieldname] = $a_1to1[$configId][$fieldname];
          } else {
              $pconfig[$fieldname] = null;
          }
        }
        // read settings with some kind of logic
        address_to_pconfig(
          $a_1to1[$configId]['source'], $pconfig['src'],
          $pconfig['srcmask'], $pconfig['srcnot'],
          $pconfig['__unused__'],$pconfig['__unused__']
        );

        address_to_pconfig(
          $a_1to1[$configId]['destination'], $pconfig['dst'],
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
    $pconfig['category'] = !empty($pconfig['category']) ? explode(",", $pconfig['category']) : [];
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

    /* input validation */
    $reqdfields = explode(" ", "interface external src dst");
    $reqdfieldsn = array(gettext("Interface"), gettext("External subnet"), gettext("Source address"), gettext("Destination address"));
    do_input_validation($pconfig, $reqdfields, $reqdfieldsn, $input_errors);


    /* For external, user can enter only ip's */
    $tmpext = explode('/', $pconfig['external']);
    if (!empty($pconfig['external'])) {
        if ($pconfig['type'] == 'binat' && (!is_ipaddr($tmpext[0]) || (count($tmpext) != 1 && $pconfig['srcmask'] != $tmpext[1]))) {
            $input_errors[] = gettext("A valid external subnet must be specified.");
        } elseif ($pconfig['type'] == 'nat' && !is_subnet($pconfig['external'])) {
            $input_errors[] = gettext("A valid external subnet must be specified.");
        }
    }
    /* For src, user can enter only ip's or networks */
    if ($pconfig['type'] == 'binat' && !is_subnet($pconfig['src']) && !is_ipaddr($pconfig['src'])) {
        $input_errors[] = sprintf(gettext("%s is not a valid source IP address."), $pconfig['src']);
    } elseif (!is_specialnet($pconfig['src']) && !is_ipaddroralias($pconfig['src'])) {
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
        $natent['category'] = implode(",", $pconfig['category']);
        $natent['descr'] = $pconfig['descr'];
        $natent['interface'] = $pconfig['interface'];
        $natent['type'] = $pconfig['type'];

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

        OPNsense\Core\Config::getInstance()->fromArray($config);
        $catmdl = new OPNsense\Firewall\Category();
        if ($catmdl->sync()) {
            $catmdl->serializeToConfig();
            $config = OPNsense\Core\Config::getInstance()->toArray(listtags());
        }
        write_config();
        mark_subsystem_dirty('natconf');
        header(url_safe('Location: /firewall_nat_1to1.php'));
        exit;
    }
}

legacy_html_escape_form_data($pconfig);

include("head.inc");

?>

<body>
  <script src="<?= cache_safe('/ui/js/tokenize2.js') ?>"></script>
  <link rel="stylesheet" type="text/css" href="<?= cache_safe(get_themed_filename('/css/tokenize2.css')) ?>">
  <script src="<?= cache_safe('/ui/js/opnsense_ui.js') ?>"></script>
  <script>
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

    // aliases and "special nets" are only allowed for nat type entries
    $("#nattype").change(function(){
        if ($(this).val() == 'binat') {
            $("#src optgroup[data-type='nat']").children().prop('disabled', true);
        } else {
            $("#src optgroup[data-type='nat']").children().prop('disabled', false);
        }
        $("#src").selectpicker('refresh');
    });
    $("#nattype").change();
    formatTokenizersUI();

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
            <form method="post" name="iform" id="iform">
              <div class="table-responsive">
                <table class="table table-striped opnsense_standard_table_form">
                  <tr>
                    <td style="width:22%"><strong><?= gettext('Edit NAT 1:1 entry') ?></strong></td>
                    <td style="width:78%;text-align:right">
                      <small><?=gettext("full help"); ?> </small>
                      <i class="fa fa-toggle-off text-danger" style="cursor: pointer;" id="show_all_help_page"></i>
                    </td>
                  </tr>
                  <tr>
                    <td><a id="help_for_disabled" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Disabled"); ?></td>
                    <td>
                      <input name="disabled" type="checkbox" id="disabled" value="yes" <?= !empty($pconfig['disabled']) ? "checked=\"checked\"" : ""; ?> />
                      <div class="hidden" data-for="help_for_disabled">
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
                          foreach (legacy_config_get_interfaces(array("enable" => true)) as $iface => $ifdetail): ?>
                          <option value="<?=$iface;?>" <?= $iface == $pconfig['interface'] ? "selected=\"selected\"" : ""; ?>>
                            <?=htmlspecialchars($ifdetail['descr']);?>
                          </option>
                          <?php endforeach; ?>
                        </select>
                      </div>
                      <div class="hidden" data-for="help_for_interface">
                        <?=gettext("Choose which interface this rule applies to"); ?>.<br />
                        <?=gettext("Hint: in most cases, you'll want to use WAN here"); ?>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td><a id="help_for_type" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Type"); ?></td>
                    <td>
                      <select name="type" class="selectpicker" data-width="auto" id="nattype">
                          <option value="binat" <?=$pconfig['type'] == 'binat' || empty($pconfig['type']) ? "selected=\"selected\"" : ""; ?>>
                            <?=gettext("BINAT");?>
                          </option>
                          <option value="nat" <?=$pconfig['type'] == 'nat' ? "selected=\"selected\"" : ""; ?>>
                            <?=gettext("NAT");?>
                          </option>
                      </select>
                      <div class="hidden" data-for="help_for_type">
                        <?=gettext("Select BINAT (default) or NAT here, when nets are equally sized binat is usually the best option.".
                                   "Using NAT we can also map unequal sized networks.");?><br />
                        <?=gettext("A BINAT rule specifies a bidirectional mapping between an external and internal network and can be used from both ends, nat only applies in one direction.");?>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td><a id="help_for_external" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("External network"); ?></td>
                    <td>
                      <input name="external" type="text" value="<?=$pconfig['external'];?>" />
                      <div class="hidden" data-for="help_for_external">
                        <?=gettext("Enter the external subnet's starting address for the 1:1 mapping or network.");?><br />
                        <?=gettext("The subnet mask from the internal address below will be applied to this IP address, when none is provided."); ?><br />
                        <?=gettext("This is the address or network the traffic will translate to/from.");?>
                      </div>
                    </td>
                  </tr>
                  <tr>
                      <td><a id="help_for_src_invert" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Source") . " / ".gettext("Invert");?> </td>
                      <td>
                        <input name="srcnot" type="checkbox" id="srcnot" value="yes" <?= !empty($pconfig['srcnot']) ? "checked=\"checked\"" : "";?> />
                        <div class="hidden" data-for="help_for_src_invert">
                          <?=gettext("Use this option to invert the sense of the match."); ?>
                        </div>
                      </td>
                  </tr>
                  <tr>
                      <td><a id="help_for_src" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Source"); ?></td>
                      <td>
                        <table class="table table-condensed">
                          <tr>
                            <td>
                              <select name="src" id="src" class="selectpicker" data-live-search="true" data-size="5" data-width="auto" data-hide-disabled="true">
                                <option data-other=true value="<?=$pconfig['src'];?>" <?=!is_specialnet($pconfig['src']) ? "selected=\"selected\"" : "";?>><?=gettext("Single host or Network"); ?></option>
                                <optgroup label="<?=gettext("Aliases");?>" data-type="nat">
  <?php                        foreach (legacy_list_aliases("network") as $alias):
  ?>
                                  <option value="<?=$alias['name'];?>" <?=$alias['name'] == $pconfig['src'] ? "selected=\"selected\"" : "";?>><?=htmlspecialchars($alias['name']);?></option>
  <?php                          endforeach; ?>
                                </optgroup>
                                <optgroup label="<?=gettext("Networks");?>" data-type="nat">
  <?php                          foreach (get_specialnets(true) as $ifent => $ifdesc):
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
                            <!-- updates to "other" option in src -->
                            <input type="text" for="src" value="<?=$pconfig['src'];?>" aria-label="<?=gettext("Source address");?>"/>
                            <select name="srcmask" class="selectpicker input-group-btn" data-size="5" id="srcmask"  data-width="auto" for="src" >
                            <?php for ($i = 32; $i > 0; $i--): ?>
                              <option value="<?=$i;?>" <?= $i == $pconfig['srcmask'] ? "selected=\"selected\"" : ""; ?>><?=$i;?></option>
                            <?php endfor; ?>
                            </select>
                          </div>
                          </td>
                        </tr>
                      </table>
                      <div class="hidden" data-for="help_for_src">
                        <?=gettext("Enter the internal subnet for the 1:1 mapping. The subnet size specified for the source will be applied to the external subnet, when none is provided."); ?>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td> <a id="help_for_dst_invert" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Destination") . " / ".gettext("Invert");?> </td>
                    <td>
                      <input name="dstnot" type="checkbox" id="srcnot" value="yes" <?= !empty($pconfig['dstnot']) ? "checked=\"checked\"" : "";?> />
                      <div class="hidden" data-for="help_for_dst_invert">
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
  <?php                          foreach (get_specialnets(true) as $ifent => $ifdesc):
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
                            <!-- updates to "other" option in dst -->
                            <input type="text" for="dst" value="<?= !is_specialnet($pconfig['dst']) ? $pconfig['dst'] : "";?>" aria-label="<?=gettext("Destination address");?>"/>
                            <select name="dstmask" class="selectpicker input-group-btn" data-size="5" id="dstmask"  data-width="auto" for="dst" >
                            <?php for ($i = 32; $i > 0; $i--): ?>
                              <option value="<?=$i;?>" <?= $i == $pconfig['dstmask'] ? "selected=\"selected\"" : ""; ?>><?=$i;?></option>
                            <?php endfor; ?>
                            </select>
                          </div>
                          </td>
                        </tr>
                      </table>
                      <div class="hidden" data-for="help_for_dst">
                        <?=gettext("The 1:1 mapping will only be used for connections to or from the specified destination."); ?><br />
                        <?=gettext("Hint: this is usually 'any'."); ?>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td><a id="help_for_category" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Category"); ?></td>
                    <td>
                      <select name="category[]" id="category" multiple="multiple" class="tokenize" data-allownew="true" data-width="334px" data-live-search="true">
  <?php
                      foreach ((new OPNsense\Firewall\Category())->iterateCategories() as $category):
                        $catname = htmlspecialchars($category['name'], ENT_QUOTES | ENT_HTML401);?>
                        <option value="<?=$catname;?>" <?=in_array($catname, $pconfig['category']) ? 'selected="selected"' : '';?> ><?=$catname;?></option>
  <?php
                      endforeach;?>
                      </select>
                      <div class="hidden" data-for="help_for_category">
                        <?=gettext("You may enter or select a category here to group firewall rules (not parsed)."); ?>
                      </div>
                  </tr>
                  <tr>
                    <td><a id="help_for_descr" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Description"); ?></td>
                    <td>
                      <input name="descr" type="text" class="formfld unknown" id="descr" size="40" value="<?=$pconfig['descr'];?>" />
                      <div class="hidden" data-for="help_for_descr">
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
                      <input name="Submit" type="submit" class="btn btn-primary" value="<?=html_safe(gettext('Save')); ?>" />
                      <input type="button" class="btn btn-default" value="<?=html_safe(gettext('Cancel'));?>" onclick="window.location.href='/firewall_nat_1to1.php'" />
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
