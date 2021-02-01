<?php

/*
 * Copyright (C) 2014 Deciso B.V.
 * Copyright (C) 2007 Scott Dale
 * Copyright (C) 2009 Jim Pingle <jimp@pfsense.org>
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
require_once("interfaces.inc");

$pconfig = $_POST;

if (is_numeric($pconfig['filterlogentries'])) {
    $config['widgets']['filterlogentries'] = $pconfig['filterlogentries'];
    $config['widgets']['filterlogentriesupdateinterval'] = $pconfig['filterlogentriesupdateinterval'];

    $acts = array();
    if ($pconfig['actpass']) {
        $acts[] = 'Pass';
    }
    if ($pconfig['actblock']) {
        $acts[] = 'Block';
    }
    if ($pconfig['actreject']) {
        $acts[] = 'Reject';
    }

    if (!empty($acts)) {
        $config['widgets']['filterlogentriesacts'] = implode(' ', $acts);
    } elseif (isset($config['widgets']['filterlogentriesacts'])) {
        unset($config['widgets']['filterlogentriesacts']);
    }

    if (!empty($pconfig['filterlogentriesinterfaces'])) {
        $config['widgets']['filterlogentriesinterfaces'] = $pconfig['filterlogentriesinterfaces'];
    } elseif (isset($config['widgets']['filterlogentriesinterfaces'])) {
        unset($config['widgets']['filterlogentriesinterfaces']);
    }

    write_config('Saved Filter Log Entries via Dashboard');
    header(url_safe('Location: /index.php'));
    exit;
}

$nentries = isset($config['widgets']['filterlogentries']) ? $config['widgets']['filterlogentries'] : 5;
$updateinterval = isset($config['widgets']['filterlogentriesupdateinterval']) ? $config['widgets']['filterlogentriesupdateinterval'] : 2;
$nentriesacts = isset($config['widgets']['filterlogentriesacts']) ?  explode(" ", $config['widgets']['filterlogentriesacts']) : array('Pass', 'Block', 'Reject');
$nentriesinterfaces = isset($config['widgets']['filterlogentriesinterfaces']) ? $config['widgets']['filterlogentriesinterfaces'] : '';

?>
<script>
    $(window).on("load", function() {
        // needed to display the widget settings menu
        $("#log-configure").removeClass("disabled");
        // icons
        const field_type_icons = {'pass': 'fa-play', 'block': 'fa-ban', 'rdr': 'fa-exchange', 'nat': 'fa-exchange'};

        var interface_descriptions = {};
        ajaxGet('/api/diagnostics/interface/getInterfaceNames', {}, function(data, status) {
            interface_descriptions = data;
        });
        function fetch_log(){
            var record_spec = [];
            // read heading, contains field specs
            $("#filter-log-entries > thead > tr > th").each(function () {
                record_spec.push({
                    'column-id': $(this).data('column-id'),
                    'type': $(this).data('type'),
                    'class': $(this).attr('class')
                });
            });
            var last_digest = $("#filter-log-entries > tbody > tr:first > td:first").text();
            ajaxGet('/api/diagnostics/firewall/log/', {'digest': last_digest, 'limit': 100}, function(data, status) {
                var filtact = [];

                if ($("#actpass").is(':checked')) {
                    filtact.push('pass');
                }
                if ($("#actblock").is(':checked') || $("#actreject").is(':checked')) {
                    filtact.push('block');
                }

                let record;
                while ((record=data.pop()) != null) {
                    var intf = record['interface'];

                    if (interface_descriptions[record['interface']] != undefined) {
                        intf = interface_descriptions[record['interface']].toLowerCase();
                    }

                    if ($("#filterlogentriesinterfaces").val() == "" || $("#filterlogentriesinterfaces").val() == intf) {
                        if ((filtact.length == 0 || filtact.indexOf(record['action']) !== -1) && record['__digest__'] != last_digest) {
                            var log_tr = $("<tr>");
                            log_tr.hide();
                            $.each(record_spec, function(idx, field){
                                var log_td = $('<td style="word-break:break-word;">').addClass(field['class']);
                                var column_name = field['column-id'];
                                var content = null;
                                switch (field['type']) {
                                    case 'icon':
                                        var icon = field_type_icons[record[column_name]];
                                        if (icon != undefined) {
                                            log_td.html('<i class="fa '+icon+'" aria-hidden="true"></i>');
                                            if (record[column_name] == 'pass') {
                                                log_td.addClass('text-success');
                                            } else {
                                                log_td.addClass('text-danger');
                                            }

                                        }
                                        break;
                                    case 'time':
                                        log_td.text(record[column_name].replace(/:[0-9]{2}$/, ''));
                                        break;
                                    case 'interface':
                                        if (interface_descriptions[record[column_name]] != undefined) {
                                            log_td.text(interface_descriptions[record[column_name]]);
                                        } else {
                                            log_td.text(record[column_name]);
                                        }
                                        break;
                                    case 'source_address':
                                        // may support ports, but needs IPv6 fixup
                                        log_td.text(record[column_name]);
                                        break;
                                    case 'destination_address':
                                        // may support ports, but needs IPv6 fixup
                                        log_td.text(record[column_name]);
                                        break;
                                    case 'destination_port':
                                        log_td.text(record[column_name]);
                                        break;
                                    default:
                                        if (record[column_name] != undefined) {
                                            log_td.text(record[column_name]);
                                        }
                                }
                                log_tr.append(log_td);
                            });
                            $("#filter-log-entries > tbody > tr:first").before(log_tr);
                        }
                    }
                }
                $("#filter-log-entries > tbody > tr:gt("+(parseInt($("#filterlogentries").val() - 1))+")").remove();
                $("#filter-log-entries > tbody > tr").show();
            });

            // schedule next fetch
            var update_interval_ms = parseInt($("#filterlogentriesupdateinterval").val()) * 1000;
            update_interval_ms = (isNaN(update_interval_ms) || update_interval_ms < 1000 || update_interval_ms > 60000) ? 5000 : update_interval_ms;
            setTimeout(fetch_log, update_interval_ms);
        }

        fetch_log();
    });
</script>

<div id="log-settings" class="widgetconfigdiv" style="display:none;">
  <form action="/widgets/widgets/log.widget.php" method="post" name="iformd">
    <table class="table table-condensed">
      <tr>
        <td>
          <label for="filterlogentries"><?= gettext('Number of log entries:') ?></label><br/>
          <select id="filterlogentries" name="filterlogentries" class="selectpicker_widget">
<?php for ($i = 1; $i <= 20; $i++): ?>
            <option value="<?= html_safe($i) ?>" <?= $nentries == $i ? 'selected="selected"' : '' ?>><?= html_safe($i) ?></option>
<?php endfor ?>
          </select><br/>
          <label for="filterlogentriesupdateinterval"><?= gettext('Update interval in seconds:') ?></label><br/>
          <select id="filterlogentriesupdateinterval" name="filterlogentriesupdateinterval" class="selectpicker_widget">
<?php for ($i = 1; $i <= 60; $i++): ?>
            <option value="<?= html_safe($i) ?>" <?= $updateinterval == $i ? 'selected="selected"' : '' ?>><?= html_safe($i) ?></option>
<?php endfor ?>
          </select><br/>
          <label for="filterlogentriesinterfaces"><?= gettext('Interfaces to display:'); ?></label><br/>
          <select id="filterlogentriesinterfaces" name="filterlogentriesinterfaces" class="selectpicker_widget">
            <option value=""><?= gettext('All') ?></option>
<?php foreach (get_configured_interface_with_descr() as $iface => $ifacename): ?>
            <option value="<?= html_safe($iface) ?>" <?= $nentriesinterfaces == $iface ? 'selected="selected"' : '' ?>>
              <?= html_safe($ifacename) ?>
            </option>
<?php endforeach ?>
          </select><br/><br/>
          <table style="width:348px">
            <tr>
              <td><label for="actblock"><input id="actblock" name="actblock" type="checkbox" value="Block" <?=in_array('Block', $nentriesacts) ? "checked=\"checked\"" : "";?> />Block</label></td>
              <td><label for="actreject"><input id="actreject" name="actreject" type="checkbox" value="Reject" <?=in_array('Reject', $nentriesacts) ? "checked=\"checked\"" : "";?> />Reject</label></td>
              <td><label for="actpass"><input id="actpass" name="actpass" type="checkbox" value="Pass" <?=in_array('Pass', $nentriesacts) ? "checked=\"checked\"" : "";?> />Pass</label></td>
              <td><button name="submit_firewall_logs_widget" type="submit" class="btn btn-primary" value="yes"><?= gettext('Save') ?></button></td>
            </tr>
          </table>
        </td>
      </tr>
    </table>
  </form>
</div>

<table class="table table-striped table-condensed" id="filter-log-entries">
  <thead>
    <tr>
      <th data-column-id="__digest__" data-type="string" class="hidden"><?= gettext('Hash') ?></th>
      <th data-column-id="action" data-type="icon" class="text-center"><?= gettext('Act') ?></th>
      <th data-column-id="__timestamp__" data-type="time"><?= gettext('Time') ?></th>
      <th data-column-id="interface" data-type="interface" class="text-center"><?= gettext('Interface') ?></th>
      <th data-column-id="src" data-type="source_address"><?= gettext('Source') ?></th>
      <th data-column-id="dst" data-type="destination_address"><?= gettext('Destination') ?></th>
      <th data-column-id="dstport" data-type="destination_port"><?= gettext('Dest Port') ?></th>
    </tr>
  </thead>
  <tbody>
    <tr></tr>
  </tbody>
</table>
