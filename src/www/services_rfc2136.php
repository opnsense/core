<?php

/*
    Copyright (C) 2014-2015 Deciso B.V.
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
require_once("interfaces.inc");
require_once("services.inc");

if (!isset($config['dnsupdates']['dnsupdate'])) {
    $config['dnsupdates']['dnsupdate'] = array();
}

$a_rfc2136 = &$config['dnsupdates']['dnsupdate'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['act']) && $_POST['act'] == "del" && isset($_POST['id'])) {
        if (!empty($a_rfc2136[$_POST['id']])) {
            unset($a_rfc2136[$_POST['id']]);
            write_config();
            configd_run('dyndns reload', true);
        }
        exit;
    } elseif (isset($_POST['act']) && $_POST['act'] == "toggle" && isset($_POST['id'])) {
        if (!empty($a_rfc2136[$_POST['id']])) {
            if (!empty($a_rfc2136[$_POST['id']]['enable'])) {
                $a_rfc2136[$_POST['id']]['enable'] = false;
            } else {
                $a_rfc2136[$_POST['id']]['enable'] = true;
            }
            write_config();
            configd_run('dyndns reload', true);
        }
        exit;
    }
}


include("head.inc");
legacy_html_escape_form_data($a_rfc2136);
$main_buttons = array(
    array('label' => gettext('Add'), 'href' => 'services_rfc2136_edit.php'),
);
?>

<body>
  <script type="text/javascript">
  $( document ).ready(function() {
    // delete service action
    $(".act_delete_service").click(function(event){
      event.preventDefault();
      var id = $(this).data("id");
      BootstrapDialog.show({
        type:BootstrapDialog.TYPE_DANGER,
        title: "<?= gettext("RFC 2136");?>",
        message: "<?=gettext("Do you really want to delete this client?");?>",
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
<section class="page-content-main">
  <div class="container-fluid">
    <div class="row">
      <?php if (isset($input_errors) && count($input_errors) > 0) print_input_errors($input_errors); ?>
      <section class="col-xs-12">
        <div class="tab-content content-box col-xs-12">
          <form method="post" name="iform" id="iform">
              <div class="table-responsive">
                <table class="table table-striped">
                  <thead>
                    <tr>
                      <th><?=gettext("If");?></th>
                      <th><?=gettext("Server");?></th>
                      <th><?=gettext("Hostname");?></th>
                      <th><?=gettext("Cached IP");?></th>
                      <th><?=gettext("Description");?></th>
                      <th></th>
                    </tr>
                  </thead>
                  <tbody>
<?php
                    $i = 0;
                    foreach ($a_rfc2136 as $rfc2136): ?>
                    <tr>
                      <td>
                        <a href="#" class="act_toggle" data-id="<?=$i;?>" data-toggle="tooltip" title="<?=(!empty($rfc2136['enable'])) ? gettext("disable") : gettext("enable");?>">
                          <span class="glyphicon glyphicon-play <?=(!empty($rfc2136['enable'])) ? "text-success" : "text-muted";?>"></span>
                        </a>
                        <?=!empty($config['interfaces'][$rfc2136['interface']]['descr']) ? $config['interfaces'][$rfc2136['interface']]['descr'] : strtoupper($rfc2136['interface']);?>
                      </td>
                      <td><?=$rfc2136['server'];?></td>
                      <td><?=$rfc2136['host'];?></td>
                      <td>
<?php
                        $filename = "/conf/dyndns_{$rfc2136['interface']}_rfc2136_" . escapeshellarg($rfc2136['host']) . "_{$rfc2136['server']}.cache";
                        if (file_exists($filename) && !empty($rfc2136['enable'])) {
                            echo "IPv4: ";
                            if (isset($rfc2136['usepublicip'])) {
                                $ipaddr = dyndnsCheckIP($rfc2136['interface']);
                            } else {
                                $ipaddr = get_interface_ip($rfc2136['interface']);
                            }
                            $cached_ip_s = explode("|", file_get_contents($filename));
                            $cached_ip = $cached_ip_s[0];
                            if ($ipaddr <> $cached_ip) {
                                echo "<font color='red'>";
                            } else {
                                echo "<font color='green'>";
                            }
                            echo htmlspecialchars($cached_ip);
                            echo "</font>";
                        } else {
                            echo "IPv4: N/A";
                        }
                        echo "<br />";
                        if (file_exists("{$filename}.ipv6")) {
                            echo "IPv6: ";
                            $ipaddr = get_interface_ipv6($rfc2136['interface']);
                            $cached_ip_s = explode("|", file_get_contents("{$filename}.ipv6"));
                            $cached_ip = $cached_ip_s[0];
                            if ($ipaddr <> $cached_ip) {
                                echo "<font color='red'>";
                            } else {
                                echo "<font color='green'>";
                            }
                            echo htmlspecialchars($cached_ip);
                            echo "</font>";
                        } else {
                          echo "IPv6: N/A";
                        }?>
                      </td>
                      <td><?=$rfc2136['descr'];?></td>
                      <td>
                        <a href="services_rfc2136_edit.php?id=<?=$i;?>">
                          <button type="button" class="btn btn-xs btn-default"><span class="fa fa-pencil"></span></button>
                        </a>
                        <a href="#" data-id="<?=$i;?>" class="act_delete_service"><button type="button" class="btn btn-xs btn-default"><span class="fa fa-trash text-muted"></span></button></a>
                      </td>
                    </tr>
<?php
                      $i++;
                    endforeach; ?>
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
