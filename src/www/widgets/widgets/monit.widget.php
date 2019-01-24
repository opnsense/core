<?php

/*
 * Copyright (C) 2018-2019 EURO-LOG AG
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * 1. Redistributions of source code must retain the above copyright notice,
 * this list of conditions and the following disclaimer.
 *
 * 2. Redistributions in binary form must reproduce the above copyright
 * notice, this list of conditions and the following disclaimer in the
 * documentation and/or other materials provided with the distribution.
 *
 * THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
 * INClUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
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
require_once("widgets/include/monit.inc");

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
   if (isset($config['widgets']['monitheight']) && is_numeric($config['widgets']['monitheight'])) {
      $pconfig['monitheight'] = $config['widgets']['monitheight'];
   }
   else {
      $pconfig['monitheight'] = 9;
   }
   if (isset($config['widgets']['monitsearch'])) {
      $pconfig['monitsearch'] = $config['widgets']['monitsearch'];
   }
   else {
      unset($pconfig['monitsearch']);
   }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pconfig = $_POST;
    if (isset($pconfig['monitheight']) && is_numeric($pconfig['monitheight'])) {
        $config['widgets']['monitheight'] = $pconfig['monitheight'];
    } else {
        $config['widgets']['monitheight'] = 9;
    }
    if (isset($pconfig['monitsearch'])) {
        $config['widgets']['monitsearch'] = $pconfig['monitsearch'];
    } else {
        unset($config['widgets']['monitsearch']);
    }
    write_config("Saved Monit Widget Settings via Dashboard");
    header(url_safe('Location: /index.php'));
    exit;
}

?>

<style>

   .monit-widget-table {
      width: 100%
   }

   .monit-widget-table > thead,
   .monit-widget-table > tbody,
   .monit-widget-table > thead > tr,
   .monit-widget-table > tbody > tr,
   .monit-widget-table > thead > tr > th,
   .monit-widget-table > tbody > tr > td {
      display: block;
      line-height: 1.5em;
   }

   .monit-widget-table > tbody > tr:after,
   .monit-widget-table > thead > tr:after {
      content: ' ';
      display: block;
      visibility: hidden;
      clear: both;
   }

   .monit-widget-table > tbody {
      overflow-y: auto;
      height: <?php echo ($pconfig['monitheight'] * 2) + 2; ?>em;
   }

   .monit-widget-table > tbody > tr > td,
   .monit-widget-table > thead > tr > th {
      width: 33%;
      float: left;
   }

</style>
<link rel="stylesheet" type="text/css" href="<?= cache_safe(get_themed_filename('/css/jquery.bootgrid.css')) ?>">
<script src="<?= cache_safe('/ui/js/jquery.bootgrid.js') ?>"></script>

<script>

$( document ).ready(function() {

   const monitServiceTypes = [
      "<?= gettext('Filesystem') ?>",
      "<?= gettext('Directory') ?>",
      "<?= gettext('File') ?>",
      "<?= gettext('Process') ?>",
      "<?= gettext('Host') ?>",
      "<?= gettext('System') ?>",
      "<?= gettext('Fifo') ?>",
      "<?= gettext('Custom') ?>",
      "<?= gettext('Network') ?>"
   ];

   const monitStatusTypes = [
      "<?= gettext('OK') ?>",
      "<?= gettext('Failed') ?>",
      "<?= gettext('Changed') ?>",
      "<?= gettext('Not changed') ?>"
   ];

   // avoid running code twice due to <script> location within <body>
   if (typeof monitPollInit === 'undefined') {
      monitPollInit = false;
   } else {
      monitPollInit = true;
   }

   function monitStatusPoll() {
      var pollInterval = 10000;
      ajaxCall("/api/monit/status/get/xml", {}, function(data, status) {
         $("#grid-monit").bootgrid("clear");
         if (data['result'] === 'ok') {
            pollInterval = data['status']['server']['poll'] * 1000;
            $.each(data['status']['service'], function(index, service) {
               $("#grid-monit").bootgrid("append", [{
                  name: service['name'],
                  type: monitServiceTypes[service['@attributes']['type']],
                  status: monitStatusTypes[service['status']]
               }]);
            });
         }
         <?php
            // apply search filter
            if (isset($config['widgets']['monitsearch'])) {
               echo '$("#grid-monit").bootgrid("search", "' .
                        $config['widgets']['monitsearch'] . '");';
            }
         ?>
         setTimeout(monitStatusPoll, pollInterval);
      });
   };

   if (monitPollInit === false) {
      $("#grid-monit").bootgrid({
         navigation: 0,
         rowCount: -1
      }).on("loaded.rs.jquery.bootgrid", function() {
         // set right margin according to the scrollbar width
         let scrollWidth = $('.monit-widget-table > tbody')[0].offsetWidth - $('.monit-widget-table > tbody')[0].clientWidth;
         $('.monit-widget-table > thead').css('margin-right', scrollWidth);
      });
      monitStatusPoll();
   }
});

</script>

<div id="monit-settings" class="widgetconfigdiv" style="display:none;">
   <form class="form-inline" action="/widgets/widgets/monit.widget.php" method="post" name="iformd">
      <div class="table-responsive">
         <table class="table table-striped table-condensed">
            <tr>
               <td>
                  <div class="control-label" id="control_label_monitheight">
                     <b><?= gettext('Rows') ?></b>
                  </div>
               </td>
               <td>
                  <input type="text" class="form-control" size="25" name="monitheight" id="monitheight" value="<?= $config['widgets']['monitheight'] ?>" />
               </td>
            </tr>
            <tr>
               <td>
                  <div class="control-label" id="control_label_monitsearch">
                     <b><?= gettext('Search') ?></b>
                  </div>
               </td>
               <td>
                  <input type="text" class="form-control" size="25" name="monitsearch" id="monitsearch" value="<?= $config['widgets']['monitsearch'] ?>" />
               </td>
            </tr>
            <tr>
               <td>
                  <button id="submitd" name="submitd" type="submit" class="btn btn-primary" value="yes">
                     <b><?= gettext('Save') ?></b>
                  </button>
               </td>
               <td> </td>
            </tr>
         </table>
      </div>
   </form>
</div>

<table id="grid-monit" class="table table-condensed table-hove table-striped table-responsive bootgrid-table monit-widget-table">
   <thead>
      <tr>
         <th data-column-id="name"><?= gettext('Name') ?></th>
         <th data-column-id="type"><?= gettext('Type') ?></th>
         <th data-column-id="status"><?= gettext('Status') ?></th>
      </tr>
   </thead>
   <tbody>
   </tbody>
   <tfoot>
   </tfoot>
</table>

<!-- needed to display the widget settings menu -->
<script>
//<![CDATA[
  $("#monit-configure").removeClass("disabled");
//]]>
</script>
