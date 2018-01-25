<?php

/*
    Copyright (C) 2014 Deciso B.V.
    Copyright (C) 2007 Scott Dale
    Copyright (C) 2009 Jim Pingle <jimp@pfsense.org>
    Copyright (C) 2004-2005 T. Lechat <dev@lechat.org>
    Copyright (C) 2004-2005 Manuel Kasper <mk@neon1.net>
    Copyright (C) 2004-2005 Jonathan Watt <jwatt@jwatt.org>
    All rights reserved.

    Redistribution and use in source and binary forms, with or without
    modification, are permitted provided that the following conditions are met:

    1. Redistributions of source code must retain the above copyright notice,
       this list of conditions and the following disclaimer.

    2. Redistributions in binary form must reproduce the above copyright
       notice, this list of conditions and the following disclaimer in the
       documentation and/or other materials provided with the distribution.

    THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
    INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
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
require_once("interfaces.inc");

if (is_numeric($_POST['filterlogentries'])) {
    $config['widgets']['filterlogentries'] = $_POST['filterlogentries'];

    $acts = array();
    if ($_POST['actpass']) {
        $acts[] = "Pass";
    }
    if ($_POST['actblock']) {
        $acts[] = "Block";
    }
    if ($_POST['actreject']) {
        $acts[] = "Reject";
    }

    if (!empty($acts)) {
        $config['widgets']['filterlogentriesacts'] = implode(" ", $acts);
    } else {
        unset($config['widgets']['filterlogentriesacts']);
    }

    if (($_POST['filterlogentriesinterfaces']) && ($_POST['filterlogentriesinterfaces'] != "All")) {
        $config['widgets']['filterlogentriesinterfaces'] = trim($_POST['filterlogentriesinterfaces']);
    } else {
        unset($config['widgets']['filterlogentriesinterfaces']);
    }

    write_config("Saved Filter Log Entries via Dashboard");
    header(url_safe('Location: /index.php'));
    exit;
}

$nentries = isset($config['widgets']['filterlogentries']) ? $config['widgets']['filterlogentries'] : 5;

//set variables for log
$nentriesacts       = isset($config['widgets']['filterlogentriesacts']) ?  explode(" ", $config['widgets']['filterlogentriesacts']) : array('All');
$nentriesinterfaces = isset($config['widgets']['filterlogentriesinterfaces']) ? $config['widgets']['filterlogentriesinterfaces'] : 'All';
?>

<script>
    $(window).load(function() {
        // needed to display the widget settings menu
        $("#log-configure").removeClass("disabled");
        // icons
        var field_type_icons = {'pass': 'fa-play', 'block': 'fa-ban'}

        var interface_descriptions = {};
        ajaxGet(url='/api/diagnostics/interface/getInterfaceNames', {}, callback=function(data, status) {
            interface_descriptions = data;
        });
        function fetch_log(){
            var record_spec = [];
            // read heading, contains field specs
            $("#filter-log-entries > thead > tr > th ").each(function(){
                record_spec.push({'column-id': $(this).data('column-id'),
                                  'type': $(this).data('type'),
                                  'class': $(this).attr('class')
                                 });
            });
            ajaxGet(url='/api/diagnostics/firewall/log/', {'limit': 100}, callback=function(data, status) {
                while ((record=data.pop()) != null) {
                    var intf = record['interface'];
                    var filtact = [];

                    if ($("#actpass").is(':checked')) {
                        filtact.push('pass');
                    }
                    if ($("#actblock").is(':checked') || $("#actreject").is(':checked')) {
                        filtact.push('block');
                    }

                    if (interface_descriptions[record['interface']] != undefined) {
                        intf = interface_descriptions[record['interface']].toLowerCase();
                    }

                    if ($("#filterlogentriesinterfaces").val() == "All" || $("#filterlogentriesinterfaces").val() == intf) {
                        if (filtact.length == 0 || filtact.indexOf(record['action']) !== -1 ) {
                            var log_tr = $("<tr>");
                            log_tr.hide();
                            $.each(record_spec, function(idx, field){
                                var log_td = $('<td>').addClass(field['class']);
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
                                        log_td.text(record[column_name]);
                                        break;
                                    case 'destination_address':
                                        log_td.text(record[column_name]);
                                        if (record[column_name+'port'] != undefined) {
                                            log_td.text(log_td.text()+':'+record[column_name+'port']);
                                        }
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
                $("#filter-log-entries > tbody > tr:gt("+(parseInt($("#filterlogentries").val())-1)+")").remove();
                $("#filter-log-entries > tbody > tr").show();
                // schedule next fetch
            });
            setTimeout(fetch_log, 5000);
        }

        fetch_log();
    });
</script>

<div id="log-settings" class="widgetconfigdiv" style="display:none;">
  <form action="/widgets/widgets/log.widget.php" method="post" name="iforma">
      <table class="table table-striped">
        <tbody>
          <tr>
            <td>
              <?= gettext('Number of lines to display:') ?>
            </td>
          </tr>
          <tr>
            <td>
              <select name="filterlogentries" class="formfld unknown" id="filterlogentries">
<?php
              for ($i = 1; $i <= 20; $i++):?>
                <option value="<?=$i?>" <?= $nentries == $i ? "selected=\"selected\"" : ""?>><?=$i;?></option>
<?php
              endfor;?>
              </select>
            </td>
          </tr>
          <tr>
            <td>
              <input id="actpass"   name="actpass"   type="checkbox" value="Pass"   <?=in_array('Pass', $nentriesacts) ? "checked=\"checked\"" : "";?>/><label for="actpass" style="padding-left: .4em; margin-right: 1.5em">Pass</label>
              <input id="actblock"  name="actblock"  type="checkbox" value="Block"  <?=in_array('Block', $nentriesacts) ? "checked=\"checked\"" : "";?>/><label for="actblock" style="padding-left: .4em; margin-right: 1.5em">Block</label>
              <input id="actreject" name="actreject" type="checkbox" value="Reject" <?=in_array('Reject', $nentriesacts) ? "checked=\"checked\"" : "";?>/><label for="actreject" style="padding-left: .4em; margin-right: 1.5em">Reject</label>
            </td>
          </tr>
          <tr>
            <td>
              <?= gettext('Interfaces:'); ?>
            </td>
          </tr>
          <tr>
            <td>
              <select id="filterlogentriesinterfaces" name="filterlogentriesinterfaces" class="formselect">
                <option value="All"><?= gettext('ALL') ?></option>
<?php
                  foreach (get_configured_interface_with_descr() as $iface => $ifacename) :?>
                  <option value="<?=$iface;?>" <?=$nentriesinterfaces == $iface ? "selected=\"selected\"" : "";?>>
                      <?=htmlspecialchars($ifacename);?>
                  </option>
<?php
                  endforeach;?>
              </select>
            </td>
          </tr>
          <tr>
            <td>
              <input id="submita" name="submita" type="submit" class="btn btn-primary formbtn" value="<?= gettext('Save') ?>" />
            </td>
          </tr>
        </tbody>
      </table>
  </form>
</div>

<table class="table table-striped table-condensed" id="filter-log-entries">
  <thead>
    <tr>
      <th data-column-id="action" data-type="icon" class="text-center"><?=gettext("Act");?></th>
      <th data-column-id="__timestamp__" data-type="time"><?=gettext("Time");?></th>
      <th data-column-id="interface" data-type="interface"><?=gettext("Interface");?></th>
      <th data-column-id="src" data-type="source_address"><?=gettext("Source");?></th>
      <th data-column-id="dst" data-type="destination_address"><?=gettext("Destination");?></th>
    </tr>
  </thead>
  <tbody>
    <tr>
      <td colspan=5></td>
    </tr>
  </tbody>
</table>
