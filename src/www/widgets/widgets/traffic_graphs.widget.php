<?php

/*
 * Copyright (C) 2014-2021 Deciso B.V.
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
    $pconfig['traffic_graphs_interfaces'] = !empty($config['widgets']['traffic_graphs_interfaces']) ?
        explode(',', $config['widgets']['traffic_graphs_interfaces']) : ['lan', 'wan'];
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pconfig = $_POST;
    if (!empty($pconfig['traffic_graphs_interfaces'])) {
        $config['widgets']['traffic_graphs_interfaces'] = implode(',', $pconfig['traffic_graphs_interfaces']);
    } elseif (isset($config['widgets']['traffic_graphs_interfaces'])) {
        unset($config['widgets']['traffic_graphs_interfaces']);
    }
    write_config("Saved Widget Interface List via Dashboard");
    header(url_safe('Location: /index.php'));
    exit;
}

?>
<script src="<?=cache_safe('/ui/js/moment-with-locales.min.js');?>"></script>
<script src="<?=cache_safe('/ui/js/chart.min.js');?>"></script>
<script src="<?=cache_safe('/ui/js/chartjs-plugin-streaming.min.js');?>"></script>
<script src="<?=cache_safe('/ui/js/chartjs-plugin-colorschemes.js');?>"></script>
<link rel="stylesheet" type="text/css" href="<?=cache_safe(get_themed_filename('/css/chart.css'));?>" rel="stylesheet" />

<script>
  /**
   * page setup
   */
  $("#dashboard_container").on("WidgetsReady", function() {
        function format_field(value) {
            if (!isNaN(value) && value > 0) {
                let fileSizeTypes = ["", "K", "M", "G", "T", "P", "E", "Z", "Y"];
                let ndx = Math.floor(Math.log(value) / Math.log(1000) );
                if (ndx > 0) {
                    return  (value / Math.pow(1000, ndx)).toFixed(2) + ' ' + fileSizeTypes[ndx];
                } else {
                    return value.toFixed(2);
                }
            } else {
                return "";
            }
        }
        /**
         * create new traffic chart
         */
        function traffic_graph(target, init_data) {
            // setup legend
            let all_datasets = [];
            Object.keys(init_data.interfaces).forEach(function(intf) {
                all_datasets.push({
                    label: init_data.interfaces[intf].name,
                    hidden: true,
                    borderColor: init_data.interfaces[intf].color,
                    backgroundColor: init_data.interfaces[intf].color,
                    pointHoverBackgroundColor: init_data.interfaces[intf].color,
                    pointHoverBorderColor: init_data.interfaces[intf].color,
                    pointBackgroundColor: init_data.interfaces[intf].color,
                    pointBorderColor: init_data.interfaces[intf].color,
                    intf: intf,
                    last_time: init_data.time,
                    last_data: init_data.interfaces[intf][target.data('src_field')],
                    src_field: target.data('src_field'),
                    data: []
                });
            });
            // new chart
            var ctx = target[0].getContext('2d');
            var config = {
                  type: 'line',
                  data: {
                      datasets: all_datasets
                  },
                  options: {
                      legend: {
                          display: false,
                      },
                      title: {
                          display: false
                      },
                      maintainAspectRatio: false,
                      scales: {
                          xAxes: [{
                              time: {
                                  tooltipFormat:'HH:mm:ss',
                                  unit: 'second',
                                  minUnit: 'second',
                                  displayFormats: {
                                      second: 'HH:mm:ss',
                                      minute: 'HH:mm:ss'
                                  }
                              },
                              type: 'realtime',
                              realtime: {
                                  duration: 50000,
                                  refresh: 5000,
                                  delay: 5000
                              },
                          }],
                          yAxes: [{
                              ticks: {
                                  callback: function (value, index, values) {
                                      return format_field(value);
                                  }
                              }
                          }]
                      },
                      tooltips: {
                          mode: 'nearest',
                          intersect: false,
                          callbacks: {
                              label: function(tooltipItem, data) {
                                  let ds = data.datasets[tooltipItem.datasetIndex];
                                  return ds.label + " : " + format_field(ds.data[tooltipItem.index].y).toString();
                              }
                          }
                      },
                      hover: {
                          mode: 'nearest',
                          intersect: false
                      },
                      plugins: {
                          streaming: {
                              frameRate: 30
                          },
                          colorschemes: {
                              scheme: 'brewer.Paired12'
                          }
                      }
                  }
            };
            return new Chart(ctx, config);
        }
        // register traffic update event
        ajaxGet('/api/diagnostics/traffic/interface',{}, function(data, status){
          $( document ).on( "updateTrafficCharts", {
              charts: [
                  traffic_graph($("#rxChart"), data),
                  traffic_graph($("#txChart"), data)
              ]
          }, function( event, data) {
              let charts = event.data.charts;
              for (var i =0 ; i < charts.length; ++i) {
                  let this_chart = charts[i];
                  Object.keys(data.interfaces).forEach(function(intf) {
                      this_chart.config.data.datasets.forEach(function(dataset) {
                          if (dataset.intf == intf) {
                              let calc_data = data.interfaces[intf][dataset.src_field];
                              let elapsed_time = data.time - dataset.last_time;
                              dataset.hidden = !$("#traffic_graphs_interfaces").val().includes(intf);
                              dataset.data.push({
                                  x: Date.now(),
                                  y: Math.round(((calc_data - dataset.last_data) / elapsed_time) * 8, 0)
                              });
                              dataset.last_time = data.time;
                              dataset.last_data = calc_data;
                              return;
                          }
                      });
                  });
                  this_chart.update();
              }
          });

          /**
           * poll for new stats and update selected charts
           */
          (function traffic_poller(){
              ajaxGet("/api/diagnostics/traffic/interface", {}, function(data, status) {
                  if (data.interfaces !== undefined) {
                      $( document ).trigger( "updateTrafficCharts", [ data ] );
                  }
              });
              setTimeout(traffic_poller, 5000);
          })();
        });
        // needed to display the widget settings menu
        $("#traffic_graphs-configure").removeClass("disabled");
  });
</script>


<div id="traffic_graphs-settings" class="widgetconfigdiv" style="display:none;">
  <form action="/widgets/widgets/traffic_graphs.widget.php" method="post" name="iformd">
    <table class="table table-condensed">
      <tr>
        <td>
          <select id="traffic_graphs_interfaces" name="traffic_graphs_interfaces[]" multiple="multiple" class="selectpicker_widget">
<?php foreach ($interfaces as $iface => $ifacename): ?>
            <option value="<?= html_safe($iface) ?>" <?= in_array($iface, $pconfig['traffic_graphs_interfaces']) ? 'selected="selected"' : '' ?>><?= html_safe($ifacename) ?></option>
<?php endforeach ?>
          </select>
          <button id="submitd" name="submitd" type="submit" class="btn btn-primary" value="yes"><?= gettext('Save') ?></button>
        </td>
      </tr>
    </table>
  </form>
</div>
<!-- traffic graph table -->
<table class="table table-condensed">
    <tbody>
      <tr>
        <td><?=gettext("In (bps)");?></td>
      </tr>
      <tr>
        <td>
          <div class="chart-container">
              <canvas id="rxChart" data-src_field="bytes received"></canvas>
          </div>
        </td>
      </tr>
      <tr>
        <td><?=gettext("Out (bps)");?></td>
      </tr>
      <tr>
        <td>
          <div class="chart-container">
              <canvas id="txChart" data-src_field="bytes transmitted"></canvas>
          </div>
        </td>
      </tr>
    </tbody>
</table>
