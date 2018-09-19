<?php

/*
    Copyright (C) 2014-2016 Deciso B.V.
    Copyright (C) 2004-2009 Scott Ullrich <sullrich@gmail.com>
    Copyright (C) 2011 Seth Mos <seth.mos@dds.nl>
    Copyright (C) 2003-2004 Manuel Kasper <mk@neon1.net>
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
require_once("interfaces.inc");
require_once("services.inc");

function leasecmp($a, $b) {
  return strcmp($a[$_GET['order']], $b[$_GET['order']]);
}

function adjust_gmt($dt) {
    global $config;

    $dhcpv6leaseinlocaltime == "no";
    if (is_array($config['dhcpdv6'])) {
        $dhcpdv6 = $config['dhcpdv6'];
        foreach ($dhcpdv6 as $dhcpv6leaseinlocaltime) {
            $dhcpv6leaseinlocaltime = $dhcpv6leaseinlocaltime['dhcpv6leaseinlocaltime'];
            if ($dhcpv6leaseinlocaltime == "yes") {
                break;
            }
        }
    }

    $timezone = $config['system']['timezone'];
    $ts = strtotime($dt . " GMT");
    if ($dhcpv6leaseinlocaltime == "yes") {
        $this_tz = new DateTimeZone($timezone);
        $dhcp_lt = new DateTime(strftime("%I:%M:%S%p", $ts), $this_tz);
        $offset = $this_tz->getOffset($dhcp_lt);
        $ts = $ts + $offset;
        return strftime("%Y/%m/%d %I:%M:%S%p", $ts);
    } else {
        return strftime("%Y/%m/%d %H:%M:%S", $ts);
    }
}

function remove_duplicate($array, $field) {
    foreach ($array as $sub) {
        $cmp[] = $sub[$field];
    }
    $unique = array_unique(array_reverse($cmp,true));
    foreach ($unique as $k => $rien) {
        $new[] = $array[$k];
    }
    return $new;
}

function parse_duid($duid_string) {
    $parsed_duid = array();
    for ($i=0; $i < strlen($duid_string); $i++) {
        $s = substr($duid_string, $i, 1);
        if ($s == '\\') {
            $n = substr($duid_string, $i+1, 1);
            if (($n == '\\') || ($n == '"')) {
                $parsed_duid[] = sprintf("%02x", ord($n));
            } elseif (is_numeric($n)) {
                $parsed_duid[] = sprintf("%02x", octdec(substr($duid_string, $i+1, 3)));
                $i += 3;
            }
        } else {
            $parsed_duid[] = sprintf("%02x", ord($s));
        }
    }
    $iaid = array_slice($parsed_duid, 0, 4);
    $duid = array_slice($parsed_duid, 4);
    return array($iaid, $duid);
}

$interfaces = legacy_config_get_interfaces(array('virtual' => false));
$leasesfile = services_dhcpdv6_leasesfile();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $awk = "/usr/bin/awk";

    /* this pattern sticks comments into a single array item */
    $cleanpattern = "'{ gsub(\"^#.*\", \"\");} { gsub(\"^server-duid.*\", \"\");} { gsub(\";$\", \"\"); print;}'";
    /* We then split the leases file by } */
    $splitpattern = "'BEGIN { RS=\"}\";} {for (i=1; i<=NF; i++) printf \"%s \", \$i; printf \"}\\n\";}'";

    /* stuff the leases file in a proper format into a array by line */
    exec("/bin/cat {$leasesfile} | {$awk} {$cleanpattern} | {$awk} {$splitpattern} | /usr/bin/grep '^ia-.. '", $leases_content);
    $leases_count = count($leases_content);
    exec("/usr/sbin/ndp -an", $rawdata);
    $ndpdata = array();
    foreach ($rawdata as $line) {
        $elements = preg_split('/\s+/ ',$line);
        if ($elements[1] != "(incomplete)") {
            $ndpent = array();
            $ip = trim(str_replace(array('(',')'),'',$elements[0]));
            $ndpent['mac'] = trim($elements[1]);
            $ndpent['interface'] = trim($elements[2]);
            $ndpdata[$ip] = $ndpent;
        }
    }

    $pools = array();
    $leases = array();
    $prefixes = array();
    $mappings = array();
    $i = 0;
    $l = 0;
    $p = 0;

    // Put everything together again
    while($i < $leases_count) {
        $entry = array();
        /* split the line by space */
        $duid_split = array();
        preg_match('/ia-.. "(.*)" { (.*)/ ', $leases_content[$i], $duid_split);
        if (!empty($duid_split[1])) {
            $iaid_duid = parse_duid($duid_split[1]);
            $entry['iaid'] = hexdec(implode("", array_reverse($iaid_duid[0])));
            $entry['duid'] = implode(":", $iaid_duid[1]);
            $data = explode(" ", $duid_split[2]);
        } else {
            $data = explode(" ", $leases_content[$i]);
        }
        /* walk the fields */
        $f = 0;
        $fcount = count($data);
        /* with less then 12 fields there is nothing useful */
        if ($fcount < 12) {
            $i++;
            continue;
        }
        while($f < $fcount) {
            switch($data[$f]) {
                case "failover":
                    $pools[$p]['name'] = $data[$f+2];
                    $pools[$p]['mystate'] = $data[$f+7];
                    $pools[$p]['peerstate'] = $data[$f+14];
                    $pools[$p]['mydate'] = $data[$f+10];
                    $pools[$p]['mydate'] .= " " . $data[$f+11];
                    $pools[$p]['peerdate'] = $data[$f+17];
                    $pools[$p]['peerdate'] .= " " . $data[$f+18];
                    $p++;
                    $i++;
                    continue 3;
                case "ia-pd":
                    $is_prefix = true;
                case "ia-na":
                    $entry['iaid'] = $tmp_iaid;
                    $entry['duid'] = $tmp_duid;
                    if ($data[$f+1][0] == '"') {
                        $duid = "";
                        /* FIXME: This needs a safety belt to prevent an infinite loop */
                        while ($data[$f][strlen($data[$f])-1] != '"') {
                            $duid .= " " . $data[$f+1];
                            $f++;
                        }
                        $entry['duid'] = $duid;
                    } else {
                        $entry['duid'] = $data[$f+1];
                    }
                    $entry['type'] = "dynamic";
                    $f = $f+2;
                    break;
                case "iaaddr":
                    $entry['ip'] = $data[$f+1];
                    $entry['type'] = "dynamic";
                    if (in_array($entry['ip'], array_keys($ndpdata))) {
                        $entry['online'] = 'online';
                    } else {
                        $entry['online'] = 'offline';
                    }
                    $f = $f+2;
                    break;
                case "iaprefix":
                    $is_prefix = true;
                    $entry['prefix'] = $data[$f+1];
                    $entry['type'] = "dynamic";
                    $f = $f+2;
                    break;
                case "starts":
                    $entry['start'] = $data[$f+2];
                    $entry['start'] .= " " . $data[$f+3];
                    $f = $f+3;
                    break;
                case "ends":
                    $entry['end'] = $data[$f+2];
                    $entry['end'] .= " " . $data[$f+3];
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
                    $entry['start'] = $data[$f+2];
                    $entry['start'] .= " " . $data[$f+3];
                    $f = $f+3;
                    break;
                case "binding":
                    switch($data[$f+2]) {
                        case "active":
                            $entry['act'] = "active";
                            break;
                        case "free":
                            $entry['act'] = "expired";
                            $entry['online'] = "offline";
                            break;
                        case "backup":
                            $entry['act'] = "reserved";
                            $entry['online'] = "offline";
                            break;
                        case "released":
                            $entry['act'] = "released";
                            $entry['online'] = "offline";
                    }
                    $f = $f+1;
                    break;
                case "next":
                    /* skip the next binding statement */
                    $f = $f+3;
                    break;
                case "hardware":
                    $f = $f+2;
                    break;
                case "client-hostname":
                    if ($data[$f+1] <> "") {
                        $entry['hostname'] = preg_replace('/"/','',$data[$f+1]);
                    } else {
                        $hostname = gethostbyaddr($entry['ip']);
                        if ($hostname <> "") {
                            $entry['hostname'] = $hostname;
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
        if ($is_prefix) {
            $prefixes[] = $entry;
        } else {
            $leases[] = $entry;
            $mappings[$entry['iaid'] . $entry['duid']] = $entry['ip'];
        }
        $l++;
        $i++;
        $is_prefix = false;
    }

    if (count($leases) > 0) {
        $leases = remove_duplicate($leases,"ip");
    }

    if (count($prefixes) > 0) {
        $prefixes = remove_duplicate($prefixes,"prefix");
    }

    if (count($pools) > 0) {
        $pools = remove_duplicate($pools,"name");
        asort($pools);
    }

    foreach ($interfaces as $ifname => $ifarr) {
        if (isset($config['dhcpdv6'][$ifname]['staticmap'])) {
            foreach($config['dhcpdv6'][$ifname]['staticmap'] as $static) {
                $slease = array();
                $slease['ip'] = $static['ipaddrv6'];
                $slease['type'] = "static";
                $slease['duid'] = $static['duid'];
                $slease['start'] = "";
                $slease['end'] = "";
                $slease['hostname'] = htmlentities($static['hostname']);
                $slease['descr'] = htmlentities($static['descr']);
                $slease['act'] = "static";
                if (in_array($slease['ip'], array_keys($ndpdata))) {
                    $slease['online'] = 'online';
                } else {
                    $slease['online'] = 'offline';
                }

                $leases[] = $slease;
            }
        }
    }

    if ($_GET['order']) {
        usort($leases, "leasecmp");
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_POST['deleteip']) && is_ipaddr($_POST['deleteip'])) {
        // delete dhcp lease
        /* Stop DHCPD */
        killbyname("dhcpd");
        $fin = @fopen($leasesfile, "r");
        $fout = @fopen($leasesfile.".new", "w");
        if ($fin) {
            $ip_to_remove = $_POST['deleteip'];
            $iaaddr = "";
            $content_to_flush = array();
            while (($line = fgets($fin, 4096)) !== false) {
                $fields = explode(' ', trim($line));
                if ($fields[0] == 'iaaddr') {
                    // lease segment, record ip
                    $iaaddr = trim($fields[1]);
                    $content_to_flush[] = $line;
                } elseif ($fields[0] == 'ia-na' || count($content_to_flush) > 0) {
                    $content_to_flush[] = $line;
                } else {
                    // output data directly if we're not in a "ia-na" section
                    fputs($fout, $line);
                }

                if ($line == "}\n") {
                    if ($iaaddr != $ip_to_remove) {
                        // write ia-na section
                        foreach ($content_to_flush as $cached_line) {
                            fputs($fout, $cached_line);
                        }
                    } else {
                        // skip empty line
                        fgets($fin, 4096);
                    }
                    // end of segment
                    $content_to_flush = array();
                    $iaaddr = "";
                }
            }
            fclose($fin);
            fclose($fout);
            @unlink($leasesfile);
            @rename($leasesfile.".new", $leasesfile);
            /* Restart DHCP Service */
            services_dhcpd_configure();
        }
    }
    exit;
}

$service_hook = 'dhcpd6';

include("head.inc");

?>
<body>
  <script>
  $( document ).ready(function() {
      $(".act_delete").click(function(){
          $.post(window.location, {deleteip: $(this).data('deleteip')}, function(data) {
              location.reload();
          });
      });
  });
  </script>
<?php include("fbegin.inc"); ?>

<section class="page-content-main">
  <div class="container-fluid">
    <div class="row">

<?php
/* only print pool status when we have one */
if (count($pools) > 0):?>
      <section class="col-xs-12">
        <div class="content-box">
          <div class="table-responsive">
              <table class="table table-striped sortable __nomb">
              <tr>
                <td><?=gettext("Failover Group"); ?></a></td>
                <td><?=gettext("My State"); ?></a></td>
                <td><?=gettext("Since"); ?></a></td>
                <td><?=gettext("Peer State"); ?></a></td>
                <td><?=gettext("Since"); ?></a></td>
              </tr>
<?php
              foreach ($pools as $data):?>
              <tr>
                  <td><?=$data['name'];?></td>
                  <td><?=$data['mystate'];?></td>
                  <td><?=adjust_gmt($data['mydate']);?></td>
                  <td><?=$data['peerstate'];?></td>
                  <td><?=adjust_gmt($data['peerdate']);?></td>
              </tr>

<?php
              endforeach;?>
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
                    <th><?=gettext("Interface"); ?></td>
                    <th><?=gettext("IPv6 address"); ?></th>
                    <th><?=gettext("IAID"); ?></th>
                    <th><?=gettext("DUID"); ?></th>
                    <th><?=gettext("Hostname/MAC"); ?></th>
                    <th><?=gettext("Description"); ?></th>
                    <th><?=gettext("Start"); ?></th>
                    <th><?=gettext("End"); ?></th>
                    <th><?=gettext("Online"); ?></th>
                    <th><?=gettext("Lease Type"); ?></th>
                    <th class="text-nowrap"></th>
                </tr>
              </thead>
              <tbody>
<?php
              $mac_man = json_decode(configd_run("interface list macdb json"), true);
              foreach ($leases as $data):
                if ($data['act'] == "static") {
                    foreach ($config['dhcpdv6'] as $dhcpif => $dhcpifconf) {
                        if (isset($dhcpifconf['staticmap'])) {
                            foreach ($dhcpifconf['staticmap'] as $staticent) {
                                if ($data['ip'] == $staticent['ipaddr']) {
                                    $data['int'] = htmlspecialchars($interfaces[$dhcpif]['descr']);
                                    $data['if'] = $dhcpif;
                                    break;
                                }
                            }
                        }
                        /* exit as soon as we have an interface */
                        if ($data['if'] != "") {
                            break;
                        }
                    }
                } else {
                  $data['if'] = convert_real_interface_to_friendly_interface_name(guess_interface_from_ip($data['ip']));
                  $data['int'] = htmlspecialchars($interfaces[$data['if']]['descr']);
                }
                ?>
                <tr>
                  <td><?=$data['int'];?></td>
                  <td><?=$data['ip'];?></td>
                  <td><?=$data['iaid'];?></td>
                  <td><?=$data['duid'];?></td>
                  <td>
                    <?=!empty($data['hostname']) ? htmlentities($data['hostname']) : "";?>
                    <?=!empty($ndpdata[$data['ip']]) ? $ndpdata[$data['ip']]['mac'] : "";?>
                  </td>
                  <td><?=htmlentities($data['descr']);?></td>
                  <td><?=$data['type'] != "static" ? adjust_gmt($data['start']) : "";?></td>
                  <td><?=$data['type'] != "static" ? adjust_gmt($data['end']) : "";?></td>
                  <td><?=$data['online'];?></td>
                  <td><?=$data['act'];?></td>
                  <td class="text-nowrap">
<?php if (!empty($data['if'])): ?>
<?php if ($data['type'] == 'dynamic'): ?>
                        <a class="btn btn-default btn-xs" href="services_dhcpv6_edit.php?if=<?=$data['if'];?>&amp;duid=<?=$data['duid'];?>&amp;hostname=<?=$data['hostname'];?>">
                          <i class="fa fa-plus fa-fw" alt="add"></i>
                        </a>
<?php if ($data['online'] != 'online'): ?>
                    <a class="act_delete btn btn-default btn-xs" href="#" data-deleteip="<?=$data['ip'];?>" title="<?= html_safe(gettext('Delete')) ?>" data-toggle="tooltip">
                      <i class="fa fa-trash fa-fw"></i>
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
        <div class="content-box">
          <header class="content-box-head container-fluid">
           <h3><?=gettext("Delegated Prefixes");?></h3>
          </header>
          <div class="table-responsive">
            <table class="table table-striped">
              <thead>
                <tr>
                  <th><?=gettext("IPv6 Prefix"); ?></th>
                  <th><?=gettext("IAID"); ?></th>
                  <th><?=gettext("DUID"); ?></th>
                  <th><?=gettext("Start"); ?></th>
                  <th><?=gettext("End"); ?></th>
                  <th><?=gettext("State"); ?></th>
                </tr>
              </thead>
              <tbody>
<?php
                foreach ($prefixes as $data):?>
                <tr>
                  <td>
                    <?=!empty($mappings[$data['iaid'] . $data['duid']]) ? $mappings[$data['iaid'] . $data['duid']] : "";?>
                    <?=$data['prefix'];?>
                  </td>
                  <td><?=$data['iaid'];?></td>
                  <td><?=$data['duid'];?></td>
                  <td><?=$data['type'] != "static" ? adjust_gmt($data['start']) : "";?></td>
                  <td><?=$data['type'] != "static" ? adjust_gmt($data['end']) : "";?></td>
                  <td><?=$data['act'];?></td>
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
          <?php if ($_GET['all']): ?>
          <input type="hidden" name="all" value="0" />
          <input type="submit" class="btn btn-default" value="<?=gettext("Show active and static leases only"); ?>" />
          <?php else: ?>
          <input type="hidden" name="all" value="1" />
          <input type="submit" class="btn btn-default" value="<?=gettext("Show all configured leases"); ?>" />
          <?php endif; ?>
          </form>
          <?php if ($leases == 0): ?>
          <p><strong><?=gettext("No leases file found. Is the DHCP server active"); ?>?</strong></p>
          <?php endif; ?>
      </section>
    </div>
  </div>
</section>

<?php include("foot.inc"); ?>
