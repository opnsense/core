<?php

/*
    Copyright (C) 2014-2016 Deciso B.V.
    Copyright (C) 2009 Ermal Luçi
    Copyright (C) 2004 Scott Ullrich
    Copyright (C) 2003-2004 Manuel Kasper <mk@neon1.net>.
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
require_once("interfaces.inc");
require_once("services.inc");
require_once('plugins.inc.d/igmpproxy.inc');

$a_igmpproxy = array();
if (isset($config['igmpproxy']['igmpentry'])) {
    $a_igmpproxy = &$config['igmpproxy']['igmpentry'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['act']) && $_POST['act'] == "del") {
        if (isset($_POST['id']) && !empty($a_igmpproxy[$_POST['id']])){
            unset($a_igmpproxy[$_POST['id']]);
            write_config();
            igmpproxy_configure_do();
        }
        header(url_safe('Location: /services_igmpproxy.php'));
        exit;
    }
}

$service_hook = 'igmpproxy';

include("head.inc");

legacy_html_escape_form_data($a_igmpproxy);

$main_buttons = array(
    array('label' => gettext('Add a new IGMP entry'), 'href' => 'services_igmpproxy_edit.php'),
);

?>
<body>
  <script type="text/javascript">
  $( document ).ready(function() {
    // delete host action
    $(".act_delete_entry").click(function(event){
      event.preventDefault();
      var id = $(this).data("id");
      // delete single
      BootstrapDialog.show({
        type:BootstrapDialog.TYPE_DANGER,
        title: "<?= gettext("IGMP Proxy");?>",
        message: "<?=gettext("Do you really want to delete this igmp entry? All elements that still use it will become invalid (e.g. filter rules)!");?>",
        buttons: [{
                  label: "<?= gettext("No");?>",
                  action: function(dialogRef) {
                      dialogRef.close();
                  }}, {
                  label: "<?= gettext("Yes");?>",
                  action: function(dialogRef) {
                    $.post(window.location, {act: 'del', id:id}, function(data) {
                        location.reload();
                    });
                }
              }]
      });
    });
  });
  //]]>
  </script>
<?php include("fbegin.inc"); ?>
  <section class="page-content-main">
    <div class="container-fluid">
      <div class="row">
        <section class="col-xs-12">
          <div class="content-box">
            <form method="post" name="iform" id="iform">
              <div class="table-responsive">
                <table class="table table-striped">
                  <thead>
                    <tr>
                      <td><?=gettext("Name");?></td>
                      <td><?=gettext("Type");?></td>
                      <td><?=gettext("Values");?></td>
                      <td><?=gettext("Description");?></td>
                      <td></td>
                    </tr>
                  </thead>
                  <tbody>
<?php
                  $i = 0;
                  foreach ($a_igmpproxy as $igmpentry): ?>
                    <tr>
                      <td>
                        <?=htmlspecialchars(convert_friendly_interface_to_friendly_descr($igmpentry['ifname']));?>
                      </td>
                      <td><?=$igmpentry['type'];?></td>
                      <td>
<?php
                        $addresses = implode(", ", array_slice(explode(" ", $igmpentry['address']), 0, 10));?>
                        <?=$addresses;?> <?=count($addresses) < 10 ? "" : "...";?>
                      </td>
                      <td><?=$igmpentry['descr'];?></td>
                      <td>
                         <a href="services_igmpproxy_edit.php?id=<?=$i;?>" title="<?=gettext("Edit this IGMP entry"); ?>" class="btn btn-default btn-xs"><span class="glyphicon glyphicon-pencil"></span></a>
                         <a href="#" data-id="<?=$i;?>" class="act_delete_entry"><button type="button" class="btn btn-xs btn-default"><span class="fa fa-trash text-muted"></span></button></a>
                      </td>
                    </tr>
<?php
                    $i++;
                  endforeach; ?>
                    <tr>
                      <td colspan="5">
                         <?=gettext("Please add the interface for upstream, the allowed subnets, and the downstream interfaces you would like the proxy to allow. Only one 'upstream' interface can be configured.");?>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </form>
          </div>
        </section>
      </div>
    </div>
  </section>
<?php include("foot.inc"); ?>
