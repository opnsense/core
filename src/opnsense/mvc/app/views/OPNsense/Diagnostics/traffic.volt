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
        function traffic_graph(target, graph_title) {
            ajaxGet("/api/diagnostics/traffic/interface", {}, function(data, status) {
                // setup legend
                let all_datasets = [];
                Object.keys(data.interfaces).forEach(function(intf) {
                    //if (data.interfaces[intf].name) {
                    let label = data.interfaces[intf].name  !== undefined ? data.interfaces[intf].name : intf;
                    all_datasets.push({
                        label: label,
                        hidden: true,
                        intf: intf,
                        last_time: data.time,
                        last_data: data.interfaces[intf][target.data('src_field')],
                        data: []
                    });
                    //}
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
                                      delay: 2000,
                                      onRefresh: function(chart) {
                                      }
                                  },
                              }],
                              yAxes: [{
                                  //type: 'logarithmic',
                                  scaleLabel: {
                                      display: true,
                                      labelString: 'value'
                                  },
                                  ticks: {
                                      callback: function (value, index, values) {
                                          if (value) {
                                              let fileSizeTypes = ["", "K", "M", "G", "T", "P", "E", "Z", "Y"];
                                              let ndx = Math.floor( Math.log(value) / Math.log(1024) );
                                              let fmt =  (value / Math.pow(1024, ndx)).toFixed(2) + ' ' + fileSizeTypes[ndx];
                                              return fmt;
                                          } else {
                                              return "";
                                          }
                                      }
                                  }
                              }]
                          },
                          tooltips: {
                              mode: 'nearest',
                              intersect: false
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
                let this_chart = new Chart(ctx, config);
                function poller(){
                    ajaxGet("/api/diagnostics/traffic/interface", {}, function(data, status) {
                        if (data.interfaces !== undefined) {
                            Object.keys(data.interfaces).forEach(function(intf) {
                                config.data.datasets.forEach(function(dataset) {
                                    if (dataset.intf == intf) {
                                        let calc_data = data.interfaces[intf][target.data('src_field')];
                                        let elapsed_time = data.time - dataset.last_time;
                                        let bps_in = Math.round(((calc_data - dataset.last_data) / elapsed_time) * 8, 0);
                                        dataset.hidden = !$("#interfaces").val().includes(intf);
                                        dataset.last_time = data.time;
                                        dataset.last_data = calc_data;
                                        dataset.data.push({
                                            x: Date.now(),
                                            y: bps_in
                                        });
                                        return;
                                    }
                                });
                            });
                            this_chart.update();
                        }
                    });
                    setTimeout(poller, 2000);
                };
                poller();
            });
        }
        // Init
        ajaxGet('/api/diagnostics/networkinsight/getInterfaces',{}, function(interface_names, status){
            let idx = 0;
            for (let key in interface_names) {
                let option = $("<option/>").attr("value",key).text(interface_names[key]);
                if (idx < 2) {
                    option.prop("selected", true);
                }
                idx++;
                $('#interfaces').append(option);

            }
            $('#interfaces').selectpicker('refresh');
            traffic_graph($("#rxChart"), '{{ lang._('In (bps)') }}')
            traffic_graph($("#txChart"), '{{ lang._('Out (bps)') }}')
        });

    });


</script>

<div class="content-box">
    <div class="content-box-main">
        <div class="table-responsive">
            <div  class="col-sm-12">
                <div class="row">
                    <div class="pull-right">
                        <select class="selectpicker" id="interfaces" multiple=multiple>
                        </select>
                        &nbsp;
                    </div>
                    <div class="chart-container">
                        <canvas id="rxChart" data-src_field="bytes received"></canvas>
                    </div>
                    <div class="chart-container">
                        <canvas id="txChart" data-src_field="bytes transmitted"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
