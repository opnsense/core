<?php

/*
    Copyright (C) 2014 Deciso B.V.
    Copyright (C) 2010 Jim Pingle <jimp@pfsense.org>
    Copyright (C) 2010 Scott Ullrich <sullrich@gmail.com>
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
    if (!empty($_GET['tablename'])) {
        $tablename = htmlspecialchars($_GET['tablename']);
    } else {
        // Set default table
        $tablename = "sshlockout";
    }
    if (isset($_GET['savemsg'])) {
        $savemsg = htmlspecialchars($_GET['savemsg']);
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_POST['tablename'])) {
          $tablename = $_POST['tablename'];
    }
    if (isset($_POST['act']) && $_POST['act'] == 'update_bogons') {
        try {
            configd_run("filter update bogons");
        } catch (Exception $e) {
            $savemsg = gettext("The bogons database has NOT been updated.");
        } finally {
            $savemsg = gettext("The bogons database has been updated.");
        }
        echo $savemsg;
        exit;
    } elseif (isset($_POST['act']) && $_POST['act'] == 'delete') {
        // delete entry
        if((is_ipaddr($_REQUEST['address']) || is_subnet($_REQUEST['address'])) && !empty($tablename)) {
              $delEntry = escapeshellarg($_REQUEST['address']);
              $delTable = escapeshellarg($tablename);
              configd_run("filter delete table {$delTable} {$delEntry}");
              header(url_safe('Location: /diag_tables.php?tablename=%s', array($tablename)));
              exit;
        }
    } elseif (isset($_POST['act']) && $_POST['act'] == 'flush')  {
        $delTable = escapeshellarg($tablename);
        configd_run("filter delete table {$delTable} ALL");
        header(url_safe('Location: /diag_tables.php?tablename=%s', array($tablename)));
        exit;
    }
}

// fetch list of tables and content of selected table
$tables = json_decode(configd_run("filter list tables json"));
if (in_array($tablename, $tables)) {
    $entries = json_decode(configd_run("filter list table {$tablename} json"));
} else {
    $entries = array();
}

include("head.inc");

?>
<body>
<?php include("fbegin.inc"); ?>


<script>
$( document ).ready(function() {
    // on change pfTable selection
     $("#tablename").change(function(){
         window.location='diag_tables.php?tablename=' + $(this).val();
     });
     $("#refresh").click(function(){
         $("#tablename").change();
     });

    // delete entry
    $(".act_delete").click(function(event){
        event.preventDefault()
        var address = $(this).attr("data-address");
        $("#address").val(address);
        $("#action").val("delete");
        $("#iform").submit();
    });

    // update bogons
    $("#update_bogons").click(function(event){
        event.preventDefault()
        $("#update_bogons_progress").addClass("fa fa-spinner fa-pulse");
        //update_bogons
        jQuery.ajax({
            type: "post",
            url: "/diag_tables.php",
            data:{'act':'update_bogons'},
            success: function(data) {
                // reload page when finished, send result as savemessage.
                window.location='diag_tables.php?tablename=' + $("#tablename").val()+'&savemsg='+data;
            }
        });
    });

    // flush table.. first ask user if it's ok to do so..
    $("#flushtable").click(function(event){
      event.preventDefault()
      BootstrapDialog.show({
        type:BootstrapDialog.TYPE_DANGER,
        title: "<?= gettext("Tables");?>",
        message: "<?=gettext("Do you really want to flush this table?");?>",
        buttons: [{
          label: "<?= gettext("No");?>",
          action: function(dialogRef) {
            dialogRef.close();
          }}, {
            label: "<?= gettext("Yes");?>",
            action: function(dialogRef) {
              $("#action").val("flush");
              $("#iform").submit()
            }
          }]
      });
    });
});
</script>

<section class="page-content-main">
  <div class="container-fluid">
    <div class="row">
      <?php if (isset($savemsg)) print_info_box($savemsg); ?>
      <form method="post" id="iform" action="<?=$_SERVER['REQUEST_URI'];?>">
        <input type="hidden" name="act" id="action"/>
        <input type="hidden" name="address" id="address"/>
        <section class="col-xs-12">
          <select id="tablename" name="tablename" class="selectpicker" data-width="auto" data-live-search="true">
<?php
          foreach ($tables as $table):?>
            <option value="<?=$table;?>" <?=$tablename == $table ? " selected=\"selected\"" : "";?>>
                <?=$table;?>
            </option>
<?php
           endforeach;?>
          </select>
          <button class="btn btn-default" id="refresh"><i class="fa fa-refresh" aria-hidden="true"></i></button>
          <button class="btn btn-default" id="flushtable"><?=gettext("Flush");?></button>
          <button class="btn btn-default pull-right" id="update_bogons"><i id="update_bogons_progress" class=""></i>
            <?=gettext("Update bogons");?>
          </button>
        </section>
        <section class="col-xs-12">
          <div class="content-box">
            <div class="table-responsive">
              <table class="table table-striped">
                <tr>
                  <td colspan="2"><?=gettext("IP Address");?></td>
                </tr>
<?php
               if (count($entries) ==0):?>
                <tr>
                  <td colspan="2"><?=gettext("No entries exist in this table.");?></td>
                </tr>
<?php
              endif;
              foreach ($entries as $entry):?>
                <tr>
                  <td><?=$entry;?></td>
                  <td>
                    <a data-address="<?=$entry;?>" title="<?=gettext("delete this entry"); ?>" data-toggle="tooltip"  class="act_delete btn btn-default btn-xs">
                      <span class="fa fa-trash text-muted"></span>
                    </a>
                  </td>
                </tr>
<?php
              endforeach;?>
              </table>
            </div>
          </div>
        </section>
      </form>
    </div>
  </div>
</section>
<?php include('foot.inc');?>
