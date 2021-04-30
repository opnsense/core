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
    $pconfig['interfacesstatisticsfilter'] = !empty($config['widgets']['interfacesstatisticsfilter']) ?
        explode(',', $config['widgets']['interfacesstatisticsfilter']) : array();
    $pconfig['interfacesstatisticsinvert'] = !empty($config['widgets']['interfacesstatisticsinvert']) ? '1' : '';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pconfig = $_POST;
    if (!empty($pconfig['interfacesstatisticsfilter'])) {
        $config['widgets']['interfacesstatisticsfilter'] = implode(',', $pconfig['interfacesstatisticsfilter']);
    } elseif (isset($config['widgets']['interfacesstatisticsfilter'])) {
        unset($config['widgets']['interfacesstatisticsfilter']);
    }
    if (!empty($pconfig['interfacesstatisticsinvert'])) {
        $config['widgets']['interfacesstatisticsinvert'] = 1;
    } elseif (isset($config['widgets']['interfacesstatisticsinvert'])) {
        unset($config['widgets']['interfacesstatisticsinvert']);
    }
    write_config("Saved Interface Statistics Filter via Dashboard");
    header(url_safe('Location: /index.php'));
    exit;
}

$ifvalues = array(
    'pkg_in' => gettext('Packets In'),
    'pkg_out' => gettext('Packets Out'),
    'bytes_in' => gettext('Bytes In'),
    'bytes_out' => gettext('Bytes Out'),
    'errors_in' => gettext('Errors In'),
    'errors_out' => gettext('Errors Out'),
    'collisions' => gettext('Collisions'),
);

?>

<script>
  /**
   * update interface statistics
   */
  function interface_statistics_widget_update(sender, data)
  {
      data.map(function(interface_data) {
          // fill in stats, use column index to determine td location
          var item_index = $("#interface_statistics_widget_intf_" + interface_data['descr']).index();
          if (item_index != -1) {
              $("#interface_statistics_widget_intf_" + interface_data['descr'] +" > td:eq(1)").html(parseInt(interface_data['inpkts']).toLocaleString());
              $("#interface_statistics_widget_intf_" + interface_data['descr'] +" > td:eq(2)").html(parseInt(interface_data['outpkts']).toLocaleString());
              $("#interface_statistics_widget_intf_" + interface_data['descr'] +" > td:eq(3)").html(interface_data['inbytes_frmt']);
              $("#interface_statistics_widget_intf_" + interface_data['descr'] +" > td:eq(4)").html(interface_data['outbytes_frmt']);
              $("#interface_statistics_widget_intf_" + interface_data['descr'] +" > td:eq(5)").html(interface_data['inerrs']);
              $("#interface_statistics_widget_intf_" + interface_data['descr'] +" > td:eq(6)").html(interface_data['outerrs']);
              $("#interface_statistics_widget_intf_" + interface_data['descr'] +" > td:eq(7)").html(interface_data['collisions']);
          }
      });
  }
</script>

<div id="interface_statistics-settings" class="widgetconfigdiv" style="display:none;">
  <form action="/widgets/widgets/interface_statistics.widget.php" method="post" name="iformd">
    <table class="table table-condensed">
      <tr>
        <td>
          <select id="interfacesstatisticsinvert" name="interfacesstatisticsinvert" class="selectpicker_widget">
            <option value="" <?= empty($pconfig['interfacesstatisticsinvert']) ? 'selected="selected"' : '' ?>><?= gettext('Hide') ?></option>
            <option value="yes" <?= !empty($pconfig['interfacesstatisticsinvert']) ? 'selected="selected"' : '' ?>><?= gettext('Show') ?></option>
          </select>
          <select id="interfacesstatisticsfilter" name="interfacesstatisticsfilter[]" multiple="multiple" class="selectpicker_widget">
<?php foreach ($interfaces as $iface => $ifacename): ?>
            <option value="<?= html_safe($iface) ?>" <?= in_array($iface, $pconfig['interfacesstatisticsfilter']) ? 'selected="selected"' : '' ?>><?= html_safe($ifacename) ?></option>
<?php endforeach;?>
          </select>
          <button id="submitd" name="submitd" type="submit" class="btn btn-primary" value="yes"><?= gettext('Save') ?></button>
        </td>
      </tr>
    </table>
  </form>
</div>

<table class="table table-striped table-condensed" data-plugin="interfaces" data-callback="interface_statistics_widget_update">
  <tr>
    <th>&nbsp;</th>
<?php foreach ($ifvalues as $ifkey => $iflabel): ?>
    <th><strong><?= $iflabel ?></strong></th>
<?php endforeach ?>
  </tr>
<?php foreach ($interfaces as $ifdescr => $ifname):
      $listed = in_array($ifdescr, $pconfig['interfacesstatisticsfilter']);
      $listed = !empty($pconfig['interfacesstatisticsinvert']) ? $listed : !$listed;
      if (!$listed) {
        continue;
      } ?>
  <tr id="interface_statistics_widget_intf_<?= html_safe($ifdescr) ?>">
    <td><strong><?= $ifname ?></strong></td>
<?php $infcount = 0; 
      while ($infcount++ < count($ifvalues)): ?>
    <td>&#126;</td>
<?php endwhile ?>
  </tr>
<?php endforeach ?>
</table>

<!-- needed to display the widget settings menu -->
<script>
//<![CDATA[
  $("#interface_statistics-configure").removeClass("disabled");
//]]>
</script>
