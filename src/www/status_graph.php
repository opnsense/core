<?php

/*
 * Copyright (C) 2014-2017 Deciso B.V.
 * Copyright (C) 2017 Jeffrey Gentes
 * Copyright (C) 2004 Scott Ullrich <sullrich@gmail.com>
 * Copyright (C) 2003-2004 Manuel Kasper <mk@neon1.net>
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
require_once('interfaces.inc');
require_once("/usr/local/www/widgets/api/plugins/traffic.inc");

// Get configured interface list
$ifdescrs = get_configured_interface_with_descr();
$interfaces = legacy_config_get_interfaces(array('virtual' => false));
$hostlist = array();
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

//Create array of hostnames from DHCP
foreach ($interfaces as $ifname => $ifarr) {
    foreach (array('dhcpd', 'dhcpdv6') as $dhcp) {
        if (isset($config[$dhcp][$ifname]['staticmap'])) {
            foreach($config[$dhcp][$ifname]['staticmap'] as $entry) {
                if (!empty($entry['hostname'])) {
                    $hostlist[$entry['ipaddr']] = htmlentities($entry['hostname']);
                }
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
        $intsubnet = find_interface_network($real_interface);
        if (is_subnetv4($intsubnet)) {
            $cmd_args = $pconfig['sort'] == 'out' ? ' -T' : ' -R';

            switch ($pconfig['filter']) {
              case 'local':
                $cmd_args .= exec_safe(' -c %s', $intsubnet);
                break;
              case 'private':
                $cmd_args .= ' -c 172.16.0.0/12 -c 192.168.0.0/16 -c 10.0.0.0/8';
                break;
              default:
                $cmd_args .= ' -lc 0.0.0.0/0';
                break;
            }

            $cmd_action = "/usr/local/bin/rate -v -i {$real_interface} -nlq 1 -Aba 20 {$cmd_args} | tr \"|\" \" \" | awk '{ printf \"%s:%s:%s:%s:%s\\n\", $1,  $2,  $4,  $6,  $8 }'";
            exec($cmd_action, $listedIPs);
            for ($idx = 2 ; $idx < count($listedIPs) ; ++$idx) {
                $fields = explode(':', $listedIPs[$idx]);
                if (!empty($pconfig['hostipformat'])) {
                    $addrdata = gethostbyaddr($fields[0]);
                    if ($pconfig['hostipformat'] == 'hostname' && $addrdata != $fields[0]){
                        $addrdata = explode(".", $addrdata)[0];
                    } else if ($pconfig['hostipformat'] == 'hostname' && array_key_exists($fields[0], $hostlist)) {
                        $addrdata = $hostlist[$fields[0]];
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
<style>
.minigraph path {
    stroke: steelblue;
    stroke-width: 1px;
    fill: none;
}
.minigraph {
    border-style: solid;
    border-color: lightgray;
    border-width: 0px 1px 1px 1px;
    padding: 0px;
    margin: 0px;
    margin-bottom: -5px;
}
.table-striped select {
  max-width: none;
}
</style>
<script>
    var graphtable = {};
    function formatSizeUnits(bytes){
        if      (bytes>=1000000000) {bytes=(bytes/1000000000).toFixed(2)+'G';}
        else if (bytes>=1000000)    {bytes=(bytes/1000000).toFixed(2)+'M';}
        else if (bytes>=1000)       {bytes=(bytes/1000).toFixed(2)+'k';}
        else if (bytes>=1)          {bytes=bytes.toFixed(2) +'b';}
        else                        {bytes='0.00b';}
        return bytes;
    }
    function containsHost(obj, list) {
        var i;
        for (i = 0; i < list.length; i++) {
            if (list[i].host === obj.host) {
                return true;
            }
        }
        return false;
    }
    // define dimensions of graph
    var m = [3, 3, 1, 3]; // margins of minigraphs
    var w = 150 - m[1] - m[3]; // width of minigraphs
    var h = 30 - m[0] - m[2]; // height of minigraphs
    var datasize = 45; //size of minigraph history - 45 @ 2sec poll = 1min 30sec history
    var maxvalue = 0; //top value of minigraph - changes based on incoming spikes
    var hostmax = 10; //arbitrary max of top 10 hosts

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
            var totalin = 0;
            var totalout = 0;
            var historyin;
            var historyout;
            if (record.in > maxvalue) {
                maxvalue = parseInt(record.in);
            }
            if (record.out > maxvalue) {
                maxvalue = parseInt(record.out);
            }
            if (record.host in graphtable) {
                        totalin = graphtable[record.host].totalin + parseFloat(record.in);
                        totalout = graphtable[record.host].totalout + parseFloat(record.out);
                        historyin = graphtable[record.host].historyin;
                        historyout = graphtable[record.host].historyout;
            } else {
                        totalin = parseFloat(record.in);
                        totalout = parseFloat(record.out);
                        historyin = Array.apply(null, Array(datasize)).map(Number.prototype.valueOf,0);
                        historyout = Array.apply(null, Array(datasize)).map(Number.prototype.valueOf,0);
            }
            historyin.push(parseInt(record.in));
            historyout.push(parseInt(record.out));
            historyin.shift();
            historyout.shift();
            graphtable[record.host] = record;
            graphtable[record.host].totalin = totalin;
            graphtable[record.host].totalout = totalout;
            graphtable[record.host].historyin = historyin;
            graphtable[record.host].historyout = historyout;
            var sum = historyin.reduce(function(a, b) { return a + b; });
                    graphtable[record.host].avgin = parseInt(sum / datasize);
                    sum = historyout.reduce(function(a, b) { return a + b; });
                    graphtable[record.host].avgout = parseInt(sum / datasize);
               });
               var tablearray = [];
               var sortval = $( "#sort option:selected" ).val();
               $.each(graphtable, function(idx, record){
            if (!containsHost(record, data)) {
                record.in = 0;
                record.out = 0;
                record.historyin.push(0);
                record.historyin.shift();
                record.historyout.push(0);
                record.historyout.shift();
                        var sum = record.historyin.reduce(function(a, b) { return a + b; });
                        record.avgin = parseInt(sum / datasize);
                        sum = record.historyout.reduce(function(a, b) { return a + b; });
                        record.avgout = parseInt(sum / datasize);
                    }
                    tablearray.push(record);
               });
               tablearray.sort(function(a, b) {
                    return parseFloat(b[sortval]) - parseFloat(a[sortval]);
               });
               if (tablearray.length > hostmax) {
                    tablearray.length = hostmax;
               }
               graphtable = {};
               $.each(tablearray, function(idx, record){
            graphtable[record.host] = record;
                    var x = d3.scale.linear().domain([0, datasize-1]).range([0, w]);
                    //using non-linear y so that large spikes don't zero out the other graphs
                    var y = d3.scale.pow().exponent(0.3).domain([0, maxvalue]).range([h, 0]);
                    var line = d3.svg.line()
                        .x(function(d,i) {
                            return x(i);
                        })
                        .y(function(d) {
                            return y(d);
                        });
                    var svg = document.createElementNS(d3.ns.prefix.svg, 'g');
                    var graphIn = d3.select(svg).append("svg:svg")
                          .attr("width", w + m[1] + m[3] + "px")
                          .attr("height", h + m[0] + m[2] + "px");
                    var svg2 = document.createElementNS(d3.ns.prefix.svg, 'g');
                    var graphOut = d3.select(svg).append("svg:svg")
                          .attr("width", w + m[1] + m[3] + "px")
                          .attr("height", h + m[0] + m[2] + "px");
                    html.push('<tr>');
                    html.push('<td>'+record.host+'</td>');
                    graphIn.append("svg:path").attr("d", line(record.historyin));
                    graphOut.append("svg:path").attr("d", line(record.historyout));
                    html.push('<td style="width: ' + w + 'px; height: ' + h + 'px;"><svg class="minigraph" style="width: ' + w  + 'px; height: ' + h + 'px;">' + graphIn.html() + '</svg></td>');
                    html.push('<td style="width: 55px;">' +formatSizeUnits(record.in)+'</td>');
                    html.push('<td style="width: ' + w + 'px; height: ' + h + 'px;"><svg class="minigraph" style="width: ' + w  + 'px; height: ' + h + 'px;">' + graphOut.html() + '</svg></td>');
                    html.push('<td style="width: 55px;">' +formatSizeUnits(record.out)+'</td>');
                    html.push('<td>'+formatSizeUnits(record.totalin)+'</td>');
                    html.push('<td>'+formatSizeUnits(record.totalout)+'</td>');
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
                    <th><?= gettext('Top') ?></th>
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
                        <option value="in">
                          <?= gettext('Bw In') ?>
                        </option>
                        <option value="out"<?= $pconfig['sort'] == "out" ? " selected=\"selected\"" : "";?>>
                          <?= gettext('Bw Out') ?>
                        </option>
                        <option value="avgin">
                          <?= gettext('Bw In Avg') ?>
                        </option>
                        <option value="avgout">
                          <?= gettext('Bw Out Avg') ?>
                        </option>
                         <option value="totalin">
                          <?= gettext('Total In') ?>
                        </option>
                         <option value="totalout">
                          <?= gettext('Total Out') ?>
                        </option>
                      </select>
                    </td>
                    <td>
                      <select id="filter" name="filter">
                        <option value="local" <?=$pconfig['filter'] == "local" ? " selected=\"selected\"" : "";?>>
                          <?= gettext('Local') ?>
                        </option>
                        <option value="private" <?=$pconfig['filter'] == "private" ? " selected=\"selected\"" : "";?>>
                          <?= gettext('RFC1918 Private Networks') ?>
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
                    <td>
                      <select id="hostmax" name="hostmax">
                        <option value="5">
                          <?= gettext('5') ?>
                        </option>
                        <option value="10" selected>
                          <?= gettext('10') ?>
                        </option>
                        <option value="20">
                          <?= gettext('20') ?>
                        </option>
                        <option value="30">
                          <?= gettext('30') ?>
                        </option>
                        <option value="40">
                          <?= gettext('40') ?>
                        </option>
                        <option value="50">
                          <?= gettext('50') ?>
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
                      <td colspan="2"><?=gettext("Bandwidth In"); ?></td>
                      <td colspan="2"><?=gettext("Bandwidth Out"); ?></td>
                      <td><?=gettext("Total In"); ?></td>
                      <td><?=gettext("Total Out"); ?></td>
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
<script>
  $('#if').on('change', function () {
        graphtable = {};
  });
  $('#filter').on('change', function () {
        graphtable = {};
  });
  $('#hostipformat').on('change', function () {
        graphtable = {};
  });
  $('#hostmax').on('change', function () {
        hostmax = parseInt($( "#hostmax option:selected" ).val());
  });
</script>
<?php include("foot.inc"); ?>
