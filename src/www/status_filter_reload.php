<?php

/*
    Copyright (C) 2006 Scott Ullrich
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

if($_GET['getstatus']) {
    $status = '';
    if (file_exists('/var/run/filter_reload_status')) {
        $status = file_get_contents('/var/run/filter_reload_status');
    }
    echo $status;
    exit;
}

if ($_POST['reloadfilter']) {
    configd_run("filter reload");
    if ( isset($config['hasync']['synchronizetoip']) && trim($config['hasync']['synchronizetoip']) != "") {
        // only try to sync when hasync is configured
        configd_run("filter sync reload");
    }
    header(url_safe('Location: /status_filter_reload.php'));
    exit;
}
if ($_POST['syncfilter']) {
    configd_run("filter sync");
    header(url_safe('Location: /status_filter_reload.php'));
    exit;
}

include("head.inc");

?>
<body>
  <script type="text/javascript">
  $( document ).ready(function() {
    function refresh_data() {
      $.ajax("status_filter_reload.php?getstatus=true", {
          type: 'get',
          cache: false,
          dataType: "html",
          data: {},
          success: function (data) {
            $('#status').html(data);
            setTimeout(refresh_data, 200);
          }
      });
    }
    refresh_data();
  });
  </script>
<?php include("fbegin.inc"); ?>
  <section class="page-content-main">
    <div class="container-fluid">
      <div class="row">
        <?php print_service_banner('firewall'); ?>
        <?php if (isset($input_errors) && count($input_errors) > 0) print_input_errors($input_errors); ?>
          <section class="col-xs-12">
            <div class="content-box ">
              <div class="col-xs-12">
                <p><form method="post" name="filter">
                <input type="submit" value="<?= gettext('Reload Filter') ?>" class="btn btn-primary" name="reloadfilter" id="reloadfilter" />
                <?php if (!empty($config['hasync']['synchronizetoip'])): ?>
                <input type="submit" value="<?= gettext('Force Config Sync') ?>" class="btn btn-primary" name="syncfilter" id="syncfilter" />
                <?php endif; ?>
                </form></p>
                <pre id="status"></pre>
              </div>
            </div>
          </section>
      </div>
    </div>
  </section>

<?php include("foot.inc"); ?>
