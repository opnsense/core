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

// request report data
$a_gateway_groups = &config_read_array('gateways', 'gateway_group');
$gateways_status = return_gateways_status(true);
$a_gateways = return_gateways_array();

legacy_html_escape_form_data($a_gateways);
legacy_html_escape_form_data($a_gateway_groups);

$service_hook = 'apinger';

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
                      if (!isset($priorities[$itemsplit[1]])) {
                          $priorities[$itemsplit[1]] = array();
                      }
                      if (!empty($a_gateways[$itemsplit[0]])) {
                          $priorities[$itemsplit[1]][$itemsplit[0]] = $a_gateways[$itemsplit[0]];
                      }
                    }
                    ksort($priorities);
?>
                    <tr>
                        <td> <?=$gateway_group['name'];?> </td>
                        <td class="hidden-xs"> <?=$gateway_group['descr'];?> </td>
                        <td>
                          <table class="table table-condensed">
<?php
                          foreach ($priorities as $priority => $gateways):?>
                          <tr>
                            <td><?=sprintf(gettext("Tier %s"), $priority);?></td>
                            <td>
<?php
                            foreach ($gateways as $gname => $gateway):
                                $online = gettext('Pending');
                                $gateway_label_class = 'default';
                                if ($gateways_status[$gname]) {
                                    $status = $gateways_status[$gname]['status'];
                                        if (stristr($status, 'force_down')) {
                                            $online = gettext('Offline (forced)');
                                            $gateway_label_class = 'danger';
                                        } elseif (stristr($status, 'down')) {
                                            $online = gettext('Offline');
                                            $gateway_label_class = 'danger';
                                        } elseif (stristr($status, 'loss')) {
                                            $online = gettext('Warning, Packetloss').': '.$status['loss'];
                                            $gateway_label_class = 'warning';
                                        } elseif (stristr($status, 'delay')) {
                                            $online = gettext('Warning, Latency').': '.$status['delay'];
                                            $gateway_label_class = 'warning';
                                        } elseif ($status == 'none') {
                                            $online = gettext('Online');
                                            $gateway_label_class = 'success';
                                    } elseif (!empty($gateway['monitor_disable']))  {
                                        $online = gettext('Online');
                                        $gateway_label_class = 'success';
                                    }
                                }
?>
                                  <div class="label label-<?= $gateway_label_class ?>" style="margin-right:4px">
                                    <i class="fa fa-globe"></i>
                                    <?=$gateway['name'];?>, <?=$online;?>
                                  </div>
<?php
                            endforeach;?>
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
