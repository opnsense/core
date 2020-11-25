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
  var system_information_widget_cpu_data = []; // reference to measures
  var system_information_widget_cpu_chart = null; // reference to chart object
  var system_information_widget_cpu_chart_data = null; // reference to chart data object

  /**
   * update cpu chart
   */
  function system_information_widget_cpu_update(sender, data)
  {
      // update cpu usage progress-bar
      var cpu_perc = parseInt(data['cpu']['used']);
      $("#system_information_widget_cpu .progress-bar").css("width",  cpu_perc + "%").attr("aria-valuenow", cpu_perc + "%");
      $("#system_information_widget_cpu .cpu_text").html(cpu_perc + " % ");
      // push new measurement, keep a maximum of 100 measures in
      system_information_widget_cpu_data.push(parseInt(data['cpu']['used']));
      if (system_information_widget_cpu_data.length > 100) {
          system_information_widget_cpu_data.shift();
      } else if (system_information_widget_cpu_data.length == 1) {
          system_information_widget_cpu_data.push(parseInt(data['cpu']['used']));
      }
      let chart_data = [];
      let count = 0;
      system_information_widget_cpu_data.map(function(item){
          chart_data.push([count, item]);
          count++;
      });
      system_information_widget_cpu_chart_data.datum([{'key':'cpu', 'values':chart_data}]).transition().duration(500).call(system_information_widget_cpu_chart);
  }

  /**
   * update widget
   */
   function system_information_widget_update(sender, data)
   {
      // update cpu usage chart
      system_information_widget_cpu_update(sender, data);

      $("#system_information_widget_cpu_type").html(data['cpu']['model'] + ' ('+data['cpu']['cpus']+' cores)');
      var uptime_days = parseInt(moment.duration(parseInt(data['uptime']), 'seconds').asDays());
      var uptime_str = "";
      if (uptime_days > 0) {
          uptime_str += uptime_days + " <?=html_safe(gettext('days'));?> ";
      }

      uptime_str += moment.utc(parseInt(data['uptime'])*1000).format("HH:mm:ss");
      $("#system_information_widget_uptime").html(uptime_str);
      $("#system_information_widget_datetime").html(data['date_frmt']);
      $("#system_information_widget_last_config_change").html(data['config']['last_change_frmt']);
      $("#system_information_widget_versions").html(data['versions'].join('<br/>'));

      var states_perc = parseInt((parseInt(data['kernel']['pf']['states']) / parseInt(data['kernel']['pf']['maxstates']))*100);
      $("#system_information_widget_states .progress-bar").css("width",  states_perc + "%").attr("aria-valuenow", states_perc + "%");
      var states_text = states_perc + " % " + "( " + data['kernel']['pf']['states'] + "/" + data['kernel']['pf']['maxstates'] + " )";
      $("#system_information_widget_states .state_text").html(states_text);

      var mbuf_perc = parseInt((parseInt(data['kernel']['mbuf']['total']) / parseInt(data['kernel']['mbuf']['max']))*100);
      $("#system_information_widget_mbuf .progress-bar").css("width",  mbuf_perc + "%").attr("aria-valuenow", mbuf_perc + "%");
      var mbuf_text = mbuf_perc + " % " + "( " + data['kernel']['mbuf']['total'] + "/" + data['kernel']['mbuf']['max'] + " )";
      $("#system_information_widget_mbuf .state_text").html(mbuf_text);

      $("#system_information_widget_load").html(data['cpu']['load'].join(','));

      var mem_perc = parseInt(data['kernel']['memory']['used'] / data['kernel']['memory']['total']*100);
      $("#system_information_widget_memory .progress-bar").css("width",  mem_perc + "%").attr("aria-valuenow", mem_perc + "%");
      var mem_text = mem_perc + " % " + "( " + parseInt(data['kernel']['memory']['used']/1024/1024) + "/";
      mem_text += parseInt(data['kernel']['memory']['total']/1024/1024) + " MB )";
      $("#system_information_widget_memory .state_text").html(mem_text);


      // swap usage
      let counter = 0;
      $("#system_information_widget_swap .swap_devices").html("");
      data['disk']['swap'].map(function(swap) {
          var html = $("#system_information_widget_swap .swap_template").html();
          html = html.replace('swap_id_sequence', 'system_information_widget_swap_'+counter);
          $("#system_information_widget_swap .swap_devices").html($("#system_information_widget_swap .swap_devices").html() + html);
          var swap_perc = parseInt(swap['used'] * 100 / swap['total']);
          $("#system_information_widget_swap_"+counter+' .progress-bar').css("width",  swap_perc + "%").attr("aria-valuenow", swap_perc + "%");
          var swap_text = swap_perc + " % " + "( " + parseInt(swap['used']/1024) + "/";
          swap_text += parseInt(swap['total']/1024) + " MB )";
          $("#system_information_widget_swap_"+counter+" .state_text").html(swap_text);
          counter += 1;
      });
      if (counter != 0) {
          $("#system_information_widget_swap_info").show();
      } else {
          $("#system_information_widget_swap_info").hide();
      }

      // disk usage
      counter = 0;
      $("#system_information_widget_disk .disk_devices").html("");
      data['disk']['devices'].map(function(device) {
          var html = $("#system_information_widget_disk .disk_template").html();
          html = html.replace('disk_id_sequence', 'system_information_widget_disk_'+counter);
          $("#system_information_widget_disk .disk_devices").html($("#system_information_widget_disk .disk_devices").html() + html);
          var disk_perc = device['capacity'].replace('%', '');
          $("#system_information_widget_disk_"+counter+' .progress-bar').css("width",  disk_perc + "%").attr("aria-valuenow", disk_perc + "%");
          var disk_text =  device['capacity'] + ' ' + device['mountpoint'] + ' ['+device['type']+'] (' + device['used'] +'/' + device['size'] + ')';
          $("#system_information_widget_disk_"+counter+" .state_text").html(disk_text);
          counter += 1;
      });
      if (counter != 0) {
          $("#system_information_widget_disk_info").show();
      } else {
          $("#system_information_widget_disk_info").hide();
      }
   }

  /**
   * page setup
   */
  $(window).on("load", function() {
      // draw cpu graph
      nv.addGraph(function() {
          system_information_widget_cpu_chart = nv.models.lineChart()
              .x(function(d) { return d[0] })
              .y(function(d) { return d[1] })
              .useInteractiveGuideline(false)
              .interactive(false)
              .showLegend(false)
              .showXAxis(false)
              .clipEdge(true)
              .margin({top:5,right:5,bottom:5,left:25});
          system_information_widget_cpu_chart.yAxis.tickFormat(d3.format('.0'));
          system_information_widget_cpu_chart.forceY([0, 100]);
          system_information_widget_cpu_chart_data = d3.select("#system_information_widget_chart_cpu_usage svg").datum([{'key':'cpu', 'values':[[0, 0]]}]);
          system_information_widget_cpu_chart_data.transition().duration(500).call(system_information_widget_cpu_chart);
      });
  });
</script>

<table class="table table-striped table-condensed" data-plugin="system" data-callback="system_information_widget_update">
  <tbody>
    <tr>
      <td style="width:30%"><?=gettext("Name");?></td>
      <td><?=$config['system']['hostname'] . "." . $config['system']['domain']; ?></td>
    </tr>
    <tr>
      <td><?=gettext("Versions");?></td>
      <td id="system_information_widget_versions"></td>
    </tr>
    <tr>
      <td><?= gettext('Updates') ?></td>
      <td>
        <a href='/ui/core/firmware#checkupdate'><?= gettext('Click to check for updates.') ?></a>
      </td>
    </tr>
    <tr>
      <td><?=gettext("CPU Type");?></td>
      <td id="system_information_widget_cpu_type"></td>
    </tr>
    <tr>
      <td><?=gettext("CPU usage");?></td>
      <td>
        <div id="system_information_widget_chart_cpu_usage">
          <svg style="height:40px;"></svg>
        </div>
      </td>
    </tr>
    <tr>
      <td><?=gettext("Load average");?></td>
      <td id="system_information_widget_load"></td>
    </tr>
    <tr>
      <td><?=gettext("Uptime");?></td>
      <td id="system_information_widget_uptime"></td>
    </tr>
    <tr>
      <td><?=gettext("Current date/time");?></td>
      <td id="system_information_widget_datetime"></td>
    </tr>
    <tr>
      <td><?=gettext("Last config change");?></td>
      <td id="system_information_widget_last_config_change"></td>
    </tr>
    <tr>
      <td><?=gettext("CPU usage");?></span></td>
      <td id="system_information_widget_cpu">
        <div class="progress" style="text-align:center;">
          <div class="progress-bar" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100" style="width: 0%; z-index: 0;"></div>
          <span class="cpu_text" style="position:absolute;right:0;left:0;"></span>
        </div>
      </td>
    </tr>
    <tr>
      <td><?=gettext("State table size");?></td>
      <td id="system_information_widget_states">
        <div class="progress" style="text-align:center;">
          <div class="progress-bar" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100" style="width: 0%; z-index: 0;"></div>
          <span class="state_text" style="position:absolute;right:0;left:0;"></span>
        </div>
      </td>
    </tr>
    <tr>
      <td><?=gettext("MBUF Usage");?></td>
      <td id="system_information_widget_mbuf">
        <div class="progress" style="text-align:center;">
          <div class="progress-bar" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100" style="width: 0%; z-index: 0;"></div>
          <span class="state_text" style="position:absolute;right:0;left:0;"></span>
        </div>
      </td>
    </tr>
    <tr>
      <td><?=gettext("Memory usage");?></td>
      <td id="system_information_widget_memory">
        <div class="progress" style="text-align:center;">
          <div class="progress-bar" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100" style="width: 0%; z-index: 0;"></div>
          <span class="state_text" style="position:absolute;right:0;left:0;"></span>
        </div>
      </td>
    </tr>
    <tr id="system_information_widget_swap_info">
      <td><?=gettext("SWAP usage");?></td>
      <td id="system_information_widget_swap">
          <div style="display:none" class="swap_template">
            <!-- template -->
            <div id="swap_id_sequence" class="progress" style="text-align:center;">
              <div class="progress-bar" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100" style="width: 0%; z-index: 0;"></div>
              <span class="state_text" style="position:absolute;right:0;left:0;"></span>
            </div>
            <div style="height:1px;">
            </div>
          </div>
          <div class="swap_devices">
          </div>
        </DIV>
      </td>
    </tr>
    <tr id="system_information_widget_disk_info">
      <td><?=gettext("Disk usage");?></td>
      <td id="system_information_widget_disk">
          <div style="display:none" class="disk_template">
            <!-- template -->
            <div id="disk_id_sequence" class="progress" style="text-align:center;">
              <div class="progress-bar" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100" style="width: 0%; z-index: 0;"></div>
              <span class="state_text" style="position:absolute;right:0;left:0;"></span>
            </div>
            <div style="height:1px;">
            </div>
          </div>
          <div class="disk_devices">
          </div>
      </td>
    </tr>
  </tbody>
</table>
