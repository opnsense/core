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
                                  refresh: 2000,
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
         * create new traffic top usage chart
         */
        function traffic_top_graph(target, graph_title, init_data) {
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
                  type: 'bubble',
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
                                  duration: 40000,
                                  refresh: 3000,
                                  delay: 500
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
                                  return [
                                    tooltipItem.xLabel,
                                    ds.label + " : " + ds.data[tooltipItem.index].address,
                                    "@ " + format_field(ds.data[tooltipItem.index].y).toString()
                                  ];
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
         * iftop (top talkers) update
         */
        function updateTopTable(data) {
            let target = $("#rxTopTable > tbody");
            let update_stamp = Math.trunc(Date.now() / 1000.0);
            let update_stamp_iso = (new Date()).toISOString();
            Object.keys(data).forEach(function(intf) {
                let intf_label = $("#interfaces > option[value="+intf+"]").data('content');
                ['in', 'out'].forEach(function(dir) {
                    for (var i=0; i < data[intf][dir].length ; i++) {
                        let item = data[intf][dir][i];
                        let tr = target.find("tr[data-address='"+item.address+"']");
                        if (tr.length === 0) {
                            tr = $("<tr/>");
                            tr.attr("data-address", item.address); // XXX: find matches on tag
                            tr.data('bps_in', 0).data('bps_out', 0).data('bps_max_in', 0)
                              .data('bps_max_out', 0).data('total_in', 0).data('total_out', 0)
                              .data('intf', intf);
                            tr.append($("<td/>").html(intf_label));
                            tr.append($("<td/>").text(item.address));
                            tr.append($("<td class='bps_in'/>").text("0b"));
                            tr.append($("<td class='bps_out'/>").text("0b"));
                            tr.append($("<td class='bps_max_in'/>").text("0b"));
                            tr.append($("<td class='bps_max_out'/>").text("0b"));
                            tr.append($("<td class='total_in'/>").text("0b"));
                            tr.append($("<td class='total_out'/>").text("0b"));
                            tr.append($("<td class='last_seen'/>"));
                            target.append(tr);
                        }
                        tr.data('bps_'+dir, item.rate_bits);
                        tr.data('total_'+ dir, tr.data('total_'+ dir) + item.cumulative_bytes);
                        tr.data('last_seen', update_stamp);
                        tr.find('td.last_seen').text(update_stamp_iso);
                        if (parseInt(tr.data('bps_max_'+dir)) < item.rate_bits) {
                              tr.data('bps_max_'+dir, item.rate_bits);
                              tr.find('td.bps_max_'+dir).text(item.rate);
                        }
                        tr.find('td.bps_'+dir).text(item.rate);
                        tr.find('td.total_'+dir).text(byteFormat(tr.data('total_'+ dir)));
                    }
                });
            });
            let ttl = 120; // keep visible for ttl seconds
            target.find('tr').each(function(){
                if (parseInt($(this).data('last_seen')) < (update_stamp - ttl)) {
                    $(this).remove();
                } else if (parseInt($(this).data('last_seen')) != update_stamp) {
                    // reset measurements not in this set
                    $(this).data('bps_in', 0);
                    $(this).data('bps_out', 0);
                    $(this).find('td.bps_in').text("0b");
                    $(this).find('td.bps_out').text("0b");
                }
            });
            // sort by current top consumer
            target.find('tr').sort(function(a, b) {
                let a_total = parseInt($(a).data('bps_in')) + parseInt($(a).data('bps_out'));
                let b_total = parseInt($(b).data('bps_in')) + parseInt($(b).data('bps_out'));
                if (b_total == 0 && a_total == 0) {
                    // sort by age (last seen)
                    return  parseInt($(b).data('last_seen')) - parseInt($(a).data('last_seen'));
                } else {
                    return  b_total - a_total;
                }
            }).appendTo(target);
            // cleanup deselected interface rows
            let intsshow = $("#interfaces").val();
            $('#rxTopTable > tbody').find('tr').each(function(){
               if (!intsshow.includes($(this).data('intf'))) {
                    $(this).remove();
                }
            });
        }

        /**
         * startup, fetch initial interface stats and create graphs
         */
        ajaxGet('/api/diagnostics/traffic/interface',{}, function(data, status){
            // XXX: startup selected interfaces load/save in localStorage in a future version
            let tmp = window.localStorage ? window.localStorage.getItem("api.diagnostics.traffic.interface") : null;
            let selected_interfaces = ['lan', 'wan'];
            if (tmp !== null) {
                selected_interfaces = tmp.split(',');
            }
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

            // register traffic update event
            $( document ).on( "updateTrafficCharts", {
                charts: [
                    traffic_graph($("#rxChart"), '{{ lang._('In (bps)') }}', data),
                    traffic_graph($("#txChart"), '{{ lang._('Out (bps)') }}', data)
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
            });

            // register traffic update event
            $( document ).on( "updateTrafficTopCharts", {
                charts: [
                    traffic_top_graph($("#rxTopChart"), '{{ lang._('Top hosts in (bps)') }}', data),
                    traffic_top_graph($("#txTopChart"), '{{ lang._('Top hosts out (bps)') }}', data)
                ]
            }, function( event, data) {
                let charts = event.data.charts;
                for (var i =0 ; i < charts.length; ++i) {
                    let this_chart = charts[i];
                    Object.keys(data).forEach(function(intf) {
                        this_chart.config.data.datasets.forEach(function(dataset) {
                            if (dataset.intf == intf) {
                                let calc_data = data[intf][dataset.src_field];
                                dataset.hidden = !$("#interfaces").val().includes(intf);
                                for (var i=0; i < data[intf][dataset.src_field].length ; ++i) {
                                    dataset.data.push({
                                        x: Date.now(),
                                        y: data[intf][dataset.src_field][i]['rate_bits'],
                                        r: 4,
                                        address: data[intf][dataset.src_field][i]['address']
                                    });
                                }
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
                setTimeout(traffic_poller, 2000);
            })();
            (function top_traffic_poller(){
                if ($("#interfaces").val().length > 0) {
                    ajaxGet('/api/diagnostics/traffic/top/' + $("#interfaces").val().join(","), {}, function(data, status){
                        if (status == 'success') {
                            $( document ).trigger( "updateTrafficTopCharts", [ data ] );
                            updateTopTable(data);
                            top_traffic_poller();
                        } else {
                            setTimeout(top_traffic_poller, 2000);
                        }
                    });
                }
            })();
        });

        $("#interfaces").change(function(){
            if (window.localStorage) {
                window.localStorage.setItem("api.diagnostics.traffic.interface", $(this).val());
            }
        });
    });


</script>
<style>
  .badge-color-1 {
      background: navy !important;
  }
</style>

<ul class="nav nav-tabs" data-tabs="tabs" id="maintabs">
    <li class="active"><a data-toggle="tab" id="graph_tab" href="#graph">{{ lang._('Graph') }}</a></li>
    <li><a data-toggle="tab" id="gtid_tab" href="#toptalkers">{{ lang._('Top talkers') }}</a></li>
    <div class="pull-right">
        <select class="selectpicker" id="interfaces" multiple=multiple>
        </select>
        &nbsp;
    </div>
</ul>
<div class="tab-content content-box">
    <div id="graph" class="tab-pane fade in active">
        <div class="table-responsive">
            <div class="row">
                <div class="col-sm-12">
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
            <div class="row">
              <div class="col-xs-12">
                  <hr/>
              </div>
            </div>
            <div class="row">
                <div class="col-xs-12 col-lg-6">
                    <div class="chart-container">
                        <canvas id="rxTopChart" data-src_field="in"></canvas>
                    </div>
                </div>
                <div class="col-xs-12 col-lg-6">
                    <div class="chart-container">
                        <canvas id="txTopChart" data-src_field="out"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div id="toptalkers" class="tab-pane fade in">
        <div class="col-xs-12 col-lg-12">
            <table class="table table-condensed" id="rxTopTable">
                <thead>
                    <tr>
                        <th></th>
                        <th>{{ lang._('Address') }}</th>
                        <th>{{ lang._('In (bps)') }}</th>
                        <th>{{ lang._('Out (bps)') }}</th>
                        <th>{{ lang._('In max(bps)') }}</th>
                        <th>{{ lang._('Out max(bps)') }}</th>
                        <th>{{ lang._('Total In') }}</th>
                        <th>{{ lang._('Total Out') }}</th>
                        <th>{{ lang._('Timestamp') }}</th>
                    </tr>
                </thead>
                <tbody>
                </tbody>
            </table>
        </div>
    </div>
</div>
