<?php

/*
    Copyright (C) 2014-2016 Deciso B.V.
    Copyright (C) 2010 Yehuda Katz

    Redistribution and use in source and binary forms, with or without
    modification, are permitted provided that the following conditions are met:

    1. Redistributions of source code must retain the above copyright notice,
    this list of conditions and the following disclaimer.

    2. Redistributions in binary form must reproduce the above copyright
    notice, this list of conditions and the following disclaimer in the
    documentation and/or other materials provided with the distribution.

    THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
    INClUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
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
require_once("widgets/include/wake_on_lan.inc");
require_once("interfaces.inc");

if (isset($config['wol']['wolentry'])) {
    $wolcomputers = $config['wol']['wolentry'];
} else {
    $wolcomputers = array();
}

?>
<table class="table table-striped table-condensed">
  <thead>
    <tr>
      <th><?=gettext("Computer / Device");?></th>
      <th><?=gettext("Interface");?></th>
      <th><?=gettext("Status");?></th>
      <th></th>
    </tr>
  </thead>
  <tbody>
<?php
    foreach ($wolcomputers as $wolent):
      $is_active = exec("/usr/sbin/arp -an |/usr/bin/grep {$wolent['mac']}| /usr/bin/wc -l|/usr/bin/awk '{print $1;}'");?>
    <tr>
        <td><?=$wolent['descr'];?><br/><?=$wolent['mac'];?></td>
        <td><?=htmlspecialchars(convert_friendly_interface_to_friendly_descr($wolent['interface']));?></td>
        <td>
          <span class="glyphicon glyphicon-<?=$is_active == 1 ? "play" : "remove";?> text-<?=$is_active == 1 ? "success" : "danger";?>" ></span>
          <?=$is_active == 1 ? gettext("Online") : gettext("Offline");?>
        </td>
        <td>
          <a href="services_wol.php?mac=<?=$wolent['mac'];?>&if=<?=$wolent['interface'];?>">
            <span class="glyphicon glyphicon-flash" title="<?=gettext("Wake Up");?>"></span>
          </a>
        </td>
    </tr>
<?php
    endforeach;
    if (count($wolcomputers) == 0):?>
    <tr>
      <td colspan="4" ><?=gettext("No saved WoL addresses");?></td>
    </tr>
<?php
    endif;?>
  </tbody>
  <tfoot>
    <tr>
      <td colspan="4"><a href="status_dhcp_leases.php" class="navlink"><?= gettext('DHCP Leases Status') ?></a></td>
    </tr>
  </tfoot>
</table>
