<?php

/*
    Copyright (C) 2014-2016 Deciso B.V.
    Copyright (C) 2004 Scott Ullrich
    Copyright (C) 2003-2004 Manuel Kasper <mk@neon1.net>.
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
require_once('interfaces.inc');
require_once("/usr/local/www/widgets/api/plugins/traffic.inc");

// Get configured interface list
$ifdescrs = get_configured_interface_with_descr();
if (isset($config['ipsec']['enable']) || isset($config['ipsec']['client']['enable'])) {
    $ifdescrs['enc0'] = "IPsec";
}
foreach (array('server', 'client') as $mode) {
    if (isset($config['openvpn']["openvpn-{$mode}"])) {
        foreach ($config['openvpn']["openvpn-{$mode}"] as $id => $setting) {
            if (!isset($setting['disable'])) {
                $ifdescrs['ovpn' . substr($mode, 0, 1) . $setting['vpnid']] = gettext("OpenVPN") . " ".$mode.": ".htmlspecialchars($setting['description']);
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // load initial form data
    $pconfig = array();
    $pconfig['if'] = array_keys($ifdescrs)[0];
    foreach($ifdescrs as $descr => $ifdescr) {
        if ($descr == $_GET['if']) {
            $pconfig['if'] = $descr;
            break;
        }
    }
    $pconfig['sort'] = !empty($_GET['sort']) ? $_GET['sort'] : "";
    $pconfig['filter'] = !empty($_GET['filter']) ? $_GET['filter'] : "";
    $pconfig['hostipformat'] = !empty($_GET['hostipformat']) ? $_GET['hostipformat'] : "";
    $pconfig['act'] = !empty($_GET['act']) ? $_GET['act'] : "";
    if ($pconfig['act'] == "traffic") {
        // traffic graph data
        echo json_encode(traffic_api());
        exit;
    } elseif ($pconfig['act'] == 'top') {
        // top data
        $result = array();
        $real_interface = get_real_interface($pconfig['if']);
        if (does_interface_exist($real_interface)) {
            $netmask = find_interface_subnet($real_interface);
            $intsubnet = gen_subnet(find_interface_ip($real_interface), $netmask) . "/$netmask";
            $cmd_args = $pconfig['filter'] == "local" ? " -c " . $intsubnet . " " : " -lc 0.0.0.0/0 ";
            $cmd_args .= $pconfig['sort'] == "out" ? " -T " : " -R ";
            $cmd_action = "/usr/local/bin/rate -i {$real_interface} -nlq 1 -Aba 20 {$cmd_args} | tr \"|\" \" \" | awk '{ printf \"%s:%s:%s:%s:%s\\n\", $1,  $2,  $4,  $6,  $8 }'";
            exec($cmd_action, $listedIPs);
            for ($idx = 2 ; $idx < count($listedIPs) ; ++$idx) {
                $fields = explode(':', $listedIPs[$idx]);
                if (!empty($pconfig['hostipformat'])) {
                    $addrdata = gethostbyaddr($fields[0]);
                    if ($pconfig['hostipformat'] == 'hostname' && $addrdata != $fields[0]){
                        $addrdata = explode(".", $addrdata)[0];
                    }
                } else {
                    $addrdata = $fields[0];
                }
                $result[] = array('host' => $addrdata, 'in' => $fields[1], 'out' => $fields[2]);
            }
        }
        echo json_encode($result);
        exit;
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header(url_safe('Location: /status_graph.php'));
    exit;
}

legacy_html_escape_form_data($pconfig);

include("head.inc");

?>
<body>
<?php include("fbegin.inc"); ?>

<script type="text/javascript">
  $( document ).ready(function() {
      function update_bandwidth_stats() {
        $.ajax("status_graph.php", {'type': 'get', 'cache': false, 'dataType': 'json', 'data': {'act': 'traffic'}}).done(function(data){
            traffic_widget_update($("[data-plugin=traffic]")[0], data);
        });
        $.ajax("status_graph.php", {
          type: 'get',
          cache: false,
          dataType: "json",
          data: { act: 'top',
                  if: $("#if").val(),
                  sort:  $("#sort").val(),
                  filter: $("#filter").val(),
                  hostipformat: $("#hostipformat").val()
                },
          success: function(data) {
            var html = [];
            $.each(data, function(idx, record){
                html.push('<tr>');
                html.push('<td>'+record.host+'</td>');
                html.push('<td>'+record.in+'</td>');
                html.push('<td>'+record.out+'</td>');
                html.push('</tr>');
            });
            $("#bandwidth_details").html(html.join(''));
          }
        });
        setTimeout(update_bandwidth_stats, 2000);
      }
      update_bandwidth_stats();

  });
</script>

<section class="page-content-main">
  <div class="container-fluid">
    <div class="row">
      <section class="col-xs-12">
        <div class="content-box">
          <div class="col-xs=-12">
<?php
            // plugin dashboard widget
            include ('/usr/local/www/widgets/widgets/traffic_graphs.widget.php');?>
          </div>
        </div>
      </section>
      <section class="col-xs-12">
        <div class="content-box">
            <div class="table-responsive" >
              <table class="table table-striped">
                <thead>
                  <tr>
                    <th><?=gettext("Interface"); ?></th>
                    <th><?= gettext('Sort by') ?></th>
                    <th><?= gettext('Filter') ?></th>
                    <th><?= gettext('Display') ?></th>
                  </tr>
                </thead>
                <tbody>
                  <tr>
                    <td>
                      <select id="if" name="if">
<?php
                      foreach ($ifdescrs as $ifn => $ifd):?>
                        <option value="<?=$ifn;?>" <?=$ifn == $pconfig['if'] ?  " selected=\"selected\"" : "";?>>
                            <?=htmlspecialchars($ifd);?>
                        </option>
<?php
                      endforeach;?>
                      </select>
                    </td>
                    <td>
                      <select id="sort" name="sort">
                        <option value="">
                          <?= gettext('Bw In') ?>
                        </option>
                        <option value="out"<?= $pconfig['sort'] == "out" ? " selected=\"selected\"" : "";?>>
                          <?= gettext('Bw Out') ?>
                        </option>
                      </select>
                    </td>
                    <td>
                      <select id="filter" name="filter">
                        <option value="local" <?=$pconfig['filter'] == "local" ? " selected=\"selected\"" : "";?>>
                          <?= gettext('Local') ?>
                        </option>
                        <option value="all" <?=$pconfig['filter'] == "all" ? " selected=\"selected\"" : "";?>>
                          <?= gettext('All') ?>
                        </option>
                      </select>
                    </td>
                    <td>
                      <select id="hostipformat" name="hostipformat">
                        <option value=""><?= gettext('IP Address') ?></option>
                        <option value="hostname" <?=$pconfig['hostipformat'] == "hostname" ? " selected" : "";?>>
                          <?= gettext('Host Name') ?>
                        </option>
                        <option value="fqdn" <?=$pconfig['hostipformat'] == "fqdn" ? " selected=\"selected\"" : "";?>>
                          <?= gettext('FQDN') ?>
                        </option>
                      </select>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
        </div>
      </section>
      <section class="col-xs-12">
        <div class="content-box">
          <div class="col-sm-12 col-xs-12">
            <div class="table-responsive" >
              <table class="table table-condensed">
                <thead>
                  <tr>
                      <td><?=empty($pconfig['hostipformat']) ? gettext("Host IP") : gettext("Host Name or IP"); ?></td>
                      <td><?=gettext("Bandwidth In"); ?></td>
                      <td><?=gettext("Bandwidth Out"); ?></td>
                 </tr>
                </thead>
                <tbody id="bandwidth_details">
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </section>
    </div>
  </div>
</section>

<?php include("foot.inc"); ?>
