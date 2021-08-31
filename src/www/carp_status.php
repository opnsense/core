<?php

/*
 * Copyright (C) 2014 Deciso B.V.
 * Copyright (C) 2004 Scott Ullrich <sullrich@gmail.com>
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

$a_vip = &config_read_array('virtualip', 'vip');

$act = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_POST['carp_maintenancemode'])) {
        $act = "maintenance";
        if (isset($config["virtualip_carp_maintenancemode"])) {
            unset($config["virtualip_carp_maintenancemode"]);
            $carp_demotion_default = '0';
            foreach ($config['sysctl']['item'] as $tunable) {
                if ($tunable['tunable'] == 'net.inet.carp.demotion' && ctype_digit($tunable['value'])) {
                    $carp_demotion_default = $tunable['value'];
                }
            }
            $carp_diff = $carp_demotion_default - get_single_sysctl('net.inet.carp.demotion');
            set_single_sysctl('net.inet.carp.demotion', $carp_diff);
            write_config("Leave CARP maintenance mode");
        } else {
            $config["virtualip_carp_maintenancemode"] = true;
            set_single_sysctl('net.inet.carp.demotion', '240');
            write_config("Enter CARP maintenance mode");
        }
    } elseif (!empty($_POST['disablecarp'])) {
        if (get_single_sysctl('net.inet.carp.allow') > 0) {
            $act = "disable";
            $savemsg = gettext("All virtual IPs have been disabled. Please note that disabling does not survive a reboot.");
            set_single_sysctl('net.inet.carp.allow', '0');
        } else {
            $act = "enable";
            $savemsg = gettext("CARP has been enabled.");
            interfaces_carp_setup();
            set_single_sysctl('net.inet.carp.allow', '1');
        }
    }
    foreach ($a_vip as $vip) {
        if (!empty($vip['vhid'])) {
            switch ($act) {
                case 'maintenance':
                    break;
                case 'enable':
                    if ($vip['mode'] == 'carp') {
                        interface_carp_configure($vip);
                    } else {
                        interface_ipalias_configure($vip);
                    }
                    break;
                case 'disable':
                    interface_vip_bring_down($vip);
                    break;
                default:
                    break;
            }
        }
    }
    header(url_safe('Location: /carp_status.php?savemsg=%s', array($savemsg)));
    exit;
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!empty($_GET['savemsg'])) {
        $savemsg = htmlspecialchars($_GET['savemsg']);
    }
}

$carpcount = 0;
foreach ($a_vip as $carp) {
    if ($carp['mode'] == "carp") {
        $carpcount++;
        break;
    }
}

// fetch pfsync info
$pfsyncnodes = json_decode(configd_run("filter list pfsync json"), true);
$current_carp_demotion = get_single_sysctl("net.inet.carp.demotion");

legacy_html_escape_form_data($a_vip);
$status = (get_single_sysctl('net.inet.carp.allow') > 0);
if (!empty($config["virtualip_carp_maintenancemode"])) {
    $carp_detected_problems = false;
} else {
    $carp_detected_problems = $current_carp_demotion > 0;
}
include("head.inc");
?>

<body>
<?php include("fbegin.inc"); ?>

<section class="page-content-main">
  <div class="container-fluid">
    <div class="row">
      <?php
            if (isset($savemsg)) {
              print_info_box($savemsg);
            }
            if ($carp_detected_problems) {
              print_info_box(gettext("CARP has detected a problem and this unit has been demoted to BACKUP status.") . "<br />" . gettext("Check link status on all interfaces with configured CARP VIPs."));
            }
      ?>
      <section class="col-xs-12">
        <div class="content-box">
          <form method="post">
            <table class="table table-condensed">
              <tbody>
                <tr>
                  <td>
                    <input type="submit" class="btn btn-primary" name="disablecarp" value="<?= ($carpcount > 0 && !$status) ? html_safe(gettext('Enable CARP')) : html_safe(gettext('Temporarily Disable CARP')) ?>" />
                    <input type="submit" class="btn btn-primary" name="carp_maintenancemode" value="<?= isset($config["virtualip_carp_maintenancemode"]) ? html_safe(gettext('Leave Persistent CARP Maintenance Mode')) : html_safe(gettext('Enter Persistent CARP Maintenance Mode')) ?>" />
                  </td>
                </tr>
              </tbody>
            </table>
          </form>
        </div>
      </section>
      <section class="col-xs-12">
        <div class="content-box">
            <div class="table-responsive">
              <table class="table table-striped table-condensed">
                <thead>
                  <tr>
                    <td><?=gettext("CARP Interface"); ?></td>
                    <td><?=gettext("Virtual IP"); ?></td>
                    <td><?=gettext("Status"); ?></td>
                  </tr>
                </thead>
                <tbody>
<?php
                if ($carpcount == 0):?>
                <tr>
                  <td colspan="3"><?=gettext("Could not locate any defined CARP interfaces.");?></td>
                </tr>
<?php
                else:
                  $intf_details = legacy_interfaces_details();
                  foreach ($a_vip as $carp):
                    if ($carp['mode'] != "carp") {
                        continue;
                    }
                    $icon = '';
                    $intf = get_real_interface($carp['interface']);
                    if (!empty($intf_details[$intf]) && !empty($intf_details[$intf]['carp'][$carp['vhid']])) {
                        $intf_status = $intf_details[$intf]['carp'][$carp['vhid']]['status'];
                    } else {
                        $intf_status = null;
                    }
                    if (($carpcount > 0 && !$status)) {
                        $icon = "fa fa-remove fa-fw text-danger";
                        $intf_status_i18n = gettext('DISABLED');
                    } elseif ($intf_status == 'MASTER') {
                        $icon = "fa fa-play fa-fw text-success";
                        $intf_status_i18n = gettext('MASTER');
                    } elseif ($intf_status == 'BACKUP') {
                        $icon = "fa fa-play fa-fw text-muted";
                        $intf_status_i18n = gettext('BACKUP');
                    } elseif ($intf_status == 'INIT') {
                        $icon = "fa fa-info-circle fa-fw";
                        $intf_status_i18n = gettext('INIT');
                    }?>
                <tr>
                  <td><?=convert_friendly_interface_to_friendly_descr($carp['interface']) . "@{$carp['vhid']}" ;?></td>
                  <td><?=$carp['subnet'];?></td>
                  <td><span class="<?=$icon;?>"></span> <?=$intf_status_i18n;?></td>
                </tr>
<?php
                  endforeach;
                endif;?>
              </tbody>
              <tfoot>
                  <tr>
                      <td colspan="2"><?=gettext("Current CARP demotion level");?></td>
                      <td><?=$current_carp_demotion;?>
                  </tr>
              </tfoot>
            </table>
          </div>
        </div>
      </section>
      <section class="col-xs-12">
        <div class="content-box">
          <div class="table-responsive">
            <table class="table table-striped table-condensed">
              <thead>
                <tr>
                  <td><?=gettext("pfSync nodes");?></td>
                </tr>
              </thead>
              <tbody>
<?php
              if (isset($pfsyncnodes['nodes'])):
                foreach ($pfsyncnodes['nodes'] as $node):?>
                <tr>
                  <td><?=$node;?></td>
                </tr>
<?php
                endforeach;
              endif;?>
              </tbody>
            </table>
          </div>
        </div>
      </section>
    </div>
  </div>
</section>

<?php include("foot.inc"); ?>
