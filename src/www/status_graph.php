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
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header("Location: status_graph.php");
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
        $.ajax("legacy_traffic_stats.php", {
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
        setTimeout(update_bandwidth_stats, 1000);
      }
      update_bandwidth_stats();
  });
</script>

<section class="page-content-main">
  <div class="container-fluid">
    <div class="row">
      <section class="col-xs-12">
        <div class="content-box">
          <form name="form1" method="get">
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
                      <select id="if" name="if" onchange="document.form1.submit()">
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
                      <select id="sort" name="sort" onchange="document.form1.submit()">
                        <option value="">
                          <?= gettext('Bw In') ?>
                        </option>
                        <option value="out"<?= $pconfig['sort'] == "out" ? " selected=\"selected\"" : "";?>>
                          <?= gettext('Bw Out') ?>
                        </option>
                      </select>
                    </td>
                    <td>
                      <select id="filter" name="filter" onchange="document.form1.submit()">
                        <option value="local" <?=$pconfig['filter'] == "local" ? " selected=\"selected\"" : "";?>>
                          <?= gettext('Local') ?>
                        </option>
                        <option value="all" <?=$pconfig['filter'] == "all" ? " selected=\"selected\"" : "";?>>
                          <?= gettext('All') ?>
                        </option>
                      </select>
                    </td>
                    <td>
                      <select id="hostipformat" name="hostipformat" onchange="document.form1.submit()">
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
          </form>
        </div>
      </section>
      <section class="col-xs-12">
        <div class="content-box">
          <div class="col-sm-6 col-xs-12">
            <div>
                <br/>
                <object  data="graph.php?ifnum=<?=htmlspecialchars($pconfig['if']);?>&amp;ifname=<?=rawurlencode($ifdescrs[htmlspecialchars($pconfig['if'])]);?>">
                  <param name="id" value="graph" />
                  <param name="type" value="image/svg+xml" />
                  <param name="width" value="100%" />
                  <param name="height" value="100%" />
                  <param name="pluginspage" value="http://www.adobe.com/svg/viewer/install/auto" />
                </object>
            </div>
          </div>
          <div class="col-sm-6 col-xs-12">
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
          <div class="col-xs-12">
            <p><?=gettext("Note:"); ?> <?=sprintf(gettext('The %sAdobe SVG Viewer%s, Firefox 1.5 or later or other browser supporting SVG is required to view the graph.'),'<a href="http://www.adobe.com/svg/viewer/install/" target="_blank">','</a>'); ?></p>
          </div>
        </div>
      </section>
    </div>
  </div>
</section>

<?php include("foot.inc"); ?>
