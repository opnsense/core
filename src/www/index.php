<?php

/*
 * Copyright (C) 2014-2016 Deciso B.V.
 * Copyright (C) 2004-2012 Scott Ullrich <sullrich@gmail.com>
 * Copyright (C) 2003-2004 Manuel Kasper <mk@neon1.net>
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

require_once('guiconfig.inc');

// if no config entry found, initialize config entry
config_read_array('widgets');

$widgetCollection = array();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $pconfig = $config['widgets'];
    // set default dashboard view
    $pconfig['sequence'] = !empty($pconfig['sequence']) ? $pconfig['sequence'] : '';
    $pconfig['column_count'] = !empty($pconfig['column_count']) ? $pconfig['column_count'] : 2;
    // build list of widgets
    $widgetSeqParts = explode(",", $pconfig['sequence']);
    foreach (glob('/usr/local/www/widgets/widgets/*.widget.php') as $php_file) {
        $widgetItem = array();
        $widgetItem['name'] = basename($php_file, '.widget.php');
        $widgetItem['display_name'] = ucwords(str_replace("_", " ", $widgetItem['name']));
        $widgetItem['filename'] = $php_file;
        $widgetItem['state'] = "none";
        /// default sort order
        $widgetItem['sortKey'] = $widgetItem['name'] == 'system_information' ? "00000000" : "99999999" . $widgetItem['name'];
        foreach ($widgetSeqParts as $seqPart) {
            $tmp = explode(':', $seqPart);
            if (count($tmp) == 3 && explode('-', $tmp[0])[0] == $widgetItem['name']) {
                $widgetItem['state'] = $tmp[2];
                $widgetItem['sortKey'] = $tmp[1];
            }
        }
        $widgetCollection[] = $widgetItem;
    }
    // sort widgets
    usort($widgetCollection, function ($item1, $item2) {
      return strcmp(strtolower($item1['sortKey']), strtolower($item2['sortKey']));
    });
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['origin']) && $_POST['origin'] == 'dashboard') {
    if (!empty($_POST['sequence'])) {
        $config['widgets']['sequence'] = $_POST['sequence'];
    } elseif (isset($config['widgets']['sequence'])) {
        unset($config['widgets']['sequence']);
    }
    if (!empty($_POST['column_count'])) {
        $config['widgets']['column_count'] = $_POST['column_count'];
    } elseif(isset($config['widgets']['column_count'])) {
        unset($config['widgets']['column_count']);
    }
    write_config('Widget configuration has been changed');
    header(url_safe('Location: /index.php'));
    exit;
}

// handle widget includes
foreach (glob("/usr/local/www/widgets/include/*.inc") as $filename) {
    include($filename);
}

$product = product::getInstance();

include("head.inc");
?>
<body>
<?php
include("fbegin.inc");?>

<?php
?>
<?php
  if (isset($config['trigger_initial_wizard']) || isset($_GET['wizard_done'])): ?>
  <script>
      $( document ).ready(function() {
        $(".page-content-head:first").hide();
      });
  </script>
  <header class="page-content-head">
    <div class="container-fluid">
<?php
     if (isset($config['trigger_initial_wizard'])): ?>
      <h1><?= gettext("Starting initial configuration!") ?></h1>
<?php
     else: ?>
      <h1><?= gettext("Finished initial configuration!") ?></h1>
<?php
     endif ?>
    </div>
  </header>
  <section class="page-content-main">
    <div class="container-fluid col-xs-12 col-sm-10 col-md-9">
      <div class="row">
        <section class="col-xs-12">
          <div class="content-box wizard" style="padding: 20px;">
            <div class="table-responsive">
<?php if (file_exists("/usr/local/opnsense/www/themes/{$themename}/build/images/default-logo.svg")): ?>
              <img src=" <?= cache_safe("/ui/themes/{$themename}/build/images/default-logo.svg") ?>" border="0" alt="logo" style="max-width:380px;" />
<?php else: ?>
              <img src=" <?= cache_safe("/ui/themes/{$themename}/build/images/default-logo.svg") ?>" border="0" alt="logo" style="max-width:380px;" />
<?php endif ?>
              <br />
              <div class="content-box-main" style="padding-bottom:0px;">
                <?php
                    if (isset($config['trigger_initial_wizard'])) {
                        echo '<p>' . sprintf(gettext('Welcome to %s!'), $product->name()) . "</p>\n";
                        echo '<p>' . gettext('One moment while we start the initial setup wizard.') . "</p>\n";
                        echo '<p class="__nomb">' . gettext('To bypass the wizard, click on the logo in the upper left corner.') . "</p>\n";
                    } else {
                        echo '<p>' . sprintf(gettext('Congratulations! %s is now configured.'), $product->name()) . "</p>\n";
                        echo '<p>' . sprintf(gettext(
                            'Please consider donating to the project to help us with our overhead costs. ' .
                            'See %sour website%s to donate or purchase available %s support services.'),
                            '<a target="_new" href="' . $product->website() . '">', '</a>', $product->name()) . "</p>\n";
                        echo '<p class="__nomb">' . sprintf(gettext('Click to %scontinue to the dashboard%s.'), '<a href="/">', '</a>') . ' ';
                        echo sprintf(gettext('Or click to %scheck for updates%s.'), '<a href="/ui/core/firmware#checkupdate">', '</a>'). "</p>\n";
                    }
                ?>
              </div>
            <div>
          </div>
        </section>
      </div>
    </div>
  </section>
<?php
     if (isset($config['trigger_initial_wizard'])): ?>
  <meta http-equiv="refresh" content="5;url=/wizard.php?xml=system">
<?php
     endif ?>
<?php
  // normal dashboard
  else:?>


<style>
/* Center the table horizontally */
.table-container {
      display: flex;
      justify-content: center;
      align-items: center;
      height: 100vh;
    }

    /* Style the table */
    table {
      width: 100%;
      max-width: 600px;
      border-collapse: collapse;
      box-shadow: 0px 0px 10px rgba(0, 0, 0, 0.2); /* Add shadow */
    }

    th, td {
      padding: 8px 16px;
      text-align: left;
      border-bottom: 1px solid #ddd;
      text-align: center;
    }

    th {
      background-color: #ffff;
    }

    /* Make table responsive on smaller screens */
    @media (max-width: 600px) {
      th, td {
        display: block;
        width: 100%;
      }

      th {
        text-align: center;
      }
    }
    .padding-0{
      padding-left:5px;
      padding-right:5px;
    }
    .p-relative{
      position: relative;
    }
    .h-100{
      height:100%;
    }
</style>

<div class="container dashboard-width-82" id="dashboard_container" style="height: auto; padding-bottom: 100px; box-sizing: border-box;">
  <div class="row" style="margin-top:30px; display:flex;">
    <div class="col-md-6 padding-0 p-relative">
      <table class="table table-striped h-100">
        <thead>
	        <tr>
            <th style="text-align:center;" colspan="2">System Information</th>
          <tr>
        </thead>
        <tbody>
          <tr>
            <td style="text-align:left; font-weight:bold;"><?=gettext("Name");?></td>
            <td style="text-align:left;"><?=$config['system']['hostname'] . "." . $config['system']['domain']; ?></td> 
          </tr>
          <tr>
            <td style="text-align:left; font-weight:bold;">
              <?=gettext("Versions");?>
            </td>
	    <!--td style="text-align:left;" id="system_information_widget_versions"></td-->
            <td style="text-align:left;"><?=$version;?></td>
          </tr>
          <tr>
            <td style="text-align:left; font-weight:bold;">
              <?=gettext("CPU type");?>
            </td>
            <td style="text-align:left;" id="system_information_widget_cpu_type"></td>
          </tr>
          <tr>
            <td style="text-align:left; font-weight:bold;"><?=gettext("Load Average")?></td>
            <td style="text-align:left;" id="system_information_widget_load"></td>
          </tr>
          <tr>
            <td style="text-align:left; font-weight:bold;"><?=gettext("Uptime")?></td>
            <td style="text-align:left;" id="system_information_widget_uptime"></td>
          </tr>
          <tr>
            <td style="text-align:left; font-weight:bold;"><?=gettext("Current date/time");?></td>
            <td style="text-align:left;" id="system_information_widget_datetime"></td>
          </tr>
        </tbody>
      </table>
    </div>
    <div class="col-md-6 padding-0 p-relative">
      <table class="table table-striped h-100">
        <tbody>
          <tr style="background-color: #FBFBFB;">
              <th>
                <div>CPU Usage</div>
              </th>
              <th>
                <div>Memory Usage</div>
              </th>
              <th>
                <div>Disk Usage</div>                
              </th>
            </tr>  
              <tr>
            <td id="system_cpu_usage"></td>
            <td id="system_memory_usage"></td>
            <td id="system_disk_usage"></td>
          </tr>
          <tr>
            <td id="cpu_usage_display"></td>
            <td id="system_memory_display"></td>
            <td id="system_disk_display"></td>
          </tr>
        </tbody>
      </table>
    </div> 
  </div>
  <div class="row" style="margin-top:30px; display:flex;">
    <div class="col-md-6 padding-0 p-relative">
      <table class="table table-striped h-100">
        <thead>
          <tr>
            <th style="text-align:center;" colspan="3">Interfaces</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <th style="text-align:left;">
              <?=gettext("Interface Name");?>
            </th>
            <th style="text-align:left;">
              <?=gettext("MAC Address");?>
            </th>
            <th style="text-align:left;">
              <?=gettext("Status");?>
            </th>
          </tr>
        <tbody>
          <?php
            $mac_man = json_decode(configd_run('interface list macdb json'), true);
            $pfctl_counters = json_decode(configd_run('filter list counters json'), true);
            $vmstat_interrupts = json_decode(configd_run('system list interrupts json'), true);
            foreach (get_interfaces_info(true) as $ifdescr => $ifinfo):
              if ($ifinfo['if'] == 'pfsync0') {
                continue;
              } 
              elseif ($ifinfo['if'] == 'pflog0') {
                continue;
              }
              elseif ($ifinfo['if'] == 'enc0') {
                continue;
              }
              elseif ($ifinfo['if'] == 'lo0') {
                continue;
              }
              $ifpfcounters = $pfctl_counters[$ifinfo['if']];
                    legacy_html_escape_form_data($ifinfo);
              $ifdescr = htmlspecialchars($ifdescr);
              $ifname = htmlspecialchars($ifinfo['descr']);
              $ifname_len = strlen($ifname);
          ?>
          <tr>
            <td style="text-align:left">
              <?= $ifname ?>
            </td>
            <td style="text-align:left">
              <?= $ifinfo['macaddr']; ?>
            </td>
            <td style="text-align:left">
            <?php if ($ifinfo['status'] == 'up'): ?>
              <span class="label-default label label-success">Online</span>
            <?php else: ?>
              <span class="label-default label label-danger">Offline</span>
            <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div class="col-md-6 padding-0 p-relative">
      <?php include('/usr/local/www/widgets/widgets/gateways.widget.php'); ?>
    </div>
  </div>
  <div class="row" style="margin-top:30px; display:flex;">
    <div class="col-md-12 padding-0 p-relative">
      <?php include('/usr/local/www/widgets/widgets/traffic_graphs.widget.php'); ?>
    </div>
  </div>
  <div class="row" style="margin-top:30px; display:flex;">
    <div class="col-md-12 padding-0 p-relative">
      <?php include('/usr/local/www/widgets/widgets/cpu_usage.widget.php'); ?>
    </div>
  </div>
  <div class="row" style="margin-top:30px; display:flex;">
    <div class="col-md-6 padding-0 p-relative">
      <table class="table table-striped h-100">
        <thead>   
          <tr>
            <th style="text-align:center;" colspan="2">License</th>
          </tr>
	</thead>
        <tbody>
          <tr>
            <th style="text-align:left;">License Type</th>
            <th style="text-align:center;">Status</th>
          </tr>        
          <tr>
        <?php
	  $Lic_names = ['Anti Virus', 'IDS/IPS', 'SDWAN', 'DLP', 'Appliance License', 'WAF', 'ZTNA', 'Sandboxing (ATP Protection)', 'Antispam', 'Application Filter', 'URLFilter'];
          $command = "/usr/local/bin/python /usr/local/applications/service/service_status.py lic_status";
	  $output = shell_exec($command);
	  $listdata = eval("return $output;");
	  $listdatas = $listdata[1];
	  foreach ($Lic_names as $Lic_name):
         ?>
	  <tr>
	    <td style="text-align:left;"><?= $Lic_name; ?></td>
	    <td style="text-align:center">
            <?php if ($listdata[0] == 'Appliance_License' && array_search($Lic_name, $listdata[1])!== false): ?>
	      <p style="color:green;"><?= $listdata[2]; ?></p>
	    <?php elseif ($listdata[0] == 'Demo'): ?>
	      <p style="color:green;"><?= $listdata[2]; ?></p>
            <?php else: ?>
                <p>
                    <span style="color: white; background-color:#e91f1f; padding: 5px; border-radius: 5px; font-size: 12px;">Expired</span>
                    <span style="color: dark black;">/</span>
                    <span style="color: white; background-color:#e8e822; padding: 5px; border-radius: 5px; font-size: 12px;">Not Subscribed</span>
                </p>

	    <?php endif; ?>
	    </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div class="col-md-6 padding-0 p-relative">
      <?php include('/usr/local/www/widgets/widgets/monit.widget.php'); ?>
    </div>
  </div>
  <div class="row" style="margin-top:30px; display:flex;">
    <div class="col-md-6 padding-0 p-relative">
      <?php include('/usr/local/www/widgets/widgets/openvpn.widget.php'); ?>
    </div>
    <div class="col-md-6 padding-0 p-relative">
      <?php include('/usr/local/www/widgets/widgets/carp_status.widget.php'); ?>
    </div>
  </div>        
  <div class="row" style="margin-top:30px; display:flex;">     
    <div class="col-md-6 padding-0 p-relative">
      <?php include('/usr/local/www/widgets/widgets/wireguard.widget.php'); ?>
    </div>
    <div class="col-md-6 padding-0 p-relative">
      <?php include('/usr/local/www/widgets/widgets/ipsec.widget.php'); ?>
    </div>
  </div>
</div>

<script src="<?= cache_safe('/ui/js/jquery-sortable.js') ?>"></script>
<script>
  function addWidget(selectedDiv) {
      $('#'+selectedDiv).show();
      $('#add_widget_'+selectedDiv).hide();
      $('#'+selectedDiv+'-config').val('show');
      showSave();
  }

  function configureWidget(selectedDiv) {
      let selectIntLink = '#' + selectedDiv + "-settings";
      if ($(selectIntLink).css('display') == "none") {
          $(selectIntLink).show();
      } else {
          $(selectIntLink).hide();
      }
  }

  function showWidget(selectedDiv,swapButtons) {
      $('#'+selectedDiv+'-container').show();
      $('#'+selectedDiv+'-min').show();
      $('#'+selectedDiv+'-max').hide();
      $('#'+selectedDiv+'-config').val('show');
      showSave();
  }

  function minimizeWidget(selectedDiv, swapButtons) {
      $('#'+selectedDiv+'-container').hide();
      $('#'+selectedDiv+'-min').hide();
      $('#'+selectedDiv+'-max').show();
      $('#'+selectedDiv+'-config').val('hide');
      showSave();
  }

  function closeWidget(selectedDiv) {
      $('#'+selectedDiv).hide();
      $('#'+selectedDiv+'-config').val('close');
      showSave();
  }

  function showSave() {
      $('#updatepref').show();
  }

  function updatePref() {
      var widgetInfo = [];
      var index = 0;
      $('.widgetdiv').each(function(key) {
          if ($(this).is(':visible')) {
              // only capture visible widgets
              var index_str = "0000000" + index;
              index_str = index_str.substr(index_str.length-8);
              let col_index = $(this).parent().attr("id").split('_')[1];
              widgetInfo.push($(this).attr('id')+'-container:'+index_str+'-'+col_index+':'+$('input[name='+$(this).attr('id')+'-config]').val());
              index++;
          }
      });
      $("#sequence").val(widgetInfo.join(','));
      $("#iform").submit();
      return false;
  }

  /**
   * ajax update widget data, searches data-plugin attributes and use function in data-callback to update widget
   */
  function process_widget_data()
  {
      var plugins = [];
      var callbacks = [];
      // collect plugins and callbacks
      $("[data-plugin]").each(function(){
          if (plugins.indexOf($(this).data('plugin')) < 0) {
              plugins.push($(this).data('plugin'));
          }
          if ($(this).data('callback') != undefined) {
              callbacks.push({'function' : $(this).data('callback'), 'plugin': $(this).data('plugin'), 'sender': $(this)});
          }
      });
      // collect data for provided plugins
      $.ajax("/widgets/api/get.php",{type: 'get', cache: false, dataType: "json", data: {'load': plugins.join(',')}})
        .done(function(response) {
            callbacks.map( function(callback) {
                try {
                    if (response['data'][callback['plugin']] != undefined) {
                        window[callback['function']](callback['sender'], response['data'][callback['plugin']]);
                    }
                } catch (err) {
                    console.log(err);
                }
            });
            // schedule next update
            setTimeout('process_widget_data()', 5000);
      });
  }
</script>

<script>
  $( document ).ready(function() {
      // rearrange widgets to stored column
      $(".widgetdiv").each(function(){
          var widget = $(this);
          widget.find('script').each(function(){
              $(this).remove();
          });
          var container = $(this).parent();
          var target_col = widget.data('sortkey').split('-')[1];
          if (target_col != undefined) {
              if (container.attr('id').split('_')[1] != target_col) {
                  widget.remove().appendTo("#dashboard_"+target_col);
              }
          } else {
              // dashboard_colx (source) is not visible, move other items to col4
              widget.remove().appendTo("#dashboard_col4");
          }
      });

      // show dashboard widgets after initial rendering
      $("#dashboard_container").show();

      // trigger WidgetsReady event
      $("#dashboard_container").trigger("WidgetsReady");

      // sortable widgets
      $(".dashboard_grid_column").sortable({
        handle: '.widget-sort-handle',
        group: 'dashboard_grid_column',
        itemSelector: '.widgetdiv',
        containerSelector: '.dashboard_grid_column',
        placeholder: '<div class="placeholder"><i class="fa fa-hand-o-right" aria-hidden="true"></i></div>',
        afterMove: function (placeholder, container, closestItemOrContainer) {
            showSave();
        }
      });

      // select number of columns
      $("#column_count").change(function(){
          if ($("#column_count_input").val() != $("#column_count").val()) {
              showSave();
          }
          $("#column_count_input").val($("#column_count").val());
          $(".dashboard_grid_column").each(function(){
              var widget_col = $(this);
              $.each(widget_col.attr("class").split(' '), function(index, classname) {
                  if (classname.indexOf('col-md') > -1) {
                      widget_col.removeClass(classname);
                  }
              });
              widget_col.addClass('col-md-'+(12 / $("#column_count_input").val()));
          });
      });
      $("#column_count").change();
      // trigger initial ajax data poller
      process_widget_data();

      // in "Add Widget" dialog, hide widgets already on screen
      $("#add_widget_btn").click(function(){
          $(".widgetdiv").each(function(widget){
              if ($(this).is(':visible')) {
                  $("#add_widget_" + $(this).attr('id')).hide();
              } else {
                  $("#add_widget_" + $(this).attr('id')).show();
              }
          });
      });
      $('.selectpicker_widget').selectpicker('refresh');
  });
</script>

<section class="page-content-main" style="display:none;>
  <form method="post" id="iform">
    <input type="hidden" value="dashboard" name="origin" id="origin" />
    <input type="hidden" value="" name="sequence" id="sequence" />
    <input type="hidden" value="<?= $pconfig['column_count'];?>" name="column_count" id="column_count_input" />
  </form>
    <div class="container-fluid">
      <div class="row">
        <div class="col-md-12 col-xs-12">
          <?php print_service_banner('bootup') ?>
          <?php print_service_banner('livecd') ?>
        </div>
      </div>
      <div id="dashboard_container" class="row" style="display:none">
        <div class="col-xs-12 col-md-2 dashboard_grid_column hidden" id="dashboard_colx">

<?php
      foreach ($widgetCollection as $widgetItem):
          $widgettitle = $widgetItem['name'] . "_title";
          $widgettitlelink = $widgetItem['name'] . "_title_link";
          switch ($widgetItem['state']) {
              case "show":
                  $divdisplay = "block";
                  $display = "block";
                  $inputdisplay = "show";
                  $mindiv = "inline";
                  break;
              case "hide":
                  $divdisplay = "block";
                  $display = "none";
                  $inputdisplay = "hide";
                  $mindiv = "none";
                  break;
              case "close":
                  $divdisplay = "none";
                  $display = "block";
                  $inputdisplay = "close";
                  $mindiv = "inline";
                  break;
              default:
                  $divdisplay = "none";
                  $display = "block";
                  $inputdisplay = "none";
                  $mindiv = "inline";
                  break;
          }?>
          <section class="widgetdiv" data-sortkey="<?=$widgetItem['sortKey'] ?>" id="<?=$widgetItem['name'];?>"  style="display:<?=$divdisplay;?>;">
            <div class="content-box">
              <header class="content-box-head container-fluid">
                <ul class="list-inline __nomb">
                  <li><h3>
<?php
                    // XXX: ${$} is intentional here, the widgets leave global vars [widget_name]_title_link and [widget_name]_title
                    if (isset(${$widgettitlelink})):?>
                        <u><span onclick="location.href='/<?= html_safe(${$widgettitlelink}) ?>'" style="cursor:pointer">
<?php
                    endif;
                        echo empty(${$widgettitle}) ? $widgetItem['display_name'] : ${$widgettitle};
                    if (isset(${$widgettitlelink})):?>
                        </span></u>
<?php
                    endif;?>
                  </h3></li>
                  <li class="pull-right">
                    <div class="btn-group">
                      <button type="button" class="btn btn-default btn-xs disabled" id="<?= $widgetItem['name'] ?>-configure" onclick='return configureWidget("<?=  $widgetItem['name'] ?>")' style="cursor:pointer"><i></i></button>
                      <button type="button" class="btn btn-default btn-xs" title="minimize" id="<?= $widgetItem['name'] ?>-min" onclick='return minimizeWidget("<?= $widgetItem['name'] ?>",true)' style="display:<?= $mindiv ?>;"><i></i></button>
                      <button type="button" class="btn btn-default btn-xs"  id="<?= $widgetItem['name'] ?>-max" onclick='return showWidget("<?= $widgetItem['name'] ?>",true)' style="display:<?= $mindiv == 'none' ? 'inline' : 'none' ?>;"><i></i></button>
                      <button type="button" class="btn btn-default btn-xs"  onclick='return closeWidget("<?= $widgetItem['name'] ?>",true)'><i></i></button>
                    </div>
                  </li>
                </ul>
                <div class="container-fluid widget-sort-handle">
                </div>
              </header>
              <div class="content-box-main collapse in" id="<?= $widgetItem['name'] ?>-container" style="display:<?= $mindiv ?>">
                <input type="hidden" value="<?= $inputdisplay ?>" id="<?= $widgetItem['name'] ?>-config" name="<?= $widgetItem['name'] ?>-config" />
<?php
                if ($divdisplay != "block"):?>
                  <div id="<?= $widgetItem['name'] ?>-loader" style="display:<?= $display ?>;">
                      &nbsp;&nbsp;<i class="fa fa-refresh"></i> <?= gettext("Save to load widget") ?>
                  </div>
<?php
                else:
                    include($widgetItem['filename']);
                endif;
?>
              </div>
            </div>
          </section>
<?php
          endforeach;?>
          </div>
          <div class="col-md-2 dashboard_grid_column" id="dashboard_col1"></div>
          <div class="col-md-2 dashboard_grid_column" id="dashboard_col2"></div>
          <div class="col-md-2 dashboard_grid_column" id="dashboard_col3"></div>
          <div class="col-md-2 dashboard_grid_column" id="dashboard_col4"></div>
          <div class="col-md-2 dashboard_grid_column" id="dashboard_col5"></div>
          <div class="col-md-2 dashboard_grid_column" id="dashboard_col6"></div>
      </div>
    </div>
</section>
<?php endif;

include("foot.inc");
