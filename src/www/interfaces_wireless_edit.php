<?php

/*
 * Copyright (C) 2014-2015 Deciso B.V.
 * Copyright (C) 2010 Erik Fonnesbeck
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

function clone_inuse($cloneif)
{
    global $config;

    foreach (array_keys(legacy_config_get_interfaces(['virtual' => false])) as $if) {
        if ($config['interfaces'][$if]['if'] == $cloneif) {
            return true;
        }
    }

    return false;
}

$a_clones = &config_read_array('wireless', 'clone');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($_GET['id']) && !empty($a_clones[$_GET['id']])) {
        $id = $_GET['id'];
    }
    if (isset($id)) {
        $pconfig['if'] = $a_clones[$id]['if'];
        $pconfig['cloneif'] = $a_clones[$id]['cloneif'];
        $pconfig['mode'] = $a_clones[$id]['mode'];
        $pconfig['descr'] = $a_clones[$id]['descr'];
    } else {
        $pconfig = ['if' => '', 'cloneif' => '', 'mode' => 'bss', 'descr' => ''];
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input_errors = array();
    $pconfig = $_POST;
    if (isset($_POST['id']) && !empty($a_clones[$pconfig['id']])) {
        $id = $pconfig['id'];
    }
    /* input validation */
    $reqdfields = explode(" ", "if mode");
    $reqdfieldsn = array(gettext("Parent interface"),gettext("Mode"));

    do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);

    if (count($input_errors) == 0) {
        $clone = array();
        $clone['if'] = $pconfig['if'];
        $clone['mode'] = $pconfig['mode'];
        $clone['descr'] = $pconfig['descr'];

        if (isset($id) && $clone['if'] == $a_clones[$id]['if']) {
            $clone['cloneif'] = $a_clones[$id]['cloneif'];
        }
        if (empty($clone['cloneif'])) {
            $clone_id = 1;
            do {
                $clone_exists = false;
                $clone['cloneif'] = "{$pconfig['if']}_wlan{$clone_id}";
                foreach ($a_clones as $existing) {
                    if ($clone['cloneif'] == $existing['cloneif']) {
                        $clone_exists = true;
                        $clone_id++;
                        break;
                    }
                }
            } while ($clone_exists);
        }

        if (isset($id) && clone_inuse($a_clones[$id]['cloneif'])) {
            if ($clone['if'] != $a_clones[$id]['if']) {
                $input_errors[] = gettext("This wireless clone cannot be modified because it is still assigned as an interface.");
            } elseif ($clone['mode'] != $a_clones[$id]['mode']) {
                $input_errors[] = gettext("Use the configuration page for the assigned interface to change the mode.");
            }
        }
        if (count($input_errors) == 0) {
            if (empty(_interfaces_wlan_clone($clone['cloneif'], $clone))) {
                $input_errors[] = sprintf(gettext('Error creating interface with mode %s. The %s interface may not support creating more clones with the selected mode.'), $wlan_modes[$clone['mode']], $clone['if']);
            } else {
                if (isset($id)) {
                    if ($clone['if'] != $a_clones[$id]['if']) {
                        mwexecf('/sbin/ifconfig %s destroy', $a_clones[$id]['cloneif']);
                    }
                    $a_clones[$id] = $clone;
                } else {
                    $a_clones[] = $clone;
                }

                usort($a_clones, function($a, $b) {
                    return strnatcmp($a['cloneif'], $b['cloneif']);
                });
                write_config();

                header(url_safe('Location: /interfaces_wireless.php'));
                exit;
            }
        }
    }
}

legacy_html_escape_form_data($pconfig);
include("head.inc");
?>

<body>
<?php include("fbegin.inc"); ?>

<section class="page-content-main">
  <div class="container-fluid">
    <div class="row">
      <?php if (isset($input_errors) && count($input_errors) > 0) print_input_errors($input_errors); ?>
      <section class="col-xs-12">
        <div class="content-box tab-content">
          <form method="post" name="iform" id="iform">
            <table class="table table-striped opnsense_standard_table_form">
              <thead>
                <tr>
                  <td style="width:22%"><strong><?=gettext("Wireless clone configuration");?></strong></td>
                  <td style="width:78%; text-align:right">
                    <small><?=gettext("full help"); ?> </small>
                    <i class="fa fa-toggle-off text-danger"  style="cursor: pointer;" id="show_all_help_page"></i>
                    &nbsp;
                  </td>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Parent interface");?></td>
                  <td>
                    <select name="if" class="selectpicker">
<?php foreach (legacy_interface_listget('wlan') as $ifn): ?>
                      <option value="<?= $ifn ?>" <?= $ifn == $pconfig['if'] ? 'selected="selected"' : '' ?>><?= html_safe($ifn) ?></option>
<?php endforeach ?>
                    </select>
                  </td>
                </tr>
                <tr>
                  <td><a id="help_for_descr" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Description");?></td>
                  <td>
                    <input name="descr" type="text" value="<?=$pconfig['descr'];?>" />
                    <div class="hidden" data-for="help_for_descr">
                      <?=gettext("You may enter a description here for your reference (not parsed).");?>
                    </div>
                  </td>
                </tr>
                <tr>
                  <td></td>
                  <td>
                    <input type="hidden" name="mode" value="<?=isset($pconfig['mode']) ? $pconfig['mode'] : 'bss' ?>" />
                    <input type="hidden" name="cloneif" value="<?=$pconfig['cloneif']; ?>" />
                    <input name="Submit" type="submit" class="btn btn-primary" value="<?=html_safe(gettext('Save'));?>" />
                    <input type="button" class="btn btn-default" value="<?=html_safe(gettext('Cancel'));?>" onclick="window.location.href='/interfaces_wireless.php'" />
                    <?php if (isset($id)): ?>
                    <input name="id" type="hidden" value="<?=$id;?>" />
                    <?php endif; ?>
                  </td>
                </tr>
              </tbody>
            </table>
          </form>
        </div>
      </section>
    </div>
  </div>
</section>
<?php include("foot.inc"); ?>
