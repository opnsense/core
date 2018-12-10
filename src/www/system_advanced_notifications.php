<?php

/*
 * Copyright (C) 2014-2015 Deciso B.V.
 * Copyright (C) 2009 Scott Ullrich <sullrich@gmail.com>
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

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // System Sounds
    $pconfig['disablebeep'] = isset($config['system']['disablebeep']);
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pconfig = $_POST;

    if (!empty($pconfig['Submit']) && $pconfig['Submit'] == gettext("Save")) {
        // System Sounds
        if (!empty($pconfig['disablebeep'])) {
            $config['system']['disablebeep'] = true;
        } elseif (isset($config['system']['disablebeep'])) {
            unset($config['system']['disablebeep']);
        }

        write_config();
        header(url_safe('Location: /system_advanced_notifications.php'));
        return;
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
      <form method="post">
<?php
      if (isset($savemsg)) {
          print_info_box($savemsg);
      }
?>
      </form>
      <section class="col-xs-12">
        <form method="post" name="iform">
          <div class="content-box tab-content table-responsive __mb">
            <table class="table table-striped opnsense_standard_table_form">
              <tr>
                <td style="width:22%"><strong><?= gettext('System Sounds') ?></strong></td>
                <td style="width:78%"></td>
              </tr>
              <tr>
                <td><a id="help_for_disablebeep" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Startup/Shutdown Sound"); ?></td>
                <td>
                  <input name="disablebeep" type="checkbox" id="disablebeep" value="yes" <?=!empty($pconfig['disablebeep']) ? 'checked="checked"' : '';?>/>
                  <?=gettext("Disable the startup/shutdown beep"); ?>
                  <div class="hidden" data-for="help_for_disablebeep">
                    <?=gettext("When this is checked, startup and shutdown sounds will no longer play."); ?>
                  </div>
                </td>
              </tr>
            </table>
          </div>
          <div class="content-box tab-content table-responsive">
            <table class="table table-striped opnsense_standard_table_form">
              <tr>
                <td style="width:22%"></td>
                <td style="width:78%">
                  <input type="submit" id="Submit" name="Submit" class="btn btn-primary" value="<?=gettext("Save"); ?>" />
                </td>
              </tr>
            </table>
          </div>
        </form>
      </section>
    </div>
  </div>
</section>
<?php include("foot.inc");
