<?php

/*
 * Copyright (C) 2014-2016 Deciso B.V.
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
require_once("system.inc");

?>
<script src="<?= cache_safe('/ui/js/moment-with-locales.min.js') ?>"></script>
<script>
  var cpu_widget_cpu_data = []; // reference to measures
  var cpu_widget_cpu_chart = null; // reference to chart object
  var cpu_widget_cpu_chart_data = null; // reference to chart data object

  /**
   * update cpu chart
   */
  function cpu_widget_cpu_update(sender, data)
  {
      // push new measurement, keep a maximum of 100 measures in
      cpu_widget_cpu_data.push([data['date_time'] * 1000, parseInt(data['cpu']['used'])]);
      if (cpu_widget_cpu_data.length > 100) {
          cpu_widget_cpu_data.shift();
      } else if (cpu_widget_cpu_data.length == 1) {
          cpu_widget_cpu_data.push([data['date_time'] * 1000, parseInt(data['cpu']['used'])]);
      }
      let chart_data = [];
      cpu_widget_cpu_data.map(function(item){
          chart_data.push(item);
      });
      cpu_widget_cpu_chart_data.datum([{'key':'cpu', 'values':chart_data}]).transition().duration(500).call(cpu_widget_cpu_chart);
  }

  function cpu_widget_update(sender, data)
   {
      // update cpu usage chart
      cpu_widget_cpu_update(sender, data);
      $("#cpu_widget_load").html(data['cpu']['load'].join(','));
   }

  /**
   * page setup
   */
  $(window).load(function() {
      // draw cpu graph
      nv.addGraph(function() {
          cpu_widget_cpu_chart = nv.models.lineChart()
              .x(function(d) { return d[0] })
              .y(function(d) { return d[1] })
              .useInteractiveGuideline(false)
              .interactive(true)
              .showLegend(false)
              .showXAxis(false)
              .clipEdge(true)
              .margin({top:5,right:5,bottom:5,left:25});
          cpu_widget_cpu_chart.yAxis.tickFormat(d3.format('.0'));
          cpu_widget_cpu_chart.xAxis.tickFormat(function(d) {
              return d3.time.format('%b %e %H:%M:%S')(new Date(d));
          });
          cpu_widget_cpu_chart.forceY([0, 100]);
          cpu_widget_cpu_chart_data = d3.select("#cpu_widget_chart_cpu_usage svg").datum([{'key':'cpu', 'values':[[0, 0]]}]);
          cpu_widget_cpu_chart_data.transition().duration(500).call(cpu_widget_cpu_chart);
      });
  });
</script>

<table class="table table-striped table-condensed" data-plugin="system" data-callback="cpu_widget_update">
  <tbody>
    <tr>
      <td>
        <div id="cpu_widget_chart_cpu_usage">
          <svg style="height:250px;"></svg>
        </div>
      </td>
    </tr>
  </tbody>
</table>
