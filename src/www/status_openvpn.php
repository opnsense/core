<?php

/*
 * Copyright (C) 2019 Franco Fichtner <franco@opnsense.org>
 * Copyright (C) 2014-2015 Deciso B.V.
 * Copyright (C) 2010 Jim Pingle <jimp@pfsense.org>
 * Copyright (C) 2008 Shrew Soft Inc. <mgrooms@shrew.net>
 * Copyright (C) 2005 Scott Ullrich <sullrich@gmail.com>
 * Copyright (C) 2005 Colin Smith <ethethlay@gmail.com>
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
require_once("plugins.inc.d/openvpn.inc");

function kill_client($port, $remipp)
{
    $tcpsrv = "unix:///var/etc/openvpn/{$port}.sock";
    $errval = '';
    $errstr = '';

    /* open a tcp connection to the management port of each server */
    $fp = @stream_socket_client($tcpsrv, $errval, $errstr, 1);
    $killed = -1;
    if ($fp) {
        stream_set_timeout($fp, 1);
        fputs($fp, "kill {$remipp}\n");
        while (!feof($fp)) {
            $line = fgets($fp, 1024);

            $info = stream_get_meta_data($fp);
            if ($info['timed_out']) {
                break;
            }
            /* parse header list line */
            if (strpos($line, "INFO:") !== false) {
                continue;
            }
            if (strpos($line, "SUCCESS") !== false) {
                $killed = 0;
            }
            break;
        }
        fclose($fp);
    }
    return $killed;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $vpnid = 0;
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] == 'kill') {
        $port = $_POST['port'];
        $remipp = $_POST['remipp'];
        if (!empty($port) && !empty($remipp)) {
            $retval = kill_client($port, $remipp);
            echo html_safe("|{$port}|{$remipp}|{$retval}|");
        } else {
            echo gettext("invalid input");
        }
        exit;
    }
}

$servers = openvpn_get_active_servers();
legacy_html_escape_form_data($servers);
$sk_servers = openvpn_get_active_servers("p2p");
legacy_html_escape_form_data($sk_servers);
$clients = openvpn_get_active_clients();
legacy_html_escape_form_data($clients);

include("head.inc"); ?>


<body>
<?php include("fbegin.inc"); ?>

<script>
    //<![CDATA[
    $(document).ready(function () {
        // link kill buttons
        $(".act_kill_client").click(function (event) {
            event.preventDefault();
            var port = $(this).data("client-port");
            var ip = $(this).data("client-ip");
            $.post(window.location, {action: 'kill', port: port, remipp: ip}, function (data) {
                location.reload();
            });
        });
        // link show/hide routes
        $(".act_show_routes").click(function () {
            $("*[data-for='" + $(this).attr('id') + "']").toggle();
        });

        // minimize all buttons, some of the buttons come from the shared service
        // functions, which outputs large buttons.
        $(".btn").each(function () {
            $(this).addClass("btn-xs");
        });

    });
    //]]>
</script>
<section class="page-content-main">
  <div class="container-fluid">
    <div class="row">
      <section class="col-xs-12">
        <!-- XXX unused? <form method="get" name="iform">-->
<?php foreach ($servers as $i => $server): ?>
          <div class="tab-content content-box __mb">
            <div class="table-responsive">
              <table class="table table-striped">
                <tbody>
                  <tr>
                    <td colspan="7">
                      <strong><?= $server['name'] ?> <?= gettext('Client connections') ?></strong>
                      <div class="pull-right">
                        <?php $ssvc = service_by_name('openvpn', array('id' => $server['vpnid'])); ?>
                        <?= service_control_icon($ssvc, true); ?>
                        <?= service_control_links($ssvc, true); ?>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td><strong><?= gettext('Common Name') ?></strong></td>
                    <td><strong><?= gettext('Real Address') ?></strong></td>
                    <td><strong><?= gettext('Virtual Address') ?></strong></td>
                    <td><strong><?= gettext('Connected Since') ?></strong></td>
                    <td><strong><?= gettext('Bytes Sent') ?></strong></td>
                    <td><strong><?= gettext('Bytes Received') ?></strong></td>
                    <td></td>
                  </tr>
<?php if (empty($server['conns'])): ?>
                  <tr>
                    <td colspan="7"><?= gettext('No OpenVPN clients are connected to this instance.') ?></td>
                  </tr>
<?php else: ?>
<?php foreach ($server['conns'] as $conn): ?>
                  <tr id="<?= html_safe("r:{$server['mgmt']}:{$conn['remote_host']}") ?>">
                    <td><?= $conn['common_name'] ?></td>
                    <td><?= $conn['remote_host'] ?></td>
                    <td><?= $conn['virtual_addr'] ?></td>
                    <td><?= $conn['connect_time'] ?></td>
                    <td><?= format_bytes($conn['bytes_sent']) ?></td>
                    <td><?= format_bytes($conn['bytes_recv']) ?></td>
                    <td>
<?php if (count($server['conns']) != 1 || $conn['common_name'] != '[error]'): ?>
                      <button data-client-port="<?= $server['mgmt']; ?>"
                        data-client-ip="<?= $conn['remote_host']; ?>"
                        title="<?= gettext("Kill client connection from") . " " . $conn['remote_host']; ?>"
                        class="act_kill_client btn btn-default">
                        <i class="fa fa-times fa-fw"></i>
                      </button>
<?php endif ?>
                    </td>
                  </tr>
<?php endforeach ?>
<?php endif ?>
<?php if (!empty($server['routes'])): ?>
                  <tr>
                    <td colspan="7">
                      <span style="cursor:pointer;" class="act_show_routes" id="showroutes_<?= $i ?>">
                        <i class="fa fa-chevron-down fa-fw"></i>
                        <strong><?= $server['name'] ?> <?= gettext('Routing Table') ?></strong>
                      </span>
                    </td>
                  </tr>
                  <tr style="display:none;" data-for="showroutes_<?= $i ?>">
                    <td><strong><?= gettext('Common Name') ?></strong></td>
                    <td><strong><?= gettext('Real Address') ?></strong></td>
                    <td><strong><?= gettext('Target Network') ?></strong></td>
                    <td><strong><?= gettext('Last Used') ?></strong></td>
                    <td colspan="3">
                  </tr>
<?php foreach ($server['routes'] as $conn): ?>
                  <tr style="display:none;" data-for="showroutes_<?= $i ?>" id="<?= html_safe("r:{$server['mgmt']}:{$conn['remote_host']}") ?>">
                    <td><?= $conn['common_name'] ?></td>
                    <td><?= $conn['remote_host'] ?></td>
                    <td><?= $conn['virtual_addr'] ?></td>
                    <td><?= $conn['last_time'] ?></td>
                    <td colspan="3">
                  </tr>
<?php endforeach ?>
                  <tr style="display:none;" data-for="showroutes_<?= $i ?>">
                    <td colspan="7"><?= gettext("An IP address followed by C indicates a host currently connected through the VPN.") ?></td>
                  </tr>
                  </tr>
<?php endif ?>
                </tbody>
              </table>
            </div>
          </div>
<? endforeach ?>
<? if (!empty($sk_servers)): ?>
          <div class="tab-content content-box __mb">
            <div class="table-responsive">
              <table class="table table-striped">
                <tbody>
                  <tr>
                    <td colspan="8"><strong><?= gettext('Peer to Peer Server Instance Statistics') ?></strong></td>
                  </tr>
                  <tr>
                    <td><strong><?= gettext('Name') ?></strong></td>
                    <td><strong><?= gettext('Remote Host') ?></strong></td>
                    <td><strong><?= gettext('Virtual Addr') ?></strong></td>
                    <td><strong><?= gettext('Connected Since') ?></strong></td>
                    <td><strong><?= gettext('Bytes Sent') ?></strong></td>
                    <td><strong><?= gettext('Bytes Received') ?></strong></td>
                    <td><strong><?= gettext('Status') ?></strong></td>
                    <td></td>
                  </tr>
<?php foreach ($sk_servers as $sk_server): ?>
                  <tr id="<?= html_safe("r:{$sk_server['port']}:{$sk_server['vpnid']}") ?>">
                    <td><?= $sk_server['name'] ?></td>
                    <td><?= $sk_server['remote_host'] ?></td>
                    <td><?= $sk_server['virtual_addr'] ?></td>
                    <td><?= $sk_server['connect_time'] ?></td>
                    <td><?= format_bytes($sk_server['bytes_sent']) ?></td>
                    <td><?= format_bytes($sk_server['bytes_recv']) ?></td>
                    <td><?= $sk_server['status'] ?></td>
                    <td>
                      <div>
                        <?php $ssvc = service_by_name('openvpn', array('id' => $sk_server['vpnid'])); ?>
                        <?= service_control_icon($ssvc, true); ?>
                        <?= service_control_links($ssvc, true); ?>
                      </div>
                    </td>
                  </tr>
<?php endforeach ?>
                </tbody>
              </table>
            </div>
          </div>
<? endif ?>
<?php if (!empty($clients)): ?>
          <div class="tab-content content-box __mb">
            <div class="table-responsive">
              <table class="table table-striped">
                <tbody>
                  <tr>
                    <td colspan="8"><strong><?= gettext('Client Instance Statistics') ?><strong></td>
                  </tr>
                  <tr>
                    <td><strong><?= gettext('Name') ?></strong></td>
                    <td><strong><?= gettext('Remote Host') ?></strong></td>
                    <td><strong><?= gettext('Virtual Addr') ?></strong></td>
                    <td><strong><?= gettext('Connected Since') ?></strong></td>
                    <td><strong><?= gettext('Bytes Sent') ?></strong></td>
                    <td><strong><?= gettext('Bytes Received') ?></strong></td>
                    <td><strong><?= gettext('Status') ?></strong></td>
                    <td></td>
                  </tr>
<?php foreach ($clients as $client): ?>
                  <tr id="<?= html_safe("r:{$client['port']}:{$client['vpnid']}") ?>">
                    <td><?= $client['name'] ?></td>
                    <td><?= $client['remote_host'] ?></td>
                    <td><?= $client['virtual_addr'] ?></td>
                    <td><?= $client['connect_time'] ?></td>
                    <td><?= format_bytes($client['bytes_sent']) ?></td>
                    <td><?= format_bytes($client['bytes_recv']) ?></td>
                    <td><?= $client['status'] ?></td>
                    <td>
                      <div>
                        <?php $ssvc = service_by_name('openvpn', array('id' => $client['vpnid'])); ?>
                        <?= service_control_icon($ssvc, true); ?>
                        <?= service_control_links($ssvc, true); ?>
                      </div>
                    </td>
                  </tr>
<?php endforeach ?>
                </tbody>
              </table>
            </div>
          </div>
<?php endif ?>
<?php if (empty($clients) && empty($servers) && empty($sk_servers)): ?>
          <div class="tab-content content-box __mb">
            <div class="table-responsive">
              <table class="table-responsive table table-striped">
                <tbody>
                  <tr>
                    <td colspan="8"><strong><?= gettext('OpenVPN Status') ?></strong></th>
                  </tr>
                  <tr>
                    <td colspan="8"><?= gettext('No OpenVPN instance defined') ?></td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
<?php endif ?>
        <!--</form>-->
      </section>
    </div>
  </div>
</section>
<?php

include 'foot.inc';
