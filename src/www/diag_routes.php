<?php

/*
    Copyright (C) 2014-2016 Deciso B.V.
    Copyright (C) 2006 Fernando Lamos
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

include('guiconfig.inc');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST['resolve'])) {
        $resolve = '-n';
    } else {
        $resolve =  '';
    }
    echo configd_run("system routes list {$resolve} json");
    exit;
}

include('head.inc');
?>
<body>

<?php include("fbegin.inc"); ?>
<link rel="stylesheet" type="text/css" href="/ui/css/jquery.bootgrid.css"/>
<script type="text/javascript" src="/ui/js/jquery.bootgrid.js"></script>

<script type="text/javascript">
  $( document ).ready(function() {
    var gridopt = {
         ajax: false,
         selection: false,
         multiSelect: false
     };
     $("#grid-routes").bootgrid('destroy');
     $("#grid-routes").bootgrid(gridopt);

     // update routes
     $("#update").click(function() {
       $("#loading").show();
       if ($("#resolve").prop("checked")) {
          resolve = "yes";
       } else {
          resolve = "";
       }
       $.post(window.location, {resolve: resolve}, function(data) {
         $("#grid-routes").bootgrid('destroy');
         var html = [];
         $.each(data, function (key, value) {
             var fields = ["proto", "destination", "gateway", "flags", "use", "mtu", "netif", "expire"];
             tr_str = '<tr>';
             for (var i = 0; i < fields.length; i++) {
                 if (value[fields[i]] != null) {
                     tr_str += '<td>' + value[fields[i]] + '</td>';
                 } else {
                     tr_str += '<td></td>';
                 }
             }
             tr_str += '</tr>';
             html.push(tr_str);
         });
         $("#grid-routes > tbody").html(html.join(''));
         $("#grid-routes").bootgrid(gridopt);
         $("#loading").hide();
       }, "json");
     });

     // initial load
     $("#update").click();
  });
</script>

<section class="page-content-main">
  <div class="container-fluid">
    <div class="row">
      <section class="col-xs-12">
        <div class="tab-content content-box col-xs-12">
          <div class="table-responsive">
            <table class="table table-striped">
              <thead>
                <tr>
                  <th><?=gettext("Name resolution");?></th>
                  <th></th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td>
                    <input type="checkbox" class="formfld" id="resolve" name="resolve" value="yes">
                  </td>
                  <td>
                    <p class="text-muted">
                      <em>
                        <small>
                          <?=gettext("Enable this to attempt to resolve names when displaying the tables.");?><br/>
                          <?= gettext('Note:') ?> <?=gettext("By enabling name resolution, the query should take a bit longer. You can stop it at any time by clicking the Stop button in your browser.");?>
                        </small>
                      </em>
                    </p>
                  </td>
                  <td>
                    <input type="button" id="update" class="btn btn-primary" value="<?=gettext("Update"); ?>" />
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
          <div class="table-responsive">
            <table id="grid-routes" class="table table-condensed table-hover table-striped table-responsive">
              <thead>
               <tr>
                   <th data-column-id="proto" data-type="string" ><?=gettext("Proto");?></th>
                   <th data-column-id="destination" data-type="string" data-identifier="true"><?=gettext("Destination");?></th>
                   <th data-column-id="gateway" data-type="string"><?=gettext("Gateway");?></th>
                   <th data-column-id="flags" data-type="string" data-css-class="hidden-xs hidden-sm" data-header-css-class="hidden-xs hidden-sm"><?=gettext("Flags");?></th>
                   <th data-column-id="use" data-type="string" data-css-class="hidden-xs hidden-sm" data-header-css-class="hidden-xs hidden-sm"><?=gettext("Use");?></th>
                   <th data-column-id="mtu" data-type="string" data-css-class="hidden-xs hidden-sm" data-header-css-class="hidden-xs hidden-sm"><?=gettext("MTU");?></th>
                   <th data-column-id="netif" data-type="string" data-css-class="hidden-xs hidden-sm" data-header-css-class="hidden-xs hidden-sm"><?=gettext("Netif");?></th>
                   <th data-column-id="expire" data-type="string" data-css-class="hidden-xs hidden-sm" data-header-css-class="hidden-xs hidden-sm"><?=gettext("Expire");?></th>
               </tr>
             </thead>
             <tbody>
             </tbody>
             <tfoot>
               <tr>
                   <td colspan="6" id="loading"><?=gettext("loading....");?></td>
               </tr>
             </tfoot>
            </table>
          </div>
        </div>
      </section>
    </div>
  </div>
</section>
<?php
include('foot.inc');?>
