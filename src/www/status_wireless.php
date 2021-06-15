<?php

/*
 * Copyright (C) 2014-2015 Deciso B.V.
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

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if(!empty($_GET['if'])) {
        $if = $_GET['if'];
    } else {
        /* if no interface is provided this invoke is invalid */
        header(url_safe('Location: /index.php'));
        exit;
    }
    $rwlif = escapeshellarg(get_real_interface($if));
    if(!empty($_GET['rescanwifi'])) {
        mwexec("/sbin/ifconfig {$rwlif} scan; sleep 1");
        header(url_safe('Location: /status_wireless.php?if=%s', array($if)));
        exit;
    }
}

include("head.inc");

?>
<body>
<?php include("fbegin.inc"); ?>
  <section class="page-content-main">
    <div class="container-fluid">
      <?php if (isset($savemsg)) print_info_box($savemsg); ?>
      <div class="row">
        <section class="col-xs-12">
          <form method="post" name="iform" id="iform">
            <div class="content-box table-responsive __mb">
            <input type="hidden" name="if" id="if" value="<?= html_safe($if) ?>">
            <header class="content-box-head container-fluid">
              <h3>
	        <?= gettext('Nearby access points or ad-hoc peers') ?>
                <a href="<?= 'status_wireless.php?if=' . html_safe($if) . '&rescanwifi=1' ?>" class="btn btn-xs btn-primary pull-right"><i class="fa fa-plus-circle fa-fw"></i> <?= gettext('Rescan') ?></a>
	      </h3>
            </header>
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
            <div class="content-box table-responsive">
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
                <tfoot>
                  <tr>
                    <td colspan="10">
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
