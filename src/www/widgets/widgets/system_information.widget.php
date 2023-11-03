<?php

require_once("guiconfig.inc");
require_once("system.inc");

?>
<script src="<?= cache_safe('/ui/js/moment-with-locales.min.js') ?>"></script>
<script src="<?=cache_safe('/ui/js/chart.min.js');?>"></script>
<script src="<?=cache_safe('/ui/js/chartjs-plugin-streaming.min.js');?>"></script>
<script src="<?=cache_safe('/ui/js/chartjs-plugin-colorschemes.js');?>"></script>
<script src="<?=cache_safe('/ui/js/moment-with-locales.min.js');?>"></script>
<script src="<?=cache_safe('/ui/js/chartjs-adapter-moment.js');?>"></script>
<link rel="stylesheet" type="text/css" href="<?=cache_safe(get_themed_filename('/css/chart.css'));?>" rel="stylesheet" />
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

      $("#system_information_widget_firmware").html(data['firmware']);

      $("#system_information_widget_cpu_type").html(data['cpu']['model'] + ' ('+data['cpu']['cores']+' cores, '+data['cpu']['cpus']+' threads)');
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

      $("#system_information_widget_load").html('Max:' +data['cpu']['load'][0]+ ',Avg:' +data['cpu']['load'][1]+ ',Min:' +data['cpu']['load'][2]);

      var mem_perc = parseInt(data['kernel']['memory']['used'] / data['kernel']['memory']['total']*100);
      $("#system_information_widget_memory .progress-bar").css("width",  mem_perc + "%").attr("aria-valuenow", mem_perc + "%");
      var mem_text = mem_perc + " % " + "( " + parseInt(data['kernel']['memory']['used']/1024/1024) + "/";
      mem_text += parseInt(data['kernel']['memory']['total']/1024/1024) + " MB )";
      if (data['kernel']['memory']['arc_txt'] !== undefined) {
          mem_text += " { " + data['kernel']['memory']['arc_txt'] + " }";
      }
      $("#system_information_widget_memory .state_text").html(mem_text);

      //Memory Usage New
      
      var mctx = $("#memory_usage")[0].getContext('2d');
      var used_mem = parseInt(data['kernel']['memory']['used'] / data['kernel']['memory']['total']*100);
      var config = {
           type: "doughnut",
           data:{
            labels: ['Used', 'Available'],
             datasets: [{
               label: 'Memory Usage',
               data: [used_mem, 100 - used_mem],
                backgroundColor: used_mem < 60 ? ['rgb(147,209,80)', ' rgb(217,217,217)'] : (used_mem < 80 ? ['rgb(237,124,48)', ' rgb(217,217,217)'] : ['rgb(255,0,0)', ' rgb(217,217,217)']),
                borderColor: used_mem < 60 ? ['rgba(0,255,0,0.8)', 'rgba(188,188,188,0.8)'] : (used_mem < 80 ? ['rgba(255,165,0,0.8)', 'rgba(188,188,188,0.8)'] : ['rgba(255, 2, 1, 0.8)', 'rgba(188,188,188,0.8)']),
               borderWidth: 1,
             }]
          },
            options: {
              responsive: false,
              maintainAspectRatio: false,
              aspectRatio: 1,
              animation: {},
              plugins: {
                legend: {
                  display: false
                },
                tooltip: {
                  enabled: true,
                }
              }
            }
          }
          if (window.memoryUsageChart) {
            window.memoryUsageChart.data = config.data;
            window.memoryUsageChart.options = config.options;
            window.memoryUsageChart.update();
          } else {
            window.memoryUsageChart = new Chart(mctx, config);;
          }
          window.memoryUsageChart.canvas.parentNode.style.width = '160px'; 
          window.memoryUsageChart.canvas.parentNode.style.height = '160px'; 
          window.memoryUsageChart.canvas.style.width = '160px';
          window.memoryUsageChart.canvas.style.height = '160px';
          $("#system_memory_display").html('<span style="font-family: SourceSansProSemibold;">'+(((data['kernel']['memory']['used']/1024)/1024)/1024).toFixed(2)+'&nbspGB of&nbsp' +(((data['kernel']['memory']['total']/1024)/1024)/1024).toFixed(2)+'&nbspGB Used</span>');

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
      [data['disk']['devices'][0]].map(function(device) {
          var html = $("#system_information_widget_disk .disk_template").html();
          html = html.replace('disk_id_sequence', 'system_information_widget_disk_'+counter);
          $("#system_information_widget_disk .disk_devices").html($("#system_information_widget_disk .disk_devices").html() + html);
          var disk_perc = device['capacity'].replace('%', '');
          $("#system_information_widget_disk_"+counter+' .progress-bar').css("width",  disk_perc + "%").attr("aria-valuenow", disk_perc + "%");
          var disk_text =  device['capacity'] + ' - ' + '(' + device['used'] +'/' + device['size'] + ')';
          $("#system_information_widget_disk_"+counter+" .state_text").html(disk_text);
          counter += 1;
      });
      if (counter != 0) {
          $("#system_information_widget_disk_info").show();
      } else {
          $("#system_information_widget_disk_info").hide();
      }
      
      //Disk Usage New

      var device = data['disk']['devices'][0];
      var used_perc = parseFloat(device['used'])/parseFloat(device['size'])*100;
      var ctx = $("#disk_usage")[0].getContext('2d');
       var config = {
           type: "doughnut",
           data:{
              labels: ['Used', 'Available'],
              datasets: [{
                label: 'Disk Usage',
                data: [parseInt(used_perc), 100 - parseInt(used_perc)],   
                backgroundColor: used_perc < 60 ? ['rgb(147,209,80)', ' rgb(217,217,217)'] : (used_perc < 80 ? ['rgb(237,124,48)', ' rgb(217,217,217)'] : ['rgb(255,0,0)', ' rgb(217,217,217)']),
                borderColor: used_perc < 60 ? ['rgba(0,255,0,0.8)', 'rgba(188,188,188,0.8)'] : (used_perc < 80 ? ['rgba(255,165,0,0.8)', 'rgba(188,188,188,0.8)'] : ['rgba(255, 2, 1, 0.8)', 'rgba(188,188,188,0.8)']),
                borderWidth: 1
              }]
            },
            options: {
              responsive: false,
              maintainAspectRatio: false,
              aspectRatio: 1,
              animation: {},
              plugins: {
                legend: {
                  display: false
                },
                tooltip: {
                  enabled: true,
                }
              }
          }
      }

      if (window.diskUsageChart) {
        window.diskUsageChart.data = config.data;
        window.diskUsageChart.options = config.options;
        window.diskUsageChart.update();
      } else {
        window.diskUsageChart = new Chart(ctx, config);
      }
      window.diskUsageChart.canvas.parentNode.style.width = '160px'; 
      window.diskUsageChart.canvas.parentNode.style.height = '160px'; 
      window.diskUsageChart.canvas.style.width = '160px';
      window.diskUsageChart.canvas.style.height = '160px';

      $("#system_disk_display").html('<span style="font-family: SourceSansProSemibold;">'+(parseFloat(device['used']))+' GB of '+(parseFloat(device['size']))+' GB Used');

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


<table class="table table-striped table-condensed h-100" data-plugin="system" data-callback="system_information_widget_update">
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
        <a href='/ui/core/firmware#checkupdate'><span id="system_information_widget_firmware"><?= gettext('Retrieving internal update status...') ?></span></a>
      </td>
    </tr>
    <tr>
      <td><?=gettext("CPU type");?></td>
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
      <td><?=gettext("CPU usage");?></td>
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
      <td><?=gettext("MBUF usage");?></td>
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
    <tr>
      <td style="height:150px !important; width:150px !important;">
        <div id="system_disk_usage" style="height:150px !important; width:150px !important;">
          <canvas id="disk_usage" width="150" height="150"></canvas>
        </div>
      </td>
    </tr>
    <tr>
      <td style="height:150px; width:150px;">
        <div id="system_memory_usage" style="height:150px; width:150px;">   
          <canvas id="memory_usage"  width="150" height="150"></canvas>
        </div>
      </td>
    </tr>
    <tr>
      <td id="system_disk_display"><td>
    </tr>
    <tr>
      <td id="system_memory_display"><td>
    </tr>
  </tbody>
</table>
<script>
  var canv =  document.createElement("canvas");
  canv.setAttribute("id","disk_usage");
  canv.style.height = "150px";
  canv.style.width = "150px";
  document.getElementById('system_disk_usage').appendChild(canv);
  var mCanv =  document.createElement("canvas");
  mCanv.setAttribute("id","memory_usage");
  console.log('memory canvas created ', mCanv);
  mCanv.style.width = "150px";
  mCanv.style.height = "150px";
  document.getElementById('system_memory_usage').appendChild(mCanv);
</script>
