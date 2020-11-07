<?php

/*
 * Copyright (C) 2015 S. Linke <dev@devsash.de>
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

$leasesfile = dhcpd_dhcpv4_leasesfile();
/* lease 192.168.42.1 {
    starts 0 2000/01/30 08:02:54;
    ends 5 2000/02/04 08:02:54;
    hardware ethernet
       00:50:04:53:D5:57;
    uid 01:00:50:04:53:D5:57;
    client-hostname "PC0097";
    }

    lease 192.168.42.1 {
        starts 0 2000/01/30 08:02:54
        ends 5 2000/02/04 08:02:54
        hardware ethernet
           00:50:04:53:D5:57
        uid 01:00:50:04:53:D5:57
        client-hostname "PC0097"
        }

lease 192.168.42.1 { starts 0 2000/01/30 08:02:54 ends 5 2000/02/04 08:02:54 hardware ethernet 00:50:04:53:D5:57 uid 01:00:50:04:53:D5:57 client-hostname "PC0097" }
lease 192.168.42.1 { starts 0 2000/01/30 08:02:54 ends 5 2000/02/04 08:02:54 hardware ethernet 00:50:04:53:D5:57 uid 01:00:50:04:53:D5:57 client-hostname "PC0097" }
lease 10.1.4.139 { starts 1 2011/04/18 19:19:07 ends 2 2011/04/19 07:19:07 binding state active next binding state free hardware ethernet 00:21:00:17:c7:8c uid '123' client-hostname 'CFA1000818' }
*/

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $awk = "/usr/bin/awk";
    /* this pattern sticks comments into a single array item */
    $cleanpattern = "'{ gsub(\"#.*\", \"\");} { gsub(\";\", \"\"); print;}'";
    /* We then split the leases file by } */
    $splitpattern = "'BEGIN { RS=\"}\";} {for (i=1; i<=NF; i++) printf \"%s \", \$i; printf \"}\\n\";}'";

    /* stuff the leases file in a proper format into an array by line */
    exec("/bin/cat {$leasesfile} | {$awk} {$cleanpattern} | {$awk} {$splitpattern}", $leases_content);
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

    /* remove duplicate items by mac address */
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
    foreach ($interfaces as $ifname => $ifarr) {
        if (isset($config['dhcpd'][$ifname]['staticmap'])) {
            foreach($config['dhcpd'][$ifname]['staticmap'] as $static) {
                $slease = array();
                $slease['ip'] = $static['ipaddr'];
                $slease['type'] = "static";
                $slease['mac'] = $static['mac'];
                $slease['start'] = '';
                $slease['end'] = '';
                $slease['hostname'] = $static['hostname'];
                $slease['descr'] = $static['descr'];
                $slease['act'] = "static";
                $slease['online'] = in_array(strtolower($slease['mac']), $arpdata_mac) ? 'online' : 'offline';
                if (isset($macs[$slease['mac']])) {
                    // update lease with static data
                    foreach ($slease as $key => $value) {
                        if (!empty($value)) {
                            $leases[$macs[$slease['mac']]][$key] = $slease[$key];
                        }
                    }
                } else {
                    $leases[] = $slease;
                }
            }
        }
    }

    $order = ( $_GET['order'] ) ? $_GET['order'] : 'ip';

    usort($leases,
        function ($a, $b) use ($order) {
            $cmp = strnatcasecmp($a[$order], $b[$order]);
            if ($cmp === 0) {
                $cmp = strnatcasecmp($a['ip'], $b['ip']);
            }
            return $cmp;
        }
    );
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_POST['deleteip']) && is_ipaddr($_POST['deleteip'])) {
        killbypid('/var/dhcpd/var/run/dhcpd.pid', 'TERM', true);
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

            dhcpd_dhcp_configure(false, 'inet');
        }
    }
    exit;
}

?>




    <div class="content-box" style="overflow:scroll;">
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <td><?=gettext("Interface"); ?></td>
                        <td class="act_sort" data-field="ip"><?=gettext("IP address"); ?></td>
                        <td class="act_sort" data-field="mac"><?=gettext("MAC address"); ?></td>
                        <td class="act_sort" data-field="hostname"><?=gettext("Hostname"); ?></td>
                        <td class="act_sort" data-field="descr"><?=gettext("Description"); ?></td>
                        <td class="act_sort" data-field="start"><?=gettext("Start"); ?></td>
                        <td class="act_sort" data-field="end"><?=gettext("End"); ?></td>
                        <td class="act_sort" data-field="online"><?=gettext("Status"); ?></td>
                        <td class="act_sort" data-field="act"><?=gettext("Lease type"); ?></td>
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
                        </td>
                    </tr>
                    <?php
                        endforeach;?>
                </tbody>
            </table>
        </div>
    </div>

