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

require_once("guiconfig.inc");
require_once("filter.inc");
require_once("vpn.inc");
require_once("services.inc");
require_once("pfsense-utils.inc");
require_once("interfaces.inc");

if (!isset($config['ipsec']) || !is_array($config['ipsec'])) {
    $config['ipsec'] = array();
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // fetch form data
    $pconfig  = array();
    $pconfig['noinstalllanspd'] = isset($config['system']['noinstalllanspd']);
    $pconfig['preferoldsa_enable'] = isset($config['ipsec']['preferoldsa']);
    foreach ($ipsec_loglevels as $lkey => $ldescr) {
        if (!empty($config['ipsec']["ipsec_{$lkey}"])) {
            $pconfig["ipsec_{$lkey}"] = $config['ipsec']["ipsec_{$lkey}"];
        } else {
            $pconfig["ipsec_{$lkey}"] = null;
        }
    }
    $pconfig['failoverforcereload'] = isset($config['ipsec']['failoverforcereload']);
    $pconfig['maxmss_enable'] = isset($config['system']['maxmss_enable']);
    $pconfig['maxmss'] = isset($config['system']['maxmss']) ? $config['system']['maxmss'] : null;
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // save form data
    $pconfig = $_POST;
    if (isset($pconfig['noinstalllanspd']) && $pconfig['noinstalllanspd'] == "yes") {
        $config['system']['noinstalllanspd'] = true;
    } elseif (isset($config['system']['noinstalllanspd'])) {
        unset($config['system']['noinstalllanspd']);
    }
    if (isset($pconfig['preferoldsa_enable']) && $pconfig['preferoldsa_enable'] == "yes") {
        $config['ipsec']['preferoldsa'] = true;
    } elseif (isset($config['ipsec']['preferoldsa'])) {
        unset($config['ipsec']['preferoldsa']);
    }
    if (isset($config['ipsec']) && is_array($config['ipsec'])) {
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

    if (isset($pconfig['failoverforcereload']) && $pconfig['failoverforcereload'] == "yes") {
        $config['ipsec']['failoverforcereload'] = true;
    } elseif (isset($config['ipsec']['failoverforcereload']))
        unset($config['ipsec']['failoverforcereload']);

    if (isset($pconfig['maxmss_enable']) && $pconfig['maxmss_enable'] == "yes") {
        $config['system']['maxmss_enable'] = true;
        if (!empty($pconfig['maxmss']) && is_numericint($pconfig['maxmss'])) {
            $config['system']['maxmss'] = $pconfig['maxmss'];
        }
    } else {
        if (isset($config['system']['maxmss_enable'])) {
            unset($config['system']['maxmss_enable']);
        }
        if (isset($config['system']['maxmss'])) {
            unset($config['system']['maxmss']);
        }
    }

    write_config();
    $retval = filter_configure();
    if (stristr($retval, "error") <> true) {
        $savemsg = get_std_save_message(gettext($retval));
    } else {
        $savemsg = gettext($retval);
    }
    vpn_ipsec_configure();
}

$pgtitle = array(gettext("VPN"),gettext("IPsec"),gettext("Settings"));
$shortcut_section = "ipsec";

include("head.inc");
?>

<body>
<?php include("fbegin.inc"); ?>

<script type="text/javascript">
//<![CDATA[
$( document ).ready(function() {
    maxmss_checked()
});

function maxmss_checked(obj) {
  if ($('#maxmss_enable').is(":checked")) {
    $('#maxmss').attr('disabled',false);
    $("#maxmss").addClass('show');
    $("#maxmss").removeClass('hidden');
  } else {
    $('#maxmss').attr('disabled',true);
    $("#maxmss").addClass('hidden');
    $("#maxmss").removeClass('show');
  }

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
                  <table class="table table-striped">
                    <tr>
                      <td ><strong><?=gettext("IPSec Advanced Settings"); ?></strong></td>
                      <td align="right">
                        <small><?=gettext("full help"); ?> </small>
                        <i class="fa fa-toggle-off text-danger"  style="cursor: pointer;" id="show_all_help_page" type="button"></i></a>
                      </td>
                    </tr>
                    <tr>
                      <td><a id="help_for_noinstalllanspd" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("LAN security associsations"); ?></td>
                      <td>
                        <input name="noinstalllanspd" type="checkbox" id="noinstalllanspd" value="yes" <?=!empty($pconfig['noinstalllanspd']) ? "checked=\"checked\""  : "";?> />
                        <strong><?=gettext("Do not install LAN SPD"); ?></strong>
                        <div class="hidden" for="help_for_noinstalllanspd">
                          <?=gettext("By default, if IPSec is enabled negating SPD are inserted to provide protection. " .
                                                  "This behaviour can be changed by enabling this setting which will prevent installing these SPDs."); ?>
                        </div>
                      </td>
                    </tr>
                    <tr>
                      <td><a id="help_for_preferoldsa_enable" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Security Associations"); ?></td>
                      <td width="78%" class="vtable">
                        <input name="preferoldsa_enable" type="checkbox" id="preferoldsa_enable" value="yes" <?= !empty($pconfig['preferoldsa_enable']) ? "checked=\"checked\"" : "";?> />
                        <strong><?=gettext("Prefer older IPsec SAs"); ?></strong>
                        <div class="hidden" for="help_for_preferoldsa_enable">
                          <?=gettext("By default, if several SAs match, the newest one is " .
                                                  "preferred if it's at least 30 seconds old. Select this " .
                                                  "option to always prefer old SAs over new ones."); ?>
                        </div>
                      </td>
                    </tr>
                    <tr>
                      <td><a id="help_for_ipsec_debug" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("IPsec Debug"); ?></td>
                      <td>
                        <div class="hidden" for="help_for_ipsec_debug">
                                      <strong><?=gettext("Start IPSec in debug mode based on sections selected"); ?></strong> <br/>
                        </div>
<?php                   foreach ($ipsec_loglevels as $lkey => $ldescr) :
?>
                        <?=$ldescr?>
                        <select name="ipsec_<?=$lkey?>" id="ipsec_<?=$lkey?>">
<?php                   foreach (array("Silent", "Audit", "Control", "Diag", "Raw", "Highest") as $lidx => $lvalue):
?>
                          <option value="<?=$lidx?>" <?= isset($pconfig["ipsec_{$lkey}"]) && $pconfig["ipsec_{$lkey}"] == $lidx ? "selected=\"selected\"" : "";?> ?>
                              <?=$lvalue?>
                          </option>
<?php
                        endforeach; ?>
                        </select>
<?php
                    endforeach; ?>
                        <div class="hidden" for="help_for_ipsec_debug">
                        <?=gettext("Launches IPSec in debug mode so that more verbose logs " .
                                                    "will be generated to aid in troubleshooting."); ?>
                        </div>
                      </td>
                    </tr>
                    <tr>
                      <td><a id="help_for_failoverforcereloadg" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("IPsec Reload on Failover"); ?></td>
                      <td>
                        <input name="failoverforcereload" type="checkbox" id="failoverforcereload" value="yes" <?= !empty($pconfig['failoverforcereload']) ? "checked=\"checked\"" : "";?> />
                        <strong><?=gettext("Force IPsec Reload on Failover"); ?></strong>
                        <div class="hidden" for="help_for_failoverforcereloadg">
                          <?=gettext("In some circumstances using a gateway group as the interface for " .
                                                  "an IPsec tunnel does not function properly, and IPsec must be forcefully reloaded " .
                                                  "when a failover occurs. Because this will disrupt all IPsec tunnels, this behavior" .
                                                  " is disabled by default. Check this box to force IPsec to fully reload on failover."); ?>
                        </div>
                      </td>
                    </tr>
                    <tr>
                      <td><a id="help_for_maxmss_enable" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Maximum MSS"); ?></td>
                      <td>
                        <input name="maxmss_enable" type="checkbox" id="maxmss_enable" value="yes" <?= !empty($pconfig['maxmss_enable']) ? "checked=\"checked\"" : "" ;?> onclick="maxmss_checked()" />
                        <strong><?=gettext("Enable MSS clamping on VPN traffic"); ?></strong>
                        <input name="maxmss" id="maxmss" type="text" value="<?= !empty($pconfig['maxmss']) ? $pconfig['maxmss'] : "1400";?>" <?= !empty($pconfig['maxmss_enable']) ? "disabled=\"disabled\"" : "" ;?> />
                        <div class="hidden" for="help_for_maxmss_enable">
                        <?=gettext("Enable MSS clamping on TCP flows over VPN. " .
                                                  "This helps overcome problems with PMTUD on IPsec VPN links. If left blank, the default value is 1400 bytes. "); ?>
                        </div>
                      </td>
                    </tr>
                    <tr>
                      <td>&nbsp;</td>
                      <td>
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
