<?php

/*
    Copyright (C) 2014-2015 Deciso B.V.
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
require_once("system.inc");

include("head.inc");

?>
<body>
<?php

include("fbegin.inc");

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['Submit'])): ?>

<script>
$(document).ready(function() {
    BootstrapDialog.show({
        type:BootstrapDialog.TYPE_INFO,
        title: '<?= html_safe(gettext('Your device is rebooting')) ?>',
        closable: false,
        onshow:function(dialogRef){
            dialogRef.setClosable(false);
            dialogRef.getModalBody().html(
                '<?= html_safe(gettext('The system is rebooting now, please wait...')) ?>' +
                ' <i class="fa fa-cog fa-spin"></i>'
            );
            setTimeout(rebootWait, 45000);
        },
    });

    function rebootWait() {
        $.ajax({
            url: '/',
            timeout: 2500
        }).fail(function () {
            setTimeout(rebootWait, 2500);
        }).done(function () {
            $(location).attr('href', '/');
        });
    }
});
</script>

<?php
      endif; ?>

<section class="page-content-main">
  <div class="container-fluid col-xs-12 col-sm-10 col-md-9">
    <div class="row">
      <section class="col-xs-12">
        <form action="<?=$_SERVER['REQUEST_URI'];?>" method="post">
          <p><strong><?=gettext("Are you sure you want to reboot the system?");?></strong></p>
          <div class="btn-group">
            <input type="submit" name="Submit" class="btn btn-primary" value="<?=gettext("Yes");?>" />
            <a href="/" class="btn btn-default"><?=gettext("No");?></a>
          </div>
        </form>
      </section>
    </div>
  </div>
</section>
<?php

include("foot.inc");

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['Submit'])) {
    system_reboot();
}
