<?php

/*
    Copyright (C) 2014-2015 Deciso B.V.
    Copyright (C) 2007 Seth Mos <seth.mos@dds.nl>
    Copyright (C) 2004-2009 Scott Ullrich <sullrich@gmail.com>
    Copyright (C) 2003-2004 Manuel Kasper <mk@neon1.net>
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
require_once("rrd.inc");
require_once("system.inc");
require_once("services.inc");

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $pconfig = array();
    $pconfig['rrdenable'] = isset($config['rrd']['enable']);
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pconfig = $_POST;
    if (!empty($_POST['action']) && $_POST['action'] == "ResetRRD") {
        $savemsg = gettext('RRD data has been cleared.');
        configd_run("systemhealth flush *");
    } elseif (!empty($_POST['action']) && $_POST['action'] == "flush_file") {
        $savemsg = gettext('RRD report has been cleared.');
        configd_run("systemhealth flush ". escapeshellarg($_POST['filename']));
    } elseif (!empty($_POST['action']) && $_POST['action'] == "flush_netflow") {
        $savemsg = gettext('All local netflow data has been cleared.');
        configd_run("netflow flush");
    } else {
        config_read_array('rrd'); /* XXX PHP 7.1 autovivification fail? */
        $config['rrd']['enable'] = !empty($_POST['rrdenable']);
        $savemsg = get_std_save_message();
        write_config();
    }

    rrd_configure();
    setup_gateways_monitor();
}

$all_rrd_files = json_decode(configd_run("systemhealth list"), true);
if (!is_array($all_rrd_files)) {
    $all_rrd_files = array();
}
ksort($all_rrd_files);
legacy_html_escape_form_data($pconfig);

include("head.inc");
?>


<body>
<script type="text/javascript">
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
            <div class="tab-content content-box col-xs-12 __mb">
              <div class="table-responsive">
                <table class="table table-striped opnsense_standard_table_form">
                  <tr>
                    <td colspan="2"><strong><?=gettext('Reporting Database Options');?></strong></td>
                  </tr>
                  <tr>
                    <td width="22%"><i class="fa fa-info-circle text-muted"></i> <?=gettext("Round-Robin-Database");?></td>
                    <td>
                      <input name="rrdenable" type="checkbox" id="rrdenable" value="yes" <?=!empty($pconfig['rrdenable']) ? "checked=\"checked\"" : ""?> />
                      &nbsp;<strong><?=gettext("Enables the RRD graphing backend.");?></strong>
                    </td>
                  </tr>
                  <tr>
                    <td>&nbsp;</td>
                    <td>
                      <input name="Submit" type="submit" class="btn btn-primary" value="<?=gettext("Save");?>" />
                      <input type="button" name="ResetRRD" id="ResetRRD" class="btn btn-default" value="<?=gettext("Reset RRD Data");?>" />
                      <input type="button" id="flush_netflow" class="btn btn-default" value="<?=gettext("Reset Netflow Data");?>" />
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
          </section>
          <section class="col-xs-12">
            <div class="tab-content content-box col-xs-12 __mb">
              <div class="table-responsive">
                <table class="table table-striped opnsense_standard_table_form">
                  <tr>
                    <td colspan="2"><strong><?=gettext('Collected reports');?></strong></td>
                  </tr>
                  <tr>
                    <td width="22%"><i class="fa fa-info-circle text-muted"></i> <?=gettext("Reports");?> </td>
                    <td>
                      <table class="table table-condensed">
<?php
                        foreach ($all_rrd_files as $rrd_name => $rrd_file):?>
                        <tr>
                          <td>
                            <button class="act_flush btn btn-default btn-xs"
                                    title="<?=gettext("flush report");?>" data-toggle="tooltip"
                                    data-id="<?=$rrd_file['filename'];?>">
                              <span class="fa fa-trash text-muted"></span>
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
<?php include("foot.inc"); ?>
