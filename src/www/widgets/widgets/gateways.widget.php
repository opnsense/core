<?php

/*
        Copyright (C) 2014-2016 Deciso B.V.
        Copyright (C) 2008 Seth Mos

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
require_once("widgets/include/gateways.inc");

$gateways_status = return_gateways_status(true);
?>

<table class="table table-striped table-condensed">
    <thead>
        <tr>
            <th><?=gettext('Name')?></th>
            <th><?=gettext('RTT')?></th>
            <th><?=gettext('Loss')?></th>
            <th><?=gettext('Status')?></th>
        </tr>
    </thead>
    <tbody>
<?php
    foreach (return_gateways_array() as $gname => $gateway):?>
      <tr>
        <td>
          <strong><?=htmlspecialchars($gateway['name']); ?></strong><br/>
<?php
          $if_gw = '~';
          if (is_ipaddr($gateway['gateway'])) {
              $if_gw = htmlspecialchars($gateway['gateway']);
          } elseif ($gateway['ipprotocol'] == "inet") {
              $if_gw = htmlspecialchars(get_interface_gateway($gateway['friendlyiface']));
          } elseif ($gateway['ipprotocol'] == "inet6") {
              $if_gw = htmlspecialchars(get_interface_gateway_v6($gateway['friendlyiface']));
          }?>
          <small><?=$if_gw;?></small>
        </td>
      <td>
        <?=!empty($gateways_status[$gname]) ? htmlspecialchars($gateways_status[$gname]['delay']) : gettext("Pending");?>
      </td>
      <td>
        <?=!empty($gateways_status[$gname]) ? htmlspecialchars($gateways_status[$gname]['loss']) : gettext("Pending");?>
      </td>
<?php
        $online = gettext("Unknown");
        $class="info";
        if (!empty($gateways_status[$gname])) {
            if (stristr($gateways_status[$gname]['status'], "force_down")) {
                $online = "Offline (forced)";
                $class="danger";
            } elseif (stristr($gateways_status[$gname]['status'], "down")) {
                $online = "Offline";
                $class="danger";
            } elseif (stristr($gateways_status[$gname]['status'], "loss")) {
                $online = "Packetloss";
                $class="warning";
            } elseif (stristr($gateways_status[$gname]['status'], "delay")) {
                $online = "Latency";
                $class="warning";
            } elseif ($gateways_status[$gname]['status'] == "none") {
                $online = "Online";
                $class="success";
            } elseif ($gateways_status[$gname]['status'] == "") {
                $online = "Pending";
                $class="info";
            }
        }?>
      <td style="width:160px;">
        <div class="bg-<?=$class;?>" style="width:150px;">
          <?=$online;?>
        </div>
      </td>
  </tr>
<?php
  endforeach; ?>
  </tbody>
</table>
