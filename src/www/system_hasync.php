<?php

/*
 * Copyright (C) 2014-2015 Deciso B.V.
 * Copyright (C) 2012 Darren Embry <dse@webonastick.com>
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

$a_hasync = &config_read_array('hasync');

$checkbox_names = array(
    'pfsyncenabled',
    'disablepreempt',
    'synchronizealiases',
    'synchronizeauthservers',
    'synchronizecerts',
    'synchronizedhcpd',
    'synchronizenat',
    'synchronizerules',
    'synchronizeschedules',
    'synchronizestaticroutes',
    'synchronizeusers',
    'synchronizevirtualip',
    'synchronizewidgets',
);

$syncplugins = plugins_xmlrpc_sync();

foreach (array_keys($syncplugins) as $key) {
    $checkbox_names[] = 'synchronize'.$key;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $pconfig = array();
    foreach ($checkbox_names as $name) {
        if (isset($a_hasync[$name])) {
            $pconfig[$name] = $a_hasync[$name];
        } else {
            $pconfig[$name] = null;
        }
    }
    foreach (array('pfsyncpeerip','pfsyncinterface','synchronizetoip','username','password') as $tag) {
        if (isset($a_hasync[$tag])) {
            $pconfig[$tag] = $a_hasync[$tag];
        } else {
            $pconfig[$tag] = null;
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input_errors = array();
    $pconfig = $_POST;

    foreach ($checkbox_names as $name) {
        if (isset($pconfig[$name])) {
            $a_hasync[$name] = $pconfig[$name];
        } else {
            $a_hasync[$name] = false;
        }
    }

    if (!empty($pconfig['pfsyncpeerip']) && !is_ipaddrv4($pconfig['pfsyncpeerip'])) {
        $input_errors[] = gettext('The synchronize peer IP must be an IPv4 address or left empty.');
    }

    if (!count($input_errors)) {
        $a_hasync['pfsyncinterface'] = $pconfig['pfsyncinterface'];
        $a_hasync['synchronizetoip'] = $pconfig['synchronizetoip'];
        $a_hasync['username'] = $pconfig['username'];
        $a_hasync['password'] = $pconfig['password'];

        if (!empty($pconfig['pfsyncpeerip'])) {
            $a_hasync['pfsyncpeerip'] = $pconfig['pfsyncpeerip'];
        } elseif (isset($a_hasync['pfsyncpeerip'])) {
            unset($a_hasync['pfsyncpeerip']);
        }

        write_config('Updated High Availability configuration');
        interfaces_carp_setup();

        header(url_safe('Location: /system_hasync.php'));
        exit;
    }
}

legacy_html_escape_form_data($pconfig);

include("head.inc");

?>
<body>
<?php include("fbegin.inc"); ?>
<section class="page-content-main">
  <form method="post">
    <div class="container-fluid">
      <div class="row">
<?php
    if (isset($input_errors) && count($input_errors)) {
        print_input_errors($input_errors);
    }
?>
        <section class="col-xs-12">
          <div class="tab-content content-box col-xs-12 __mb">
            <div class="table-responsive">
              <table class="table table-striped opnsense_standard_table_form">
                <tr>
                  <td style="width:22%"><strong><?= gettext('State Synchronization') ?></strong></td>
                  <td style="width:78%; text-align:right">
                    <small><?=gettext("full help"); ?> </small>
                    <i class="fa fa-toggle-off text-danger" style="cursor: pointer;" id="show_all_help_page"></i>
                  </td>
                </tr>
                <tr>
                  <td><a id="help_for_pfsyncenabled" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext('Synchronize States') ?></td>
                  <td>
                    <input type="checkbox" name="pfsyncenabled" value="on" <?= !empty($pconfig['pfsyncenabled']) ? "checked=\"checked\"" : "";?> />
                    <div class="hidden" data-for="help_for_pfsyncenabled">
                      <?= sprintf(gettext('pfsync transfers state insertion, update, and deletion messages between firewalls.%s' .
                        'Each firewall sends these messages out via multicast on a specified interface, using the PFSYNC protocol (%sIP Protocol 240%s).%s' .
                        'It also listens on that interface for similar messages from other firewalls, and imports them into the local state table.%s' .
                        'This setting should be enabled on all members of a failover group.'), '<br/>','<a href="https://www.openbsd.org/faq/pf/carp.html" target="_blank">','</a>','<br/>','<br/>') ?>
                      <div class="well well-sm" ><b><?=gettext('Clicking save will force a configuration sync if it is enabled! (see Configuration Synchronization Settings below)') ?></b></div>
                    </div>
                  </td>
                </tr>
                <tr>
                  <td><a id="help_for_disablepreempt" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext('Disable preempt') ?></td>
                  <td>
                    <input type="checkbox" name="disablepreempt" value="on" <?= !empty($pconfig['disablepreempt']) ? "checked=\"checked\"" : "";?> />
                    <div class="hidden" data-for="help_for_disablepreempt">
                      <?=gettext("When this device is configured as CARP master it will try to switch to master when powering up, this option will keep this one slave if there already is a master on the network. A reboot is required to take effect.");?>
                    </div>
                  </td>
                </tr>
                <tr>
                  <td><a id="help_for_pfsyncinterface" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext('Synchronize Interface') ?></td>
                  <td>
                    <select name="pfsyncinterface" class="selectpicker" data-style="btn-default" data-live-search="true">
<?php
                    $ifaces = get_configured_interface_with_descr();
                    $ifaces["lo0"] = gettext("loopback");
                    foreach ($ifaces as $ifname => $iface):
?>
                      <option value="<?=htmlentities($ifname);?>" <?= ($pconfig['pfsyncinterface'] === $ifname) ? 'selected="selected"' : ''; ?>>
                        <?= htmlentities($iface); ?>
                      </option>
<?php
                    endforeach; ?>
                    </select>
                    <div class="hidden" data-for="help_for_pfsyncinterface">
                      <?=gettext('If Synchronize States is enabled, it will utilize this interface for communication.') ?><br/><br/>
                      <div class="well">
                        <ul>
                        <li><?=gettext('We recommend setting this to a interface other than LAN! A dedicated interface works the best.') ?></li>
                        <li><?=gettext('You must define an IP on each machine participating in this failover group.') ?></li>
                        <li><?=gettext('You must have an IP assigned to the interface on any participating sync nodes.') ?></li>
                        </ul>
                      </div>
                    </div>
                  </td>
                </tr>
                <tr>
                  <td><a id="help_for_pfsyncpeerip" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext('Synchronize Peer IP') ?></td>
                  <td>
                    <input name="pfsyncpeerip" type="text" placeholder="224.0.0.240" value="<?=$pconfig['pfsyncpeerip']; ?>" />
                    <div class="hidden" data-for="help_for_pfsyncpeerip">
                      <?=gettext('Setting this option will force pfsync to synchronize its state table to this IP address. The default is directed multicast.') ?>
                    </div>
                  </td>
                </tr>
              </table>
            </div>
          </div>
          <div class="tab-content content-box col-xs-12 __mb">
            <div class="table-responsive">
              <table class="table table-striped opnsense_standard_table_form">
                <tr>
                  <td colspan="2">
                    <strong><?= gettext('Configuration Synchronization Settings (XMLRPC Sync)') ?></strong>
                    <small><a href="/status_habackup.php"><?=gettext("Perform synchronization");?> </a></small>
                  </td>
                </tr>
                <tr>
                  <td style="width:22%"><a id="help_for_synchronizetoip" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?= gettext('Synchronize Config to IP') ?></td>
                  <td>
                    <input name="synchronizetoip" type="text" value="<?=$pconfig['synchronizetoip']; ?>" />
                    <div class="hidden" data-for="help_for_synchronizetoip">
                      <?=gettext('Enter the IP address of the firewall to which the selected configuration sections should be synchronized.') ?><br />
                      <div class="well">
                        <ul>
                          <li><?=sprintf(gettext('When using XMLRPC sync to a backup machine running on another port/protocol please input the full url (example: %s)'), 'https://192.168.1.1:444/') ?></li>
                          <li><?=gettext('For setting up the backup machine leave this field empty, and do not forget to allow incoming connections on the specified interface for synchronization.') ?></li>
                        </ul>
                      </div>
                    </div>
                  </td>
                </tr>
                <tr>
                  <td><a id="help_for_username" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext('Remote System Username') ?></td>
                  <td>
                    <input  name="username" type="text" value="<?=$pconfig['username'];?>" />
                    <div class="hidden" data-for="help_for_username">
                      <?=gettext('Enter the web GUI username of the system entered above for synchronizing your configuration.') ?><br />
                      <div class="well well-sm">
                        <b><?=gettext('Do not use the Synchronize Config to IP and username option on backup cluster members!') ?></b>
                      </div>
                    </div>
                  </td>
                </tr>
                <tr>
                  <td><a id="help_for_password" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext('Remote System Password') ?></td>
                  <td>
                    <input  type="password" name="password" value="<?=$pconfig['password']; ?>" />
                    <div class="hidden" data-for="help_for_password">
                      <?=gettext('Enter the web GUI password of the system entered above for synchronizing your configuration.') ?><br />
                      <div class="well well-sm">
                        <b><?=gettext('Do not use the Synchronize Config to IP and password option on backup cluster members!') ?></b>
                      </div>
                    </div>
                  </td>
                </tr>
                <!-- Hook xmlrpc sync plugins -->
<?php
                foreach ($syncplugins as $syncid => $synccnf):?>
                <tr>
                  <td><a id="help_for_synchronize<?=$syncid?>" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=$synccnf['description'];?></td>
                  <td>
                    <input type="checkbox" name="synchronize<?=$syncid?>" value="on" <?=!empty($pconfig['synchronize'.$syncid]) ? "checked=\"checked\"" :"";?> />
                    <div class="hidden" data-for="help_for_synchronize<?=$syncid?>">
                      <?=$synccnf['help'];?>
                    </div>
                  </td>
                </tr>
<?php
                endforeach;?>
              </table>
            </div>
          </div>
          <div class="tab-content content-box col-xs-12">
            <div class="table-responsive">
              <table class="table table-striped opnsense_standard_table_form">
                <tr>
                  <td style="width:22%"></td>
                  <td>
                    <input name="Submit" type="submit" class="btn btn-primary" value="<?= html_safe(gettext('Save')) ?>" />
                    <input type="button" class="btn btn-default" value="<?= html_safe(gettext('Cancel')) ?>" onclick="window.location.href='/system_hasync.php'" />
                  </td>
                </tr>
              </table>
            </div>
          </div>
        </section>
      </div>
    </div>
  </form>
</section>


<?php include("foot.inc");
