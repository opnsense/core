<?php

/*
 * Copyright (C) 2014-2016 Deciso B.V.
 * Copyright (C) 2007 Scott Dale
 * Copyright (C) 2004-2005 T. Lechat <dev@lechat.org>
 * Copyright (C) 2004-2005 Manuel Kasper <mk@neon1.net>
 * Copyright (C) 2004-2005 Jonathan Watt <jwatt@jwatt.org>
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
require_once("widgets/include/interface_list.inc");
require_once("interfaces.inc");

$interfaces = get_configured_interface_with_descr();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $pconfig = array();
    $pconfig['interfaceslistfilter'] = !empty($config['widgets']['interfaceslistfilter']) ?
        explode(',', $config['widgets']['interfaceslistfilter']) : array();
    $pconfig['interfaceslistinvert'] = !empty($config['widgets']['interfaceslistinvert']) ? '1' : '';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pconfig = $_POST;
    if (!empty($pconfig['interfaceslistfilter'])) {
        $config['widgets']['interfaceslistfilter'] = implode(',', $pconfig['interfaceslistfilter']);
    } elseif (isset($config['widgets']['interfaceslistfilter'])) {
        unset($config['widgets']['interfaceslistfilter']);
    }
    if (!empty($pconfig['interfaceslistinvert'])) {
        $config['widgets']['interfaceslistinvert'] = 1;
    } elseif (isset($config['widgets']['interfaceslistinvert'])) {
        unset($config['widgets']['interfaceslistinvert']);
    }
    write_config("Saved Interface List Filter via Dashboard");
    header(url_safe('Location: /index.php'));
    exit;
}

?>

<script>
  /**
   * Hybrid widget only update interface status using ajax
   */
  function interface_widget_update(sender, data)
  {
      data.map(function(interface_data) {
          var tr_id = 'interface_widget_item_' + interface_data['name'];
          if ($("#"+tr_id).length) {
              switch (interface_data['status']) {
                  case 'up':
                    $("#"+tr_id).find('.text-danger').removeClass('text-danger').addClass('text-success');
                    $("#"+tr_id).find('.fa-arrow-down').removeClass('fa-arrow-down').addClass('fa-arrow-up');
                    $("#"+tr_id).find('.fa-times').removeClass('fa-times').addClass('fa-arrow-up');
                    break;
                  case 'down':
                    $("#"+tr_id).find('.text-success').removeClass('text-success').addClass('text-danger');
                    $("#"+tr_id).find('.fa-arrow-up').removeClass('fa-arrow-up').addClass('fa-arrow-down');
                    $("#"+tr_id).find('.fa-times').removeClass('fa-times').addClass('fa-arrow-down');
                    break;
                  default:
                    $("#"+tr_id).find('.text-success').removeClass('text-success').addClass('text-danger');
                    $("#"+tr_id).find('.fa-arrow-down').removeClass('fa-arrow-down').addClass('fa-times');
                    $("#"+tr_id).find('.fa-arrow-up').removeClass('fa-arrow-up').addClass('fa-times');
                    break;
              }
          }
      });
  }
</script>

<div id="interface_list-settings" class="widgetconfigdiv" style="display:none;">
  <form action="/widgets/widgets/interface_list.widget.php" method="post" name="iformd">
    <table class="table table-condensed">
      <tr>
        <td>
          <select id="interfaceslistinvert" name="interfaceslistinvert" class="selectpicker_widget">
            <option value="" <?= empty($pconfig['interfaceslistinvert']) ? 'selected="selected"' : '' ?>><?= gettext('Hide') ?></option>
            <option value="yes" <?= !empty($pconfig['interfaceslistinvert']) ? 'selected="selected"' : '' ?>><?= gettext('Show') ?></option>
          </select>
          <select id="interfaceslistfilter" name="interfaceslistfilter[]" multiple="multiple" class="selectpicker_widget">
<?php foreach ($interfaces as $iface => $ifacename): ?>
            <option value="<?= html_safe($iface) ?>" <?= in_array($iface, $pconfig['interfaceslistfilter']) ? 'selected="selected"' : '' ?>><?= html_safe($ifacename) ?></option>
<?php endforeach ?>
          </select>
          <button id="submitd" name="submitd" type="submit" class="btn btn-primary" value="yes"><?= gettext('Save') ?></button>
        </td>
      </tr>
    </table>
  </form>
</div>

<table class="table table-striped table-condensed" data-plugin="interfaces" data-callback="interface_widget_update">
<?php
  $ifsinfo = get_interfaces_info();
  foreach ($interfaces as $ifdescr => $ifname):
    $listed = in_array($ifdescr, $pconfig['interfaceslistfilter']);
    $listed = !empty($pconfig['interfaceslistinvert']) ? $listed : !$listed;
    if (!$listed) {
      continue;
    }
    $ifinfo = $ifsinfo[$ifdescr];
    $iswireless = is_interface_wireless($ifdescr); ?>
  <tr id="interface_widget_item_<?= html_safe($ifname) ?>">
    <td style="width:15%; word-break: break-word;">
<?php if (isset($ifinfo['ppplink'])): ?>
      <span title="3g" class="fa fa-mobile text-success"></span>
<?php elseif ($iswireless): ?>
<?php if ($ifinfo['status'] == 'associated' || $ifinfo['status'] == 'up'): ?>
      <span title="wlan" class="fa fa-signal text-success"></span>
<?php else: ?>
      <span title="wlan_d" class="fa fa-signal text-danger"></span>
<?php endif ?>
<?php else: ?>
<?php if ($ifinfo['status'] == 'up'): ?>
      <span title="cablenic" class="fa fa-exchange text-success"></span>
<?php else: ?>
      <span title="cablenic" class="fa fa-exchange text-danger"></span>
<?php endif ?>
<?php endif ?>
      &nbsp;
      <strong>
        <u>
          <span onclick="location.href='/interfaces.php?if=<?=htmlspecialchars($ifdescr); ?>'" style="cursor:pointer">
            <?= htmlspecialchars($ifname) ?>
          </span>
        </u>
      </strong>
    </td>
    <td style="width:5%; word-break: break-word;">
<?php if ($ifinfo['status'] == 'up' || $ifinfo['status'] == 'associated'): ?>
      <span class="fa fa-arrow-up text-success"></span>
<?php elseif ($ifinfo['status'] == "down"): ?>
      <span class="fa fa-arrow-down text-danger"></span>
<?php elseif ($ifinfo['status'] == "no carrier"): ?>
      <span class="fa fa-times text-danger"></span>
<?php else: ?>
      <?= htmlspecialchars($ifinfo['status']) ?>
<?php endif ?>
    </td>
    <td style="width:35%; word-break: break-word;">
      <?= empty($ifinfo['media']) ? htmlspecialchars($ifinfo['cell_mode']) : htmlspecialchars($ifinfo['media']) ?>
    </td>
    <td style="width:45%; word-break: break-word;">
      <?= htmlspecialchars($ifinfo['ipaddr']) ?>
      <?= !empty($ifinfo['ipaddr']) ? '<br/>' : '' ?>
      <?= htmlspecialchars(isset($config['interfaces'][$ifdescr]['dhcp6prefixonly']) ? $ifinfo['linklocal'] : $ifinfo['ipaddrv6']) ?>
    </td>
  </tr>
<?php endforeach ?>
</table>

<!-- needed to display the widget settings menu -->
<script>
//<![CDATA[
  $("#interface_list-configure").removeClass("disabled");
//]]>
</script>
