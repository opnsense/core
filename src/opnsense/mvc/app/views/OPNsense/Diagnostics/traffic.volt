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
<script src="{{ cache_safe('/ui/js/chart.umd.min.js') }}"></script>
<script src="{{ cache_safe('/ui/js/chartjs-plugin-colorschemes.min.js') }}"></script>
<script src="{{ cache_safe('/ui/js/moment-with-locales.min.js') }}"></script>
<script src="{{ cache_safe('/ui/js/chartjs-adapter-moment.min.js') }}"></script>
<script src="{{ cache_safe('/ui/js/chartjs-plugin-streaming.js') }}"></script>
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
        function set_alpha(color, opacity) {
            const op = Math.round(Math.min(Math.max(opacity || 1, 0), 1) * 255);
            return color + op.toString(16).toUpperCase();
        }

        function limit(number, lower_bound, upper_bound) {
            return Math.min(Math.max(parseInt(number), lower_bound), upper_bound);
        }

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
                    backgroundColor: set_alpha(init_data.interfaces[intf].color, 0.5),
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
                      maintainAspectRatio: false,
                      elements: {
                        line: {
                            fill: true,
                            cubicInterpolationMode: 'monotone',
                            clip: 0
                        }
                      },
                      scales: {
                          x: {
                              time: {
                                  tooltipFormat:'HH:mm:ss',
                                  unit: 'second',
                                  stepSize: init_data.interval < 10000 ? 5 : init_data.interval / 1000,
                                  minUnit: 'second',
                                  displayFormats: {
                                      second: 'HH:mm:ss',
                                      minute: 'HH:mm:ss'
                                  }
                              },
                              type: 'realtime',
                              realtime: {
                                  duration: 20000,
                                  refresh: init_data.interval,
                                  delay: init_data.interval
                              },
                          },
                          y: {
                              ticks: {
                                  callback: function (value, index, values) {
                                      return format_field(value);
                                  }
                              }
                          }
                      },
                      hover: {
                          mode: 'nearest',
                          intersect: false
                      },
                      plugins: {
                          tooltip: {
                            mode: 'nearest',
                            intersect: false,
                            callbacks: {
                                label: function(context) {
                                    return context.dataset.label + ": " + format_field(context.dataset.data[context.dataIndex].y).toString();
                                }
                            }
                          },
                          title: {
                            display: true,
                            text: graph_title
                          },
                          legend: {
                            display: false
                          },
                          streaming: {
                            frameRate: 30
                          },
                          colorschemes: {
                            scheme: 'tableau.Classic10'
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
                    backgroundColor: set_alpha(init_data.interfaces[intf].color, 0.5),
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
                      maintainAspectRatio: false,
                      scales: {
                          x: {
                              time: {
                                  tooltipFormat:'HH:mm:ss',
                                  unit: 'second',
                                  stepSize: init_data.interval < 10000 ? 5 : init_data.interval / 1000,
                                  minUnit: 'second',
                                  displayFormats: {
                                      second: 'HH:mm:ss',
                                      minute: 'HH:mm:ss'
                                  }
                              },
                              type: 'realtime',
                              realtime: {
                                  duration: 40000,
                                  refresh: init_data.interval,
                                  delay: init_data.interval,
                              },
                          },
                          y: {
                              ticks: {
                                  callback: function (value, index, values) {
                                      return format_field(value);
                                  }
                              }
                          }
                      },
                      hover: {
                          mode: 'nearest',
                          intersect: false
                      },
                      plugins: {
                          tooltip: {
                            mode: 'nearest',
                            intersect: false,
                            callbacks: {
                                label: function(context) {
                                    let split = context.formattedValue.split(",")[0]
                                    let time = split.replace('(', '')
                                    return [
                                        time,
                                        context.dataset.label + ": " + context.dataset.data[context.dataIndex].address,
                                        "@ " + format_field(context.dataset.data[context.dataIndex].y).toString()
                                    ];
                                }
                            }
                          },
                          title: {
                            display: true,
                            text: graph_title
                          },
                          legend: {
                            display: false,
                          },
                          streaming: {
                              frameRate: 30
                          },
                          colorschemes: {
                              scheme: 'tableau.Classic10'
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
                    for (var i=0; i < data[intf]['records'].length ; i++) {
                        let item = data[intf]['records'][i];
                        let tr = target.find("tr[data-address='"+item.address+"']");
                        if (tr.length === 0) {
                            tr = $("<tr/>");
                            tr.attr("data-address", item.address); // XXX: find matches on tag
                            tr.data('bps_in', 0).data('bps_out', 0).data('bps_max_in', 0)
                              .data('bps_max_out', 0).data('total_in', 0).data('total_out', 0)
                              .data('intf', intf);
                            tr.append($("<td/>").html(intf_label));
                            if (item.rname) {
                                tr.append(
                                  $("<td/>").append(
                                      $("<span/>").text(item.rname), $("<small/>").text("("+item.address+")")
                                  )
                                );
                            } else {
                                tr.append($("<td/>").text(item.address));
                            }
                            tr.append($("<td class='bps_in'/>").text("0b"));
                            tr.append($("<td class='bps_out'/>").text("0b"));
                            tr.append($("<td class='bps_max_in'/>").text("0b"));
                            tr.append($("<td class='bps_max_out'/>").text("0b"));
                            tr.append($("<td class='total_in'/>").text("0b"));
                            tr.append($("<td class='total_out'/>").text("0b"));
                            tr.append($("<td class='last_seen'/>"));
                            target.append(tr);
                        }
                        ['in', 'out'].forEach(function(dir) {
                            tr.data('bps_'+dir, item['rate_bits'+dir]);
                            tr.data('total_'+ dir, tr.data('total_'+ dir) + item['cumulative_bytes_'+dir]);
                            tr.data('last_seen', update_stamp);
                            tr.find('td.last_seen').text(update_stamp_iso);
                            if (parseInt(tr.data('bps_max_'+dir)) < item['rate_bits_'+dir]) {
                                  tr.data('bps_max_'+dir, item['rate_bits_'+dir]);
                                  tr.find('td.bps_max_'+dir).text(item['rate_'+dir]);
                            }
                            tr.find('td.bps_'+dir).text(item['rate_'+dir]);
                            tr.find('td.total_'+dir).text(byteFormat(tr.data('total_'+ dir)));
                        });
                    }
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

        // Store references to the charts globally
        let g_charts = {traffic: [], traffic_top: []};

        $("#intervals").change(function() {
            if (window.localStorage) {
                window.localStorage.setItem("api.diagnostics.traffic.interval", $(this).val());
            }

            g_charts['interval'] = limit(Number($("#intervals").val()), 500, 10000);

            Object.keys(g_charts).forEach(function(key) {
                if (Array.isArray(g_charts[key])) {
                    g_charts[key].forEach(function(chart) {
                        chart.config.options.scales.x.realtime = {
                            duration: 20000,
                            refresh: g_charts['interval'],
                            delay: g_charts['interval']
                        };
                        chart.config.options.scales.x.time.stepSize = g_charts['interval'] < 10000 ? 5 : g_charts['interval'] / 1000;
                    })
                }
            })
        });

        /**
         * startup, fetch initial interface stats and create graphs
         */
        ajaxGet('/api/diagnostics/traffic/interface',{}, function(data, status) {
            // XXX: startup selected interfaces load/save in localStorage in a future version
            let tmp = window.localStorage ? window.localStorage.getItem("api.diagnostics.traffic.interface") : null;
            let selected_interfaces = ['lan', 'wan'];
            if (tmp !== null) {
                selected_interfaces = tmp.split(',');
            }
            let i = 0;
            Object.keys(data.interfaces).forEach(function(intf) {
                let colors = Chart.colorschemes.tableau.Classic10;
                let colorIdx = i % colors.length;
                data.interfaces[intf].color = colors[colorIdx];

                let option = $("<option/>").attr("value", intf);
                if (selected_interfaces.includes(intf)) {
                    option.prop("selected", true);
                }
                option.attr(
                    'data-content',
                    $("<span class='badge' style='background:"+data.interfaces[intf].color+"'/>").text(data.interfaces[intf].name).prop('outerHTML')
                );
                i++;
                $('#interfaces').append(option);
            });
            $('#interfaces').selectpicker('refresh');

            // XXX: limit the amount of minimum interval that can be set
            $("#intervals").val(2000);
            if (window.localStorage && window.localStorage.getItem("api.diagnostics.traffic.interval") !== null) {
                $("#intervals").val(window.localStorage.getItem("api.diagnostics.traffic.interval"));
            }
            $('#intervals').selectpicker('refresh');

            data.interval = limit(Number($("#intervals").val()), 500, 10000);
            g_charts['interval'] = data.interval;

            const chart_types = ["rxChart", "txChart", "rxTopChart", "txTopChart"];
            chart_types.forEach(function(chart) {
                /* Create the charts */
                if (chart.includes('Top')) {
                    let rxtx = chart.includes('rx') ? '{{ lang._('Top hosts in (bps)') }}' : '{{ lang._('Top hosts out (bps)') }}';
                    let graph = traffic_top_graph($("#" + chart), rxtx, data);
                    g_charts['traffic_top'].push(graph);
                } else {
                    let rxtx = chart.includes('rx') ? '{{ lang._('In (bps)') }}' : '{{ lang._('Out (bps)') }}';
                    let graph = traffic_graph($("#" + chart), rxtx, data);
                    g_charts['traffic'].push(graph);
                }
            });

            /**
             * poll for new stats and update selected charts
             */
            (function traffic_poller() {
                ajaxGet("/api/diagnostics/traffic/interface", {}, function(data, status) {
                    if (data.interfaces !== undefined) {
                        update_traffic_charts(g_charts['traffic'], data);
                    }
                });
                setTimeout(traffic_poller, g_charts['interval']);
            })();

            (function top_traffic_poller() {
                if ($("#interfaces").val().length > 0) {
                    ajaxGet('/api/diagnostics/traffic/top/' + $("#interfaces").val().join(","), {}, function(data, status){
                        if (status == 'success') {
                            update_top_charts(g_charts['traffic_top'], data);
                            updateTopTable(data);
                            top_traffic_poller();
                        } else {
                            setTimeout(top_traffic_poller, g_charts['interval']);
                        }
                    });
                }
            })();
        });

        function update_traffic_charts(charts, data) {
            charts.forEach(function(chart) {
                Object.keys(data.interfaces).forEach(function(intf) {
                    chart.config.data.datasets.forEach(function(dataset) {
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
                chart.update('quiet');
            });
        }

        function update_top_charts(charts, data) {
            charts.forEach(function(chart) {
                Object.keys(data).forEach(function(intf) {
                    chart.config.data.datasets.forEach(function(dataset) {
                        if (dataset.intf == intf) {
                            let calc_data = data[intf]['records'];
                            dataset.hidden = !$("#interfaces").val().includes(intf);
                            for (var i=0; i < data[intf]['records'].length ; ++i) {
                                dataset.data.push({
                                    x: Date.now(),
                                    y: data[intf]['records'][i]['rate_bits_' + dataset.src_field],
                                    r: 4,
                                    address: data[intf]['records'][i]['address']
                                });
                            }
                            return;
                        }
                    });
                });
                chart.update('quiet');
            });
        }

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

  .left {
    float: left;
  }

  .right {
    float: right;
  }

</style>

<ul class="nav nav-tabs" data-tabs="tabs" id="maintabs">
    <li class="active"><a data-toggle="tab" id="graph_tab" href="#graph">{{ lang._('Graph') }}</a></li>
    <li><a data-toggle="tab" id="gtid_tab" href="#toptalkers">{{ lang._('Top talkers') }}</a></li>
    <div class="pull-right">
        <div class="right">
            <select class="selectpicker" id="interfaces" multiple=multiple>
            </select>
            &nbsp;
        </div>
        <div class="left">
            <select class="selectpicker" id="intervals" data-width="auto">
                <option value="500">500 Milliseconds</option>
                <option value="1000">1 Second</option>
                <option value="2000">2 Seconds</option>
                <option value="5000">5 Seconds</option>
                <option value="10000">10 Seconds</option>
            </select>
            &nbsp;
        </div>
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
