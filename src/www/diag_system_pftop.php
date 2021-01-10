<?php

/*
 * Copyright (C) 2014 Deciso B.V.
 * Copyright (C) 2008-2009 Scott Ullrich <sullrich@gmail.com>
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

$sorttypes = array('age', 'bytes', 'dest', 'dport', 'exp', 'none', 'peak', 'pkt', 'rate', 'size', 'sport', 'src');
$viewtypes = array('default', 'label', 'long', 'rules', 'size', 'speed', 'state', 'time');
$numstates = array('50', '100', '200', '500', '1000', '99999999999');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // fetch only valid input data (items from above lists)
    $viewtype = 'default';
    $numstate = '200';
    $sorttype ='bytes';
    if (isset($_POST['viewtype']) && in_array($_POST['viewtype'], $viewtypes)) {
        $viewtype = $_POST['viewtype'];
    }
    if (isset($_POST['states']) && in_array($_POST['states'], $numstates)) {
            $numstate = $_POST['states'];
    }
    if (isset($_POST['sorttype']) && in_array($_POST['sorttype'], $sorttypes)) {
        $sorttype = $_POST['sorttype'];
    }

    // fetch pftop data
    echo configdp_run('filter diag top', array($sorttype, $viewtype, $numstate));
    exit;
}

include("head.inc");

?>
<body>
<?php include("fbegin.inc"); ?>
<script>
$( document ).ready(function() {
    /**
     * fetch pftop data from backend
     */
    function getpftopactivity() {
      $.ajax(
        '/diag_system_pftop.php',
        {
          type: 'post',
          data: {'getactivity':'yes'
                ,'sorttype':$('#sorttype').val()
                ,'viewtype':$('#viewtype').val()
                ,'states':$('#states').val()
                },
          complete: function(transport) {
              $('#pftopactivitydiv').html('<pre>' + transport.responseText  + '<\/pre>');
              setTimeout(getpftopactivity, 2500);
          }
        });
    }

    $("#viewtype").change(function() {
      var selected = $("#viewtype option:selected").val();
      switch(selected) {
        case "rules":
          $(".show_opt").addClass("hidden");
          break;
        default:
          $(".show_opt").removeClass("hidden");
      }
    });

    // toggle initial viewtype select
    $("#viewtype").change();
    // start initial fetch
    getpftopactivity();
});
</script>

<section class="page-content-main">
  <div class="container-fluid">
    <div class="row">
      <div class="table-responsive">
        <form method="post">
          <table class="table table-striped">
            <thead>
              <tr>
                <th><?=gettext("View type:"); ?></th>
                <th class="show_opt"><?=gettext("Sort type:"); ?></th>
                <th class="show_opt"><?=gettext("Number of States:"); ?></th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td>
                  <select name='viewtype' id='viewtype' class="selectpicker" data-width="auto" data-live-search="true">
                    <option value='default' selected="selected"><?=gettext("Default");?></option>
                    <option value='label'><?=gettext("Label");?></option>
                    <option value='long'><?=gettext("Long");?></option>
                    <option value='rules'><?=gettext("Rules");?></option>
                    <option value='size'><?=gettext("Size");?></option>
                    <option value='speed'><?=gettext("Speed");?></option>
                    <option value='state'><?=gettext("State");?></option>
                    <option value='time'><?=gettext("Time");?></option>
                  </select>
                </td>
                <td class="show_opt">
                  <div>
                    <select name='sorttype' id='sorttype' class="selectpicker" data-width="auto" data-live-search="true">
                      <option value='age'><?=gettext("Age");?></option>
                      <option value='bytes'><?=gettext("Bytes");?></option>
                      <option value='dest'><?=gettext("Destination Address");?></option>
                      <option value='dport'><?=gettext("Destination Port");?></option>
                      <option value='exp'><?=gettext("Expiry");?></option>
                      <option value='none'><?=gettext("None");?></option>
                      <option value='peak'><?=gettext("Peak");?></option>
                      <option value='pkt'><?=gettext("Packet");?></option>
                      <option value='rate'><?=gettext("Rate");?></option>
                      <option value='size'><?=gettext("Size");?></option>
                      <option value='sport'><?=gettext("Source Port");?></option>
                      <option value='src'><?=gettext("Source Address");?></option>
                    </select>
                  </div>
                </td>
                <td  class="show_opt">
                  <div id='statesdiv'>
                    <select name='states' id='states' class="selectpicker" data-width="auto" data-live-search="true">
                      <option value='50'>50</option>
                      <option value='100'>100</option>
                      <option value='200' selected="selected">200</option>
                      <option value='500'>500</option>
                      <option value='1000'>1000</option>
                      <option value='99999999999'><?= gettext('all') ?></option>
                    </select>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </form>
        <section class="col-xs-12">
            <div id="pftopactivitydiv"><?=gettext("Gathering pfTOP activity, please wait...");?></div>
        </section>
      </div>
    </div>
  </div>
</section>

<?php include("foot.inc");
