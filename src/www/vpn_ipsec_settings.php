<?php
/*
	Copyright (C) 2014-2015 Deciso B.V.
	Copyright (C) 2014 Electric Sheep Fencing, LLC
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

require_once("functions.inc");
require_once("guiconfig.inc");
require_once("filter.inc");
require_once("ipsec.inc");
require_once("vpn.inc");
require_once("services.inc");
require_once("pfsense-utils.inc");

if (!is_array($config['ipsec'])) {
        $config['ipsec'] = array();
}

$pconfig['noinstalllanspd'] = $config['system']['noinstalllanspd'];
$pconfig['preferoldsa_enable'] = isset($config['ipsec']['preferoldsa']);
foreach ($ipsec_loglevels as $lkey => $ldescr) {
    if (!empty($config['ipsec']["ipsec_{$lkey}"])) {
        $pconfig["ipsec_{$lkey}"] = $config['ipsec']["ipsec_{$lkey}"];
    }
}
$pconfig['failoverforcereload'] = isset($config['ipsec']['failoverforcereload']);
$pconfig['maxmss_enable'] = isset($config['system']['maxmss_enable']);
$pconfig['maxmss'] = $config['system']['maxmss'];

if ($_POST) {
    unset($input_errors);
    $pconfig = $_POST;

    if (!$input_errors) {
        if ($_POST['noinstalllanspd'] == "yes") {
            $config['system']['noinstalllanspd'] = true;
        } else {
            if (isset($config['system']['noinstalllanspd'])) {
                unset($config['system']['noinstalllanspd']);
            }
        }

        if ($_POST['preferoldsa_enable'] == "yes") {
            $config['ipsec']['preferoldsa'] = true;
        } elseif (isset($config['ipsec']['preferoldsa']))
            unset($config['ipsec']['preferoldsa']);

        if (is_array($config['ipsec'])) {
            foreach ($ipsec_loglevels as $lkey => $ldescr) {
                if (empty($_POST["ipsec_{$lkey}"])) {
                    if (isset($config['ipsec']["ipsec_{$lkey}"])) {
                        unset($config['ipsec']["ipsec_{$lkey}"]);
                    }
                } else {
                    $config['ipsec']["ipsec_{$lkey}"] = $_POST["ipsec_{$lkey}"];
                }
            }
        }

        if ($_POST['failoverforcereload'] == "yes") {
            $config['ipsec']['failoverforcereload'] = true;
        } elseif (isset($config['ipsec']['failoverforcereload']))
            unset($config['ipsec']['failoverforcereload']);

        if ($_POST['maxmss_enable'] == "yes") {
            $config['system']['maxmss_enable'] = true;
            $config['system']['maxmss'] = $_POST['maxmss'];
        } else {
            unset($config['system']['maxmss_enable']);
            unset($config['system']['maxmss']);
        }

        write_config();

        $retval = 0;
        $retval = filter_configure();
        if (stristr($retval, "error") <> true) {
            $savemsg = get_std_save_message(gettext($retval));
        } else {
            $savemsg = gettext($retval);
        }

        vpn_ipsec_configure_preferoldsa();
        vpn_ipsec_configure();
        vpn_ipsec_configure_loglevels();

//		header("Location: vpn_ipsec_settings.php");
//		return;
    }
}

$pgtitle = array(gettext("VPN"),gettext("IPsec"),gettext("Settings"));
$shortcut_section = "ipsec";

include("head.inc");
?>

<body>
<?php include("fbegin.inc"); ?>

<script type="text/javascript">
//<![CDATA[

function maxmss_checked(obj) {
	if (obj.checked)
		jQuery('#maxmss').attr('disabled',false);
	else
		jQuery('#maxmss').attr('disabled','true');
}

//]]>
</script>

	<section class="page-content-main">
		<div class="container-fluid">
			<div class="row">


				<?php
                if (isset($savemsg)) {
                    print_info_box($savemsg);
                }
                if (isset($input_errors) && count($input_errors) > 0) {
                    print_input_errors($input_errors);
                }
                ?>


			    <section class="col-xs-12">

				<? $active_tab = "/vpn_ipsec_settings.php";
                include('vpn_ipsec_tabs.inc'); ?>

					<div class="tab-content content-box col-xs-12">

							<form action="vpn_ipsec_settings.php" method="post" name="iform" id="iform">

								<div class="table-responsive">
									<table class="table table-striped table-sort">

										<thead>
		                                    <tr>
							<th colspan="2" class="listtopic"><?=gettext("IPSec Advanced Settings"); ?></th>
		                                    </tr>
		                                </thead>

									<tbody>

										<tr>
											<td width="22%" valign="top" class="vncell"><?=gettext("LAN security associsations"); ?></td>
											<td width="78%" class="vtable">
												<input name="noinstalllanspd" type="checkbox" id="noinstalllanspd" value="yes" <?php if ($pconfig['noinstalllanspd']) {
                                                    echo "checked=\"checked\"";
} ?> />
												<strong><?=gettext("Do not install LAN SPD"); ?></strong>
												<br />
												<?=gettext("By default, if IPSec is enabled negating SPD are inserted to provide protection. " .
                                                "This behaviour can be changed by enabling this setting which will prevent installing these SPDs."); ?>
											</td>
										</tr>
										<tr>
											<td width="22%" valign="top" class="vncell"><?=gettext("Security Associations"); ?></td>
											<td width="78%" class="vtable">
												<input name="preferoldsa_enable" type="checkbox" id="preferoldsa_enable" value="yes" <?php if ($pconfig['preferoldsa_enable']) {
                                                    echo "checked=\"checked\"";
} ?> />
												<strong><?=gettext("Prefer older IPsec SAs"); ?></strong>
												<br />
												<?=gettext("By default, if several SAs match, the newest one is " .
                                                "preferred if it's at least 30 seconds old. Select this " .
                                                "option to always prefer old SAs over new ones."); ?>
											</td>
										</tr>
										<tr>
											<td width="22%" valign="top" class="vncell"><?=gettext("IPsec Debug"); ?></td>
											<td width="78%" class="vtable">
												<strong><?=gettext("Start IPSec in debug mode based on sections selected"); ?></strong>
												<br />
												<table summary="ipsec debug">
											<?php foreach ($ipsec_loglevels as $lkey => $ldescr) :
?>
												<tr>
													<td width="22%" valign="top" class="vncell"><?=$ldescr;?></td>
													<td width="78%" valign="top" class="vncell">
													<?php	echo "<select name=\"ipsec_{$lkey}\" id=\"ipsec_{$lkey}\">\n";
                                                    foreach (array("Silent", "Audit", "Control", "Diag", "Raw", "Highest") as $lidx => $lvalue) {
                                                        echo "<option value=\"{$lidx}\" ";
                                                        if ($pconfig["ipsec_{$lkey}"] == $lidx) {
                                                            echo "selected=\"selected\"";
                                                        }
                                                        echo ">{$lvalue}</option>\n";
                                                    }
                                                    ?>
														</select>
													</td>
												</tr>
											<?php
endforeach; ?>
												<tr style="display:none;"><td></td></tr>
												</table>
												<br /><?=gettext("Launches IPSec in debug mode so that more verbose logs " .
                                                "will be generated to aid in troubleshooting."); ?>
											</td>
										</tr>
										<tr>
											<td width="22%" valign="top" class="vncell"><?=gettext("IPsec Reload on Failover"); ?></td>
											<td width="78%" class="vtable">
												<input name="failoverforcereload" type="checkbox" id="failoverforcereload" value="yes" <?php if ($pconfig['failoverforcereload']) {
                                                    echo "checked=\"checked\"";
} ?> />
												<strong><?=gettext("Force IPsec Reload on Failover"); ?></strong>
												<br />
												<?=gettext("In some circumstances using a gateway group as the interface for " .
                                                "an IPsec tunnel does not function properly, and IPsec must be forcefully reloaded " .
                                                "when a failover occurs. Because this will disrupt all IPsec tunnels, this behavior" .
                                                " is disabled by default. Check this box to force IPsec to fully reload on failover."); ?>
											</td>
										</tr>
										<tr>
											<td width="22%" valign="top" class="vncell"><?=gettext("Maximum MSS"); ?></td>
											<td width="78%" class="vtable">
												<input name="maxmss_enable" type="checkbox" id="maxmss_enable" value="yes" <?php if ($pconfig['maxmss_enable'] == true) {
                                                    echo "checked=\"checked\"";
} ?> onclick="maxmss_checked(this)" />
												<strong><?=gettext("Enable MSS clamping on VPN traffic"); ?></strong>
												<br />
												<input name="maxmss" id="maxmss" value="<?php if ($pconfig['maxmss'] <> "") {
                                                    echo $pconfig['maxmss'];

} else {
    "1400";
} ?>" class="formfld unknown" <?php if ($pconfig['maxmss_enable'] == false) {
    echo "disabled=\"disabled\"";
} ?> />
												<br />
												<?=gettext("Enable MSS clamping on TCP flows over VPN. " .
                                                "This helps overcome problems with PMTUD on IPsec VPN links. If left blank, the default value is 1400 bytes. "); ?>
											</td>
										</tr>
										<tr>
											<td width="22%" valign="top">&nbsp;</td>
											<td width="78%">
												<input name="submit" type="submit" class="btn btn-primary" value="<?=gettext("Save"); ?>" />
											</td>
										</tr>
									</table>
								</div>
							</form>

					</div>
				</section>
			</div>
		</div>
	</section>
<?php include("foot.inc");
