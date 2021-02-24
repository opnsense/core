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

// closing should be $_POST, but the whole notice handling needs more attention. Leave it as is for now.
if (isset($_REQUEST['closenotice'])) {
    close_notice($_REQUEST['closenotice']);
    echo get_menu_messages();
    exit;
}

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
              <img src=" <?= cache_safe("/ui/themes/{$themename}/build/images/default-logo.png") ?>" border="0" alt="logo" style="max-width:380px;" />
<?php endif ?>
              <br />
              <div class="content-box-main" style="padding-bottom:0px;">
                <?php
                    if (isset($config['trigger_initial_wizard'])) {
                        echo '<p>' . sprintf(gettext('Welcome to %s!'), $g['product_name']) . "</p>\n";
                        echo '<p>' . gettext('One moment while we start the initial setup wizard.') . "</p>\n";
                        echo '<p class="__nomb">' . gettext('To bypass the wizard, click on the logo in the upper left corner.') . "</p>\n";
                    } else {
                        echo '<p>' . sprintf(gettext('Congratulations! %s is now configured.'), $g['product_name']) . "</p>\n";
                        echo '<p>' . sprintf(gettext(
                            'Please consider donating to the project to help us with our overhead costs. ' .
                            'See %sour website%s to donate or purchase available %s support services.'),
                            '<a target="_new" href="' . $g['product_website'] . '">', '</a>', $g['product_name']) . "</p>\n";
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

<section class="page-content-main">
  <form method="post" id="iform">
    <input type="hidden" value="dashboard" name="origin" id="origin" />
    <input type="hidden" value="" name="sequence" id="sequence" />
    <input type="hidden" value="<?= $pconfig['column_count'];?>" name="column_count" id="column_count_input" />
  </form>
    <div class="container-fluid">
      <div class="row">
        <div class="col-md-12 col-xs-12">
<?php
          print_service_banner('livecd');
          $crash_report = get_crash_report();
          if (!empty($crash_report)) {
              print_info_box($crash_report);
          }?>
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
                    if (isset($widgettitlelink)):?>
                        <u><span onclick="location.href='/<?= $widgettitlelink ?>'" style="cursor:pointer">
<?php
                    endif;
                        echo empty($widgettitle) ?   $widgetItem['display_name'] : $widgettitle;
                    if (isset($widgettitlelink)):?>
                        </span></u>
<?php
                    endif;?>
                  </h3></li>
                  <li class="pull-right">
                    <div class="btn-group">
                      <button type="button" class="btn btn-default btn-xs disabled" id="<?= $widgetItem['name'] ?>-configure" onclick='return configureWidget("<?=  $widgetItem['name'] ?>")' style="cursor:pointer"><i class="fa fa-pencil fa-fw"></i></button>
                      <button type="button" class="btn btn-default btn-xs" title="minimize" id="<?= $widgetItem['name'] ?>-min" onclick='return minimizeWidget("<?= $widgetItem['name'] ?>",true)' style="display:<?= $mindiv ?>;"><i class="fa fa-minus fa-fw"></i></button>
                      <button type="button" class="btn btn-default btn-xs" title="maximize" id="<?= $widgetItem['name'] ?>-max" onclick='return showWidget("<?= $widgetItem['name'] ?>",true)' style="display:<?= $mindiv == 'none' ? 'inline' : 'none' ?>;"><i class="fa fa-plus fa-fw"></i></button>
                      <button type="button" class="btn btn-default btn-xs" title="remove widget" onclick='return closeWidget("<?= $widgetItem['name'] ?>",true)'><i class="fa fa-remove fa-fw"></i></button>
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
