<?php

/*
    Copyright (C) 2014 Deciso B.V.
    Copyright (C) 2005 Colin Smith
    Copyright (C) 2010 Jim Pingle
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

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $cnf = OPNsense\Core\Config::getInstance();
    $confvers = $cnf->getBackups(true);
    if (!empty($_GET['getcfg'])) {
        foreach ($confvers as $filename => $revision) {
            if ($revision['time'] == $_GET['getcfg']) {
                $exp_name = urlencode("config-{$config['system']['hostname']}.{$config['system']['domain']}-{$_GET['getcfg']}.xml");
                $exp_data = file_get_contents($filename);
                $exp_size = strlen($exp_data);

                header("Content-Type: application/octet-stream");
                header("Content-Disposition: attachment; filename={$exp_name}");
                header("Content-Length: $exp_size");
                echo $exp_data;
                exit;
            }
        }
    } elseif (!empty($_GET['diff']) && isset($_GET['oldtime']) && isset($_GET['newtime'])
          && is_numeric($_GET['oldtime']) && (is_numeric($_GET['newtime']) || ($_GET['newtime'] == 'current'))) {
        $oldfile = '';
        $newfile = '';
        // search filenames to compare
        foreach ($confvers as $filename => $revision) {
            if ($revision['time'] == $_GET['oldtime']) {
                $oldfile = $filename;
            }
            if ($revision['time'] == $_GET['newtime']) {
                $newfile = $filename;
            }
        }

        $diff = '';

        $oldtime = $_GET['oldtime'];
        $oldcheck = $oldtime;

        if ($_GET['newtime'] == 'current') {
            $newfile = '/conf/config.xml';
            $newtime = $config['revision']['time'];
        } else {
            $newtime = $_GET['newtime'];
            $newcheck = $newtime;
        }

        if (file_exists($oldfile) && file_exists($newfile)) {
            exec("/usr/bin/diff -u " . escapeshellarg($oldfile) . " " . escapeshellarg($newfile), $diff);
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cnf = OPNsense\Core\Config::getInstance();
    $confvers = $cnf->getBackups(true);
    if (!empty($_POST['backupcount'])) {
        if (is_numeric($_POST['backupcount']) && ($_POST['backupcount'] >= 0)) {
            $config['system']['backupcount'] = $_POST['backupcount'];
        } else {
            unset($config['system']['backupcount']);
        }
        write_config(gettext('Changed backup revision count.'));
    } elseif (!empty($_POST['act']) && $_POST['act'] == "revert") {
        foreach ($confvers as $filename => $revision) {
            if (isset($revision['time']) && $revision['time'] == $_POST['time']) {
                if (config_restore($filename)== 0) {
                    $savemsg = sprintf(gettext('Successfully reverted to timestamp %s with description "%s".'), date(gettext("n/j/y H:i:s"), $_POST['id']), $revision['description']);
                } else {
                    $savemsg = gettext("Unable to revert to the selected configuration.");
                }
                break;
            }
        }
    } elseif (!empty($_POST['act']) && $_POST['act'] == "delete") {
        foreach ($confvers as $filename => $revision) {
            if (isset($revision['time']) && $revision['time'] == $_POST['time']) {
                if (file_exists($filename)) {
                    @unlink($filename);
                    $savemsg = sprintf(gettext('Deleted backup with timestamp %s and description "%s".'), date(gettext("n/j/y H:i:s"), $revision['time']),$revision['description']);
                } else {
                    $savemsg = gettext("Unable to delete the selected configuration.");
                }
                unset($confvers[$filename]);
                break;
            }
        }
    }

}


include("head.inc");
?>


<script type="text/javascript">
//<![CDATA[
$( document ).ready(function() {
    // revert config dialog
    $(".act_revert").click(function(){
        var id = $(this).data('id');
        BootstrapDialog.show({
          type:BootstrapDialog.TYPE_INFO,
          title: "<?= gettext("Action");?>",
          message: "<?=gettext("Restore from Configuration Backup");?> <br/> <?=gettext('Version');?>: " + id,
          buttons: [{
                    label: "<?= gettext("No");?>",
                    action: function(dialogRef) {
                        dialogRef.close();
                    }}, {
                    label: "<?= gettext("Yes");?>",
                    action: function(dialogRef) {
                      $("#time").val(id);
                      $("#action").val("revert");
                      $("#iform").submit()
                  }
                }]
        });
    });

    // delete backup dialog
    $(".act_delete").click(function(){
        var id = $(this).data('id');
        BootstrapDialog.show({
          type:BootstrapDialog.TYPE_INFO,
          title: "<?= gettext("Action");?>",
          message: "<?=gettext("Remove Configuration Backup");?> <br/> <?=gettext('Version');?>: " + id,
          buttons: [{
                    label: "<?= gettext("No");?>",
                    action: function(dialogRef) {
                        dialogRef.close();
                    }}, {
                    label: "<?= gettext("Yes");?>",
                    action: function(dialogRef) {
                      $("#time").val(id);
                      $("#action").val("delete");
                      $("#iform").submit()
                  }
                }]
        });
    });

});
//]]>
</script>


<body>
  <?php
    include("fbegin.inc");
    if($savemsg)
      print_info_box($savemsg);
  ?>

  <section class="page-content-main">
    <div class="container-fluid">
      <div class="row">
        <section class="col-xs-12">
          <div class="container-fluid">
            <form method="post" id="iform">
              <input type="hidden" id="time" name="time" value="" />
              <input type="hidden" id="action" name="act" value="" />
              <section style="margin-bottom:15px;">
                <div class="content-box">
                  <div class="content-box-main">
                    <div class="table-responsive">
                      <table class="table table-striped">
                        <thead>
                          <tr>
                            <th><?=gettext("Backup Count");?></th>
                            <th></th>
                          </tr>
                        </thead>
                        <tbody>
                          <tr>
                            <td><input name="backupcount" type="text" class="formfld unknown" size="5" value="<?=htmlspecialchars($config['system']['backupcount']);?>"/></td>
                            <td><?= gettext("Enter the number of older configurations to keep in the local backup cache. By default this is 30."); ?></td>
                          </tr>
                          <tr>
                            <td>
                              <input name="save" type="submit" class="btn btn-primary" value="<?=gettext("Save"); ?>" />
                            </td>
                          </tr>
                        </tbody>
                      </table>
                      <hr/>
                      <div class="container-fluid">
                        <?= gettext("NOTE: Be aware of how much space is consumed by backups before adjusting this value. Current space used by backups: "); ?> <?= exec("/usr/bin/du -sh /conf/backup | /usr/bin/awk '{print $1;}'") ?>
                      </div>
                    </div>
                  </div>
                </div>
              </section>
            </form>
            <?php if ($diff): ?>
            <section style="margin-bottom:15px;">
              <div class="content-box">
                <header class="content-box-head container-fluid">
                    <h3><?=gettext("Configuration diff from");?> <?= date(gettext("n/j/y H:i:s"), $oldtime); ?> <?=gettext("to");?> <?=date(gettext("n/j/y H:i:s"), $newtime); ?></h3>
                </header>
                <div class="content-box-main">
                  <div class="container-fluid __mb">
                    <div class="table-responsive" style="overflow: scroll;">
                      <table class="table table-condensed table-striped">
<?php
                      foreach ($diff as $line):
                        switch (substr($line, 0, 1)) {
                          case '+':
                            $color = '#3bbb33';
                            break;
                          case '-':
                            $color = '#c13928';
                            break;
                          case '@':
                            $color = '#3bb9c3';
                            break;
                          default:
                            $color = '#000000';
                        }
                        ?>
                        <tr>
                          <td style="color: <?=$color;?>; white-space: pre-wrap; font-family: monospace;"><?=htmlentities($line);?></td>
                        </tr>
<?php
                      endforeach;?>
                    </table>
                </div>
                 </div>
              </div>
              </div>
            </section>
            <?php endif; ?>
            <form method="get">
            <section>
              <div class="content-box">
                <header class="content-box-head container-fluid">
                  <h3><?=gettext('History');?></h3>
                </header>
                <div class="content-box-main">
                  <div class="container-fluid __mb">
                    <table class="table table-condensed">
                      <tr>
                        <td>
                          <button type="submit" name="diff" class="btn btn-primary pull-left" value="Diff">
                            <?=gettext('View differences');?>
                          </button>
                        </td>
                        <td>
                          <?= gettext("To view the differences between an older configuration and a newer configuration, select the older configuration using the left column of radio options and select the newer configuration in the right column, then press the button."); ?>
                        </td>
                      </tr>
                    </table>
                  </div>
                  <table class="table table-striped">
                    <thead>
                      <tr>
                        <th colspan="2"><?=gettext("Diff");?></th>
                        <th><?=gettext("Date");?></th>
                        <th><?=gettext("Version");?></th>
                        <th><?=gettext("Size");?></th>
                        <th><?=gettext("Configuration Change");?></th>
                        <th>&nbsp;</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr>
                        <td></td>
                        <td>
                          <input type="radio" name="newtime" value="current" <?= !isset($newcheck) || $newcheck == 'current' ? 'checked="checked"' : '' ?>/>
                        </td>
                        <td> <?=date(gettext("n/j/y H:i:s"), $config['revision']['time']) ?></td>
                        <td> <?=$config['version'] ?></td>
                        <td> <?=format_bytes(filesize("/conf/config.xml")) ?></td>
                        <td> <?="{$config['revision']['username']}: {$config['revision']['description']}" ?></td>
                        <td><b><?=gettext("Current");?></b></td>
                      </tr>
<?php
                    $i = 0;
                    foreach($confvers as $version):?>
                      <tr>
                        <td>
                          <input type="radio" name="oldtime" value="<?=$version['time'];?>" <?= (!isset($oldcheck) && $i == 0)  || (isset($oldcheck) && $oldcheck == $version['time']) ? 'checked="checked"' : '' ?>/>
                        </td>
                        <td>
                          <input type="radio" name="newtime" value="<?=$version['time'];?>" <?= isset($newcheck) && $newcheck == $version['time'] ? 'checked="checked"' : ''?>/>
                        </td>
                        <td> <?= date(gettext("n/j/y H:i:s"), $version['time']) ?></td>
                        <td> <?= $version['version'] ?></td>
                        <td> <?= format_bytes($version['filesize']) ?></td>
                        <td> <?= "{$version['username']}: {$version['description']}" ?></td>
                        <td>
                          <a data-id="<?=$version['time'];?>" href="#" class="act_revert btn btn-default btn-xs" data-toggle="tooltip" data-placement="left" title="<?=gettext("Revert to this configuration");?>">
                             <span class="glyphicon glyphicon-log-in"></span>
                           </a>
                           <a data-id="<?=$version['time'];?>" href="#" class="act_delete btn btn-default btn-xs" data-toggle="tooltip" data-placement="left" title="<?=gettext("Remove this backup");?>" >
                             <span class="glyphicon glyphicon-remove"></span>
                           </a>
                           <a href="diag_confbak.php?getcfg=<?=$version['time'];?>" class="btn btn-default btn-xs" title="<?=gettext("Download this backup");?>">
                           <span class="glyphicon glyphicon-download"></span>
                         </a>
                        </td>
                      </tr>
<?php
                    $i++;
                    endforeach;?>
                    </tbody>
                  </table>
                </div>
              </div>
            </section>
          </form>
        </div>
      </section>
    </div>
  </div>
</section>
<?php include("foot.inc"); ?>
