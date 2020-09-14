<?php

/*
    Copyright (C) 2014 Deciso B.V.
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

$data_tabs = array("info" => gettext("info"), "memory" => gettext("memory"), "timeouts" => gettext("timeouts"), "interfaces" => gettext("interfaces"), "rules" => gettext("rules"), "nat" => gettext("nat"));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['getactivity'])) {
        $diag =  configd_run("filter diag info json");
        echo $diag;
    }
    exit;
}

include("head.inc");

?>
<body>
<?php include("fbegin.inc"); ?>
<script>
$( document ).ready(function() {
  function getpfinfo() {
    jQuery.ajax({
      type: "post",
      url: "/diag_pf_info.php",
      data: 'getactivity=yes',
      dataType: "json",
      success: function(data) {
          // push data into tabs
          $.each(data, function(key, value) {
              if ($("#data_"+key.toLowerCase()).length) {
                  $("#data_"+key.toLowerCase()).text(value);
              }
          });
          setTimeout(getpfinfo, 2000);
      }
    });
  }

  getpfinfo();
});
</script>
<section class="page-content-main">
  <div class="container-fluid col-xs-12">
    <div class="row">
        <?php print_service_banner('firewall'); ?>
        <section class="col-xs-12">
          <ul class="nav nav-tabs" data-tabs="tabs" id="maintabs">
<?php
             foreach(array_keys($data_tabs) as $i => $tabname):?>
            <li <?= $i == 0 ? 'class="active"' : '';?>>
              <a data-toggle="tab" href="#<?=$tabname;?>" id="<?=$tabname;?>_tab">
                <?=ucfirst($data_tabs[$tabname]);?>
              </a>
            </li>
<?php
            endforeach;?>
          </ul>
          <div class="tab-content content-box">
<?php
             foreach(array_keys($data_tabs) as $i => $tabname):?>
            <div id="<?=$tabname;?>" class="tab-pane fade in <?= $i == 0 ? 'active' : '';?>">
              <br/>
              <div class="container-fluid">
                <pre id="data_<?=$tabname;?>" class="pre-scrollable" >
                <?=gettext("Gathering PF information, please wait...");?>
                </pre>
              </div>
              <br/>
            </div>
<?php
            endforeach;?>
          </div>
     </section>
    </div>
  </div>
</section>
<?php include("foot.inc"); ?>
