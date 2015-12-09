<?php

/*
    Copyright (C) 2014 Deciso B.V.
    Copyright (C) 2004 Scott Ullrich
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
require_once("pfsense-utils.inc");
require_once("interfaces.inc");

function interfaces_carp_set_maintenancemode($carp_maintenancemode)
{
    global $config;
    if (isset($config["virtualip_carp_maintenancemode"]) && $carp_maintenancemode == false) {
        unset($config["virtualip_carp_maintenancemode"]);
        write_config("Leave CARP maintenance mode");
    } elseif (!isset($config["virtualip_carp_maintenancemode"]) && $carp_maintenancemode == true) {
        $config["virtualip_carp_maintenancemode"] = true;
        write_config("Enter CARP maintenance mode");
    }

    $viparr = &$config['virtualip']['vip'];
    foreach ($viparr as $vip) {
        if ($vip['mode'] == "carp") {
            interface_carp_configure($vip);
        }
    }
}

// init $config['virtualip']['vip']
if ( !isset($config['virtualip']['vip']) || !is_array($config['virtualip']['vip'])) {
    $config['virtualip']['vip'] = array();
}
$a_vip = &$config['virtualip']['vip'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_POST['carp_maintenancemode'])) {
        interfaces_carp_set_maintenancemode(!isset($config["virtualip_carp_maintenancemode"]));
    } elseif (!empty($_POST['disablecarp'])) {
        if (get_single_sysctl('net.inet.carp.allow') > 0) {
            $carp_counter = 0;
            set_single_sysctl('net.inet.carp.allow', '0');
            foreach ($a_vip as $vip) {
                switch ($vip['mode']) {
                    case "carp":
                        interface_vip_bring_down($vip);
                        $carp_counter++;
                        sleep(1);
                        break;
                }
            }
            $savemsg = sprintf(gettext("%s IPs have been disabled. Please note that disabling does not survive a reboot."), $carp_counter);
        } else {
            $savemsg = gettext("CARP has been enabled.");
            foreach ($a_vip as $vip) {
                switch ($vip['mode']) {
                    case "carp":
                        interface_carp_configure($vip);
                        sleep(1);
                        break;
                }
            }
            interfaces_carp_setup();
            set_single_sysctl('net.inet.carp.allow', '1');
        }
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

legacy_html_escape_form_data($a_vip);
$status = (get_single_sysctl('net.inet.carp.allow') > 0);
$carp_detected_problems = (array_pop(get_sysctl("net.inet.carp.demotion")) > 0);
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
              <tr>
                <td>
                  <input type="submit" class="btn btn-primary" name="disablecarp" value="<?=($carpcount > 0 && $status) ? gettext("Enable CARP") : gettext("Temporarily Disable CARP") ;?>" />
                  <input type="submit" class="btn btn-primary" name="carp_maintenancemode" value="<?=isset($config["virtualip_carp_maintenancemode"]) ? gettext("Leave Persistent CARP Maintenance Mode") : gettext("Enter Persistent CARP Maintenance Mode");?> " />
                </td>
              </tr>
            </table>
            <div class="table-responsive">
              <table class="table table-striped">
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
                  foreach ($a_vip as $carp):
                    if ($carp['mode'] != "carp") {
                        continue;
                    }
                    $icon = "";
                    $intf_status = get_carp_interface_status("{$carp['interface']}_vip{$carp['vhid']}");
                    if (($carpcount > 0 && $status)) {
                        $icon = "glyphicon glyphicon-remove text-danger";
                        $intf_status = "DISABLED";
                    } elseif ($intf_status == "MASTER") {
                        $icon = "glyphicon glyphicon-play text-success";
                    } elseif ($intf_status == "BACKUP") {
                        $icon = "glyphicon glyphicon-play text-muted";
                    } elseif ($intf_status == "INIT") {
                        $icon = "glyphicon glyphicon-info-sign";
                    }?>
                <tr>
                  <td><?=convert_friendly_interface_to_friendly_descr($carp['interface']) . "@{$carp['vhid']}" ;?></td>
                  <td><?=$carp['subnet'];?></td>
                  <td><span class="<?=$icon;?>"></span> <?=$intf_status;?></td>
                </tr>
<?php
                  endforeach;
                endif;?>
              </tbody>
            </table>
          </div>
          <hr/>
          <div class="table-responsive">
            <table class="table table-striped">
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
