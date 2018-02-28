<?php

/*
    Copyright (C) 2014-2016 Deciso B.V.
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
require_once("plugins.inc.d/openvpn.inc");

$servers = openvpn_get_active_servers();
$sk_servers = openvpn_get_active_servers("p2p");
$clients = openvpn_get_active_clients();

?>
<script>
    $(window).load(function() {
        // link kill buttons
        $(".act_kill_client").click(function(event){
            event.preventDefault();
            var port = $(this).data("client-port");
            var ip = $(this).data("client-ip");
            $.post('/status_openvpn.php', {action: 'kill', port:port, remipp:ip}, function(data) {
                location.reload();
            });
        });
    });
</script>

<?php
    foreach ($servers as $server) :?>
    <table class="table table-striped table-condensed">
      <thead>
        <tr>
          <th colspan="3">
            <?=$server['name'];?> <?=gettext("Client connections");?>
          </th>
        </tr>
        <tr>
          <th><?=gettext("Name/Time");?></th>
          <th><?=gettext("Real/Virtual IP");?></th>
          <th></th>
        </tr>
      </thead>
      <tbody>
<?php
        foreach ($server['conns'] as $conn) :?>
          <tr>
            <td><?=$conn['common_name'];?><br/><?=$conn['connect_time'];?></td>
            <td><?=$conn['remote_host'];?><br/><?=$conn['virtual_addr'];?></td>
            <td>
               <span class="glyphicon glyphicon-remove act_kill_client" data-client-port="<?=$server['mgmt'];?>"
                 data-client-ip="<?=$conn['remote_host'];?>"
                 style='cursor:pointer;'
                 title='Kill client connection from <?=$conn['remote_host']; ?>'>
               </span>
            </td>
          </tr>
<?php
        endforeach; ?>
      </tbody>
    </table>
    <br/>
<?php
    endforeach; ?>
<?php
    if (!empty($sk_servers)):?>
    <table class="table table-striped table-condensed">
      <thead>
        <tr>
          <th colspan="3"><?= gettext('Peer to Peer Server Instance Statistics') ?></th>
        </tr>
        <tr>
          <th><?= gettext('Name/Time') ?></th>
          <th><?= gettext('Remote/Virtual IP') ?></th>
          <th></th>
        </tr>
      </thead>
      <tbody>
<?php
      foreach ($sk_servers as $sk_server) :?>
        <tr>
          <td><?=$sk_server['name'];?><br/><?=$sk_server['connect_time'];?></td>
          <td><?=$sk_server['remote_host'];?><br/><?=$sk_server['virtual_addr'];?></td>
          <td>
            <span class='glyphicon glyphicon-transfer <?=$sk_server['status'] == "up" ? "text-success" : "text-danger";?>'></span>
          </td>
        </tr>
<?php
      endforeach; ?>
      </tbody>
    </table>
    <br/>
<?php
endif; ?>
<?php
    if (!empty($clients)) {?>
    <table class="table table-striped table-condensed">
      <thead>
          <tr>
            <th colspan="3"><?= gettext('Client Instance Statistics') ?></th>
          </tr>
          <tr>
            <th><?= gettext('Name/Time') ?></th>
            <th><?= gettext('Remote/Virtual IP') ?></th>
            <th></th>
          </tr>
      </thead>
      <tbody>

<?php
      foreach ($clients as $client) :?>
        <tr>
          <td><?=$client['name'];?><br/><?=$client['connect_time'];?></td>
          <td><?=$client['remote_host'];?><br/><?=$client['virtual_addr'];?></td>
          <td>
            <span class='glyphicon glyphicon-transfer <?=$client['status'] == "up" ? "text-success" : "text-danger" ;?>'></span>
          </td>
        </tr>
<?php
      endforeach; ?>
      </tbody>
    </table>

<?php
}

if ($DisplayNote) {
    echo "<br /><b>".gettext('NOTE:')."</b> ".gettext("You need to bind each OpenVPN client to enable its management daemon: use 'Local port' setting in the OpenVPN client screen");
}

if ((empty($clients)) && (empty($servers)) && (empty($sk_servers))): ?>
    <table class="table table-striped table-condensed">
      <tr>
        <td><?= gettext('No OpenVPN instance defined or enabled.') ?></td>
      </tr>
    </table>
<?php endif;
