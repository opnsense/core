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
<link rel="stylesheet" href="/ui/css/nv.d3.css">

<!-- d3 -->
<script type="text/javascript" src="/ui/js/d3.min.js"></script>

<!-- nvd3 -->
<script type="text/javascript" src="/ui/js/nv.d3.min.js"></script>

<script type="text/javascript">
    $( document ).ready(function() {
      var resizeEnd ;
      $(window).on('resize', function() {
          clearTimeout(resizeEnd);
          resizeEnd = setTimeout(function() {
              $(window).trigger('resize-end');
          }, 500);
      });

      // collect all chars for resize update
      var pageCharts = [];

      // human readable interface names
      var interface_names = [];

      function interface_totals(target, from_date, to_date, resolution, direction) {
        var dfObj = new $.Deferred();

        if (direction != 'in' && direction != 'out') {
            fetch_params = from_date + '/' + to_date + '/' + resolution + '/if' ;
        } else {
            fetch_params = from_date + '/' + to_date + '/' + resolution + '/if,direction' ;
        }
        ajaxGet('/api/diagnostics/networkinsight/timeserie/FlowInterfaceTotals/octets_ps/' + fetch_params,{},function(data,status){
            nv.addGraph(function() {
              var chart = nv.models.stackedAreaChart()
                  .x(function(d) { return d[0] })
                  .y(function(d) { return d[1] })
                  .useInteractiveGuideline(true)
                  .interactive(true)
                  .showControls(true)
                  .clipEdge(true)
                  ;

              if (resolution < 60) {
                  chart.xAxis.tickSize(8).tickFormat(function(d) {
                    return d3.time.format('%b %e %H:%M:%S')(new Date(d));
                  });
              } else if (resolution < 3600) {
                  chart.xAxis.tickSize(16).tickFormat(function(d) {
                    return d3.time.format('%b %e %H:%M')(new Date(d));
                  });
              } else if (resolution < 86400) {
                  chart.xAxis.tickSize(16).tickFormat(function(d) {
                    return d3.time.format('%b %e %H h')(new Date(d));
                  });
              } else {
                  chart.xAxis.tickFormat(function(d) {
                    return d3.time.format('%b %e')(new Date(d));
                  });
              }
              chart.yAxis.tickFormat(d3.format(',.2s'));

              chart_data = [];
              data.map(function(item){
                  if (direction != undefined) {
                      item_dir = item.key.split(',').pop();
                      item_intf = item.key.split(',')[0];
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
                  } else {
                      if (item.key != '0' && item.key != 'lo0' ) {
                          chart_data.push(item);
                      }
                  }
              });

              chart_data.sort(function(a, b) {
                  return a.key > b.key;
              });

              d3.select("#" + target + " svg")
                  .datum(chart_data)
                  .call(chart);

              pageCharts.push(chart);
              return chart;
            }, function(){
              // wait some time before resolving promise, easier for some browsers to handle the amount of data
              setTimeout(function() {
                dfObj.resolve();
              }, 1000);
            });

        });
        return dfObj;
      }
      $(window).on('resize-end', function() {
          pageCharts.forEach(function(chart) {
              chart.update();
          });
      });

      // change time select
      $("#total_time_select").change(function(){
        // current time stamp
        var timestamp_now = Math.round((new Date()).getTime() / 1000);
        var duration = 0;
        var resolution = 0;
        switch ($(this).val()) {
            case "2h":
              duration = 60*60*2;
              resolution = 30;
              break;
            case "8h":
              duration = 60*60*8;
              resolution = 300;
              break;
            case "1w":
              duration = 60*60*24*7;
              resolution = 3600;
              break;
            case "1y":
              duration = 60*60*24*365;
              resolution = 86400;
              break;
        }
        if (resolution != 0) {
            // remove all charts
            var svg = d3.select("svg");
            svg.selectAll("*").remove();
            pageCharts = [];
            // fetch interface names
            ajaxGet('/api/diagnostics/networkinsight/getInterfaces',{},function(intf_names,status){
                interface_names = intf_names;
                interface_totals('chart_intf_in', timestamp_now - duration, timestamp_now, resolution, 'in');
                interface_totals('chart_intf_out', timestamp_now - duration, timestamp_now, resolution, 'out');
            });
        }
      });

      $('a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
          // load charts for selected tab
          if (e.target.id == 'totals_tab'){
              $("#total_time_select").change();
          } else if (e.target.id == 'history_tab'){
          }
      });

      // trigger initial tab load
      $("#total_time_select").change();

    });
</script>

<ul class="nav nav-tabs" data-tabs="tabs" id="maintabs">
    <li class="active"><a data-toggle="tab" id="totals_tab" href="#totals">{{ lang._('Totals') }}</a></li>
    <li><a data-toggle="tab" id="history_tab" href="#history">{{ lang._('History') }}</a></li>
</ul>
<div class="tab-content content-box tab-content" style="padding: 10px;">
    <div id="totals" class="tab-pane fade in active">
      <div class="pull-right">
        <select class="selectpicker" id="total_time_select">
          <option value="2h">{{ lang._('Last 2 hours, 30 second average') }}</option>
          <option value="8h">{{ lang._('Last 8 hours, 5 minute average') }}</option>
          <option value="1w"> {{ lang._('Last week, 1 hour average') }}</option>
          <option value="1y"> {{ lang._('Last year, 24 hour average') }}</option>
        </select>
      </div>
      <br/>
      <br/>
      <div class="panel panel-primary">
        <div class="panel-heading panel-heading-sm">
          {{ lang._('Interface totals') }}
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
          {{ lang._('') }}
        </div>
        <div class="panel-body">
        </div>
      </div>
    </div>
    <div id="history" class="tab-pane fade in">
      <br/>

    </div>
</div>
