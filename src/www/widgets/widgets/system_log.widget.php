<?php

/*
 * Copyright (C) 2015 S. Linke <dev@devsash.de>
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

$system_logfile = '/var/log/system.log';

if (!$config['widgets']['systemlogfiltercount']){
  $syslogEntriesToFetch = 20;
} else {
  $syslogEntriesToFetch = $config['widgets']['systemlogfiltercount'];
}

if (is_numeric($_POST['logfiltercount'])) {
   $countReceived =  $_POST['logfiltercount'];
   $config['widgets']['systemlogfiltercount'] = $countReceived;
   write_config("Saved Widget System Log Filter Setting");
   header(url_safe('Location: /index.php'));
   exit;
}
?>

<div id="system_log-settings" class="widgetconfigdiv" style="display:none;">
  <form action="/widgets/widgets/system_log.widget.php" method="post" name="iform">
    <table class="table table-striped">
      <tr>
        <td><?=gettext("Number of Log lines to display");?>:</td>
        <td>
          <select name="logfiltercount" id="logfiltercount">
            <?php for ($i = 1; $i <= 50; $i++) {?>
            <option value="<?= html_safe($i) ?>" <?php if ($syslogEntriesToFetch == $i) { echo "selected=\"selected\"";}?>><?= html_safe($i) ?></option>
            <?php } ?>
          </select>
        </td>
        <td>
          <input id="submit_system_log_widget" name="submit_system_log_widget" type="submit" class="btn btn-primary formbtn" value="<?= html_safe(gettext('Save')) ?>">
        </td>
      </tr>
    </table>
  </form>
</div>

<div id="system_log-widgets" class="content-box" style="overflow:scroll;">
  <table class="table table-striped">
      <tbody>
<?php
        $logdata = json_decode(
            configdp_run("system diag log", [$syslogEntriesToFetch, 0, "", "core", "system"]),
            true
        );
        $records = !empty($logdata) && !empty($logdata['rows']) ? $logdata['rows'] : [];
        foreach($records as $record):?>
        <tr>
            <td style="width:150px;" class="text-nowrap"><?=$record['timestamp'];?></td>
            <td><?=html_safe($record['line']);?></td>
        </tr>

<?php
        endforeach;?>
      </tbody>
  </table>
</div>

<!-- needed to display the widget settings menu -->
<script>
//<![CDATA[
  $("#system_log-configure").removeClass("disabled");
//]]>
</script>
