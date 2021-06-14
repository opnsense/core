<?php

/*
 * Copyright (C) 2015 Manuel Faux <mfaux@conf.at>
 * Copyright (C) 2014-2016 Deciso B.V.
 * Copyright (C) 2014 Warren Baker <warren@decoy.co.za>
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
require_once("interfaces.inc");
require_once("plugins.inc.d/unbound.inc");

$a_hosts = &config_read_array('unbound', 'hosts');
$a_domains = &config_read_array('unbound', 'domainoverrides');

/* Backwards compatibility for records created before introducing RR types. */
foreach ($a_hosts as $i => $hostent) {
    if (!isset($hostent['rr'])) {
        $a_hosts[$i]['rr'] = is_ipaddrv6($hostent['ip']) ? 'AAAA' : 'A';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pconfig = $_POST;
    if (!empty($pconfig['apply'])) {
        system_resolvconf_generate();
        unbound_configure_do();
        plugins_configure('dhcp');
        clear_subsystem_dirty('unbound');
        header(url_safe('Location: /services_unbound_overrides.php'));
        exit;
    } elseif (!empty($pconfig['act']) && $pconfig['act'] == 'del') {
        if (isset($pconfig['id']) && !empty($a_hosts[$pconfig['id']])) {
            unset($a_hosts[$pconfig['id']]);
            write_config();
            mark_subsystem_dirty('unbound');
            exit;
        }
    } elseif (!empty($pconfig['act']) && $pconfig['act'] == 'doverride') {
        if (isset($pconfig['id']) && !empty($a_domains[$pconfig['id']])) {
            unset($a_domains[$pconfig['id']]);
            write_config();
            mark_subsystem_dirty('unbound');
            exit;
        }
    }
}

$service_hook = 'unbound';

legacy_html_escape_form_data($a_hosts);
legacy_html_escape_form_data($a_domains);

include_once("head.inc");

?>
<body>

  <script>
  $( document ).ready(function() {
    // delete host action
    $(".act_delete_host").click(function(event){
      event.preventDefault();
      var id = $(this).data("id");
      // delete single
      BootstrapDialog.show({
        type:BootstrapDialog.TYPE_DANGER,
        title: "<?= gettext('Unbound') ?>",
        message: "<?=gettext("Do you really want to delete this host?");?>",
        buttons: [{
                  label: "<?= gettext("No");?>",
                  action: function(dialogRef) {
                      dialogRef.close();
                  }}, {
                  label: "<?= gettext("Yes");?>",
                  action: function(dialogRef) {
                    $.post(window.location, {act: 'del', id:id}, function(data) {
                        location.reload();
                    });
                }
              }]
      });
    });

    $(".act_delete_override").click(function(event){
      event.preventDefault();
      var id = $(this).data("id");
      // delete single
      BootstrapDialog.show({
        type:BootstrapDialog.TYPE_DANGER,
        title: "<?= gettext('Unbound') ?>",
        message: "<?=gettext("Do you really want to delete this domain override?");?>",
        buttons: [{
                  label: "<?= gettext("No");?>",
                  action: function(dialogRef) {
                      dialogRef.close();
                  }}, {
                  label: "<?= gettext("Yes");?>",
                  action: function(dialogRef) {
                    $.post(window.location, {act: 'doverride', id:id}, function(data) {
                        location.reload();
                    });
                }
              }]
      });
    });
  });
  </script>

<?php include("fbegin.inc"); ?>
  <section class="page-content-main">
    <div class="container-fluid">
      <div class="row">
        <?php if (is_subsystem_dirty('unbound')): ?>
        <?php print_info_box_apply(gettext('The Unbound configuration has been changed.') . ' ' . gettext('You must apply the changes in order for them to take effect.')) ?>
        <?php endif; ?>
        <form method="post" name="iform" id="iform">
          <section class="col-xs-12">
            <div class="content-box">
                <div class="table-responsive">
                  <table class="table table-striped">
                    <tbody>
                      <tr>
                        <td colspan="6"><strong><?= gettext('Host Overrides') ?></strong></td>
                      </tr>
                      <tr>
                        <td><strong><?= gettext('Host') ?></strong></td>
                        <td><strong><?= gettext('Domain') ?></strong></td>
                        <td><strong><?= gettext('Type') ?></strong></td>
                        <td><strong><?= gettext('Value') ?></strong></td>
                        <td><strong><?= gettext('Description') ?></strong></td>
                        <td class="text-nowrap">
                          <a href="services_unbound_host_edit.php" class="btn btn-primary btn-xs"><i class="fa fa-plus fa-fw"></i></a>
                        </td>
                      </tr>
<?php foreach ($a_hosts as $i => $hostent): ?>
                      <tr>
                        <td><?=strtolower($hostent['host']);?></td>
                        <td><?=strtolower($hostent['domain']);?></td>
                        <td><?=strtoupper($hostent['rr']);?></td>
                        <td>
<?php
                          /* Presentation of DNS value differs between chosen RR type. */
                          switch ($hostent['rr']) {
                              case 'A':
                              case 'AAAA':
                                  print $hostent['ip'];
                                  break;
                              case 'MX':
                                  print $hostent['mxprio'] . " " . $hostent['mx'];
                                  break;
                              default:
                                  print '&nbsp;';
                                  break;
                          }?>
                        </td>
                        <td><?=$hostent['descr'];?></td>
                        <td class="text-nowrap">
                          <a href="services_unbound_host_edit.php?id=<?=$i;?>" class="btn btn-default btn-xs"><i class="fa fa-pencil fa-fw"></i></a>
                          <a href="#" data-id="<?=$i;?>" class="act_delete_host btn btn-xs btn-default"><i class="fa fa-trash fa-fw"></i></a>
                        </td>
                      </tr>
<?php if (isset($hostent['aliases']['item'])): ?>
<?php foreach ($hostent['aliases']['item'] as $alias): ?>
                      <tr>
                        <td><?= strtolower(!empty($alias['host']) ? $alias['host'] : $hostent['host']) ?></td>
                        <td><?= strtolower(!empty($alias['domain']) ? $alias['domain'] : $hostent['domain']) ?></td>
                        <td><?=strtoupper($hostent['rr']);?></td>
                        <td><?= gettext('Alias for');?> <?=$hostent['host'] ? htmlspecialchars($hostent['host'] . '.' . $hostent['domain']) : htmlspecialchars($hostent['domain']);?></td>
                        <td><?= !empty($alias['descr']) ? $alias['descr'] : $hostent['descr'] ?></td>
                        <td class="text-nowrap">
                          <a href="services_unbound_host_edit.php?id=<?=$i;?>" class="btn btn-default btn-xs"><i class="fa fa-pencil fa-fw"></i></a>
                        </td>
                      </tr>
<?php endforeach ?>
<?php endif ?>
<?php endforeach ?>
                      <tr>
                        <td colspan="6">
                          <?=gettext("Entries in this section override individual results from the forwarders.");?>
                          <?=gettext("Use these for changing DNS results or for adding custom DNS records.");?>
                          <?=gettext("Keep in mind that all resource record types (i.e. A, AAAA, MX, etc. records) of a specified host below are being overwritten.");?>
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>
            </div>
          </section>
         <section class="col-xs-12">
            <div class="content-box">
                <div class="table-responsive">
                  <table class="table table-striped">
                    <tbody>
                      <tr>
                        <td colspan="4"><strong><?= gettext('Domain Overrides') ?></strong></td>
                      </tr>
                      <tr>
                        <td><strong><?= gettext('Domain') ?></strong></td>
                        <td><strong><?= gettext('IP') ?></strong></td>
                        <td><strong><?= gettext('Description') ?></strong></td>
                        <td class="text-nowrap">
                          <a href="services_unbound_domainoverride_edit.php" class="btn btn-primary btn-xs"><i class="fa fa-plus fa-fw"></i></a>
                        </td>
                      </tr>
<?php foreach ($a_domains as $i => $doment): ?>
                      <tr>
                        <td><?= strtolower($doment['domain']) ?></td>
                        <td><?= $doment['ip'] ?></td>
                        <td><?= $doment['descr'] ?></td>
                        <td class="text-nowrap">
                          <a href="services_unbound_domainoverride_edit.php?id=<?=$i;?>" class="btn btn-default btn-xs"><i class="fa fa-pencil fa-fw"></i></a>
                          <a href="#" data-id="<?=$i;?>" class="act_delete_override btn btn-xs btn-default"><i class="fa fa-trash fa-fw"></i></a>
                        </td>
                      </tr>
<?php endforeach  ?>
                      <tr>
                        <td colspan="4">
                          <?= gettext('Entries in this area override an entire domain by specifying an authoritative DNS server to be queried for that domain.') ?>
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>
            </div>
          </section>
        </form>
      </div>
    </div>
  </section>
<?php include("foot.inc"); ?>
