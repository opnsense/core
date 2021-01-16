{#

OPNsense® is Copyright © 2016 by Deciso B.V.
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

<style type="text/css">
.panel-heading-sm{
    height: 28px;
    padding: 4px 10px;
}
</style>

<!-- nvd3 -->
<link rel="stylesheet" type="text/css" href="{{ cache_safe(theme_file_or_default('/css/nv.d3.css', ui_theme|default('opnsense'))) }}" />

<!-- d3 -->
<script src="{{ cache_safe('/ui/js/d3.min.js') }}"></script>

<!-- nvd3 -->
<script src="{{ cache_safe('/ui/js/nv.d3.min.js') }}"></script>

<script>
    $( document ).ready(function() {
      var resizeEnd ;
      $(window).on('resize', function() {
          clearTimeout(resizeEnd);
          resizeEnd = setTimeout(function() {
              $(window).trigger('resize-end');
          }, 500);
      });

      // collect all chars for resize update
      var pageCharts = {};

      // form metadata definitions
      var interface_names = [];
      var service_names = [];
      var protocol_names = [];

      /**
       * load shared metadata (interfaces, protocols, )
       */
      function get_metadata()
      {
          var dfObj = new $.Deferred();
          ajaxGet('/api/diagnostics/netflow/isEnabled', {}, function(is_enabled, status){
              if (is_enabled['local'] == 0) {
                  dfObj.reject();
                  return;
              }
              // fetch interface names
              ajaxGet('/api/diagnostics/networkinsight/getInterfaces',{}, function(intf_names, status){
                  interface_names = intf_names;
                  // fetch protocol names
                  ajaxGet('/api/diagnostics/networkinsight/getProtocols',{}, function(protocols, status) {
                      protocol_names = protocols;
                      // fetch service names
                      ajaxGet('/api/diagnostics/networkinsight/getServices',{}, function(services, status) {
                          service_names = services;
                          // return promise, no need to wait for getMetadata
                          dfObj.resolve();
                          // fetch aggregators
                          ajaxGet('/api/diagnostics/networkinsight/getMetadata',{}, function(metadata, status) {
                            Object.keys(metadata['aggregators']).forEach(function (agg_name) {
                              var res = metadata['aggregators'][agg_name]['resolutions'].join(',');
                              $("#export_collection").append($("<option data-resolutions='"+res+"'/>").val(agg_name).text(agg_name));
                            });
                            $("#export_collection").change(function(){
                                $("#export_resolution").html("");
                                var resolutions = String($(this).find('option:selected').data('resolutions'));
                                resolutions.split(',').map(function(item) {
                                    $("#export_resolution").append($("<option/>").val(item).text(item));
                                });
                                $("#export_resolution").selectpicker('refresh');
                            });
                            $("#export_collection").change();
                            $("#export_collection").selectpicker('refresh');
                          });
                      });
                  });
              });
          });

          return dfObj;
      }

      /**
       * delete all graps accounted in pageCharts
       */
      function delete_all_charts()
      {
          var svg = d3.select("svg");
          svg.selectAll("*").remove();
          pageCharts = {};
      }

      /**
       * get selected time
       */
      function get_time_select()
      {
          // current time stamp
          var timestamp_now = Math.round((new Date()).getTime() / 1000);
          var duration = 0;
          var resolution = 0;
          switch ($("#total_time_select").val()) {
              case "2h":
                duration = 60*60*2;
                resolution = 30;
                break;
              case "8h":
                duration = 60*60*8;
                resolution = 300;
                break;
              case "24h":
                duration = 60*60*24;
                resolution = 300;
                break;
              case "7d":
                duration = 60*60*24*7;
                resolution = 3600;
                break;
              case "14d":
                duration = 60*60*24*14;
                resolution = 3600;
                break;
              case "30d":
                duration = 60*60*24*30;
                resolution = 86400;
                break;
              case "60d":
                duration = 60*60*24*60;
                resolution = 86400;
                break;
              case "90d":
                duration = 60*60*24*90;
                resolution = 86400;
                break;
              case "182d":
                duration = 60*60*24*182;
                resolution = 86400;
                break;
              case "1y":
                duration = 60*60*24*365;
                resolution = 86400;
                break;
          }
          // always round from timestamp to nearest hour
          const from_timestamp =  Math.floor((timestamp_now -duration) / 3600 ) * 3600;
          return {resolution: resolution, from: from_timestamp, to: timestamp_now};
      }

      /**
       * draw interface totals
       */
      function chart_interface_totals() {
        var selected_time = get_time_select();
        const fetch_params = selected_time.from + '/' + selected_time.to + '/' + selected_time.resolution + '/if,direction' ;
        ajaxGet('/api/diagnostics/networkinsight/timeserie/FlowInterfaceTotals/bps/' + fetch_params,{},function(data,status){
            $.each(['chart_intf_in', 'chart_intf_out'], function(idx, target) {
                let direction = '';
                if (target == 'chart_intf_in') {
                    direction = 'in';
                } else {
                    direction = 'out';
                }
                nv.addGraph(function() {
                  let chart = nv.models.stackedAreaChart()
                      .x(function(d) { return d[0] })
                      .y(function(d) { return d[1] })
                      .useInteractiveGuideline(true)
                      .interactive(true)
                      .showControls(true)
                      .clipEdge(true);

                  if (selected_time.resolution <= 300) {
                      chart.xAxis.tickSize(8).tickFormat(function(d) {
                        return d3.time.format('%H:%M:%S')(new Date(d));
                      });
                  } else if (selected_time.resolution < 3600) {
                      chart.xAxis.tickSize(8).tickFormat(function(d) {
                        return d3.time.format('%b %e %H:%M')(new Date(d));
                      });
                  } else if (selected_time.resolution < 86400) {
                      chart.xAxis.tickSize(8).tickFormat(function(d) {
                        return d3.time.format('%b %e %H h')(new Date(d));
                      });
                  } else {
                      chart.xAxis.tickFormat(function(d) {
                        return d3.time.format('%b %e')(new Date(d));
                      });
                  }
                  chart.yAxis.tickFormat(d3.format(',.2s'));

                  let chart_data = [];
                  data.map(function(item){
                      let item_dir = item.key.split(',').pop();
                      let item_intf = item.key.split(',')[0];
                      if (item_intf != '0' && item_intf != 'lo0' ) {
                          if (direction == item_dir) {
                              if (interface_names[item_intf] != undefined) {
                                  item.key = interface_names[item_intf];
                              } else {
                                  item.key = item_intf;
                              }
                              chart_data.push(item);
                          }
                      }
                  });

                  chart_data.sort(function(a, b) {
                      return a.key > b.key;
                  });

                  d3.select("#" + target + " svg").datum(chart_data).call(chart);

                  pageCharts[target] = chart;
                  return chart;
                });
              });
          });
      }

      /**
       * graph top usage per destination port for selected time period
       */
      function chart_top_dst_port_usage()
      {
        // remove existing chart
        if (pageCharts["chart_top_ports"] != undefined) {
            var svg = d3.select("#chart_top_ports > svg");
            svg.selectAll("*").remove();
        }
        var selected_time = get_time_select();
        var time_url = selected_time.from + '/' + selected_time.to;
        ajaxGet('/api/diagnostics/networkinsight/top/FlowDstPortTotals/'+time_url+'/dst_port,protocol/octets/25/',
            {'filter_field': 'if', 'filter_value': $('#interface_select').val()}, function(data, status){
            if (status == 'success'){
              nv.addGraph(function() {
                var chart = nv.models.pieChart()
                    .x(function(d) { return d.label })
                    .y(function(d) { return d.value })
                    .showLabels(true)
                    .labelThreshold(.05)
                    .labelType("percent")
                    .donut(true)
                    .donutRatio(0.35)
                    .legendPosition("right")
                    .valueFormat(d3.format(',.2s'));

                let chart_data = [];
                data.map(function(item){
                    var label = "(other)";
                    var proto = "";
                    if (item.protocol != "") {
                        if (item.protocol in protocol_names) {
                            proto = ' (' + protocol_names[item.protocol] + ')';
                        }
                        if (item.dst_port in service_names) {
                            label = service_names[item.dst_port];
                        } else {
                            label = item.dst_port
                        }
                    }
                    chart_data.push({'label': label + proto, 'value': item.total});
                });

                var diag = d3.select("#chart_top_ports svg")
                    .datum(chart_data)
                    .transition().duration(350)
                    .call(chart);
                pageCharts["chart_top_ports"] = chart;
                pageCharts["chart_top_ports"].data = data;

                // copy selection to detail page and query results
                chart.pie.dispatch.on('elementClick', function(e){
                    var data = pageCharts["chart_top_ports"].data;
                    if (data[e.index].dst_port != "") {
                        $("#interface_select_detail").val($("#interface_select").val());
                        $('#interface_select_detail').selectpicker('refresh');
                        $("#service_port_detail").val(data[e.index].dst_port);
                        $("#src_address_detail").val("");
                        $("#dst_address_detail").val("");
                        $("#details_tab").click();
                        grid_details();
                    }
                });

                return chart;
              });
            }
        });
      }

      /**
       * graph top usage per destination port for selected time period
       */
      function chart_top_src_addr_usage()
      {
        // remove existing chart
        if (pageCharts["chart_top_sources"] != undefined) {
            var svg = d3.select("#chart_top_sources > svg");
            svg.selectAll("*").remove();
        }
        var selected_time = get_time_select();
        var time_url = selected_time.from + '/' + selected_time.to;
        ajaxGet('/api/diagnostics/networkinsight/top/FlowSourceAddrTotals/'+time_url+'/src_addr/octets/25/',
            {'filter_field': 'if', 'filter_value': $('#interface_select').val()}, function(data, status){
            if (status == 'success'){
              let add_src_pie = function(chart_data_in) {
                  nv.addGraph(function() {
                    var chart = nv.models.pieChart()
                        .x(function(d) { return d.label })
                        .y(function(d) { return d.value })
                        .showLabels(true)
                        .labelThreshold(.05)
                        .labelType("percent")
                        .donut(true)
                        .donutRatio(0.35)
                        .legendPosition("right")
                        .valueFormat(d3.format(',.2s'));

                    let chart_data = [];
                    chart_data_in.map(function(item){
                        var label = "(other)";
                        if (item.hostname !== undefined) {
                            label = item.hostname;
                        } else if (item.src_addr != "") {
                            label = item.src_addr;
                        }
                        chart_data.push({'label': label, 'value': item.total});
                    });

                    d3.select("#chart_top_sources svg")
                        .datum(chart_data)
                        .transition().duration(350)
                        .call(chart);
                    pageCharts["chart_top_sources"] = chart;
                    pageCharts["chart_top_sources"].data = chart_data_in;

                    // copy selection to detail tab and query results
                    chart.pie.dispatch.on('elementClick', function(e){
                        var data = pageCharts["chart_top_sources"].data;
                        if (data[e.index].src_addr != "") {
                            $("#interface_select_detail").val($("#interface_select").val());
                            $('#interface_select_detail').selectpicker('refresh');
                            $("#service_port_detail").val("");
                            $("#dst_address_detail").val("");
                            $("#src_address_detail").val(data[e.index].src_addr);
                            $("#details_tab").click();
                            grid_details();
                        }
                    });
                    chart.legend.margin({top: 0, right: 0, left: 0, bottom: 20});
                    return chart;
                  });
              };
              if ($("#reverse_lookup").is(':checked')) {
                  var addresses = [];
                  data.map(function(item){
                      if (item.src_addr != "") {
                          addresses.push(item.src_addr);
                      }
                  });
                  // use full width when names are resolved
                  $("#chart_top_sources,#chart_top_ports").parent().removeClass('col-sm-6');
                  $("#chart_top_sources,#chart_top_ports").parent().addClass('col-sm-12');
                  ajaxGet('/api/diagnostics/dns/reverse_lookup', {'address': addresses}, function(lookup_data, status) {
                      data.map(function(item){
                          if (lookup_data[item.src_addr] != undefined) {
                              item.src_addr = item.src_addr;
                              item.hostname = lookup_data[item.src_addr];
                          }
                      });
                      add_src_pie(data);
                      $(window).trigger('resize-end'); // force chart update
                  });
              } else {
                  // split charts, half for ports half for sources
                  $("#chart_top_sources,#chart_top_ports").parent().removeClass('col-sm-12');
                  $("#chart_top_sources,#chart_top_ports").parent().addClass('col-sm-6');
                  add_src_pie(data);
                  $(window).trigger('resize-end'); // force chart update
              }
            }
        });
      }

      /**
       * total traffic for selected interface and time period
       */
      function grid_totals()
      {
        var fileSizeTypes = ["", "K", "M", "G", "T", "P", "E", "Z", "Y"];
        var measures = ['octets', 'packets'];
        var selected_time = get_time_select();
        var time_url = selected_time.from + '/' + selected_time.to;
        measures.map(function(measure){
          ajaxGet('/api/diagnostics/networkinsight/top/FlowInterfaceTotals/'+time_url+'/direction/'+measure+'/25/',
              {'filter_field': 'if', 'filter_value': $('#interface_select').val()}, function(data, status){
                var total_in = 0;
                var total_out = 0;
                var total = 0;
                if (measure == 'octets') {
                    var kb = 1024; // 1 KB = 1024 bytes
                } else {
                    var kb = 1000; // 1 K = 1000 (packets)
                }

                data.map(function(item) {
                    var ndx = Math.floor( Math.log(item.total) / Math.log(kb) );
                    var formated_total =  (item.total / Math.pow(kb, ndx)).toFixed(2) + ' ' + fileSizeTypes[ndx];
                    if (item.direction == "in") {
                        total_in = formated_total;
                    } else {
                        total_out = formated_total;
                    }
                    total += item.total;
                });
                if (total > 0) {
                    var ndx = Math.floor( Math.log(total) / Math.log(kb) );
                    total =  (total / Math.pow(kb, ndx)).toFixed(2) + ' ' + fileSizeTypes[ndx];
                }
                $("#total_interface_"+measure+" > td:eq(1)").html(total_in);
                $("#total_interface_"+measure+" > td:eq(2)").html(total_out);
                $("#total_interface_"+measure+" > td:eq(3)").html(total);
          });
        });
      }

      /**
       * collect netflow details
       */
      function grid_details()
      {
        var filters = {'filter_field': [], 'filter_value': []};
        if ($("#interface_select_detail").val() != "") {
            filters['filter_field'].push('if');
            filters['filter_value'].push($("#interface_select_detail").val());
        }
        if ($("#service_port_detail").val() != "") {
            filters['filter_field'].push('service_port');
            filters['filter_value'].push($("#service_port_detail").val());
        }
        if ($("#src_address_detail").val() != "") {
            filters['filter_field'].push('src_addr');
            filters['filter_value'].push($("#src_address_detail").val());
        }
        if ($("#dst_address_detail").val() != "") {
            filters['filter_field'].push('dst_addr');
            filters['filter_value'].push($("#dst_address_detail").val());
        }

        var time_url = $("#date_detail_from").val() + '/' +  $("#date_detail_to").val();
        ajaxGet('/api/diagnostics/networkinsight/top/FlowSourceAddrDetails/'+time_url+'/service_port,protocol,if,src_addr,dst_addr/octets/100/',
            {'filter_field': filters['filter_field'].join(','), 'filter_value': filters['filter_value'].join(',')}, function(data, status){
            if (status == 'success'){
                let html = [];
                // count total traffic
                let grand_total = 0;
                data.map(function(item){
                    grand_total += item['total'];
                });
                // dump  rows
                data.map(function(item){
                  let proto = '';
                  if (item.protocol in protocol_names) {
                      proto = ' (' + protocol_names[item.protocol] + ')';
                  }
                  let service_port;
                  if (item.service_port in service_names) {
                      service_port = service_names[item.service_port];
                  } else {
                      service_port = item.service_port
                  }
                  let tr_str = '<tr>';
                  if (service_port != "") {
                      tr_str += '<td> <span data-toggle="tooltip" title="'+proto+'/'+item.service_port+'">'+service_port+' </span> '+proto+'</td>';
                  } else {
                      tr_str += "<td>{{ lang._('(other)') }}</td>";
                  }
                  tr_str += '<td>' + item['src_addr'] + '</td>';
                  tr_str += '<td>' + item['dst_addr'] + '</td>';
                  tr_str += '<td>' + byteFormat(item['total']) + ' ' + '</td>';
                  if (item['last_seen'] != "") {
                      tr_str += '<td>' + d3.time.format('%b %e %H:%M:%S')(new Date(item['last_seen']*1000)) + '</td>';
                  } else {
                      tr_str += '<td></td>'
                  }


                  let percentage = parseInt((item['total'] /grand_total) * 100);
                  let perc_text = ((item['total'] /grand_total) * 100).toFixed(2);
                  tr_str += '<td>';
                  tr_str += '<div class="progress-bar progress-bar-warning progress-bar-striped" role="progressbar" ';
                  tr_str += 'aria-valuenow="'+percentage+'" aria-valuemin="0" aria-valuemax="100" style="color: black; min-width: 2em; width:' ;
                  tr_str += percentage+'%;">'+perc_text+'&nbsp;%</div>';
                  tr_str += '</td>';
                  tr_str += '</tr>';
                  html.push(tr_str);
                });
                $("#netflow_details > tbody").html(html.join(''));
                if (grand_total > 0) {
                    $("#netflow_details_total").html(byteFormat(grand_total));
                } else {
                    $("#netflow_details_total").html("");
                }

                // link tooltips
                $('[data-toggle="tooltip"]').tooltip();
            }
        });
      }

      /**
       * export detailed data (generate download link and click)
       */
      function export_flow_data()
      {
          let time_url = $("#export_date_from").val() + '/' +  $("#export_date_to").val();
          let url = '/api/diagnostics/networkinsight/export/'+$("#export_collection").val()+'/'+time_url+'/'+$("#export_resolution").val();
          let link = document.createElement("a");
          $(link).click(function(e) {
              e.preventDefault();
              window.location.href = url;
          });
          $(link).click();
      }

      // hide heading
      $(".page-content-head").addClass("hidden");

      // event page resize
      $(window).on('resize-end', function() {
          $.each(pageCharts, function(idx, chart) {
            chart.update();
          });
      });

      // event change time select
      $("#total_time_select").change(function(){
          delete_all_charts();
          chart_interface_totals();
          chart_top_dst_port_usage();
          chart_top_src_addr_usage();
          grid_totals();
      });

      // event change interface selection
      $('#interface_select').change(function(){
          chart_top_dst_port_usage();
          chart_top_src_addr_usage();
          grid_totals();
      });

      $("#reverse_lookup").change(function(){
          chart_top_src_addr_usage();
      });

      $('a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
          // load charts for selected tab
          if (e.target.id == 'totals_tab'){
              $("#total_time_select").change();
          } else if (e.target.id == 'details_tab'){
          }
      });
      // detail page, search on <enter>
      $("#service_port_detail, #src_address_detail, #dst_address_detail").keypress(function (e) {
          if (e.which == 13) {
              grid_details();
          }
      });


      // trigger initial tab load
      get_metadata().done(function(){
          // known interfaces
          for (var key in interface_names) {
              $('#interface_select').append($("<option></option>").attr("value",key).text(interface_names[key]));
              $('#interface_select_detail').append($("<option></option>").attr("value",key).text(interface_names[key]));
          }
          $('#interface_select').selectpicker('refresh');
          $('#interface_select_detail').selectpicker('refresh');

          // generate date selection (utc start, end times)
          var now = new Date;
          var date_begin = Date.UTC(now.getUTCFullYear(),now.getUTCMonth(), now.getUTCDate(), 0, 0, 0, 0);
          var date_end  = Date.UTC(now.getUTCFullYear(),now.getUTCMonth(), now.getUTCDate(), 23, 59, 59, 0);
          var tmp_date = new Date();
          for (let i=0; i < 62; i++) {
              let from_date_ts = (date_begin - (24*60*60*1000 * i)) / 1000;
              let to_date_ts = parseInt((date_end - (24*60*60*1000 * i)) / 1000);
              tmp_date = new Date(from_date_ts*1000);
              let tmp = tmp_date.toISOString().substr(0, 10);
              if (i < 62) {
                  $("#date_detail_from").append($("<option/>").val(from_date_ts).text(tmp));
                  $("#date_detail_to").append($("<option/>").val(to_date_ts).text(tmp));
              }
              $("#export_date_from").append($("<option/>").val(from_date_ts).text(tmp));
              $("#export_date_to").append($("<option/>").val(to_date_ts).text(tmp));

          }

          $("#date_detail_from").selectpicker('refresh');
          $("#date_detail_to").selectpicker('refresh');
          $("#date_detail_from").change(function(){
              // change to date on change from date.
              if ($("#date_detail_to").prop('selectedIndex') > $("#date_detail_from").prop('selectedIndex')) {
                  $("#date_detail_to").prop('selectedIndex', $("#date_detail_from").prop('selectedIndex'));
                  $("#date_detail_to").selectpicker('refresh');
              }
          });
          $("#export_date_from").selectpicker('refresh');
          $("#export_date_to").selectpicker('refresh');

          chart_interface_totals();
          chart_top_dst_port_usage();
          chart_top_src_addr_usage();
          grid_totals();
      }).fail(function(){
          // netflow / local collection not active.
          $("#info_tab").show();
          $("#info_tab").click();
      });


      $("#refresh_details").click(grid_details);
      $("#export_btn").click(export_flow_data);
    });
</script>

<ul class="nav nav-tabs" data-tabs="tabs" id="maintabs">
    <li><a data-toggle="tab" id="info_tab" style="display:none;" href="#info">{{ lang._('Info') }}</a></li>
    <li class="active"><a data-toggle="tab" id="totals_tab" href="#totals">{{ lang._('Totals') }}</a></li>
    <li><a data-toggle="tab" id="details_tab" href="#details">{{ lang._('Details') }}</a></li>
    <li><a data-toggle="tab" id="export_tab" href="#export">{{ lang._('Export') }}</a></li>
</ul>
<div class="tab-content content-box" style="padding: 10px;">
    <div id="info" class="tab-pane fade in">
      <br/>
      <div class="alert alert-warning" role="alert">
        {{ lang._('Local data collection is not enabled at the moment, please configure netflow first') }}
        <br/>
        <a href="/ui/diagnostics/netflow/">{{ lang._('Go to netflow configuration') }} </a>
      </div>
    </div>
    <div id="totals" class="tab-pane fade in active">
      <div class="pull-right">
        <select class="selectpicker" id="total_time_select">
          <option value="2h">{{ lang._('Last 2 hours, 30 second average') }}</option>
          <option value="8h">{{ lang._('Last 8 hours, 5 minute average') }}</option>
          <option value="24h">{{ lang._('Last 24 hours, 5 minute average') }}</option>
          <option value="7d">{{ lang._('7 days, 1 hour average') }}</option>
          <option value="14d">{{ lang._('14 days, 1 hour average') }}</option>
          <option value="30d">{{ lang._('30 days, 24 hour average') }}</option>
          <option value="60d">{{ lang._('60 days, 24 hour average') }}</option>
          <option value="90d">{{ lang._('90 days, 24 hour average') }}</option>
          <option value="182d">{{ lang._('182 days, 24 hour average') }}</option>
          <option value="1y">{{ lang._('Last year, 24 hour average') }}</option>
        </select>
      </div>
      <br/>
      <br/>
      <div class="panel panel-primary">
        <div class="panel-heading panel-heading-sm">
          {{ lang._('Interface totals (bits/sec)') }}
        </div>
        <div class="panel-body">
          <div id="chart_intf_in">
            <small>{{ lang._('IN') }}</small>
            <svg style="height:150px;"></svg>
          </div>
          <div id="chart_intf_out">
            <small>{{ lang._('OUT') }}</small>
            <svg style="height:150px;"></svg>
          </div>
        </div>
      </div>
      <div class="panel panel-primary">
        <div class="panel-heading panel-heading-sm">
          {{ lang._('Top usage ports / sources (bytes)') }}
        </div>
        <div class="panel-body">
          <div class="row">
            <div class="col-xs-12">
              <select class="selectpicker" id="interface_select">
              </select>
              <div class="checkbox-inline pull-right">
                <label>
                  <input id="reverse_lookup" type="checkbox">
                  <span class="fa fa-search"></span> {{ lang._('Reverse lookup') }}
                </label>
              </div>
            </div>
            <div class="col-xs-12 col-sm-6">
              <div id="chart_top_ports">
                <svg style="height:300px;"></svg>
              </div>
            </div>
            <div class="col-xs-12 col-sm-6">
              <div id="chart_top_sources">
                <svg style="height:300px;"></svg>
              </div>
            </div>
          </div>
          <div class="col-xs-12">
            <small>{{ lang._('click on pie for details') }}</small>
          </div>
          <hr/>
          <div class="col-sm-6 col-xs-12">
            <table class="table table-condensed">
              <thead>
                <tr>
                  <th></th>
                  <th>{{ lang._('In') }}</th>
                  <th>{{ lang._('Out') }}</th>
                  <th>{{ lang._('Total') }}</th>
                </tr>
              </thead>
              <tbody>
                <tr id="total_interface_packets">
                  <td>{{ lang._('Packets') }}</td>
                  <td></td>
                  <td></td>
                  <td></td>
                </tr>
                <tr id="total_interface_octets">
                  <td>{{ lang._('Bytes') }}</td>
                  <td></td>
                  <td></td>
                  <td></td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
    <div id="details" class="tab-pane fade in">
      <table class="table table-condensed">
        <thead>
          <tr>
            <th>{{ lang._('Date from') }}</th>
            <th>{{ lang._('Date to') }}</th>
            <th>{{ lang._('Interface') }}</th>
            <th>{{ lang._('(dst) Port') }}</th>
            <th>{{ lang._('(dst) Address') }}</th>
            <th>{{ lang._('(src) Address') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td>
              <select class="selectpicker" id="date_detail_from"  data-live-search="true" data-size="10" data-width="120px"></select>
            </td>
            <td>
              <select class="selectpicker" id="date_detail_to"  data-live-search="true" data-size="10" data-width="120px"></select>
            </td>
            <td>
              <select class="selectpicker" id="interface_select_detail" data-width="150px"></select>
            </td>
            <td><input type="text" id="service_port_detail" style="width:80px;"></td>
            <td><input type="text" id="dst_address_detail"></td>
            <td><input type="text" id="src_address_detail"></td>
            <td><span id="refresh_details" class="btn btn-default"><i class="fa fa-refresh"></i></span></td>
          </tr>
        </tbody>
      </table>
      <br/>
      <table class="table table-condensed table-striped" id="netflow_details">
        <thead>
          <tr>
            <th>{{ lang._('Service') }}</th>
            <th>{{ lang._('Source') }}</th>
            <th>{{ lang._('Destination') }}</th>
            <th>{{ lang._('Bytes') }}</th>
            <th>{{ lang._('Last seen') }}</th>
            <th>%</th>
          </tr>
        </thead>
        <tbody>
        </tbody>
        <tfoot>
          <tr>
            <td colspan="3">{{ lang._('Total (selection)') }}</td>
            <td id="netflow_details_total"></td>
          </tr>
        </tfoot>
      </table>
    </div>
    <div id="export" class="tab-pane fade in">
      <br/>
      <table class="table table-condensed table-striped">
        <thead>
          <tr>
            <th>{{ lang._('Attribute') }}</th>
            <th>{{ lang._('Value') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td>{{ lang._('Collection') }}</td>
            <td>
              <select class="selectpicker" id="export_collection">
              </select>
            </td>
          </tr>
          <tr>
            <td>{{ lang._('Resolution (seconds)') }}</td>
            <td>
              <select class="selectpicker" id="export_resolution">
              </select>
            </td>
          </tr>
          <tr>
            <td>{{ lang._('From date') }}</td>
            <td>
              <select class="selectpicker" id="export_date_from"  data-live-search="true" data-size="10"></select>
            </td>
          </tr>
          <tr>
            <td>{{ lang._('To date') }}</td>
            <td>
              <select class="selectpicker" id="export_date_to"  data-live-search="true" data-size="10"></select>
            </td>
          </tr>
          <tr>
            <td></td>
            <td>
              <button id="export_btn" class="btn btn-default btn-xs"><i class="fa fa-cloud-download"></i> {{ lang._('Export')}}</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
</div>
