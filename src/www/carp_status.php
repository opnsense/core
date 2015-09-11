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


unset($interface_arr_cache);
unset($carp_interface_count_cache);
unset($interface_ip_arr_cache);

$status = get_carp_status();
if ($_POST['carp_maintenancemode'] <> "") {
    interfaces_carp_set_maintenancemode(!isset($config["virtualip_carp_maintenancemode"]));
}
if ($_POST['disablecarp'] <> "") {
    if ($status == true) {
        set_single_sysctl('net.inet.carp.allow', '0');
        if (is_array($config['virtualip']['vip'])) {
            $viparr = &$config['virtualip']['vip'];
            foreach ($viparr as $vip) {
                switch ($vip['mode']) {
                    case "carp":
                        interface_vip_bring_down($vip);
                        sleep(1);
                        break;
                }
            }
        }
        $savemsg = sprintf(gettext("%s IPs have been disabled. Please note that disabling does not survive a reboot."), $carp_counter);
    } else {
        $savemsg = gettext("CARP has been enabled.");
        if (is_array($config['virtualip']['vip'])) {
            $viparr = &$config['virtualip']['vip'];
            foreach ($viparr as $vip) {
                switch ($vip['mode']) {
                    case "carp":
                        interface_carp_configure($vip);
                        sleep(1);
                        break;
                }
            }
        }
        interfaces_carp_setup();
        set_single_sysctl('net.inet.carp.allow', '1');
    }
}

$status = get_carp_status();

$carp_detected_problems = (array_pop(get_sysctl("net.inet.carp.demotion")) > 0);

$pgtitle = array(gettext("Status"),gettext("CARP"));
$shortcut_section = "carp";
include("head.inc");

?>

<body>
<?php include("fbegin.inc"); ?>


<section class="page-content-main">
	<div class="container-fluid">
		<div class="row">
			<section class="col-xs-12">

				<?php if (isset($savemsg)) {
                    print_info_box($savemsg);
} ?>

				<?PHP	if ($carp_detected_problems) {
                    print_info_box(gettext("CARP has detected a problem and this unit has been demoted to BACKUP status.") . "<br />" . gettext("Check link status on all interfaces with configured CARP VIPs."));
} ?>


				<div class="content-box">

                    <form action="<?=$_SERVER['REQUEST_URI'];?>" method="post">
                    <?php
                            $carpcount = 0;
                    if (isset($config['virtualip']['vip'])) {
                        foreach ($config['virtualip']['vip'] as $carp) {
                            if ($carp['mode'] == "carp") {
                                $carpcount++;
                                break;
                            }
                        }
                    }
                    if ($carpcount > 0) {
                        if ($status == false) {
                            $carp_enabled = false;
                            echo "<input type=\"submit\" name=\"disablecarp\" id=\"disablecarp\" value=\"" . gettext("Enable CARP") . "\" />";
                        } else {
                            $carp_enabled = true;
                            echo "<input type=\"submit\" name=\"disablecarp\" id=\"disablecarp\" value=\"" . gettext("Temporarily Disable CARP") . "\" />";
                        }
                        if (isset($config["virtualip_carp_maintenancemode"])) {
                            echo "<input type=\"submit\" name=\"carp_maintenancemode\" id=\"carp_maintenancemode\" value=\"" . gettext("Leave Persistent CARP Maintenance Mode") . "\" />";
                        } else {
                            echo "<input type=\"submit\" name=\"carp_maintenancemode\" id=\"carp_maintenancemode\" value=\"" . gettext("Enter Persistent CARP Maintenance Mode") . "\" />";
                        }
                    }
                    ?>

                    <div class="table-responsive">

                        <table class="table table-striped table-sort sortable">
							<tr>
								<td class="listhdrr" align="center"><?=gettext("CARP Interface"); ?></td>
								<td class="listhdrr" align="center"><?=gettext("Virtual IP"); ?></td>
								<td class="listhdrr" align="center"><?=gettext("Status"); ?></td>
							</tr>
							<?php
                            if ($carpcount == 0) {
                                echo "</table></div></form><center><br />" . gettext("Could not locate any defined CARP interfaces.");


                            } elseif (is_array($config['virtualip']['vip'])) {
                                foreach ($config['virtualip']['vip'] as $carp) {
                                    if ($carp['mode'] != "carp") {
                                        continue;
                                    }
                                    $ipaddress = $carp['subnet'];
                                    $password = $carp['password'];
                                    $netmask = $carp['subnet_bits'];
                                    $vhid = $carp['vhid'];
                                    $advskew = $carp['advskew'];
                                    $advbase = $carp['advbase'];
                                    $status = get_carp_interface_status("{$carp['interface']}_vip{$carp['vhid']}");
                                    echo "<tr>";
                                    $align = "style=\"vertical-align:middle\"";
                                    if ($carp_enabled == false) {
                                        $icon = "<span {$align} class=\"glyphicon glyphicon-remove text-danger\" alt=\"disabled\" ></span>";
                                        $status = "DISABLED";
                                    } else {
                                        if ($status == "MASTER") {
                                            $icon = "<span {$align} class=\"glyphicon glyphicon-play text-success\" alt=\"master\" ></span>";
                                        } elseif ($status == "BACKUP") {
                                            $icon = "<span {$align} class=\"glyphicon glyphicon-play text-muted\" alt=\"backup\" ></span>";
                                        } elseif ($status == "INIT") {
                                            $icon = "<span {$align} class=\"glyphicon glyphicon-info-sign\" alt=\"init\" ></span>";
                                        }
                                    }
                                    echo "<td class=\"listlr\" align=\"center\">" . convert_friendly_interface_to_friendly_descr($carp['interface']) . "@{$vhid} &nbsp;</td>";
                                    echo "<td class=\"listlr\" align=\"center\">" . $ipaddress . "&nbsp;</td>";
                                    echo "<td class=\"listlr\" align=\"center\">{$icon}&nbsp;&nbsp;" . $status . "&nbsp;</td>";
                                    echo "</tr>";
                                }
                            }
                            ?>
						</table>

                    </div>

                    <div class="col-xs-12">
						<p class="vexpl">
							<span class="red"><strong><?=gettext("Note"); ?>:</strong></span>
							<br />
							<?=gettext("You can configure high availability settings");
?> <a href="system_hasync.php"><?=gettext("here"); ?></a>.
						</p>

						<?php
                            echo "<br />" . gettext("pfSync nodes") . ":<br />";
                            echo "<pre>";
                            system("/sbin/pfctl -vvss | /usr/bin/grep creator | /usr/bin/cut -d\" \" -f7 | /usr/bin/sort -u");
                            echo "</pre>";
                        ?>
                    </div>

				</div>
			</section>
		</div>
	</div>
</section>

<?php include("foot.inc"); ?>
