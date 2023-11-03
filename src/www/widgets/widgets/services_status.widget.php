<?php

/*
 * Copyright (C) 2014-2023 Deciso B.V.
 * Copyright (C) 2007 Sam Wenham
 * Copyright (C) 2005-2006 Colin Smith <ethethlay@gmail.com>
 * Copyright (C) 2004-2005 Scott Ullrich <sullrich@gmail.com>
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
 * INClUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
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
require_once("interfaces.inc");

if (isset($_POST['servicestatusfilter'])) {
    $config['widgets']['servicestatusfilter'] = $_POST['servicestatusfilter'];
    write_config("Saved Service Status Filter via Dashboard");
    header(url_safe('Location: /index.php'));
    exit;
}

?>

<script>
    $(window).on('load', function () {
        function control_services(action, id, title, icon) {
            return '<span data-service_action="' + action + '" data-service="' + id + '" ' +
              'class="btn btn-xs btn-default srv_status_act2" title="' + title + '">' +
              '<i class="fa fa-' + icon + ' fa-fw"></i></span>';
        }
        function fetch_services() {
            ajaxGet('/api/core/service/search', {}, function(data, status) {
                if (data['rows'] !== undefined) {
                    let $table = $('#service_widget_table');
                    let items = {};
                    $table.find('tr[data-service-widget-id]').each(function() {
                        let $item = $(this);
                        items[$item.attr('data-service-widget-id')] = $item;
                    });
                    $.each(data['rows'], function(key, value) {
                        let $item = items.hasOwnProperty(value.id) ? items[value.id] : null;
                        if (!$item) {
                            $item = $('<tr>').attr('data-service-widget-id', value.id);
                            $item.append($('<td/>'));
                            $item.append($('<td/>'));
                            $item.append($('<td style="width: 3em;"/>'));
                            $item.append($('<td style="width: 5em; white-space: nowrap;"/>'));
                            $item.hide();
                            $table.append($item);
                            items[value.id] = $item;
                        }
                        $item.find('td:eq(0)').text(value.name);
                        $item.find('td:eq(1)').text(value.description);
                        if (value.running) {
                            $item.find('td:eq(2)').html('<span class="label label-opnsense label-opnsense-xs label-success pull-right" title="<?= gettext('Running') ?>"><i class="fa fa-play fa-fw"></i></span>');
                        } else {
                            $item.find('td:eq(2)').html('<span class="label label-opnsense label-opnsense-xs label-danger pull-right" title="<?= gettext('Stopped') ?>"><i class="fa fa-stop fa-fw"></i></span>');
                        }
                        if (value.locked) {
                            $item.find('td:eq(3)').html(control_services('restart', value.id, "<?= gettext('Restart') ?>", 'repeat'));
                        } else if (value.running) {
                            $item.find('td:eq(3)').html(control_services('restart', value.id, "<?= gettext('Restart') ?>", 'repeat') +
                                control_services('stop', value.id, "<?= gettext('Stop') ?>", 'stop'));
                        } else {
                            $item.find('td:eq(3)').html(control_services('start', value.id, "<?= gettext('Start') ?>", 'play'));
                        }
                        let hide_items = $("#servicestatusfilter").val().split(',');
                        if (!hide_items.includes(value.name)) {
                            $item.show();
                        } else {
                            $item.hide();
                        }
                    });
                    $('.srv_status_act2').click(function (event) {
                        event.preventDefault();
                        let url = '/api/core/service/' + $(this).data('service_action') + '/' + $(this).data('service');
                        $("#OPNsenseStdWaitDialog").modal('show');
                        $.post(url, {}, function (data) {
                            // refresh page after service action via server
                            location.reload(true);
                        });
                    });
                }
            });
            setTimeout(fetch_services, 5000);
        }
        fetch_services();
    });
</script>

<!-- service options -->
<div id="services_status-settings" style="display:none;">
  <form action="/widgets/widgets/services_status.widget.php" method="post" name="iformd">
    <table class="table table-condensed">
      <thead>
        <tr>
            <th><?= gettext('Comma-separated list of services to NOT display in the widget') ?></th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td><input type="text" name="servicestatusfilter" id="servicestatusfilter" value="<?= html_safe($config['widgets']['servicestatusfilter'] ?? '') ?>" /></td>
        </tr>
        <tr>
          <td>
            <input id="submitd" name="submitd" type="submit" class="btn btn-primary" value="<?= html_safe(gettext('Save')) ?>" />
          </td>
        </tr>
      </tbody>
    </table>
  </form>
</div>

<!-- service table -->


<!-- needed to display the widget settings menu -->
<script>
//<![CDATA[
  $("#services_status-configure").removeClass("disabled");
//]]>
</script>
