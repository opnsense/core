<?php

/*
 * Copyright (C) 2014 Deciso B.V.
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
require_once("system.inc");

$input_errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_POST['Submit'])) {
        $user = getUserEntry($_SESSION['Username']);
        if (userHasPrivilege($user, 'user-config-readonly')) {
            $input_errors[] = gettext('You do not have the permission to perform this action.');
        }
    }
}

$default_config_ip = '192.168.1.1'; /* failsafe default */
if (is_file('/usr/local/etc/config.xml')) {
    try {
        $restore_conf = load_config_from_file('/usr/local/etc/config.xml');
        if (
          is_array($restore_conf) &&
          !empty($restore_conf['interfaces']) &&
          !empty($restore_conf['interfaces']['lan']) &&
          !empty($restore_conf['interfaces']['lan']['ipaddr'])
        ) {
            $default_config_ip = $restore_conf['interfaces']['lan']['ipaddr'];
        }
    } catch (Exception $e) { }
}

include("head.inc");

?>
<body>
<?php

include("fbegin.inc");

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['Submit']) && !count($input_errors)): ?>

<script>
$(document).ready(function() {
    BootstrapDialog.show({
        type:BootstrapDialog.TYPE_INFO,
        title: '<?= html_safe(gettext('Your device is powering off')) ?>',
        closable: false,
        message: '<?= html_safe(gettext('The system has been reset to factory defaults and is shutting down.')) ?>',
    });
});
</script>

<?php endif ?>

<section class="page-content-main">
  <div class="container-fluid">
    <div class="row">
      <?php if (count($input_errors)) print_input_errors($input_errors); ?>
      <section class="col-xs-12">
        <form method="post">
          <p><strong> <?=gettext('If you click "Yes", the system will:')?></strong></p>
          <ul>
            <li><?= gettext('Reset to factory defaults') ?></li>
            <li><?= sprintf(gettext('LAN IP address will be reset to %s'), $default_config_ip) ?></li>
            <li><?= gettext('System will be configured as a DHCP server on the default LAN interface') ?></li>
            <li><?= gettext('WAN interface will be set to obtain an address automatically from a DHCP server') ?></li>
            <li><?= gettext('Admin user name and password will be reset') ?></li>
            <li><?= gettext('Shut down after changes are complete') ?></li>
          </ul>
          <p><strong><?=gettext("Are you sure you want to proceed?");?></strong></p>
          <div class="btn-group">
            <input type="submit" name="Submit" class="btn btn-primary" value="<?= html_safe(gettext('Yes')) ?>" />
            <a href="/" class="btn btn-default"><?=gettext("No");?></a>
          </div>
        </form>
      </section>
    </div>
  </div>
</section>
<?php

include("foot.inc");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_POST['Submit'])) {
        if (!count($input_errors)) {
            reset_factory_defaults(false);
        }
    }
}
