<?php

/*
 Copyright (C) 2018 EURO-LOG AG

 All rights reserved.
 
 Redistribution and use in source and binary forms, with or without
 modification, are permitted provided that the following conditions are met:
 
 1. Redistributions of source code must retain the above copyright notice,
 this list of conditions and the following disclaimer.
 
 2. Redistributions in binary form must reproduce the above copyright
 notice, this list of conditions and the following disclaimer in the
 documentation and/or other materials provided with the distribution.
 
 THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
 INClUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 POSSIBILITY OF SUCH DAMAGE.
 */

require_once("widgets/include/monit.inc");

?>
<link rel="stylesheet" type="text/css" href="/ui/css/jquery.bootgrid.css">
<script src="ui/js/jquery.bootgrid.js"></script>

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
      ajaxCall(url="/api/monit/status/get/xml", sendData={}, callback=function(data, status) {
         $("#grid-monit").bootgrid("clear");
         if (data['result'] === 'ok') {
            pollInterval = data['status']['server']['poll'] * 1000;
            $.each(data['status']['service'], function(index, service) {
               $("#grid-monit").bootgrid("append", [{name: service['name'], type: monitServiceTypes[service['@attributes']['type']], status: monitStatusTypes[service['status']]}]);
            });
         }
         setTimeout(monitStatusPoll, pollInterval);
      });
   };

   if (monitPollInit === false) {
      $("#grid-monit").bootgrid({
         navigation: 2,
         rowCount: 7
      });
      monitStatusPoll();
   }
});

</script>

<table id="grid-monit" class="table table-condensed table-hove table-striped table-responsive bootgrid-table">
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
