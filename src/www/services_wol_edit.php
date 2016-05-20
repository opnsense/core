<?php

/*
    Copyright (C) 2014-2016 Deciso B.V.
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

function wolcmp($a, $b) {
    return strcmp($a['descr'], $b['descr']);
}


if (empty($config['wol']['wolentry']) || !is_array($config['wol']['wolentry'])) {
    $config['wol'] = array();
    $config['wol']['wolentry'] = array();
}
$a_wol = &$config['wol']['wolentry'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($_GET['id']) && !empty($a_wol[$_GET['id']])) {
        $id = $_GET['id'];
    }
    $pconfig = array();
    foreach (array('interface', 'mac', 'descr') as $fieldname) {
        if (isset($id) && isset($a_wol[$id][$fieldname])) {
            $pconfig[$fieldname] = $a_wol[$id][$fieldname];
        } elseif (isset($_GET[$fieldname])) {
            $pconfig[$fieldname] = $_GET[$fieldname];
        } else {
            $pconfig[$fieldname] = null;
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['id']) && !empty($a_wol[$_POST['id']])) {
        $id = $_POST['id'];
    }
    $pconfig = $_POST;
    $input_errors = array();

    /* input validation */
    $reqdfields = explode(" ", "interface mac");
    $reqdfieldsn = array(gettext("Interface"),gettext("MAC address"));

    do_input_validation($pconfig, $reqdfields, $reqdfieldsn, $input_errors);

    /* normalize MAC addresses - lowercase and convert Windows-ized hyphenated MACs to colon delimited */
    $pconfig['mac'] = strtolower(str_replace("-", ":", $pconfig['mac']));

    if (!empty($pconfig['mac']) && !is_macaddr($_POST['mac'])) {
        $input_errors[] = gettext("A valid MAC address must be specified.");
    }
    if (count($input_errors) == 0) {
        $wolent = array();
        $wolent['interface'] = $_POST['interface'];
        $wolent['mac'] = $_POST['mac'];
        $wolent['descr'] = $_POST['descr'];

        if (isset($id)) {
            $a_wol[$id] = $wolent;
        } else {
            $a_wol[] = $wolent;
        }
        usort($config['wol']['wolentry'], "wolcmp");
        write_config();

        header("Location: services_wol.php");
        exit;
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
            <form method="post" name="iform" id="iform">
              <div class="table-responsive">
                <table class="table table-striped opnsense_standard_table_form">
                  <tr>
                    <td width="22%">
                      <strong><?=gettext("Edit WOL entry");?></strong>
                    </td>
                    <td width="78%" align="right">
                      <small><?=gettext("full help"); ?> </small>
                      <i class="fa fa-toggle-off text-danger"  style="cursor: pointer;" id="show_all_help_page"></i>
                    </td>
                  </tr>
                  <tr>
                    <td><a id="help_for_interface" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Interface");?></td>
                    <td>
                      <select name="interface" class="selectpicker">
<?php
                      foreach (get_configured_interface_with_descr() as $iface => $ifacename): ?>
                        <option value="<?=$iface;?>" <?= !link_interface_to_bridge($iface) && $iface == $pconfig['interface'] ? "selected=\"selected\"" : ""; ?>>
                        <?=htmlspecialchars($ifacename);?>
                        </option>
<?php
                      endforeach; ?>
                      </select>
                      <div class="hidden" for="help_for_interface">
                        <?=gettext("Choose which interface this host is connected to.");?>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td><a id="help_for_mac" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("MAC address");?></td>
                    <td>
                      <input name="mac" type="text" value="<?=$pconfig['mac'];?>" />
                      <div class="hidden" for="help_for_mac">
                        <?=gettext("Enter a MAC address  in the following format: xx:xx:xx:xx:xx:xx");?>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td><a id="help_for_descr" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Description");?></td>
                    <td>
                      <input name="descr" type="text" value="<?=$pconfig['descr'];?>" />
                      <div class="hidden" for="help_for_descr">
                        <?=gettext("You may enter a description here for your reference (not parsed).");?>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td>&nbsp;</td>
                    <td>
                      <input name="Submit" type="submit" class="btn btn-primary" value="<?=gettext("Save");?>" />
                      <input type="button" class="btn btn-default" value="<?=gettext("Cancel");?>" onclick="window.location.href='<?=(isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '/services_wol.php');?>'" />
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
