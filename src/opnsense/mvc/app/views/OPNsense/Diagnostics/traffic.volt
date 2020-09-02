{#

OPNsense® is Copyright © 2020 by Deciso B.V.
All rights reserved.

Redistribution and use in source and binary forms, with or without modification,
are permitted provided that the following conditions are met:

1.  Redistributions of source code must retain the above copyright notice,
this list of conditions and the following disclaimer.

2.  Redistributions in binary form must reproduce the above copyright notice,
this list of conditions and the following disclaimer in the documentation
and/or other materials provided with the distribution.

THIS SOFTWARE IS PROVIDED “AS IS” AND ANY EXPRESS OR IMPLIED WARRANTIES,
INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
POSSIBILITY OF SUCH DAMAGE.

#}
{% set theme_name = ui_theme|default('opnsense') %}
<script src="{{ cache_safe('/ui/js/moment-with-locales.min.js') }}"></script>
<script src="{{ cache_safe('/ui/js/chart.min.js') }}"></script>
<script src="{{ cache_safe('/ui/js/chartjs-plugin-streaming.min.js') }}"></script>
<script src="{{ cache_safe('/ui/js/chartjs-plugin-colorschemes.js') }}"></script>
<link rel="stylesheet" type="text/css" href="{{ cache_safe(theme_file_or_default('/css/chart.css', theme_name)) }}" rel="stylesheet" />

<style>
  .chart-container {
    position: relative;
    margin: auto;
    height: 300px ;
  }
</style>

<script>
    'use strict';

    $( document ).ready(function() {
        function format_field(value) {
            if (value) {
                let fileSizeTypes = ["", "K", "M", "G", "T", "P", "E", "Z", "Y"];
                let ndx = Math.floor(Math.log(value) / Math.log(1024) );
                let fmt =  (value / Math.pow(1024, ndx)).toFixed(2) + ' ' + fileSizeTypes[ndx];
                return fmt;
            } else {
                return "";
            }
        }
        /**
         * create new traffic chart
         */
        function traffic_graph(target, graph_title, init_data) {
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
                          display: true,
                          text: graph_title
                      },
                      maintainAspectRatio: false,
                      scales: {
                          xAxes: [{
                              type: 'realtime',
                              realtime: {
                                  duration: 20000,
                                  refresh: 1000,
                                  delay: 2000
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
        /**
         * poll for new stats and update selected charts
         */
        function traffic_poller(charts){
            ajaxGet("/api/diagnostics/traffic/interface", {}, function(data, status) {
                if (data.interfaces !== undefined) {
                    for (var i =0 ; i < charts.length; ++i) {
                        let this_chart = charts[i];
                        Object.keys(data.interfaces).forEach(function(intf) {
                            this_chart.config.data.datasets.forEach(function(dataset) {
                                if (dataset.intf == intf) {
                                    let calc_data = data.interfaces[intf][dataset.src_field];
                                    let elapsed_time = data.time - dataset.last_time;
                                    dataset.hidden = !$("#interfaces").val().includes(intf);
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
                }
            });
            setTimeout(function(){traffic_poller(charts)}, 1000);
        };
        /**
         * startup, fetch initial interface stats and create graphs
         */
        ajaxGet('/api/diagnostics/traffic/interface',{}, function(data, status){
            // XXX: startup selected interfaces load/save in localStorage in a future version
            let selected_interfaces = ['lan', 'wan'];
            let i = 1;
            Object.keys(data.interfaces).forEach(function(intf) {
                let colors = Chart.colorschemes.tableau.Tableau20.length;
                let colorIdx = i - parseInt(i / colors) * colors;
                data.interfaces[intf].color = Chart.colorschemes.tableau.Tableau20[colorIdx];

                let option = $("<option/>").attr("value", intf);
                if (selected_interfaces.includes(intf)) {
                    option.prop("selected", true);
                }
                option.data(
                    'content',
                    $("<span class='badge' style='background:"+data.interfaces[intf].color+"'/>").text(data.interfaces[intf].name).prop('outerHTML')
                );
                i++;
                $('#interfaces').append(option);
            });
            $('#interfaces').selectpicker('refresh');
            traffic_poller([
                traffic_graph($("#rxChart"), '{{ lang._('In (bps)') }}', data),
                traffic_graph($("#txChart"), '{{ lang._('Out (bps)') }}', data)
            ]);
        });

    });


</script>
<style>
  .badge-color-1 {
      background: navy !important;
  }
</style>

<div class="content-box">
    <div class="content-box-main">
        <div class="table-responsive">
                <div class="row">
                    <div class="col-sm-12">
                      <div class="pull-right">
                          <select class="selectpicker" id="interfaces" multiple=multiple>
                          </select>
                          &nbsp;
                      </div>
                    </div>
                    <div class="col-xs-12 col-lg-6">
                      <div class="chart-container">
                          <canvas id="rxChart" data-src_field="bytes received"></canvas>
                      </div>
                    </div>
                    <div class="col-xs-12 col-lg-6">
                        <div class="chart-container">
                            <canvas id="txChart" data-src_field="bytes transmitted"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
