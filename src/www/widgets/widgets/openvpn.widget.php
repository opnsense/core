<?php

/*
 * Copyright (C) 2014-2023 Deciso B.V.
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
require_once("plugins.inc.d/openvpn.inc");

function openvpn_config()
{
    global $config;
    $result = [];
    foreach (['openvpn-server', 'openvpn-client'] as $section) {
        $result[$section] = [];
        if (!empty($config['openvpn'][$section])) {
            foreach ($config['openvpn'][$section] as $settings) {
                if (empty($settings) || isset($settings['disable'])) {
                    continue;
                }
                $server = [];
                $default_port = ($section == 'openvpn-server') ? 1194 : '';
                $server['port'] = !empty($settings['local_port']) ? $settings['local_port'] : $default_port;
                $server['mode'] = $settings['mode'];
                if (empty($settings['description'])) {
                    $settings['description'] = ($section == 'openvpn-server') ? 'Server' : 'Client';
                }
                $server['name'] = "{$settings['description']} {$settings['protocol']}:{$server['port']}";
                $server['vpnid'] = $settings['vpnid'];
                $result[$section][] = $server;
            }
        }
    }

    foreach ((new OPNsense\OpenVPN\OpenVPN())->Instances->Instance->iterateItems() as $key => $node) {
        if (!empty((string)$node->enabled)) {
            $section = "openvpn-{$node->role}";
            $default_port = ($section == 'openvpn-server') ? 1194 : '';
            $default_desc = ($section == 'openvpn-server') ? 'Server' : 'Client';
            $server = [
                'port' => !empty((string)$node->port) ? (string)$node->port : $default_port,
                'mode' => (string)$node->role,
                'description' => !empty((string)$node->description) ? (string)$node->description : $default_desc,
                'name' => "{$node->description} {$node->proto}:{$node->port}",
                'vpnid' => $key
            ];
            $result[$section][] = $server;
        }
    }

    return $result;
}

$openvpn_status = json_decode(configd_run('openvpn connections client,server'), true) ?? [];
$openvpn_cfg = openvpn_config();
foreach ($openvpn_cfg as $section => &$ovpncfg) {
    foreach ($ovpncfg as &$item) {
        $opt = ($section == 'openvpn-server') ? 'server' : 'client';
        if (!empty($openvpn_status[$opt][$item['vpnid']])) {
            $item = array_merge($openvpn_status[$opt][$item['vpnid']], $item);
        }
    }
}

?>
<script>
    $("#dashboard_container").on("WidgetsReady", function() {
        // link kill buttons
        $(".act_kill_client").click(function(event){
            event.preventDefault();
            let params = {server_id:  $(this).data("client-port"), session_id: $(this).data("client-ip")};
            $.post('/api/openvpn/service/kill_session/', params, function(data, status){
                location.reload();
            });
        });
    });
</script>

<?php
    foreach ($openvpn_cfg['openvpn-server'] as $server) :?>
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
    if (!empty($server['client_list'])):
        foreach ($server['client_list'] as $conn) :?>
          <tr>
            <td><?=$conn['common_name'] ?? '';?><br/><?=$conn['connected_since'] ?? '';?></td>
            <td><?=$conn['real_address'] ?? '';?><br/><?=$conn['virtual_address'] ?? '';?></td>
            <td>
               <span class="fa fa-times fa-fw act_kill_client" data-client-port="<?=$server['vpnid'];?>"
                 data-client-ip="<?=$conn['real_address'];?>"
                 style='cursor:pointer;'
                 title='Kill client connection from <?=$conn['real_address']; ?>'>
               </span>
            </td>
          </tr>
<?php
        endforeach;
    elseif (!empty($server['timestamp'])):?>
          <tr>
            <td><?=date('Y-m-d H:i:s', $server['timestamp']);?></td>
            <td><?=$server['real_address'];?><br/><?=$server['virtual_address'];?></td>
            <td>
            <span class='fa fa-exchange fa-fw <?=$server['status'] == "connected" ? "text-success" : "text-danger" ;?>'></span>
            </td>
          </tr>
<?php
    endif;?>
      </tbody>
    </table>
    <br/>
<?php
    endforeach; ?>

<?php
    if (!empty($openvpn_cfg['openvpn-client'])) {?>
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
foreach ($openvpn_cfg['openvpn-client'] as $client) :?>
        <tr>
          <td><?=$client['name'];?><br/><?=date('Y-m-d H:i:s', $client['timestamp']);?></td>
          <td><?=$client['real_address'];?><br/><?=$client['virtual_address'];?></td>
          <td>
            <span class='fa fa-exchange fa-fw <?=$client['status'] == "connected" ? "text-success" : "text-danger" ;?>'></span>
          </td>
        </tr>
<?php
      endforeach; ?>
      </tbody>
    </table>

<?php
}

if (empty($openvpn_cfg['openvpn-client']) && empty($openvpn_cfg['openvpn-server'])): ?>
    <table class="table table-striped table-condensed">
      <tr>
        <td><?= gettext('No OpenVPN instance defined or enabled.') ?></td>
      </tr>
    </table>
<?php endif;
