<?php

/*
    Copyright (C) 2014 Deciso B.V.
    Copyright (C) 2007 Sam Wenham
    Copyright (C) 2005-2006 Colin Smith <ethethlay@gmail.com>
    Copyright (C) 2004-2005 Scott Ullrich <sullrich@gmail.com>
    All rights reserved.

    Redistribution and use in source and binary forms, with or without
    modification, are permitted provided that the following conditions are met:

    1. Redistributions of source code must retain the above copyright notice,
       this list of conditions and the following disclaimer.

    2. Redistributions in binary form must reproduce the above copyright
       notice, this list of conditions and the following disclaimer in the
       documentation and/or other materials provided with the distribution.

    THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
    INClUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
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
require_once("services.inc");
require_once("system.inc");
require_once("interfaces.inc");

$services = services_get();

if (isset($_POST['servicestatusfilter'])) {
    $config['widgets']['servicestatusfilter'] = htmlspecialchars($_POST['servicestatusfilter'], ENT_QUOTES | ENT_HTML401);
    write_config("Saved Service Status Filter via Dashboard");
    header(url_safe('Location: /index.php'));
    exit;
}

?>
<div id="services_status-settings" class="widgetconfigdiv" style="display:none;">
  <form action="/widgets/widgets/services_status.widget.php" method="post" name="iformd">
    <table class="table table-condensed">
      <thead>
        <tr>
            <th><?= gettext('Comma separated list of services to NOT display in the widget') ?></th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td><input type="text" name="servicestatusfilter" id="servicestatusfilter" value="<?= $config['widgets']['servicestatusfilter'] ?>" /></td>
        </tr>
        <tr>
          <td>
            <input id="submitd" name="submitd" type="submit" class="btn btn-primary" value="<?=gettext("Save");?>" />
          </td>
        </tr>
      </tbody>
    </table>
  </form>
</div>

<table class="table table-striped table-condensed">
  <thead>
    <tr>
      <th><?= gettext('Service') ?></th>
      <th><?= gettext('Description') ?></th>
      <th style="width:100px;"><?= gettext('Status') ?></th>
    </tr>
  </thead>
  <tbody>
<?php
  $skipservices = explode(",", $config['widgets']['servicestatusfilter']);
  if (count($services) > 0):
      foreach ($services as $service):
          if (!$service['name'] || in_array($service['name'], $skipservices)) {
              continue;
          } ?>
        <tr>
          <td><?=$service['name'];?></td>
          <td><?=$service['description'];?></td>
          <td style="white-space: nowrap;">
             <?= get_service_status_icon($service, true) ?>
             <?= get_service_control_links($service, true) ?>
          </td>
        </tr>
<?php
      endforeach;
  else:?>
  <tr><td colspan="3"><?=gettext("No services found");?></td></tr>
<?php
  endif;?>
  </tbody>
</table>

<!-- needed to display the widget settings menu -->
<script type="text/javascript">
//<![CDATA[
  $("#services_status-configure").removeClass("disabled");
//]]>
</script>
