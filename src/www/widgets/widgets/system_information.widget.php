<?php

/*
    Copyright (C) 2014-2016 Deciso B.V.
    Copyright (C) 2007 Scott Dale
    Copyright (C) 2004-2005 T. Lechat <dev@lechat.org>, Manuel Kasper <mk@neon1.net>
    and Jonathan Watt <jwatt@jwatt.org>.
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

require_once("guiconfig.inc");
require_once("pfsense-utils.inc");
require_once("system.inc");
require_once("stats.inc");

## Check to see if we have a swap space,
## if true, display, if false, hide it ...
$swapinfo = `/usr/sbin/swapinfo`;
if (stristr($swapinfo, '%')) {
    $showswap = true;
} else {
    $showswap = false;
}


if (isset($_POST['getupdatestatus'])) {
    $pkg_json = trim(configd_run('firmware check'));
    if ($pkg_json != '') {
        $pkg_status = json_decode($pkg_json, true);
    }

    if (!isset($pkg_status) || $pkg_status["connection"]=="error") {
        echo "<span class='text-danger'>".gettext("Connection Error")."</span><br/><span class='btn-link' onclick='system_information_widget_checkupdate()'>".gettext("Click to retry")."</span>";
    } elseif ($pkg_status["repository"]=="error") {
        echo "<span class='text-danger'>".gettext("Repository Problem")."</span><br/><span class='btn-link' onclick='system_information_widget_checkupdate()'>".gettext("Click to retry")."</span>";
    } elseif ($pkg_status["updates"]=="0") {
        echo "<span class='text-info'>".gettext("Your system is up to date.")."</span><br/><span class='btn-link' onclick='system_information_widget_checkupdate()'>".gettext('Click to check for updates')."</span>";
    } else {
        echo "<span class='text-info'>".sprintf(gettext("There are %s update(s) available."),$pkg_status["updates"])."</span><br/><a href='/ui/core/firmware/#checkupdate'>".gettext("Click to upgrade")."</a> | <span class='btn-link' onclick='system_information_widget_checkupdate()'>".gettext('Re-check now')."</span>";
    }

    exit;
}

$filesystems = get_mounted_filesystems();
?>

<script src="/ui/js/moment-with-locales.min.js" type="text/javascript"></script>
<script type="text/javascript">
  var system_information_widget_cpu_data = []; // reference to measures
  var system_information_widget_cpu_chart = null; // reference to chart object
  var system_information_widget_cpu_chart_data = null; // reference to chart data object

  /**
   * check for updates
   */
  function system_information_widget_checkupdate() {
      $('#updatestatus').html('<span class="text-info"><?= html_safe(gettext('Fetching... (may take up to 30 seconds)')) ?></span>');
      $.ajax({
        type: "POST",
        url: '/widgets/widgets/system_information.widget.php',
        data:{getupdatestatus:'yes'},
        success:function(html) {
            $('#updatestatus').prop('innerHTML',html);
        }
      });
  }

  /**
   * update cpu chart
   */
  function system_information_widget_cpu_update(sender, data)
  {
      // tooltip current percentage
      $("#system_information_widget_chart_cpu_usage").tooltip({ title: ''});
      $("#system_information_widget_chart_cpu_usage").attr("title", data['cpu']['used'] + ' %').tooltip('fixTitle');
      // push new measurement, keep a maximum of 100 measures in
      system_information_widget_cpu_data.push(parseInt(data['cpu']['used']));
      if (system_information_widget_cpu_data.length > 100) {
          system_information_widget_cpu_data.shift();
      } else if (system_information_widget_cpu_data.length == 1) {
          system_information_widget_cpu_data.push(parseInt(data['cpu']['used']));
      }
      chart_data = [];
      count = 0;
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
      system_information_widget_cpu_update(sender, data);
      $("#system_information_widget_cpu_type").html(data['cpu']['model'] + ' ( '+data['cpu']['cpus']+' cores )');
      var uptime_days = parseInt(moment.duration(parseInt(data['uptime']), 'seconds').asDays());
      var uptime_str = "";
      if (uptime_days > 0) {
          uptime_str += uptime_days + " <?=html_safe(gettext('days'));?> ";
      }

      uptime_str += moment.utc(parseInt(data['uptime'])*1000).format("HH:mm:ss");
      $("#system_information_widget_uptime").html(uptime_str);
      $("#system_information_widget_datetime").html(data['date_frmt']);
      $("#system_information_widget_last_config_change").html(data['config']['last_change_frmt']);

      var states_perc = parseInt((parseInt(data['kernel']['pf']['states']) / parseInt(data['kernel']['pf']['maxstates']))*100);
      $("#system_information_widget_states .progress-bar").css("width",  states_perc + "%").attr("aria-valuenow", states_perc + "%");
      var states_text = states_perc + " % " + "( " + data['kernel']['pf']['states'] + "/" + data['kernel']['pf']['maxstates'] + " )"
      $("#system_information_widget_states .state_text").html(states_text);
      //$("#system_information_widget_states").html(states_perc);
   }

  /**
   * page setup
   */
  $( document ).ready(function() {
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

<script type="text/javascript">
//<![CDATA[
  jQuery(function() {
    jQuery("#statePB").css( { width: '<?php echo get_pfstate(true); ?>%' } );
    jQuery("#mbufPB").css( { width: '<?php echo get_mbuf(true); ?>%' } );
    jQuery("#cpuPB").css( { width:0 } );
    jQuery("#memUsagePB").css( { width: '<?php echo mem_usage(); ?>%' } );

<?php $d = 0; ?>
<?php foreach ($filesystems as $fs) : ?>
    jQuery("#diskUsagePB<?php echo $d++; ?>").css( { width: '<?php echo $fs['percent_used']; ?>%' } );
<?php endforeach; ?>

    <?php if ($showswap == true) : ?>
      jQuery("#swapUsagePB").css( { width: '<?php echo swap_usage(); ?>%' } );
    <?php endif; ?>
    <?php if (get_temp() != "") : ?>
      jQuery("#tempPB").css( { width: '<?php echo get_temp(); ?>%' } );
    <?php endif; ?>
  });
//]]>
</script>

<table class="table table-striped table-condensed" data-plugin="system" data-callback="system_information_widget_update">
  <tbody>
    <tr>
      <td width="30%"><?=gettext("Name");?></td>
      <td><?=$config['system']['hostname'] . "." . $config['system']['domain']; ?></td>
    </tr>
    <tr>
      <td><?=gettext("Versions");?></td>
      <td>
          <?=sprintf('%s %s-%s', $g['product_name'], explode('-', trim(file_get_contents('/usr/local/opnsense/version/opnsense')))[0], php_uname('m'));?><br/>
          <?=php_uname('s') . ' ' . php_uname('r'); ?><br/>
          <?=exec('/usr/local/bin/openssl version'); ?>
      </td>
    </tr>
    <tr>
      <td><?= gettext('Updates') ?></td>
      <td>
        <div id='updatestatus'><span class='btn-link' onclick='system_information_widget_checkupdate()'><?=gettext("Click to check for updates");?></span></div>
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
      <td><?=gettext("State table size");?></td>
      <td id="system_information_widget_states">
        <div class="progress" style="text-align:center;">
          <span class="state_text" style="position:absolute;right:0;left:0;z-index:200;"></span>
          <div class="progress-bar" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100" style="width: 0%;"></div>
        </div>
      </td>
    </tr>



    <tr>
      <td><?=gettext("MBUF Usage");?></td>
      <td>
        <?php
                    $mbufstext = get_mbuf();
                    $mbufusage = get_mbuf(true);
                ?>

        <div class="progress">
          <div id="mbufPB" class="progress-bar" role="progressbar" aria-valuenow="60" aria-valuemin="0" aria-valuemax="100" style="width: 0%;">
            <span class="sr-only"></span>
          </div>
        </div>
        <span id="mbufusagemeter"><?= $mbufusage.'%'; ?></span> (<span id="mbuf"><?= $mbufstext ?></span>)
      </td>
    </tr>
                <?php if (get_temp() != "") :
?>
                <tr>
                        <td><?=gettext("Temperature");?></td>
      <td>
        <?php $TempMeter = $temp = get_temp(); ?>

        <div class="progress">
          <div id="tempPB" class="progress-bar" role="progressbar" aria-valuenow="60" aria-valuemin="0" aria-valuemax="100" style="width: 0%;">
            <span class="sr-only"></span>
          </div>
        </div>
        <span id="tempmeter"><?= $temp."&#176;C"; ?></span>
      </td>
                </tr>
                <?php endif; ?>
    <tr>
      <td><?=gettext("Load average");?></td>
      <td>
      <div id="load_average" title="Last 1, 5 and 15 minutes"><?= get_load_average(); ?></div>
      </td>
    </tr>
    <tr>
      <td><?=gettext("Memory usage");?></td>
      <td>
        <?php $memUsage = mem_usage(); ?>
        <div class="progress">
          <div id="memUsagePB" class="progress-bar" role="progressbar" aria-valuenow="60" aria-valuemin="0" aria-valuemax="100" style="width: 0%;">
            <span class="sr-only"></span>
          </div>
        </div>
        <span id="memusagemeter"><?= $memUsage.'%'; ?></span> used <?= sprintf("%.0f/%.0f", $memUsage/100.0 * get_single_sysctl('hw.physmem') / (1024*1024), get_single_sysctl('hw.physmem') / (1024*1024)) ?> MB
      </td>
    </tr>
    <?php if ($showswap == true) :
?>
    <tr>
      <td><?=gettext("SWAP usage");?></td>
      <td>
        <?php $swapusage = swap_usage(); ?>
        <div class="progress">
          <div id="swapUsagePB" class="progress-bar" role="progressbar" aria-valuenow="60" aria-valuemin="0" aria-valuemax="100" style="width: 0%;">
            <span class="sr-only"></span>
          </div>
        </div>
        <span id="swapusagemeter"><?= $swapusage.'%'; ?></span> used <?= sprintf("%.0f/%.0f", `/usr/sbin/swapinfo -m | /usr/bin/grep -v Device | /usr/bin/awk '{ print $3;}'`, `/usr/sbin/swapinfo -m | /usr/bin/grep -v Device | /usr/bin/awk '{ print $2;}'`) ?> MB
      </td>
    </tr>
    <?php endif; ?>
    <tr>
      <td><?=gettext("Disk usage");?></td>
      <td>
<?php $d = 0; ?>
<?php foreach ($filesystems as $fs) : ?>
        <div class="progress">
          <div id="diskUsagePB<?php echo $d; ?>" class="progress-bar" role="progressbar" aria-valuenow="60" aria-valuemin="0" aria-valuemax="100" style="width: 0%;">
            <span class="sr-only"></span>
          </div>
        </div>
        <?php if (substr(basename($fs['device']), 0, 5) == "tmpfs") {
                    $fs['type'] .= " in RAM";
} ?>
        <?php echo "{$fs['mountpoint']} ({$fs['type']})";?>: <span id="diskusagemeter<?php echo $d++ ?>"><?= $fs['percent_used'].'%'; ?></span> used <?php echo $fs['used_size'] ."/". $fs['total_size'];
        if ($d != count($filesystems)) {
          echo '<br/><br/>';
        }
endforeach; ?>
      </td>
    </tr>
  </tbody>
</table>
