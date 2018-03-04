<?php

/*
    Copyright (C) 2014-2016 Deciso B.V.
    Copyright (C) 2008 Ermal Luçi
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
require_once("services.inc");

$a_checkipservices = &config_read_array('checkipservices', 'service');

// Append the factory default check IP service to the list.
$a_checkipservices[] = array(
    "enable" => true,
    "name" => 'Default',
    "url" => 'http://checkip.dyndns.org',
    #"url" => 'http://ip.3322.net/',    /* Chinese alternative */
    #"username" => '',
    #"password" => '',
    #"verifysslpeer" => true,
    "descr" => 'Factory Default Check IP Service'
);
$factory_default = count($a_checkipservices) - 1;

// Is the factory default check IP service disabled?
if (isset($config['checkipservices']['disable_factory_default'])) {
    unset($a_checkipservices[$factory_default]['enable']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $a_checkipservices[$_POST['id']]['name'];
    if (isset($_POST['act']) && $_POST['act'] == "del" && isset($_POST['id'])) {
        unset($a_checkipservices[$_POST['id']]);
        $wc_msg = gettext("Deleted a check IP service: %s");
    } elseif (isset($_POST['act']) && $_POST['act'] == "toggle" && isset($_POST['id'])) {
        if ($a_checkipservices[$_POST['id']]) {
            if (isset($a_checkipservices[$_POST['id']]['enable'])) {
                unset($a_checkipservices[$_POST['id']]['enable']);
                $wc_msg = gettext("Disabled a check IP service: %s");
            } else {
                $a_checkipservices[$_POST['id']]['enable'] = true;
                $wc_msg = gettext("Enabled a check IP service: %s");
            }
            if ($_POST['id'] == $factory_default) {
                if (isset($config['checkipservices']['disable_factory_default'])) {
                    unset($config['checkipservices']['disable_factory_default']);
                } else {
                    $config['checkipservices']['disable_factory_default'] = true;
                }
            }
        }
    }
    unset($a_checkipservices[$factory_default]);
    write_config(sprintf($wc_msg, htmlspecialchars($name)));

    header(url_safe('Location: /services_checkip.php'));
    exit;
}

include("head.inc");

legacy_html_escape_form_data($a_checkipservices);

$main_buttons = array(
    array('label' => gettext('Add'), 'href' => 'services_checkip_edit.php'),
);

?>
<body>
  <script>
  $( document ).ready(function() {
    // delete service action
    $(".act_delete_service").click(function(event){
      event.preventDefault();
      var id = $(this).data("id");
      BootstrapDialog.show({
        type:BootstrapDialog.TYPE_DANGER,
        title: "<?= gettext("Check IP Service");?>",
        message: "<?=gettext("Do you really want to delete this entry?");?>",
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
    // link toggle buttons
    $(".act_toggle").click(function(event){
        event.preventDefault();
        $.post(window.location, {act: 'toggle', id:$(this).data("id")}, function(data) {
            location.reload();
        });
    });
    // watch scroll position and set to last known on page load
    watchScrollPosition();
  });
  </script>

<?php include("fbegin.inc"); ?>
  <section class="page-content-main"><h2 style="display:none"><?=gettext('Check IP Services')?></h2>
    <div class="container-fluid">
      <div class="row">
        <section class="col-xs-12"><h3 style="display:none"><?=gettext('Services List')?></h3>
          <div class="tab-content content-box col-xs-12">
            <form method="post" name="iform" id="iform">
              <div class="table-responsive">
                <table class="table table-striped">
                  <thead>
                    <tr>
                      <th></th>
                      <th><?=gettext("Name")?></th>
                      <th><?=gettext("URL")?></th>
                      <th><?=gettext("Verify SSL Peer")?></th>
                      <th><?=gettext("Description")?></th>
                      <th></th>
                    </tr>
                  </thead>
                  <tbody>
<?php
                    $i = 0;
                    foreach ($a_checkipservices as $checkipservice):

                      // Hide edit and delete controls on the factory default check IP service entry (last one; id = count-1), and retain layout positioning.
                      $visibility = $i == $factory_default ? 'invisible' : 'visible';
?>
                    <tr>
                      <td class="text-left" style="width:0px">
                        <a href="#" class="act_toggle" data-id="<?=$i;?>" data-toggle="tooltip" title="<?=(!empty($checkipservice['enable'])) ? gettext("Disable") : gettext("Enable");?>">
                          <i style="width:15px" class="fa fa-play <?=(!empty($checkipservice['enable'])) ? "text-success" : "text-muted";?>"></i>
                        </a>
                      </td>
                      <td>
                        <?=htmlspecialchars($checkipservice['name'])?>
                      </td>
                      <td>
                        <a target="Check_IP" rel="noopener noreferrer" href="<?= html_safe($checkipservice['url']) ?>"><?=htmlspecialchars($checkipservice['url'])?></a>
                      </td>
                      <td class="text-center">
                        <i<?=(isset($checkipservice['verifysslpeer'])) ? ' class="fa fa-check"' : '';?>></i>
                      </td>
                      <td>
                        <?=htmlspecialchars($checkipservice['descr'])?>
                      </td>
                      <td>
                        <a href="services_checkip_edit.php?id=<?=$i;?>" class="btn btn-default btn-xs <?=$visibility?>">
                          <i class="fa fa-pencil"></i>
                        </a>
                        <a href="#" data-id="<?=$i;?>" class="act_delete_service btn btn-default btn-xs <?=$visibility?>">
                          <i class="fa fa-trash"></i>
                        </a>
                      </td>
                    </tr>
<?php
                      $i++;
                    endforeach; ?>
                    <tr>
                      <td colspan=6>
                        <?= htmlspecialchars(gettext('The first (highest in list) enabled check IP service will be used to check IP addresses for Dynamic DNS services, and RFC 2136 entries that have the "Use public IP" option enabled.')); ?><br /><br />
                        <?= htmlspecialchars(gettext('The service must return an HTML body in the format of: "<body>Current IP Address: {IP_Address}</body>"')); ?><br />
                        <?= htmlspecialchars(gettext('Example PHP: <html><head><title>Current IP Check</title></head><body>Current IP Address: <?=$_SERVER[\'REMOTE_ADDR\']?></body></html>')); ?>
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
