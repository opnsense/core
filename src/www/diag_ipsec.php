<?php

/*
  Copyright (C) 2014 Deciso B.V.
  Copyright (C) 2004-2009 Scott Ullrich
  Copyright (C) 2008 Shrew Soft Inc <mgrooms@shrew.net>.
  Copyright (C) 2003-2004 Manuel Kasper
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $act = $_POST['action'];
    } else {
        $act = null;
    }
    switch ($act) {
      case 'connect':
          if (!empty($_POST['connid'])) {
              configd_run("ipsec connect ".$_POST['connid']);
          }
          break;
      case 'disconnect':
          if (!empty($_POST['connid'])) {
              configd_run("ipsec disconnect ".$_POST['connid']);
          }
          break;
      default:
          break;
    }

    header("Location: diag_ipsec.php");
    exit(0);
}

$ipsec_status = json_decode(configd_run("ipsec list_status"), true);
if ($ipsec_status == null) {
    $ipsec_status = array();
}
$pgtitle = array(gettext('VPN'), gettext('IPsec'), gettext('Status Overview'));
$shortcut_section = 'ipsec';

include("head.inc");
?>
<script type="text/javascript">
  $( document ).ready(function() {
      // show / hide connection details
      $(".ipsec_info").click(function(event){
          $("#" + $(this).data('target')).toggleClass('hidden visible');
          event.preventDefault();
      });
  });
</script>
<body>

<?php include("fbegin.inc"); ?>
  <section class="page-content-main">
    <div class="container-fluid">
      <div class="row">
        <?php if (isset($input_errors) && count($input_errors) > 0) print_input_errors($input_errors); ?>
          <section class="col-xs-12">
            <div class="tab-content content-box">
              <div class="table-responsive">
                <table class="table table-striped">
                  <thead>
                  <tr>
                    <th><?= gettext("Connection");?></th>
                    <th class="hidden-xs hidden-sm"><?= gettext("Version");?></th>
                    <th class="hidden-xs"><?= gettext("Local ID");?></th>
                    <th class="hidden-xs"><?= gettext("Local IP");?></th>
                    <th class="hidden-xs"><?= gettext("Remote ID");?></th>
                    <th><?= gettext("Remote IP");?></th>
                    <th class="hidden-xs hidden-sm"><?= gettext("Local Auth");?></th>
                    <th class="hidden-xs hidden-sm"><?= gettext("Remote Auth");?></th>
                    <th><?= gettext("Status");?></th>
                  </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($ipsec_status as $ipsec_conn_key => $ipsec_conn): ?>
                      <tr>
                        <td><?= $ipsec_conn_key;?></td>
                        <td class="hidden-xs hidden-sm"><?= $ipsec_conn['version'] ?></td>
                        <td class="hidden-xs"><?= $ipsec_conn['local-id'] ?></td>
                        <td class="hidden-xs"><?= $ipsec_conn['local-addrs'] ?></td>
                        <td class="hidden-xs"><?= $ipsec_conn['remote-id'] ?></td>
                        <td><?= $ipsec_conn['remote-addrs'] ?></td>
                        <td class="hidden-xs hidden-sm"><?= $ipsec_conn['local-class'] ?></td>
                        <td class="hidden-xs hidden-sm"><?= $ipsec_conn['remote-class'] ?></td>
                        <td>
                            <?php if (count($ipsec_conn['sas'])):
?>
                            <form method="post">
                              <input type="hidden" value="<?=$ipsec_conn_key;?>" name="connid"/>
                              <button type="submit" class="btn btn-xs" name="action" value="disconnect">
                                <span class="glyphicon glyphicon-remove text-default"/>
                              </button>
                              <button type="submit" class="btn btn-xs" name="action" value="connect">
                                  <span class="glyphicon glyphicon-play text-success" title="" alt=""></span>
                              </button>
                              <button type="none" class="btn btn-xs ipsec_info" data-target="info_<?=$ipsec_conn_key?>">
                                  <span class="glyphicon glyphicon-info-sign"></i>
                              </button>
                            </form>
                            <?php else:
?>
                            <form method="post">
                              <input type="hidden" value="<?=$ipsec_conn_key;?>" name="connid"/>
                              <button type="submit" class="btn btn-xs" name="action" value="connect">
                                <span class="glyphicon glyphicon-play text-warning"/>
                              </button>
                            </form>
                            <?php endif;
?>
                        </td>
                      </tr>
                      <?php if (count($ipsec_conn['sas'])):
?>
                      <tr>
                        <td colspan="9" class="hidden" id="info_<?=$ipsec_conn_key?>">
                          <table class="table table-condensed">
                            <thead>
                              <tr>
                                <th><?= gettext("Local subnets");?></th>
                                <th class="hidden-xs hidden-sm"><?= gettext("SPI(s)");?></th>
                                <th><?= gettext("Remote subnets");?></th>
                                <th class="hidden-xs hidden-sm"><?= gettext("State");?></th>
                                <th><?= gettext("Stats");?></th>
                              </tr>
                            </head>
                            <tbody>
                            <?php foreach ($ipsec_conn['sas'] as $sa_key => $sa):?>
                              <?php foreach ($sa['child-sas'] as $child_sa_key => $child_sa):?>
                              <tr>
                                <td>
                                  <?= implode('<br/>', $child_sa['local-ts'])?>
                                </td>
                                <td class="hidden-xs hidden-sm">
                                  <?=gettext('in')?> : <?= $child_sa['spi-in'] ?><br/>
                                  <?=gettext('out')?> : <?= $child_sa['spi-out'] ?>
                                </td>
                                <td>
                                  <?= implode('<br/>', $child_sa['remote-ts'])?>
                                </td>
                                <td class="hidden-xs hidden-sm">
                                  <?= $child_sa['state']?>
                                </td>
                                <td>
                                    <small>
                                    <?= gettext('Time');?>  : <?= $child_sa['install-time']?><br/>
                                    <?= gettext('Bytes in');?> : <?= $child_sa['bytes-in']?><br/>
                                    <?= gettext('Bytes out');?> : <?= $child_sa['bytes-out']?>
                                    </small>
                                </td>
                              </tr>
                              <?php endforeach; ?>
                            <?php endforeach; ?>
                            </tbody>
                          </table>
                        </td>
                      </tr>
                      <?php endif;
?>

                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </section>
      </div>
    </div>
  </section>
<?php
include("foot.inc"); ?>
