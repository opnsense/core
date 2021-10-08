<?php

/*
 * Copyright (C) 2014-2021 Deciso B.V.
 * Copyright (C) 2004-2009 Scott Ullrich <sullrich@gmail.com>
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
require_once("config.inc");
require_once("interfaces.inc");
require_once("plugins.inc.d/dhcpd.inc");

function adjust_utc($dt)
{
    foreach (config_read_array('dhcpd') as $dhcpd) {
        if (!empty($dhcpd['dhcpleaseinlocaltime'])) {
            /* we want local time, so specify this is actually UTC */
            return strftime('%Y/%m/%d %H:%M:%S', strtotime("{$dt} UTC"));
        }
    }

    /* lease time is in UTC, here just pretend it's the correct time */
    return strftime('%Y/%m/%d %H:%M:%S UTC', strtotime($dt));
}

function remove_duplicate($array, $field)
{
    foreach ($array as $sub) {
        $cmp[] = $sub[$field];
    }
    $unique = array_unique(array_reverse($cmp,true));
    foreach ($unique as $k => $rien) {
        $new[] = $array[$k];
    }
    return $new;
}

$interfaces = legacy_config_get_interfaces(array('virtual' => false));

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $leases_content = dhcpd_leases(4);
    $leases_count = count($leases_content);

    exec("/usr/sbin/arp -an", $rawdata);
    $arpdata_ip = array();
    $arpdata_mac = array();
    foreach ($rawdata as $line) {
        $elements = explode(' ',$line);
        if ($elements[3] != "(incomplete)") {
            $arpent = array();
            $arpdata_ip[] = trim(str_replace(array('(',')'),'',$elements[1]));
            $arpdata_mac[] = strtolower(trim($elements[3]));
        }
    }
    unset($rawdata);
    $pools = array();
    $leases = array();
    $i = 0;
    $l = 0;
    $p = 0;

    // Put everything together again
    foreach($leases_content as $lease) {
        /* split the line by space */
        $data = explode(" ", $lease);
        /* walk the fields */
        $f = 0;
        $fcount = count($data);
        /* with less then 20 fields there is nothing useful */
        if ($fcount < 20) {
            $i++;
            continue;
        }
        while($f < $fcount) {
            switch($data[$f]) {
                case "failover":
                    $pools[$p]['name'] = trim($data[$f+2], '"');
                    $pools[$p]['name'] = "{$pools[$p]['name']} (" . convert_friendly_interface_to_friendly_descr(substr($pools[$p]['name'], 5)) . ")";
                    $pools[$p]['mystate'] = $data[$f+7];
                    $pools[$p]['peerstate'] = $data[$f+14];
                    $pools[$p]['mydate'] = $data[$f+10];
                    $pools[$p]['mydate'] .= " " . $data[$f+11];
                    $pools[$p]['peerdate'] = $data[$f+17];
                    $pools[$p]['peerdate'] .= " " . $data[$f+18];
                    $p++;
                    $i++;
                    continue 3;
                case "lease":
                    $leases[$l]['ip'] = $data[$f+1];
                    $leases[$l]['type'] = "dynamic";
                    $f = $f+2;
                    break;
                case "starts":
                    $leases[$l]['start'] = $data[$f+2];
                    $leases[$l]['start'] .= " " . $data[$f+3];
                    $f = $f+3;
                    break;
                case "ends":
                    $leases[$l]['end'] = $data[$f+2];
                    $leases[$l]['end'] .= " " . $data[$f+3];
                    $f = $f+3;
                    break;
                case "tstp":
                    $f = $f+3;
                    break;
                case "tsfp":
                    $f = $f+3;
                    break;
                case "atsfp":
                    $f = $f+3;
                    break;
                case "cltt":
                    $f = $f+3;
                    break;
                case "binding":
                    switch($data[$f+2]) {
                        case "active":
                            $leases[$l]['act'] = "active";
                            break;
                        case "free":
                            $leases[$l]['act'] = "expired";
                            $leases[$l]['online'] = "offline";
                            break;
                        case "backup":
                            $leases[$l]['act'] = "reserved";
                            $leases[$l]['online'] = "offline";
                            break;
                    }
                    $f = $f+1;
                    break;
                case "next":
                    /* skip the next binding statement */
                    $f = $f+3;
                    break;
                case "rewind":
                    /* skip the rewind binding statement */
                    $f = $f+3;
                    break;
                case "hardware":
                    $leases[$l]['mac'] = $data[$f+2];
                    /* check if it's online and the lease is active */
                    if (in_array($leases[$l]['ip'], $arpdata_ip)) {
                        $leases[$l]['online'] = 'online';
                    } else {
                        $leases[$l]['online'] = 'offline';
                    }
                    $f = $f+2;
                    break;
                case "client-hostname":
                    if ($data[$f + 1] != '') {
                        $leases[$l]['hostname'] = preg_replace('/"/','',$data[$f + 1]);
                    } else {
                        $hostname = gethostbyaddr($leases[$l]['ip']);
                        if ($hostname != '') {
                            $leases[$l]['hostname'] = $hostname;
                        }
                    }
                    $f = $f+1;
                    break;
                case "uid":
                    $f = $f+1;
                    break;
          }
          $f++;
        }
        $l++;
        $i++;
        /* slowly chisel away at the source array */
        array_shift($leases_content);
    }
    /* remove the old array */
    unset($lease_content);

    if (count($leases) > 0) {
        $leases = remove_duplicate($leases,"ip");
    }

    if (count($pools) > 0) {
        $pools = remove_duplicate($pools,"name");
        asort($pools);
    }

    $macs = [];
    foreach ($leases as $i => $this_lease) {
        if (!empty($this_lease['mac'])) {
            $macs[$this_lease['mac']] = $i;
        }
    }

    foreach (dhcpd_staticmap("not.found", legacy_interfaces_details(), false, 4) as $static) {
        $slease = [];
        $slease['ip'] = $static['ipaddr'];
        $slease['type'] = 'static';
        $slease['mac'] = $static['mac'];
        $slease['start'] = '';
        $slease['end'] = '';
        $slease['hostname'] = $static['hostname'];
        $slease['descr'] = $static['descr'];
        $slease['act'] = 'static';
        $slease['online'] = in_array(strtolower($slease['mac']), $arpdata_mac) ? 'online' : 'offline';

        if (isset($macs[$slease['mac']])) {
            /* update lease with static data */
            foreach ($slease as $key => $value) {
                if (!empty($value)) {
                    $leases[$macs[$slease['mac']]][$key] = $slease[$key];
                }
            }
        } else {
            $leases[] = $slease;
        }
    }

    if (isset($_GET['order']) && in_array($_GET['order'], ['int', 'ip', 'mac', 'hostname', 'descr', 'start', 'end', 'online', 'act'])) {
        $order = $_GET['order'];
    } else {
        $order = 'ip';
    }

    usort($leases,
        function ($a, $b) use ($order) {
            $cmp = ($order === 'ip') ? 0 : strnatcasecmp($a[$order], $b[$order]);
            if ($cmp === 0) {
                $cmp = ipcmp($a['ip'], $b['ip']);
            }
            return $cmp;
        }
    );
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_POST['deleteip']) && is_ipaddr($_POST['deleteip'])) {
        killbypid('/var/dhcpd/var/run/dhcpd.pid', 'TERM', true);
        $leasesfile = '/var/dhcpd/var/db/dhcpd.leases'; /* XXX needs wrapper */
        $fin = @fopen($leasesfile, "r");
        $fout = @fopen($leasesfile.".new", "w");
        if ($fin) {
            $ip_to_remove = $_POST['deleteip'];
            $lease = '';
            while (($line = fgets($fin, 4096)) !== false) {
                $fields = explode(' ', $line);
                if ($fields[0] == 'lease') {
                    // lease segment, record ip
                    $lease = trim($fields[1]);
                }

                if ($lease != $ip_to_remove) {
                    fputs($fout, $line);
                }

                if ($line == "}\n") {
                    // end of segment
                    $lease = '';
                }
            }
            fclose($fin);
            fclose($fout);
            @unlink($leasesfile);
            @rename($leasesfile.".new", $leasesfile);

            dhcpd_dhcp4_configure();
        }
    }
    exit;
}

$service_hook = 'dhcpd';

include("head.inc");

$leases_count = 0;

foreach ($leases as $data) {
   if (!($data['act'] == 'active' || $data['act'] == 'static' || $_GET['all'] == 1)) {
       continue;
   }
   $leases_count++;
}

$gentitle_suffix = " ($leases_count)";
legacy_html_escape_form_data($leases);

?>
<body>
  <script>
  $( document ).ready(function() {
      $(".act_delete").click(function(){
          $.post(window.location, {deleteip: $(this).data('deleteip')}, function(data) {
              location.reload();
          });
      });
      // keep sorting in place.
      $(".act_sort").click(function(){
          var all = <?=!empty($_GET['all']) ? 1 : 0;?> ;
          document.location = document.location.origin + window.location.pathname +"?all="+all+"&order="+$(this).data('field');
      });
  });

  </script>
<?php include("fbegin.inc"); ?>

<section class="page-content-main">
  <div class="container-fluid">
    <div class="row">
<?php
      /* only print pool status when we have one */
      legacy_html_escape_form_data($pools);
      if (count($pools) > 0):?>
      <section class="col-xs-12">
        <div class="content-box">
          <div class="table-responsive">
            <table class="table table-striped">
              <thead>
                <tr>
                  <th><?=gettext("Failover Group"); ?></th>
                  <th><?=gettext("My State"); ?></th>
                  <th><?=gettext("Since"); ?></th>
                  <th><?=gettext("Peer State"); ?></th>
                  <th><?=gettext("Since"); ?></th>
                </tr>
              </thead>
              <tbody>
<?php
              foreach ($pools as $data):?>
                <tr>
                    <td><?=$data['name'];?></td>
                    <td><?=$data['mystate'];?></td>
                    <td><?=adjust_utc($data['mydate']);?></td>
                    <td><?=$data['peerstate'];?></td>
                    <td><?=adjust_utc($data['peerdate']);?></td>
                </tr>
<?php
              endforeach;?>
              </tbody>
            </table>
          </div>
        </div>
      </section>

<?php
      endif;?>

      <section class="col-xs-12">
        <div class="content-box">
          <div class="table-responsive">
            <table class="table table-striped">
              <thead>
                <tr>
                    <td class="act_sort" data-field="int"><?=gettext("Interface"); ?></td>
                    <td class="act_sort" data-field="ip"><?=gettext("IP address"); ?></td>
                    <td class="act_sort" data-field="mac"><?=gettext("MAC address"); ?></td>
                    <td class="act_sort" data-field="hostname"><?=gettext("Hostname"); ?></td>
                    <td class="act_sort" data-field="descr"><?=gettext("Description"); ?></td>
                    <td class="act_sort" data-field="start"><?=gettext("Start"); ?></td>
                    <td class="act_sort" data-field="end"><?=gettext("End"); ?></td>
                    <td class="act_sort" data-field="online"><?=gettext("Status"); ?></td>
                    <td class="act_sort" data-field="act"><?=gettext("Lease type"); ?></td>
                    <td class="text-nowrap"></td>
                </tr>
              </thead>
              <tbody>
<?php
              // Load MAC-Manufacturer table
              $mac_man = json_decode(configd_run("interface list macdb json"), true);
              foreach ($leases as $data):
                  if (!($data['act'] == "active" || $data['act'] == "static" || $_GET['all'] == 1)) {
                      continue;
                  }
                  $dhcpd = array();
                  if (isset($config['dhcpd'])) {
                      $dhcpd = $config['dhcpd'];
                  }

                  $lip = ip2ulong($data['ip']);
                  foreach ($dhcpd as $dhcpif => $dhcpifconf) {
                      if (!empty($interfaces[$dhcpif]['ipaddr'])) {
                          $ip_min = gen_subnet($interfaces[$dhcpif]['ipaddr'], $interfaces[$dhcpif]['subnet']);
                          $ip_max = gen_subnet_max($interfaces[$dhcpif]['ipaddr'], $interfaces[$dhcpif]['subnet']);
                          if ($lip >= ip2ulong($ip_min) && $lip <= ip2ulong($ip_max)) {
                              $data['int'] = htmlspecialchars($interfaces[$dhcpif]['descr']);
                              $data['if'] = $dhcpif;
                          }
                      }
                  }
                  $mac_hi = strtoupper($data['mac'][0] . $data['mac'][1] . $data['mac'][3] . $data['mac'][4] . $data['mac'][6] . $data['mac'][7]);
              ?>
              <tr>
                  <td><?=$data['int'];?></td>
                  <td><?=$data['ip'];?></td>
                  <td>
                      <?=$data['mac'];?><br />
                      <small><i><?= !empty($mac_man[$mac_hi]) ? $mac_man[$mac_hi] : '' ?></i></small>
                  </td>
                  <td><?=$data['hostname'];?></td>
                  <td><?=$data['descr'];?></td>
                  <td><?= !empty($data['start']) ? adjust_utc($data['start']) : '' ?></td>
                  <td><?= !empty($data['end']) ? adjust_utc($data['end']) : '' ?></td>
                  <td>
                    <i class="fa fa-<?=$data['online']=='online' ? 'signal' : 'ban';?>" title="<?=$data['online'];?>" data-toggle="tooltip"></i>
                  </td>
                  <td><?=$data['act'];?></td>
                  <td class="text-nowrap">
<?php if (!empty($data['if'])): ?>
<?php if ($data['type'] == 'dynamic'): ?>
                      <a class="btn btn-default btn-xs" href="services_dhcp_edit.php?if=<?=$data['if'];?>&amp;mac=<?=$data['mac'];?>&amp;hostname=<?=$data['hostname'];?>">
                        <i class="fa fa-plus fa-fw" data-toggle="tooltip" title="<?=gettext("add a static mapping for this MAC address");?>"></i>
                      </a>
<?php if ($data['online'] != 'online'):?>

                      <a class="act_delete btn btn-default btn-xs" href="#" data-deleteip="<?=$data['ip'];?>">
                        <i class="fa fa-trash fa-fw" title="<?= html_safe(gettext('Delete')) ?>" data-toggle="tooltip"></i>
                      </a>
<?php endif ?>
<?php endif ?>
<?php endif ?>
                  </td>
              </tr>
<?php
              endforeach;?>
              </tbody>
            </table>
          </div>
        </div>
      </section>
      <section class="col-xs-12">
        <form method="get">
        <input type="hidden" name="order" value="<?=htmlspecialchars($_GET['order']);?>" />
<?php
        if (!empty($_GET['all'])): ?>
        <input type="hidden" name="all" value="0" />
        <input type="submit" class="btn btn-default" value="<?= html_safe(gettext('Show active and static leases only')) ?>" />
<?php
        else: ?>
        <input type="hidden" name="all" value="1" />
        <input type="submit" class="btn btn-default" value="<?= html_safe(gettext('Show all configured leases')) ?>" />
<?php
        endif; ?>
        </form>
<?php if ($leases == 0): ?>
        <p><?=gettext("No leases file found. Is the DHCP server active?") ?></p>
<?php endif ?>
      </section>
    </div>
  </div>
</section>
<?php

include("foot.inc");
