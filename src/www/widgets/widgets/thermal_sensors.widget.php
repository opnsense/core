<?php

/*
 * Copyright (C) 2014-2016 Deciso B.V.
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

config_read_array('widgets', 'thermal_sensors_widget');

function fix_temp_value($value, int $default): int
{
    if (is_numeric($value) && (int)$value == $value && $value >= 0 and $value <= 100) {
        return (int)$value;
    } else {
        return $default;
    }
}

function fix_checkbox_value($value): bool
{
    if ($value !== '') {
        return true;
    } else {
        return false;
    }
}

$fields = [
    ['name' => 'thermal_sensors_widget_zone_warning_threshold', 'default' => 70, 'processFunc' => 'fix_temp_value'],
    ['name' => 'thermal_sensors_widget_zone_critical_threshold', 'default' => 80, 'processFunc' => 'fix_temp_value'],
    ['name' => 'thermal_sensors_widget_core_warning_threshold', 'default' => 70, 'processFunc' => 'fix_temp_value'],
    ['name' => 'thermal_sensors_widget_core_critical_threshold', 'default' => 80, 'processFunc' => 'fix_temp_value'],
    ['name' => 'thermal_sensors_widget_show_one_core_temp', 'default' => false, 'processFunc' => 'fix_checkbox_value'],
    ['name' => 'thermal_sensors_widget_show_temp_in_fahrenheit', 'default' => false, 'processFunc' => 'fix_checkbox_value'],
];
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $pconfig = [];
    foreach ($fields as $field) {
        $pconfig[$field['name']] = !empty($config['widgets']['thermal_sensors_widget'][$field['name']]) ? $config['widgets']['thermal_sensors_widget'][$field['name']] : $field['default'];
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($fields as $field) {
        $newValue = $field['processFunc']($_POST[$field['name']] ?? '', $field['default']);
        $config['widgets']['thermal_sensors_widget'][$field['name']] = $newValue;
    }
    write_config("Thermal sensors widget saved via Dashboard.");
    header(url_safe('Location: /index.php'));
    exit;
}

?>
<script>
    'use strict';

    function thermal_sensors_widget_update(sender, data) {
        let firstCore = true;
        data.map(function (sensor) {
            // Skip if the user only wants one core temperature and we have already showed one.
            if (!firstCore) {
                return;
            }
            if (sensor['type'] === 'core' && $('#thermal_sensors_widget_show_one_core_temp').attr('checked') === 'checked') {
                firstCore = false;
            }

            const tr_id = "thermal_sensors_widget_" + sensor['device'].replace(/\./g, '_');

            let tbody = sender.find('tbody');
            if (tbody.find('#' + tr_id).length === 0) {
                let tr = $('<tr>');
                tr.attr('id', tr_id);

                let td = $('<td>');
                td.html($('#thermal_sensors_widget_progress_bar').html());

                tr.append(td);
                tbody.append(tr);
            }

            // probe warning / danger temp
            let danger_temp, warning_temp;
            if (sensor['type'] === 'core') {
                danger_temp = parseInt($('#thermal_sensors_widget_core_critical_threshold').val());
                warning_temp = parseInt($('#thermal_sensors_widget_core_warning_threshold').val());
            } else {
                danger_temp = parseInt($('#thermal_sensors_widget_zone_critical_threshold').val());
                warning_temp = parseInt($('#thermal_sensors_widget_zone_warning_threshold').val());
            }

            // progress bar style
            let progressBar = $('#' + tr_id + ' .progress-bar');
            const tempIntValue = parseInt(sensor['temperature']);
            if (tempIntValue > danger_temp) {
                progressBar.removeClass('progress-bar-success')
                    .removeClass('progress-bar-warning')
                    .removeClass('progress-bar-danger')
                    .addClass('progress-bar-danger');
            } else if (tempIntValue > warning_temp) {
                progressBar.removeClass('progress-bar-success')
                    .removeClass('progress-bar-warning')
                    .removeClass('progress-bar-danger')
                    .addClass('progress-bar-warning');
            } else {
                progressBar.removeClass('progress-bar-success')
                    .removeClass('progress-bar-warning')
                    .removeClass('progress-bar-danger')
                    .addClass('progress-bar-success');
            }

            // update bar
            if ($('#thermal_sensors_widget_show_temp_in_fahrenheit').attr('checked') === 'checked') {
                progressBar.html(Number.parseFloat(1.8 * sensor['temperature'] + 32).toFixed(1) + ' &deg;F');
            } else {
                progressBar.html(sensor['temperature'] + ' &deg;C');
            }
            progressBar.css("width", tempIntValue + "%").attr("aria-valuenow", tempIntValue + "%");

            // update label
            $('#' + tr_id + ' .info').html(sensor['type_translated'] + ' ' + sensor['device_seq'] + ' <small>(' + sensor['device'] + ')<small>');
        });
    }
</script>

<div id="thermal_sensors-settings" class="widgetconfigdiv" style="display:none;">
  <form action="/widgets/widgets/thermal_sensors.widget.php" method="post" id="iform_thermal_sensors_settings" name="iform_thermal_sensors_settings">
    <table class="table table-striped">
      <thead>
        <tr>
          <th colspan="2"><?= gettext('Thresholds in °C (1 to 100):') ?></th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td><?= gettext('Zone Warning:') ?></td>
          <td>
            <input type="text" id="thermal_sensors_widget_zone_warning_threshold" name="thermal_sensors_widget_zone_warning_threshold" value="<?= $pconfig['thermal_sensors_widget_zone_warning_threshold']; ?>" />
          </td>
        </tr>
        <tr>
          <td><?= gettext('Zone Critical:') ?></td>
          <td>
            <input type="text" id="thermal_sensors_widget_zone_critical_threshold" name="thermal_sensors_widget_zone_critical_threshold" value="<?= $pconfig['thermal_sensors_widget_zone_critical_threshold']; ?>" />
          </td>
        </tr>
        <tr>
          <td><?= gettext('Core Warning:') ?></td>
          <td>
            <input type="text" id="thermal_sensors_widget_core_warning_threshold" name="thermal_sensors_widget_core_warning_threshold" value="<?= $pconfig['thermal_sensors_widget_core_warning_threshold']; ?>" />
          </td>
        </tr>
        <tr>
          <td><?= gettext('Core Critical:') ?></td>
          <td>
            <input type="text" id="thermal_sensors_widget_core_critical_threshold" name="thermal_sensors_widget_core_critical_threshold" value="<?= $pconfig['thermal_sensors_widget_core_critical_threshold']; ?>" />
          </td>
        </tr>
        <tr>
            <td></td>
            <td>
                <input type="checkbox" id="thermal_sensors_widget_show_one_core_temp" name="thermal_sensors_widget_show_one_core_temp" <?=$pconfig['thermal_sensors_widget_show_one_core_temp'] ? 'checked="checked"' : ''; ?>/>
                <?= gettext('Only show first found CPU core temperature') ?>
            </td>
        </tr>
        <tr>
            <td></td>
            <td>
                <input type="checkbox" id="thermal_sensors_widget_show_temp_in_fahrenheit" name="thermal_sensors_widget_show_temp_in_fahrenheit" <?=$pconfig['thermal_sensors_widget_show_temp_in_fahrenheit'] ? 'checked="checked"' : ''; ?>/>
                <?= gettext('Display temperature in fahrenheit') ?>
            </td>
        </tr>
        <tr>
          <td colspan="2">
            <input type="submit" id="thermal_sensors_widget_submit" name="thermal_sensors_widget_submit" class="btn btn-primary formbtn" value="<?= html_safe(gettext('Save')) ?>" />
          </td>
        </tr>
        <tr>
          <td colspan="2">
            <span>* <?= sprintf(gettext('You can configure a proper Thermal Sensor / Module %shere%s.'),'<a href="system_advanced_misc.php">','</a>') ?></span>
          </td>
        </tr>
      </tbody>
    </table>
  </form>
</div>

<!-- template progress bar used for all constructed items in thermal_sensors_widget_update() -->
<div style="display:none" id="thermal_sensors_widget_progress_bar">
  <div class="progress">
    <div class="progress-bar progress-bar-success" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100" style="width: 0%"></div>
  </div>
  <span class="info">
  </span>
</div>

<table class="table table-striped table-condensed" data-plugin="temperature" data-callback="thermal_sensors_widget_update">
  <tbody>
  </tbody>
</table>

<!-- needed to display the widget settings menu -->
<script>
//<![CDATA[
  $("#thermal_sensors-configure").removeClass("disabled");
//]]>
</script>
