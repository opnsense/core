<?php

/*
 * Copyright (C) 2014-2015 Deciso B.V.
 * Copyright (C) 2007 Seth Mos <seth.mos@dds.nl>
 * Copyright (C) 2004-2009 Scott Ullrich <sullrich@gmail.com>
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

require_once("guiconfig.inc");
require_once("interfaces.inc");
require_once("system.inc");
require_once("plugins.inc.d/unbound.inc");

$rrdcfg = &config_read_array('rrd');
$unboundcfg = &config_read_array('OPNsense', 'unboundplus', 'general');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $pconfig = [];
    $pconfig['rrdenable'] = isset($rrdcfg['enable']);
    $pconfig['unboundenable'] = !empty($unboundcfg['stats']);
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pconfig = $_POST;
    $configure_unbound = false;
    if (!empty($pconfig['action']) && $pconfig['action'] == "ResetRRD") {
        $savemsg = gettext('RRD data has been cleared.');
        configd_run('health flush *');
    } elseif (!empty($pconfig['action']) && $pconfig['action'] == "flush_file") {
        $savemsg = gettext('RRD report has been cleared.');
        configdp_run('health flush', [$pconfig['filename']]);
    } elseif (!empty($pconfig['action']) && $pconfig['action'] == "flush_netflow") {
        $savemsg = gettext('All local netflow data has been cleared.');
        configd_run('netflow flush');
    } elseif (!empty($pconfig['action']) && $pconfig['action'] == "repair_netflow") {
        $savemsg = gettext('Database repair in progress, daemon will start when done.');
        configd_run('netflow aggregate stop');
        configd_run('netflow aggregate repair', true);
    } elseif (!empty($pconfig['action']) && $pconfig['action'] == "SaveDNS") {
        $configure_unbound = true;
        $unboundcfg['stats'] = !empty($pconfig['unboundenable']) ? '1' : '0';
        $savemsg = get_std_save_message();
        write_config();
    } elseif (!empty($pconfig['action']) && $pconfig['action'] == "ResetDNS") {
        $savemsg = gettext('All local Unbound statistics data has been cleared.');
        configd_run('unbound qstats reset');
    } else {
        $rrdcfg['enable'] = !empty($pconfig['rrdenable']);
        $savemsg = get_std_save_message();
        write_config();
    }

    if ($configure_unbound) {
        unbound_configure_do();
    } else {
        plugins_configure('monitor');
        /* rrd graphs depend on a cronjob */
        system_cron_configure();
    }
}

$all_rrd_files = json_decode(configd_run('health list'), true);
if (!is_array($all_rrd_files)) {
    $all_rrd_files = [];
}
ksort($all_rrd_files);

legacy_html_escape_form_data($pconfig);

include("head.inc");

?>
<body>
<script>
//<![CDATA[
$(document).ready(function() {
    // messagebox, flush all rrd graphs
    $("#ResetRRD").click(function(event){
        event.preventDefault();
        BootstrapDialog.show({
            type:BootstrapDialog.TYPE_DANGER,
            title: "<?= gettext("RRD");?>",
            message: "<?=gettext('Do you really want to reset the RRD graphs? This will erase all graph data.');?>",
            buttons: [{
                    label: "<?= gettext("No");?>",
                    action: function(dialogRef) {
                        dialogRef.close();
                    }}, {
                      label: "<?= gettext("Yes");?>",
                      action: function(dialogRef) {
                        $("#action").val("ResetRRD");
                        $("#iform").submit()
                    }
                }]
        });
    });
    // flush all netflow data
    $("#flush_netflow").click(function(event){
        event.preventDefault();
        BootstrapDialog.show({
            type:BootstrapDialog.TYPE_DANGER,
            title: "<?= gettext("Netflow/Insight");?>",
            message: "<?=gettext('Do you really want to reset the netflow data? This will erase all Insight graph data.');?>",
            buttons: [{
                    label: "<?= gettext("No");?>",
                    action: function(dialogRef) {
                        dialogRef.close();
                    }}, {
                      label: "<?= gettext("Yes");?>",
                      action: function(dialogRef) {
                        $("#action").val("flush_netflow");
                        $("#iform").submit()
                    }
                }]
        });
    });
    $("#repair_netflow").click(function(event){
        event.preventDefault();
        BootstrapDialog.show({
            type:BootstrapDialog.TYPE_DANGER,
            title: "<?= gettext("Netflow/Insight");?>",
            message: "<?=gettext('Do you really want to force a repair of the netflow data? This might take a while.');?>",
            buttons: [{
                    label: "<?= gettext("No");?>",
                    action: function(dialogRef) {
                        dialogRef.close();
                    }}, {
                      label: "<?= gettext("Yes");?>",
                      action: function(dialogRef) {
                        $("#action").val("repair_netflow");
                        $("#iform").submit()
                    }
                }]
        });
    });

    $(".act_flush").click(function(event){
        var filename = $(this).data('id');
        event.preventDefault();
        BootstrapDialog.show({
            type:BootstrapDialog.TYPE_DANGER,
            title: filename,
            message: "<?=gettext('Do you really want to reset the selected graph?');?>",
            buttons: [{
                    label: "<?= gettext("No");?>",
                    action: function(dialogRef) {
                        dialogRef.close();
                    }}, {
                      label: "<?= gettext("Yes");?>",
                      action: function(dialogRef) {
                        $("#action").val("flush_file");
                        $("#filename").val(filename);
                        $("#iform").submit()
                    }
                }]
        });
    });

    $("#SaveDNS").click(function(event) {
        event.preventDefault();
        $("#action").val("SaveDNS");
        $("#iform").submit();
    });

    $("#ResetDNS").click(function(event) {
        event.preventDefault();
        BootstrapDialog.show({
            type:BootstrapDialog.TYPE_DANGER,
            message: "<?=gettext('Do you really want to reset the Unbound statistics data?');?>",
            buttons: [{
                label: "<?= gettext("No");?>",
                action: function(dialogRef) {
                    dialogRef.close();
                }}, {
                label: "<?= gettext("Yes");?>",
                action: function(dialogRef) {
                    $("#action").val("ResetDNS");
                    $("#iform").submit()
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
<?php
      if (isset($savemsg)) {
          print_info_box($savemsg);
      }
?>
        <form method="post" name="iform" id="iform">
          <input type="hidden" id="action" name="action" value="" />
          <input type="hidden" id="filename" name="filename" value="" />
          <section class="col-xs-12">
            <div class="tab-content content-box col-xs-12">
              <div class="table-responsive">
                <table class="table table-striped opnsense_standard_table_form">
                  <tr>
                    <td colspan="2"><strong><?=gettext('Unbound DNS reporting');?></strong></td>
                  </tr>
                  <tr>
                    <td style="width:22%"><i class="fa fa-info-circle text-muted"></i> <?=gettext("Statistics");?></td>
                    <td>
                        <input name="unboundenable" type="checkbox" id="unboundenable" value="yes" <?=!empty($pconfig['unboundenable']) ? "checked=\"checked\"" : ""?> />
                        &nbsp;<strong><?=gettext("Enables local gathering of statistics.");?></strong>
                    </td>
                  </tr>
                  <tr>
                    <td>&nbsp;</td>
                    <td>
                        <input type="button" name="SaveDNS" id="SaveDNS" class="btn btn-primary" value="<?= html_safe(gettext("Save")) ?>" />
                        <input type="button" name="ResetDNS" id="ResetDNS" class="btn btn-default" value="<?= html_safe(gettext("Reset DNS data")) ?>" />
                    </td>
                  </tr>
                </table>
              </div>
            </div>
          </section>
          <section class="col-xs-12">
            <div class="tab-content content-box col-xs-12">
              <div class="table-responsive">
                <table class="table table-striped opnsense_standard_table_form">
                  <tr>
                    <td colspan="2"><strong><?=gettext('Reporting Database Options');?></strong></td>
                  </tr>
                  <tr>
                    <td style="width:22%"><i class="fa fa-info-circle text-muted"></i> <?=gettext("Round-Robin-Database");?></td>
                    <td>
                      <input name="rrdenable" type="checkbox" id="rrdenable" value="yes" <?=!empty($pconfig['rrdenable']) ? "checked=\"checked\"" : ""?> />
                      &nbsp;<strong><?=gettext("Enables the RRD graphing backend.");?></strong>
                    </td>
                  </tr>
                  <tr>
                    <td>&nbsp;</td>
                    <td>
                      <button name="Submit" type="submit" class="btn btn-primary" value="yes"><?= gettext('Save') ?></button>
                      <input type="button" name="ResetRRD" id="ResetRRD" class="btn btn-default" value="<?= html_safe(gettext("Reset RRD Data")) ?>" />
                      <input type="button" id="flush_netflow" class="btn btn-default" value="<?= html_safe(gettext("Reset Netflow Data")) ?>" />
                      <input type="button" id="repair_netflow" class="btn btn-default" value="<?= html_safe(gettext("Repair Netflow Data")) ?>" />
                    </td>
                  </tr>
                  <tr>
                    <td colspan="2">
                      <?=gettext("Graphs will not be allowed to be recreated within a 1 minute interval, please " .
                        "take this into account after changing the style.");?>
                    </td>
                  </tr>
                </table>
              </div>
            </div>
            <div class="tab-content content-box col-xs-12">
              <div class="table-responsive">
                <table class="table table-striped opnsense_standard_table_form">
                  <tr>
                    <td style="width:22%"><i class="fa fa-info-circle text-muted"></i> <?=gettext("Collected Reports");?> </td>
                    <td>
                      <table class="table table-condensed">
<?php
                        foreach ($all_rrd_files as $rrd_name => $rrd_file):?>
                        <tr>
                          <td>
                            <button class="act_flush btn btn-default btn-xs"
                                    title="<?=gettext("flush report");?>" data-toggle="tooltip"
                                    data-id="<?=$rrd_file['filename'];?>">
                              <i class="fa fa-trash fa-fw"></i>
                            </button>
                            <?=$rrd_name;?>
                          </td>
                        </tr>
<?php
                        endforeach;?>
                      </table>
                    </td>
                  </tr>
                </table>
              </div>
            </div>
          </section>
        </form>
      </div>
    </div>
  </section>
<?php

include("foot.inc");
