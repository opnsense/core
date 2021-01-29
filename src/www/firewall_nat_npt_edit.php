<?php

/*
    Copyright (C) 2014 Deciso B.V.
    Copyright (C) 2011 Seth Mos <seth.mos@dds.nl>
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

$a_npt = &config_read_array('nat', 'npt');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($_GET['dup']) && isset($a_npt[$_GET['dup']])) {
        $configId = $_GET['dup'];
        $after = $_GET['dup'];
    } elseif (isset($_GET['id']) && isset($a_npt[$_GET['id']])) {
        $configId = $_GET['id'];
        $id = $configId;
    }

    $pconfig = array();
    // set defaults
    $pconfig['interface'] = "wan";
    if (isset($configId)) {
      // copy 1-to-1 attributes
      foreach (array('disabled','interface','descr', 'category') as $fieldname) {
          if (isset($a_npt[$configId][$fieldname])) {
              $pconfig[$fieldname] = $a_npt[$configId][$fieldname];
          }
      }
      // load attributes with some kind of logic
      address_to_pconfig(
          $a_npt[$configId]['source'], $pconfig['src'],$pconfig['srcmask'], $pconfig['srcnot'],
          $pconfig['__unused__'],$pconfig['__unused__']
      );

      address_to_pconfig(
          $a_npt[$configId]['destination'], $pconfig['dst'],$pconfig['dstmask'], $pconfig['dstnot'],
          $pconfig['__unused__'],$pconfig['__unused__']
      );
    }

    // initialize empty form values
    foreach (array('disabled','interface','descr','src','srcmask','dst','dstmask','srcnot','dstnot') as $fieldname) {
        if (!isset($pconfig[$fieldname])) {
            $pconfig[$fieldname] = null;
        }
    }
    $pconfig['category'] = !empty($pconfig['category']) ? explode(",", $pconfig['category']) : [];
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input_errors = array();
    $pconfig = $_POST;
    if (isset($pconfig['id']) && isset($a_npt[$pconfig['id']])) {
        $id = $pconfig['id'];
    }

    if (isset($pconfig['after']) && isset($a_npt[$pconfig['after']])) {
        // place record after provided sequence number
        $after = $pconfig['after'];
    }

    /* input validation */
    $reqdfields = explode(" ", "interface");
    $reqdfieldsn = array(gettext("Interface"));
    $reqdfields[] = "src";
    $reqdfieldsn[] = gettext("Source prefix");
    $reqdfields[] = "dst";
    $reqdfieldsn[] = gettext("Destination prefix");

    do_input_validation($pconfig, $reqdfields, $reqdfieldsn, $input_errors);

    if (!is_ipaddroralias($pconfig['src'])) {
        $input_errors[] = sprintf(gettext("%s is not a valid source IP address or alias."), $pconfig['src']);
    }
    if (!is_ipaddroralias($pconfig['dst'])) {
        $input_errors[] = sprintf(gettext("%s is not a valid destination IP address or alias."), $pconfig['dst']);
    }

    if (count($input_errors) == 0) {
      $natent = array();

      $natent['disabled'] = isset($pconfig['disabled']) ? true:false;
      $natent['category'] = implode(",", $pconfig['category']);
      $natent['descr'] = $pconfig['descr'];
      $natent['interface'] = $pconfig['interface'];
      pconfig_to_address(
          $natent['source'], trim($pconfig['src']),$pconfig['srcmask'], !empty($pconfig['srcnot'])
      );

      pconfig_to_address(
          $natent['destination'], trim($pconfig['dst']),$pconfig['dstmask'], !empty($pconfig['dstnot'])
      );

      if (isset($id)) {
          $a_npt[$id] = $natent;
      } elseif (isset($after)) {
          array_splice($a_npt, $after+1, 0, array($natent));
      } else {
          $a_npt[] = $natent;
      }
      OPNsense\Core\Config::getInstance()->fromArray($config);
      $catmdl = new OPNsense\Firewall\Category();
      if ($catmdl->sync()) {
          $catmdl->serializeToConfig();
          $config = OPNsense\Core\Config::getInstance()->toArray(listtags());
      }
      write_config();
      mark_subsystem_dirty('natconf');
      header(url_safe('Location: /firewall_nat_npt.php'));
      exit;
    }
}

legacy_html_escape_form_data($pconfig);

include("head.inc");

?>
<script src="<?= cache_safe('/ui/js/tokenize2.js') ?>"></script>
<link rel="stylesheet" type="text/css" href="<?= cache_safe(get_themed_filename('/css/tokenize2.css')) ?>">
<script src="<?= cache_safe('/ui/js/opnsense_ui.js') ?>"></script>
<script>
$( document ).ready(function() {
    formatTokenizersUI();
});
</script>
<body>
  <?php include("fbegin.inc"); ?>
  <section class="page-content-main">
    <div class="container-fluid">
      <div class="row">
        <?php if (isset($input_errors) && count($input_errors) > 0) print_input_errors($input_errors); ?>
          <section class="col-xs-12">
            <div class="content-box">
              <form method="post" name="iform" id="iform">
                <table class="table table-striped opnsense_standard_table_form">
                  <tr>
                    <td><?=gettext("Edit NAT NPTv6 entry"); ?></td>
                    <td style="text-align:right">
                        <small><?=gettext("full help"); ?> </small>
                        <i class="fa fa-toggle-off text-danger"  style="cursor: pointer;" id="show_all_help_page"></i>
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
                      <td colspan="2"><?=gettext("Internal IPv6 Prefix"); ?></td>
                  </tr>
                  <tr>
                      <td><a id="help_for_srcnot" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Source") . " / ".gettext("Invert");?> </td>
                      <td>
                        <input name="srcnot" type="checkbox" value="yes" <?= !empty($pconfig['srcnot']) ? "checked=\"checked\"" :"";?> />
                        <div class="hidden" data-for="help_for_srcnot">
                            <?=gettext("Use this option to invert the sense of the match."); ?>
                        </div>
                      </td>
                  </tr>
                  <tr>
                    <td><a id="help_for_src" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Source") . " / ". gettext("Address:"); ?></td>
                    <td>
                      <table style="border:0;">
                        <tr>
                          <td style="width:348px">
                            <input name="src" type="text" value="<?=$pconfig['src'];?>" aria-label="<?=gettext("Source address");?>"/>
                          </td>
                          <td>
                            <select name="srcmask" class="selectpicker" data-size="5"  data-width="auto">
                              <?php for ($i = 128; $i > 0; $i--): ?>
                                <option value="<?=$i;?>" <?= $i == $pconfig['srcmask'] ? "selected=\"selected\"" : ""; ?>><?=$i;?></option>
                              <?php endfor; ?>
                            </select>
                          </td>
                        </tr>
                      </table>
                      <div class="hidden" data-for="help_for_src">
                        <?=gettext("Enter the internal (LAN) ULA IPv6 Prefix for the Network Prefix translation. The prefix size specified for the internal IPv6 prefix will be applied to the external prefix.");?>
                      </div>
                    </td>
                  </tr>
                  <tr>
                      <td colspan="2"><?=gettext("Destination IPv6 Prefix"); ?></td>
                  </tr>
                  <tr>
                      <td><a id="help_for_dstnot" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Destination") . " / ".gettext("Invert");?> </td>
                      <td>
                        <input name="dstnot" type="checkbox" value="yes" <?= !empty($pconfig['dstnot']) ? "checked=\"checked\"" :"";?> />
                        <div class="hidden" data-for="help_for_dstnot">
                            <?=gettext("Use this option to invert the sense of the match."); ?>
                        </div>
                      </td>
                  </tr>
                  <tr>
                    <td><a id="help_for_dst" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Destination") . " / ". gettext("Address:"); ?></td>
                    <td>
                      <table style="border:0;">
                        <tr>
                          <td style="width:348px">
                            <input name="dst" type="text" value="<?=$pconfig['dst'];?>" aria-label="<?=gettext("Source address");?>"/>
                          </td>
                          <td>
                            <select name="dstmask" class="selectpicker" data-size="5"  data-width="auto">
                              <?php for ($i = 128; $i > 0; $i--): ?>
                                <option value="<?=$i;?>" <?= $i == $pconfig['dstmask'] ? "selected=\"selected\"" : ""; ?>><?=$i;?></option>
                              <?php endfor; ?>
                            </select>
                          </td>
                        </tr>
                      </table>
                      <div class="hidden" data-for="help_for_dst">
                        <?=gettext("Enter the Global Unicast routable IPv6 prefix here"); ?>
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
                        <option value="<?=$catname;?>" <?=!empty($pconfig['category']) && in_array($catname, $pconfig['category']) ? 'selected="selected"' : '';?> ><?=$catname;?></option>
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
                    <td>&nbsp;</td>
                    <td>
                      <input name="Submit" type="submit" class="btn btn-primary" value="<?=html_safe(gettext('Save')); ?>" />
                      <input type="button" class="btn btn-default" value="<?=html_safe(gettext('Cancel'));?>" onclick="window.location.href='/firewall_nat_npt.php'" />
                      <?php if (isset($id)): ?>
                        <input name="id" type="hidden" value="<?=htmlspecialchars($id);?>" />
                      <?php endif; ?>
                      <input name="after" type="hidden" value="<?=isset($after) ? $after : "";?>" />
                    </td>
                  </tr>
                </table>
              </div>
            </form>
          </section>
      </div>
    </div>
  </section>

<?php include("foot.inc"); ?>
