<?php

/*
    Copyright (C) 2014-2015 Deciso B.V.
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
require_once("interfaces.inc");


if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if(!empty($_GET['if'])) {
        $if = $_GET['if'];
    }
    if (!empty($_GET['savemsg']) && $_GET['savemsg'] == 'rescan') {
        $savemsg = gettext("Rescan has been initiated in the background. Refresh this page in 10 seconds to see the results.");
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_POST['if'])) {
        $if = $_POST['if'];
    }
    $rwlif = escapeshellarg(get_real_interface($if));
    if(!empty($_POST['rescanwifi'])) {
        mwexecf_bg('/sbin/ifconfig %s scan', $rwlif);
        header(url_safe('Location: status_wireless.php?if=%s&savemsg=rescan', $if));
        exit;
    }
}

$ciflist = get_configured_interface_with_descr();
if(empty($if)) {
    /* Find the first wireless interface */
    foreach($ciflist as $interface => $ifdescr) {
        if(is_interface_wireless(get_real_interface($interface))) {
            $if = $interface;
            break;
        }
    }
}
$rwlif = escapeshellarg(get_real_interface($if));
include("head.inc");
?>

<body>
<?php include("fbegin.inc"); ?>
  <section class="page-content-main">
    <div class="container-fluid">
      <div class="row">
        <section class="col-xs-12">
        <?php if (isset($savemsg)) print_info_box($savemsg); ?>
<?php
        $tab_array = array();
        foreach($ciflist as $interface => $ifdescr) {
            if (is_interface_wireless(get_real_interface($interface))) {
              $enabled = false;
              if($if == $interface) {
                  $enabled = true;
              }
              $tab_array[] = array(gettext("Status") . " ({$ifdescr})", $enabled, "status_wireless.php?if={$interface}");
            }
        }
        display_top_tabs($tab_array);
?>
        <div class="content-box">
          <form method="post" name="iform" id="iform">
            <input type="hidden" name="if" id="if" value="<?= html_safe($if) ?>">
            <header class="content-box-head container-fluid">
              <h3><?=gettext("Nearby access points or ad-hoc peers"); ?></h3>
            </header>
            <div class="table-responsive">
              <table class="table table-striped">
                <thead>
                  <tr>
                    <th><?=gettext("SSID");?></th>
                    <th><?=gettext("BSSID");?></th>
                    <th><?=gettext("CHAN");?></th>
                    <th><?=gettext("RATE");?></th>
                    <th><?=gettext("RSSI");?></th>
                    <th><?=gettext("INT");?></th>
                    <th><?=gettext("CAPS");?></th>
                  </tr>
                </thead>
                <tbody>
<?php
                exec("/sbin/ifconfig {$rwlif} list scan 2>&1", $states, $ret);
                /* Skip Header */
                array_shift($states);

                $counter=0;
                foreach($states as $state):
                  /* Split by Mac address for the SSID Field */
                  $split = preg_split("/([0-9a-f][[0-9a-f]\:[0-9a-f][[0-9a-f]\:[0-9a-f][[0-9a-f]\:[0-9a-f][[0-9a-f]\:[0-9a-f][[0-9a-f]\:[0-9a-f][[0-9a-f])/i", $state);
                  preg_match("/([0-9a-f][[0-9a-f]\:[0-9a-f][[0-9a-f]\:[0-9a-f][[0-9a-f]\:[0-9a-f][[0-9a-f]\:[0-9a-f][[0-9a-f]\:[0-9a-f][[0-9a-f])/i", $state, $bssid);
                  $ssid = htmlspecialchars($split[0]);
                  $bssid = $bssid[0];
                  /* Split the rest by using spaces for this line using the 2nd part */
                  $split = preg_split("/[ ]+/i", $split[1]);
                  $channel = $split[1];
                  $rate = $split[2];
                  $rssi = $split[3];
                  $int = $split[4];
                  $caps = "$split[5] $split[6] $split[7] $split[8] $split[9] $split[10] $split[11] ";
?>
                  <tr>
                    <td><?=$ssid;?></td>
                    <td><?=$bssid;?></td>
                    <td><?=$channel;?></td>
                    <td><?=$rate;?></td>
                    <td><?=$rssi;?></td>
                    <td><?=$int;?></td>
                    <td><?=$caps;?></td>
                  </tr>
<?php
                  endforeach;?>
                </tbody>
              </table>
            </div>
            <br/>
            <div class="table-responsive">
              <header class="content-box-head container-fluid">
                <h3><?=gettext("Associated or ad-hoc peers"); ?></h3>
              </header>
              <table class="table table-striped">
                <thead>
                  <tr>
                    <th><?=gettext("ADDR");?></th>
                    <th><?=gettext("AID");?></th>
                    <th><?=gettext("CHAN");?></th>
                    <th><?=gettext("RATE");?></th>
                    <th><?=gettext("RSSI");?></th>
                    <th><?=gettext("IDLE");?></th>
                    <th><?=gettext("TXSEQ");?></th>
                    <th><?=gettext("RXSEQ");?></th>
                    <th><?=gettext("CAPS");?></th>
                    <th><?=gettext("ERP");?></th>
                  </tr>
                </thead>
                <tbody>
<?php
                $states = array();
                exec("/sbin/ifconfig {$rwlif} list sta 2>&1", $states, $ret);
                array_shift($states);
                $counter=0;
                foreach($states as $state):
                  $split = preg_split("/[ ]+/i", $state);?>
                  <tr>
                    <td><?=$split[0];?></td>
                    <td><?=$split[1];?></td>
                    <td><?=$split[2];?></td>
                    <td><?=$split[3];?></td>
                    <td><?=$split[4];?></td>
                    <td><?=$split[5];?></td>
                    <td><?=$split[6];?></td>
                    <td><?=$split[7];?></td>
                    <td><?=$split[8];?></td>
                    <td><?=$split[9];?></td>
                  </tr>
<?php
                endforeach;?>
                </tbody>
              </table>
            </div>
            <div class="table-responsive">
              <table class="table table-striped">
                <tr>
                  <td>
                    <input type="submit" name="rescanwifi" value="<?=gettext("Rescan");?>" class="btn btn-primary"/>
                  </td>
                </tr>
                <tfoot>
                  <tr>
                    <td>
                      <b><?=gettext('Flags:') ?></b> <?=gettext('A = authorized, E = Extended Rate (802.11g), P = Power save mode') ?><br />
                      <b><?=gettext('Capabilities:') ?></b> <?=gettext('E = ESS (infrastructure mode), I = IBSS (ad-hoc mode), P = privacy (WEP/TKIP/AES), S = Short preamble, s = Short slot time') ?>
                    </td>
                  </tr>
                </tfoot>
              </table>
            </div>
          </form>
        </div>
      </section>
    </div>
  </div>
</section>
<?php include("foot.inc"); ?>
