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

$gateways = (new \OPNsense\Routing\Gateways(legacy_interfaces_details()))->gatewaysIndexedByName();
?>

<script>
    $(window).on("load", function() {
        function fetch_gateway_statusses(){
            ajaxGet('/api/routes/gateway/status', {}, function(data, status) {
                if (data.items !== undefined) {
                    $.each(data.items, function(key, gateway) {
                        let $gw_item = $("#gateways_widget_gw_"+gateway.name);
                        if ($gw_item.length == 0) {
                            $gw_item = $("<tr>").attr('id', "gateways_widget_gw_"+gateway.name);
                            $gw_item.append($("<td/>").append(
                              "<small><strong>~</strong><br/><div>~</div></small>")
                            );
                            $gw_item.append($("<td class='text-nowrap'/>").text("~"));
                            $gw_item.append($("<td class='text-nowrap'/>").text("~"));
                            $gw_item.append($("<td class='text-nowrap'/>").text("~"));
                            $gw_item.append(
                                $("<td/>").append(
                                    $("<span class='label label-default'/>").text("<?= gettext('Unknown') ?>")
                                )
                            );
                            $("#gateway_widget_table").append($gw_item);
                            $gw_item.hide();
                        }
                        $gw_item.find('td:eq(0) > small > strong').text(gateway.name);
                        $gw_item.find('td:eq(0) > small > div').text(gateway.address);
                        $gw_item.find('td:eq(1)').text(gateway.delay);
                        $gw_item.find('td:eq(2)').text(gateway.stddev);
                        $gw_item.find('td:eq(3)').text(gateway.loss);
                        let status_color;
                        switch (gateway.status) {
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
                            status_color = 'default';
                            break;
                        }
                        $gw_item.find('td:eq(4) > span').removeClass("label-danger label-warning label-success label");
                        if (status_color != '') {
                            $gw_item.find('td:eq(4) > span')
                              .addClass("label label-" + status_color)
                              .text(gateway.status_translated);
                        }
                        let show_item = $("#gatewaysinvert").val() == 'yes' ? false  : true;
                        if ($("#gatewaysfilter").val() && $("#gatewaysfilter").val().includes(gateway.name)) {
                            show_item = !show_item;
                        }
                        if (show_item) {
                            $gw_item.show();
                        }
                    });
                }
            });
            setTimeout(fetch_gateway_statusses, 5000);
        }
        fetch_gateway_statusses();
    });
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
<?php foreach (array_keys($gateways) as $gwname): ?>
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
<table id="gateway_widget_table" class="table table-striped table-condensed">
  <tr>
    <th><?=gettext('Name')?></th>
    <th><?=gettext('RTT')?></th>
    <th><?=gettext('RTTd')?></th>
    <th><?=gettext('Loss')?></th>
    <th><?=gettext('Status')?></th>
  </tr>
</table>

<!-- needed to display the widget settings menu -->
<script>
//<![CDATA[
  $("#gateways-configure").removeClass("disabled");
//]]>
</script>
