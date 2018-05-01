<?php

/*
    Copyright (C) 2014-2015 Deciso B.V.
    Copyright (C) 2007 Scott Ullrich <sullrich@gmail.com>
    Copyright (C) 2007 Bill Marquette <bill.marquette@gmail.com>
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

$save_and_test = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    # XXX this needs repairing, can also be a list
    $authsrv = auth_get_authserver($config['system']['webgui']['authmode']);
    if ($authsrv['type'] == 'ldap') {
        $save_and_test = true;
    } else {
        $savemsg = gettext('The test was not performed because it is supported only for LDAP-based backends.');
    }
}

legacy_html_escape_form_data($pconfig);
include("head.inc");

?>
<body>

<?php
if ($save_and_test):?>
<script>
    myRef = window.open('system_usermanager_settings_test.php?authserver=<?=$pconfig['authmode'];?>','mywin','left=20,top=20,width=700,height=550,toolbar=1,resizable=0');
    if (myRef==null || typeof(myRef)=='undefined') alert('<?=gettext("Popup blocker detected. Action aborted.");?>');
</script>;
<?php
endif;?>
<?php include("fbegin.inc");?>
  <section class="page-content-main">
    <div class="container-fluid">
      <div class="row">
<?php
      if (isset($savemsg)) {
        print_info_box($savemsg);
      }
?>
        <section class="col-xs-12">
          <form method="post">
            <button type="submit" class="btn btn-default">Start LDAP Test</button>
          </form>
        </section>
      </div>
    </div>
  </section>
<?php include("foot.inc");
