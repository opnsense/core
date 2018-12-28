<?php

/*
    Copyright (C) 2014-2016 Deciso B.V.
    Copyright (C) 2007 Scott Dale
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

?>

<script>
  var traffic_graph_widget_data = [];
  var traffic_graph_widget_chart_in = null;
  var traffic_graph_widget_chart_data_in = null;
  var traffic_graph_widget_chart_out = null;
  var traffic_graph_widget_chart_data_out = null;

  function traffic_widget_update(sender, data, max_measures)
  {
      if (max_measures == undefined) {
          max_measures = 100;
      }
      // push new measurement, keep a maximum of max_measures measures in
      traffic_graph_widget_data.push(data);
      if (traffic_graph_widget_data.length > max_measures) {
          traffic_graph_widget_data.shift();
      } else if (traffic_graph_widget_data.length == 1) {
          traffic_graph_widget_data.push(data);
      }

      let chart_data_in = [];
      let chart_data_out = [];
      let chart_data_keys = {};
      for (var i=traffic_graph_widget_data.length-1 ; i > 0 ; --i) {
          var elapsed_time = traffic_graph_widget_data[i]['time'] - traffic_graph_widget_data[i-1]['time'];
          for (var key in traffic_graph_widget_data[i]['interfaces']) {
              var intf_item = traffic_graph_widget_data[i]['interfaces'][key];
              var prev_intf_item = traffic_graph_widget_data[i-1]['interfaces'][key];
              if (chart_data_keys[key] == undefined && intf_item['name'] != undefined) {
                  // only show configured interfaces
                  chart_data_keys[key] = chart_data_in.length;
                  chart_data_in[chart_data_in.length] = {'key': intf_item['name'], 'values': []};
                  chart_data_out[chart_data_out.length] = {'key': intf_item['name'], 'values': []};
              }
              if (chart_data_keys[key] != undefined) {
                  let bps_in, bps_out;
                  if (elapsed_time > 0) {
                      bps_in = ((parseInt(intf_item['bytes received']) - parseInt(prev_intf_item['bytes received']))/elapsed_time)*8;
                      bps_out = ((parseInt(intf_item['bytes transmitted']) - parseInt(prev_intf_item['bytes transmitted']))/elapsed_time)*8;
                  } else {
                      bps_in = 0;
                      bps_out = 0;
                  }
                  chart_data_in[chart_data_keys[key]]['values'].push([traffic_graph_widget_data[i]['time']*1000, bps_in])
                  chart_data_out[chart_data_keys[key]]['values'].push([traffic_graph_widget_data[i]['time']*1000, bps_out])
              }
          }
      }
      // get selections
      var deselected_series_in = [];
      var deselected_series_out = [];
      d3.select("#traffic_graph_widget_chart_in").selectAll(".nv-series").each(function(d, i) {
          if (d.disabled) {
              deselected_series_in.push(d.key);
          }
      });
      d3.select("#traffic_graph_widget_chart_out").selectAll(".nv-series").each(function(d, i) {
          if (d.disabled) {
              deselected_series_out.push(d.key);
          }
      });

      // load data
      traffic_graph_widget_chart_data_in.datum(chart_data_in).transition().duration(500).call(traffic_graph_widget_chart_in);
      if (traffic_graph_widget_chart_data_out !== null) {
          traffic_graph_widget_chart_data_out.datum(chart_data_out).transition().duration(500).call(traffic_graph_widget_chart_out);
      }

      // set selection
      d3.selectAll("#traffic_graph_widget_chart_in").selectAll(".nv-series").each(function(d, i) {
          if (deselected_series_in.indexOf(d.key) > -1) {
              d3.select(this).on("click").apply(this, [d, i]);
          }
      });
      d3.selectAll("#traffic_graph_widget_chart_out").selectAll(".nv-series").each(function(d, i) {
          if (deselected_series_out.indexOf(d.key) > -1) {
              d3.select(this).on("click").apply(this, [d, i]);
          }
      });
  }
  /**
   * page setup
   */
  $(window).load(function() {
      // draw traffic in graph
      nv.addGraph(function() {
          traffic_graph_widget_chart_in = nv.models.lineChart()
              .x(function(d) { return d[0] })
              .y(function(d) { return d[1] })
              .useInteractiveGuideline(false)
              .interactive(true)
              .showLegend(true)
              .showXAxis(false)
              .clipEdge(true)
              .margin({top:5,right:5,bottom:5,left:50})
              ;
          traffic_graph_widget_chart_in.yAxis.tickFormat(d3.format(',.2s'));
          traffic_graph_widget_chart_in.xAxis.tickFormat(function(d) {
              return d3.time.format('%b %e %H:%M:%S')(new Date(d));
          });

          traffic_graph_widget_chart_data_in = d3.select("#traffic_graph_widget_chart_in svg").datum([{'key':'', 'values':[[0, 0]]}]);
          traffic_graph_widget_chart_data_in.transition().duration(500).call(traffic_graph_widget_chart_in);
      });
      // draw traffic out graph
      nv.addGraph(function() {
          traffic_graph_widget_chart_out = nv.models.lineChart()
              .x(function(d) { return d[0] })
              .y(function(d) { return d[1] })
              .useInteractiveGuideline(false)
              .interactive(true)
              .showLegend(true)
              .showXAxis(false)
              .clipEdge(true)
              .margin({top:5,right:5,bottom:5,left:50})
              ;
          traffic_graph_widget_chart_out.yAxis.tickFormat(d3.format(',.2s'));
          traffic_graph_widget_chart_out.xAxis.tickFormat(function(d) {
              return d3.time.format('%b %e %H:%M:%S')(new Date(d));
          });

          traffic_graph_widget_chart_data_out = d3.select("#traffic_graph_widget_chart_out svg").datum([{'key':'', 'values':[[0, 0]]}]);
          traffic_graph_widget_chart_data_out.transition().duration(500).call(traffic_graph_widget_chart_out);
      });
  });
</script>


<!-- traffic graph table -->
<table class="table table-condensed" data-plugin="traffic" data-callback="traffic_widget_update">
    <tbody>
      <tr>
        <td><?=gettext("In (bps)");?></td>
      </tr>
      <tr>
        <td>
          <div id="traffic_graph_widget_chart_in">
            <svg style="height:150px;"></svg>
          </div>
        </td>
      </tr>
      <tr>
        <td><?=gettext("Out (bps)");?></td>
      </tr>
      <tr>
        <td>
          <div id="traffic_graph_widget_chart_out">
            <svg style="height:150px;"></svg>
          </div>
        </td>
      </tr>
    </tbody>
</table>
