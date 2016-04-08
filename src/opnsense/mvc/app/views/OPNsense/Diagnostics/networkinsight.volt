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

      function interface_totals(target, from_date, to_date, resolution) {
        var dfObj = new $.Deferred();

        fetch_params = from_date + '/' + to_date + '/' + resolution + '/if_in' ;
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

              chart.xAxis.tickFormat(function(d) {
                  if (resolution < 60) {
                      return d3.time.format('%b %e %H:%M:%S')(new Date(d));
                  } else if (resolution < 3600) {
                      return d3.time.format('%b %e %H:%M')(new Date(d));
                  } else if (resolution < 86400) {
                      return d3.time.format('%b %e %H h')(new Date(d));
                  } else {
                      return d3.time.format('%b %e')(new Date(d));
                  }

              });
              chart.yAxis.tickFormat(d3.format(',.2s'));

              d3.select("#" + target + " svg")
                  .datum(data)
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

      $('a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
          // remove all charts
          var svg = d3.select("svg");
          svg.selectAll("*").remove();
          pageCharts = [];
          // current time stamp
          var timestamp_now = Math.round((new Date()).getTime() / 1000);
          // load charts for selected tab
          if (e.target.id == 'current_tab'){
              interface_totals('chart_intf_2h', timestamp_now - (60*60*2), timestamp_now, 30).done(function(){
                interface_totals('chart_intf_8h', timestamp_now - (60*60*8), timestamp_now, 300);
              });
          } else if (e.target.id == 'history_tab'){
              interface_totals('chart_intf_1yr', timestamp_now -  (60*60*24*365), timestamp_now, 86400).done(function(){
                interface_totals('chart_intf_7d', timestamp_now - (60*60*24*7), timestamp_now, 3600);
              });

          }
      });

      $('a[data-toggle="tab"]').trigger('shown.bs.tab');
      $("#test").click(function(){
          $(".panel-body").show();
      });
    });
</script>

<ul class="nav nav-tabs" data-tabs="tabs" id="maintabs">
    <li class="active"><a data-toggle="tab" id="current_tab" href="#current">{{ lang._('Current') }}</a></li>
    <li><a data-toggle="tab" id="history_tab" href="#history">{{ lang._('History') }}</a></li>
</ul>
<div class="tab-content content-box tab-content" style="padding: 10px;">
    <div id="current" class="tab-pane fade in active">
      <br/>
      <div class="panel panel-primary">
        <div class="panel-heading panel-heading-sm">
          {{ lang._('Last 2 hours, 30 second average') }}
        </div>
        <div class="panel-body">
          <div id="chart_intf_2h">
            <svg style="height:300px;"></svg>
          </div>
        </div>
      </div>
      <div class="panel panel-primary">
        <div class="panel-heading panel-heading-sm">
          {{ lang._('Last 8 hours, 5 minute average') }}
        </div>
        <div class="panel-body">
          <div id="chart_intf_8h">
            <svg style="height:300px;"></svg>
          </div>
        </div>
      </div>
    </div>
    <div id="history" class="tab-pane fade in">
      <br/>
      <div class="panel panel-primary">
        <div class="panel-heading panel-heading-sm">
          {{ lang._('Last week, 1 hour average') }}
        </div>
        <div class="panel-body">
          <div id="chart_intf_7d">
            <svg style="height:300px;"></svg>
          </div>
        </div>
      </div>

      <div class="panel panel-primary">
        <div class="panel-heading panel-heading-sm">
          {{ lang._('Last year, 8 hour average') }}
        </div>
        <div class="panel-body">
          <div id="chart_intf_1yr">
            <svg style="height:300px;"></svg>
          </div>
        </div>
      </div>
    </div>
</div>
<button id="test" class='btn btn-primary'>test</button>
