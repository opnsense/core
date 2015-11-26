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

// request report data
if (!isset($config['gateways']['gateway_group']) || !is_array($config['gateways']['gateway_group'])) {
  $a_gateway_groups = array();
} else {
  $a_gateway_groups = &$config['gateways']['gateway_group'];
}
$gateways_status = return_gateways_status();
$a_gateways = return_gateways_array();

legacy_html_escape_form_data($a_gateways);
legacy_html_escape_form_data($a_gateway_groups);
$pgtitle = array(gettext('System'), gettext('Gateways'), gettext('Group Status'));
$shortcut_section = "gateway-groups";
include("head.inc");
?>

<body>

<?php include("fbegin.inc"); ?>
  <section class="page-content-main">
    <div class="container-fluid">
      <div class="row">
          <section class="col-xs-12">
<?php
          /* active tabs */
          $tab_array = array();
          $tab_array[] = array(gettext("Gateways"), false, "status_gateways.php");
          $tab_array[] = array(gettext("Gateway Groups"), true, "status_gateway_groups.php");
          display_top_tabs($tab_array);
?>
            <div class="tab-content content-box col-xs-12">
              <div class="responsive-table">
                <table class="table table-striped">
                  <thead>
                    <tr>
                      <td><?=gettext("Group Name"); ?></td>
                      <td class="hidden-xs"><?=gettext("Description"); ?></td>
                      <td><?=gettext("Gateways"); ?></td>
                    </tr>
                  </thead>
                  <tbody>
<?php
                  foreach ($a_gateway_groups as $gateway_group):
                    $priorities = array();
                    foreach($gateway_group['item'] as $item) {
                      $itemsplit = explode("|", $item);
                      $priorities[$itemsplit[1]] = $a_gateways[$itemsplit[0]];
                    }
                    ksort($priorities);
?>
                    <tr>
                        <td> <?=$gateway_group['name'];?> </td>
                        <td class="hidden-xs"> <?=$gateway_group['descr'];?> </td>
                        <td>
                          <table class="table table-condensed">
<?php
                          foreach ($priorities as $priority => $gateway):
                              $monitor = isset($gateway['monitor']) && is_ipaddr($gateway['monitor']) ? $gateway['monitor'] : $gateway['gateway'];
                              $status = $gateways_status[$monitor]['status'];
                              if (stristr($status, "down")) {
                                  $online = gettext("Offline");
                                  $bgcolor = "#F08080";  // lightcoral
                              } elseif (stristr($status, "loss")) {
                                  $online = gettext("Warning, Packetloss");
                                  $bgcolor = "#F0E68C";  // khaki
                              } elseif (stristr($status, "delay")) {
                                  $online = gettext("Warning, Latency");
                                  $bgcolor = "#F0E68C";  // khaki
                              } elseif ($status == "none") {
                                  $online = gettext("Online");
                                  $bgcolor = "#90EE90";  // lightgreen
                              } else {
                                  $online = gettext("Gathering data");
                                  $bgcolor = "#ADD8E6";  // lightblue
                              }
?>
                              <tr>
                                <td><?=sprintf(gettext("Tier %s"), $priority);?></td>
                                <td>
                                  <div style="background: <?=$bgcolor;?>">
                                    &nbsp;
                                    <i class="fa fa-globe"></i>
                                    <?=$gateway['name'];?>, <?=$online;?>
                                  </div>
                                </td>
                              </tr>
<?php
                          endforeach; ?>
                          </table>
                        </td>
                    </tr>

<?php
                  endforeach; ?>
                  </tbody>
                </table>
              </div>
            </div>
        </section>
      </div>
    </div>
  </section>

<?php include("foot.inc"); ?>
