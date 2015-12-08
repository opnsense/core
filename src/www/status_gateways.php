<?php

/*
    Copyright (C) 2014-2015 Deciso B.V.
    Copyright (C) 2010 Seth Mos <seth.mos@dds.nl>.
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
require_once("services.inc");
require_once("interfaces.inc");

$a_gateways = return_gateways_array();
$gateways_status = return_gateways_status(true);
legacy_html_escape_form_data($a_gateways);

include("head.inc");

?>
<body>
<?php include("fbegin.inc"); ?>
  <section class="page-content-main">
    <div class="container-fluid">
      <div class="row">
        <section class="col-xs-12">
          <div class="tab-content content-box col-xs-12">
            <div class="responsive-table">
              <table class="table table-striped">
                <thead>
                  <tr>
                    <td><?=gettext("Name"); ?></td>
                    <td class="hidden-xs"><?=gettext("Gateway"); ?></td>
                    <td class="hidden-xs"><?=gettext("Monitor"); ?></td>
                    <td class="hidden-xs"><?=gettext("RTT"); ?></td>
                    <td class="hidden-xs"><?=gettext("Loss"); ?></td>
                    <td><?=gettext("Status"); ?></td>
                    <td><?=gettext("Description"); ?></td>
                  </tr>
                </thead>
                <tbody>
<?php
                foreach ($a_gateways as $gname => $gateway):?>
                <tr>
                    <td>
                      <?=$gateway['name'];?>
                    </td>
                    <td class="hidden-xs">
                      <?=$gateway['gateway'];?>
                    </td>
                    <td class="hidden-xs">
                      <?= !empty($gateways_status[$gname]) ? $gateways_status[$gname]['monitorip'] : $gateway['monitorip'];?>
                    </td>
                    <td class="hidden-xs">
                      <?=	!empty($gateways_status[$gname]) ? $gateways_status[$gname]['delay'] : gettext("Pending") ;?>
                    </td>
                    <td class="hidden-xs">
                      <?=	!empty($gateways_status[$gname]) ? $gateways_status[$gname]['loss'] : gettext("Pending"); ?>
                    </td>
                    <td>
<?php
                    if ($gateways_status[$gname]) {
                        $status = $gateways_status[$gname];
                        if (stristr($status['status'], "force_down")) {
                            $online = gettext("Offline (forced)");
                            $bgcolor = "#F08080";  // lightcoral
                        } elseif (stristr($status['status'], "down")) {
                            $online = gettext("Offline");
                            $bgcolor = "#F08080";  // lightcoral
                        } elseif (stristr($status['status'], "loss")) {
                            $online = gettext("Warning, Packetloss").': '.$status['loss'];
                            $bgcolor = "#F0E68C";  // khaki
                        } elseif (stristr($status['status'], "delay")) {
                            $online = gettext("Warning, Latency").': '.$status['delay'];
                            $bgcolor = "#F0E68C";  // khaki
                        } elseif ($status['status'] == "none") {
                            $online = gettext("Online");
                            $bgcolor = "#90EE90";  // lightgreen
                        }
                    } else if (isset($gateway['monitor_disable'])) {
                        $online = gettext("Online");
                        $bgcolor = "#90EE90";  // lightgreen
                    } else {
                        $online = gettext("Pending");
                        $bgcolor = "#D3D3D3";  // lightgray
                    }
?>
                      <div style="background: <?=$bgcolor;?>">
                        <i class="fa fa-globe"></i>
                        <?=$online;?>
                      </div>
                      <div  class="hidden-xs">
                        <?=!empty($gateways_status[$gname]['lastcheck']) ? gettext("Last check:") . '<br />' . $gateways_status[$gname]['lastcheck']  : "";?>
                      </div>
                    </td>
                    <td>
                      <?=$gateway['descr']; ?>
                    </td>
                </tr>

<?php
                endforeach;?>
              </tbody>
            </table>
          </div>
        </div>
      </section>
    </div>
  </div>
</section>

<?php include("foot.inc"); ?>
