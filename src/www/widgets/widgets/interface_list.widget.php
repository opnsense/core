<?php

/*
    Copyright (C) 2014-2016 Deciso B.V.
    Copyright (C) 2007 Scott Dale
    Copyright (C) 2004-2005 T. Lechat <dev@lechat.org>
    Copyright (C) 2004-2005 Manuel Kasper <mk@neon1.net>
    Copyright (C) 2004-2005 Jonathan Watt <jwatt@jwatt.org>
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
require_once("widgets/include/interfaces.inc");
require_once("interfaces.inc");

?>

<script type="text/javascript">
  /**
   * Hybrid widget only update interface status using ajax
   */
  function interface_widget_update(sender, data)
  {
      var tbody = sender.find('tbody');
      data.map(function(interface_data) {
          var tr_id = 'interface_widget_item_' + interface_data['name'];
          if (tbody.find("#"+tr_id).length != 0) {
              switch (interface_data['status']) {
                  case 'up':
                    $("#"+tr_id).find('.text-danger').removeClass('text-danger').addClass('text-success');
                    $("#"+tr_id).find('.glyphicon-arrow-down').removeClass('glyphicon-arrow-down').addClass('glyphicon-arrow-up');
                    $("#"+tr_id).find('.glyphicon-arrow-remove').removeClass('glyphicon-arrow-remove').addClass('glyphicon-arrow-up');
                    break;
                  case 'down':
                    $("#"+tr_id).find('.text-success').removeClass('text-success').addClass('text-danger');
                    $("#"+tr_id).find('.glyphicon-arrow-up').removeClass('glyphicon-arrow-up').addClass('glyphicon-arrow-down');
                    $("#"+tr_id).find('.glyphicon-arrow-remove').removeClass('glyphicon-arrow-remove').addClass('glyphicon-arrow-down');
                    break;
                  default:
                    $("#"+tr_id).find('.text-success').removeClass('text-success').addClass('text-danger');
                    $("#"+tr_id).find('.glyphicon-arrow-down').removeClass('glyphicon-arrow-down').addClass('glyphicon-arrow-remove');
                    $("#"+tr_id).find('.glyphicon-arrow-up').removeClass('glyphicon-arrow-up').addClass('glyphicon-arrow-remove');
              }
          }
      });
  }
</script>
<table class="table table-striped table-condensed" data-plugin="interfaces" data-callback="interface_widget_update">
  <tbody>
<?php
    $ifsinfo = get_interfaces_info();
    foreach (get_configured_interface_with_descr() as $ifdescr => $ifname):
      $ifinfo = $ifsinfo[$ifdescr];
      $iswireless = is_interface_wireless($ifdescr);?>
      <tr id="interface_widget_item_<?=$ifname;?>">
        <td>
<?php
          if (isset($ifinfo['ppplink'])):?>
            <span title="3g" class="glyphicon glyphicon-phone text-success"></span>
<?php
          elseif ($iswireless):
            if ($ifinfo['status'] == 'associated' || $ifinfo['status'] == 'up'):?>
            <span title="wlan" class="glyphicon glyphicon-signal text-success"></span>
<?php
            else:?>
            <span title="wlan_d" class="glyphicon glyphicon-signal text-danger"></span>
<?php
            endif;?>
<?php
          else:?>
<?php
            if ($ifinfo['status'] == "up"):?>
              <span title="cablenic" class="glyphicon glyphicon-transfer text-success"></span>
<?php
            else:?>
              <span title="cablenic" class="glyphicon glyphicon-transfer text-danger"></span>
<?php
            endif;?>
<?php
          endif;?>
          &nbsp;
          <strong>
            <u>
              <span onclick="location.href='/interfaces.php?if=<?=htmlspecialchars($ifdescr); ?>'" style="cursor:pointer">
                <?=htmlspecialchars($ifname);?>
              </span>
            </u>
          </strong>
        </td>
        <td>
<?php
        if ($ifinfo['status'] == "up" || $ifinfo['status'] == "associated"):?>
          <span class="glyphicon glyphicon-arrow-up text-success"></span>
<?php
        elseif ($ifinfo['status'] == "no carrier"):?>
          <span class="glyphicon glyphicon-arrow-down text-danger"></span>
<?php
        elseif ($ifinfo['status'] == "down"):?>
          <span class="glyphicon glyphicon-arrow-remove text-danger"></span>
<?php
        else:?>
          <?=htmlspecialchars($ifinfo['status']);?>
<?php
        endif;?>
        <td>
          <?=empty($ifinfo['media']) ? htmlspecialchars($ifinfo['cell_mode']) : htmlspecialchars($ifinfo['media']);?>
        </td>
        <td>
          <?=htmlspecialchars($ifinfo['ipaddr']);?>
          <?=!empty($ifinfo['ipaddr']) ? "<br/>" : "";?>
          <?=htmlspecialchars($ifinfo['ipaddrv6']);?>
        </td>
      </tr>
<?php
    endforeach;?>
  </tbody>
</table>
