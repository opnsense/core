<?php

/*
 * Copyright (C) 2014-2016 Deciso B.V.
 * Copyright (C) 2008 Seth Mos <seth.mos@dds.nl>
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
require_once("widgets/include/gateways.inc");

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $pconfig = array();
    $pconfig['gatewaysfilter'] = !empty($config['widgets']['gatewaysfilter']) ?
        explode(',', $config['widgets']['gatewaysfilter']) : array();
    $pconfig['gatewaysinvert'] = !empty($config['widgets']['gatewaysinvert']) ? '1' : '';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pconfig = $_POST;
    if (!empty($pconfig['gatewaysfilter'])) {
        $config['widgets']['gatewaysfilter'] = implode(',', $pconfig['gatewaysfilter']);
    } elseif (isset($config['widgets']['gatewaysfilter'])) {
        unset($config['widgets']['gatewaysfilter']);
    }
    if (!empty($pconfig['gatewaysinvert'])) {
        $config['widgets']['gatewaysinvert'] = 1;
    } elseif (isset($config['widgets']['gatewaysinvert'])) {
        unset($config['widgets']['gatewaysinvert']);
    }
    write_config("Saved Gateways Filter via Dashboard");
    header(url_safe('Location: /index.php'));
    exit;
}

$gateways = return_gateways_array();

?>

<script>
  function gateways_widget_update(sender, data)
  {
      data.map(function(gateway) {
          var tr_id = "gateways_widget_gw_" + gateway['name'];
          if ($("#"+tr_id).length) {
              $("#"+tr_id+" > td:eq(0)").html('<small><strong>'+gateway['name']+'</strong><br/>'+gateway['address']+'</small>');
              $("#"+tr_id+" > td:eq(1)").html(gateway['delay']);
<?php if (isset($config['system']['prefer_dpinger'])): ?>
              $("#"+tr_id+" > td:eq(2)").html(gateway['stddev']);
              $("#"+tr_id+" > td:eq(3)").html(gateway['loss']);
              $("#"+tr_id+" > td:eq(4)").html('<span>'+gateway['status_translated']+'</span>');
<?php else: ?>
              $("#"+tr_id+" > td:eq(2)").html(gateway['loss']);
              $("#"+tr_id+" > td:eq(3)").html('<span>'+gateway['status_translated']+'</span>');
<?php endif ?>

              // set color on status text
              switch (gateway['status']) {
                case 'force_down':
                case 'down':
                  status_color = 'danger';
                  break;
                case 'loss':
                case 'delay':
                  status_color = 'warning';
                  break;
                case 'none':
                  status_color = 'success';
                  break;
                default:
                  status_color = 'default'
                  break;
              }

<?php if (isset($config['system']['prefer_dpinger'])): ?>
              $("#"+tr_id+" > td:eq(4) > span").removeClass("label-danger label-warning label-success label");
              if (status_color != '') {
                $("#"+tr_id+" > td:eq(4) > span").addClass("label label-" + status_color);
              }
<?php else: ?>
              $("#"+tr_id+" > td:eq(3) > span").removeClass("label-danger label-warning label-success label");
              if (status_color != '') {
                $("#"+tr_id+" > td:eq(3) > span").addClass("label label-" + status_color);
              }
<?php endif ?>
          }
      });
  }
</script>

<div id="gateways-settings" class="widgetconfigdiv" style="display:none;">
  <form action="/widgets/widgets/gateways.widget.php" method="post" name="iformd">
    <table class="table table-condensed">
      <tr>
        <td>
          <select id="gatewaysinvert" name="gatewaysinvert" class="selectpicker_widget">
            <option value="" <?= empty($pconfig['gatewaysinvert']) ? 'selected="selected"' : '' ?>><?= gettext('Hide') ?></option>
            <option value="yes" <?= !empty($pconfig['gatewaysinvert']) ? 'selected="selected"' : '' ?>><?= gettext('Show') ?></option>
          </select>
          <select id="gatewaysfilter" name="gatewaysfilter[]" multiple="multiple" class="selectpicker_widget">
<?php foreach ($gateways as $gwname => $unused): ?>
            <option value="<?= html_safe($gwname) ?>" <?= in_array($gwname, $pconfig['gatewaysfilter']) ? 'selected="selected"' : '' ?>><?= html_safe($gwname) ?></option>
<?php endforeach;?>
          </select>
          <button id="submitd" name="submitd" type="submit" class="btn btn-primary" value="yes"><?= gettext('Save') ?></button>
        </td>
      </tr>
    </table>
  </form>
</div>

<!-- gateway table -->
<table class="table table-striped table-condensed" data-plugin="gateway" data-callback="gateways_widget_update">
  <tr>
    <th><?=gettext('Name')?></th>
    <th><?=gettext('RTT')?></th>
<?php if (isset($config['system']['prefer_dpinger'])): ?>
    <th><?=gettext('RTTd')?></th>
<?php endif ?>
    <th><?=gettext('Loss')?></th>
    <th><?=gettext('Status')?></th>
  </tr>
<?php foreach ($gateways as $gwname => $unused):
      $listed = in_array($gwname, $pconfig['gatewaysfilter']);
      $listed = !empty($pconfig['gatewaysinvert']) ? $listed : !$listed;
      if (!$listed) {
        continue;
      } ?>
   <tr id="gateways_widget_gw_<?= html_safe($gwname) ?>">
     <td><small><strong><?= $gwname ?></strong><br/>~</small></td>
     <td class="text-nowrap">~</td>
<?php if (isset($config['system']['prefer_dpinger'])): ?>
     <td class="text-nowrap">~</td>
<?php endif ?>
     <td class="text-nowrap">~</td>
     <td><span class="label label-default"><?= gettext('Unknown') ?></span></td>
  </tr>
<?php endforeach ?>
</table>

<!-- needed to display the widget settings menu -->
<script>
//<![CDATA[
  $("#gateways-configure").removeClass("disabled");
//]]>
</script>
