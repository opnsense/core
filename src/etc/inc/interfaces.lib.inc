<?php

/*
 * Copyright (c) 2015-2024 Franco Fichtner <franco@opnsense.org>
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

function legacy_interface_error($cmd, $opt = null)
{
    $msg = 'The command `' . $cmd . '\' failed to execute';

    if ($opt !== null) {
        $msg .= " ({$opt})";
    }

    log_msg($msg, LOG_ERR);
}

function legacy_interface_listget($flag = 'all')
{
    $cmd_wlan = 'sysctl -n net.wlan.devices';
    $cmd = '/sbin/ifconfig -l';
    $ifs_wlan = [];
    $ifs = [];

    exec($cmd_wlan . ' 2>&1', $out_wlan, $ret_wlan);
    if (!$ret_wlan && !empty($out_wlan[0])) {
        $ifs_wlan = explode(' ', $out_wlan[0]);
    }

    if ($flag === 'up') {
        $cmd .= 'u';
    } elseif ($flag === 'wlan') {
        return ($ifs_wlan);
    }

    exec($cmd . ' 2>&1', $out, $ret);
    if ($ret) {
        legacy_interface_error($cmd);
        return ($ifs);
    }

    if (isset($out[0])) {
        $ifs = explode(' ', $out[0]);
    }

    if (count($ifs_wlan)) {
        $ifs = array_merge($ifs, $ifs_wlan);
    }

    return ($ifs);
}

function legacy_interface_flags($ifs, $flag, $report_errors = true)
{
    /* $flags isn't escaped because it can be an argument list */
    $cmd = '/sbin/ifconfig ' . escapeshellarg($ifs) . ' ' . $flag;

    exec($cmd . ' 2>&1', $out, $ret);
    if (!empty($ret) && $report_errors) {
        legacy_interface_error($cmd);
    }
}

function legacy_interface_create($ifs, $name = null)
{
    $cmd = '/sbin/ifconfig ' . escapeshellarg($ifs) . ' create';
    $new = null;

    exec($cmd . ' 2>&1', $out, $ret);
    if ($ret) {
        legacy_interface_error($cmd);
        return ($new);
    }

    if (isset($out[0])) {
        $new = $out[0];
    }

    if (!empty($name)) {
        $cmd = '/sbin/ifconfig ' . escapeshellarg($new) . ' name ' . escapeshellarg($name);

        exec($cmd . ' 2>&1', $out, $ret);
        if ($ret) {
            legacy_interface_error($cmd);
        }

        /* return new name here anyway to force proper device not found errors later */
        $new = $name;
    } elseif (empty($new)) {
        /* we are here but have no name which means it was passed via $ifs instead */
        $new = $ifs;
    }

    return ($new);
}

function legacy_interface_destroy($ifs)
{
    $cmd = '/sbin/ifconfig ' . escapeshellarg($ifs) . ' destroy';

    exec($cmd . ' 2>&1', $out, $ret);
    if ($ret) {
        /* disable error reporting to avoid spurious errors on first configuration */
        //legacy_interface_error($cmd);
    }
}

function legacy_interface_setaddress($ifs, $addr, $family = 4)
{
    $cmd = implode(' ', ['/sbin/ifconfig', escapeshellarg($ifs), $family == 6 ? 'inet6' : 'inet', escapeshellarg($addr), 'alias']);

    exec($cmd . ' 2>&1', $out, $ret);
    if ($ret) {
        legacy_interface_error($cmd);
    }
}

function legacy_interface_deladdress($ifs, $addr, $family = 4)
{
    $cmd = implode(' ', ['/sbin/ifconfig', escapeshellarg($ifs), $family == 6 ? 'inet6' : 'inet', escapeshellarg($addr), '-alias']);

    exec($cmd . ' 2>&1', $out, $ret);
    if ($ret) {
        legacy_interface_error($cmd);
    }
}

function legacy_interface_mtu($ifs, $mtu)
{
    $cmd = '/sbin/ifconfig ' . escapeshellarg($ifs) . ' mtu ' . escapeshellarg($mtu);

    exec($cmd . ' 2>&1', $out, $ret);
    if ($ret) {
        legacy_interface_error($cmd);
    }
}

function legacy_bridge_member($ifs, $member)
{
    $cmd = '/sbin/ifconfig ' . escapeshellarg($ifs) . ' addm ' . escapeshellarg($member);

    exec($cmd . ' 2>&1', $out, $ret);
    if ($ret) {
        legacy_interface_error($cmd);
    }
}

function legacy_vlan_tag($ifs, $member, $tag, $pcp, $proto)
{
    $cmd = '/sbin/ifconfig ' . escapeshellarg($ifs) . ' vlan ' . escapeshellarg($tag) . ' vlandev ' . escapeshellarg($member) . ' vlanpcp ' . escapeshellarg($pcp) . ' vlanproto ' . escapeshellarg($proto);

    exec($cmd . ' 2>&1', $out, $ret);
    if ($ret) {
        legacy_interface_error($cmd);
    }
}

function legacy_vlan_remove_tag($ifs)
{
    $cmd = '/sbin/ifconfig ' . escapeshellarg($ifs) . ' -vlandev';

    exec($cmd . ' 2>&1', $out, $ret);
    if ($ret) {
        legacy_interface_error($cmd);
    }
}

function legacy_vlan_pcp($ifs, $pcp)
{
    $cmd = '/sbin/ifconfig ' . escapeshellarg($ifs) . ' vlanpcp ' . escapeshellarg($pcp);

    exec($cmd . ' 2>&1', $out, $ret);
    if ($ret) {
        legacy_interface_error($cmd);
    }
}

function legacy_vlan_proto($ifs, $proto)
{
    $cmd = '/sbin/ifconfig ' . escapeshellarg($ifs) . ' vlanproto ' . escapeshellarg($proto);

    exec($cmd . ' 2>&1', $out, $ret);
    if ($ret) {
        legacy_interface_error($cmd);
    }
}

function legacy_interface_stats($ifs = null)
{
    if ($ifs != null) {
        // only request data for selected interface
        $cmd = '/usr/local/sbin/ifinfo ' . escapeshellarg($ifs);
    } else {
        // all interfaces
        $cmd = '/usr/local/sbin/ifinfo';
    }
    $stats = [];

    exec($cmd . ' 2>&1', $out, $ret);
    if ($ret) {
        legacy_interface_error($cmd);
        return $stats;
    }

    $current_interface = '';
    foreach ($out as $line) {
        if (preg_match('/^Interface ([^\s]+) \(([^\)]+)\):$/i', $line, $names)) {
            $stats[$names[1]] = [
                'device' => $names[1],
                'driver' => $names[2],
            ];
            $current_interface = $names[1];
        } elseif ($current_interface != '') {
            $stat = explode(':', $line);
            $stats[$current_interface][trim($stat[0])] = trim($stat[1]);
        }
    }
    if ($ifs != null) {
        return $stats[$current_interface];
    } else {
        return $stats;
    }
}

/**
 * detect interface capabilities using ifconfig -m
 * @param string|null $intf
 * @return array list of interface specifics indexed by physical interface name
 */
function legacy_interfaces_details($intf = null)
{
    $result = [];
    if (!empty($intf)) {
        $tmp_intf = escapeshellarg($intf);
    } else {
        $tmp_intf = '';
    }

    $cmd = '/sbin/ifconfig -m -v ' . $tmp_intf;
    exec($cmd . ' 2>&1', $ifconfig_data, $ret);
    if ($ret) {
        /* only error if no explicit interface was chosen */
        if (empty($intf)) {
            legacy_interface_error($cmd, implode(' ', $ifconfig_data));
        }
        return $result;
    }

    $vfaces = interfaces_virtual_patterns();
    $current_interface = null;

    foreach ($ifconfig_data as $lineid => $line) {
        $line_parts = explode(' ', $line);
        if (strpos(trim($line), 'flags=') !== false && $line[0] != "\t") {
            $current_interface = explode(':', $line)[0];
            $result[$current_interface] = [];
            $result[$current_interface]["flags"] = [];
            $result[$current_interface]["capabilities"] = [];
            $result[$current_interface]["options"] = [];
            $result[$current_interface]["macaddr"] = "00:00:00:00:00:00";
            $result[$current_interface]["ipv4"] = [];
            $result[$current_interface]["ipv6"] = [];
            $result[$current_interface]["supported_media"] = [];
            $result[$current_interface]["is_physical"] = !count(array_intersect(preg_split('/\d+/', $current_interface), $vfaces));
            $result[$current_interface]["device"] = $current_interface;
            if (preg_match("/ mtu ([0-9]*).*$/", $line, $matches)) {
                $result[$current_interface]["mtu"] = $matches[1];
            }
            if (preg_match("/<(.*)>.*$/", $line, $matches)) {
                $result[$current_interface]["flags"] = explode(",", strtolower($matches[1]));
            }
        } elseif (empty($current_interface)) {
            // skip parsing, no interface found (yet)
            continue;
        } elseif (strpos(trim($line), 'capabilities=') !== false) {
            // parse capabilities
            $capabilities = substr($line, strpos($line, '<') + 1, -1);
            foreach (explode(',', $capabilities) as $capability) {
                $result[$current_interface]["capabilities"][] = strtolower(trim($capability));
            }
        } elseif (strpos(trim($line), 'options=') === 0) {
            // parse options
            $options = substr($line, strpos($line, '<') + 1, -1);
            foreach (explode(',', $options) as $option) {
                $result[$current_interface]["options"][] = strtolower(trim($option));
            }
        } elseif (strpos($line, "\tether ") !== false) {
            // current mac address
            $result[$current_interface]["macaddr"] = $line_parts[1];
            // fill original as well if not found
            if (!isset($result[$current_interface]['macaddr_hw'])) {
                $result[$current_interface]['macaddr_hw'] = $line_parts[1];
            }
        } elseif (strpos($line, "\thwaddr ") !== false) {
            // original mac address
            $result[$current_interface]['macaddr_hw'] = $line_parts[1];
        } elseif (strpos($line, "\tinet ") !== false) {
            // IPv4 information
            unset($mask);
            unset($vhid);
            for ($i = 0; $i < count($line_parts); ++$i) {
                if ($line_parts[$i] == 'netmask') {
                    /* look-ahead due to keyword match */
                    $mask = substr_count(base_convert(hexdec($line_parts[$i + 1]), 10, 2), '1');
                    ++$i;
                } elseif ($line_parts[$i] == 'vhid') {
                    /* look-ahead due to keyword match */
                    $vhid = $line_parts[$i + 1];
                    ++$i;
                }
            }
            if (isset($mask)) {
                $tmp = ['ipaddr' => $line_parts[1], 'subnetbits' => $mask, 'tunnel' => false];
                if ($line_parts[2] == '-->') {
                    $tmp['tunnel'] = true;
                    $tmp['endpoint'] = $line_parts[3];
                }
                if (isset($vhid)) {
                    $tmp['vhid'] = $vhid;
                }
                $result[$current_interface]['ipv4'][] = $tmp;
            }
        } elseif (strpos($line, "\tinet6 ") !== false) {
            // IPv6 information
            $addr = strtok($line_parts[1], '%');
            $tmp = [
                'autoconf' => false,
                'deprecated' => false,
                'detached' => false,
                'ipaddr' => $addr,
                'link-local' => !!preg_match('/^fe[89ab][0-9a-f]:/i', $addr),
                'tentative' => false,
                'tunnel' => false,
            ];
            for ($i = 0; $i < count($line_parts); ++$i) {
                if ($line_parts[$i] == 'prefixlen') {
                    /* look-ahead due to keyword match */
                    $tmp['subnetbits'] = intval($line_parts[$i + 1]);
                    ++$i;
                } elseif ($line_parts[$i] == 'vhid') {
                    /* look-ahead due to keyword match */
                    $tmp['vhid'] = $line_parts[$i + 1];
                    ++$i;
                } elseif ($line_parts[$i] == '-->') {
                    /* look-ahead due to keyword match */
                    $tmp['endpoint'] = $line_parts[$i + 1];
                    $tmp['tunnel'] = true;
                    ++$i;
                } elseif ($line_parts[$i] == 'autoconf') {
                    $tmp['autoconf'] = true;
                } elseif ($line_parts[$i] == 'deprecated') {
                    $tmp['deprecated'] = true;
                } elseif ($line_parts[$i] == 'detached') {
                    $tmp['detached'] = true;
                } elseif ($line_parts[$i] == 'tentative') {
                    $tmp['tentative'] = true;
                }
            }
            if (isset($tmp['subnetbits'])) {
                $result[$current_interface]['ipv6'][] = $tmp;
                // sort link local to bottom, leave rest of sorting as-is (primary address on top)
                usort($result[$current_interface]['ipv6'], function ($a, $b) {
                    return $a['link-local'] - $b['link-local'];
                });
            }
        } elseif (strpos($line, "\ttunnel ") !== false) {
            // extract tunnel proto, source and destination
            $result[$current_interface]["tunnel"] = [];
            $result[$current_interface]["tunnel"]["proto"] = $line_parts[1];
            $result[$current_interface]["tunnel"]["src_addr"] = $line_parts[2];
            $result[$current_interface]["tunnel"]["dest_addr"] = $line_parts[4];
        } elseif (preg_match("/media: (.*)/", $line, $matches)) {
            // media, when link is between parenthesis grep only the link part
            $result[$current_interface]['media'] = $matches[1];
            if (preg_match("/media: .*? \((.*?)\)/", $line, $matches)) {
                $result[$current_interface]['media'] = $matches[1];
            }
            $result[$current_interface]['media_raw'] = substr(trim($line), 7);
        } elseif (preg_match("/media (.*)/", $line, $matches)) {
            $result[$current_interface]["supported_media"][] = str_replace(" mediaopt ", " ", trim($matches[1]));
        } elseif (preg_match("/plugged: (.*)/", $line, $matches)) {
            $result[$current_interface]['sfp'] = [
                'plugged' => $matches[1]
            ];
        } elseif (
            isset($result[$current_interface]['sfp']) && /* safety precaution, plugged should be in the previous line */
            preg_match("/vendor:\s+(.*)\s+PN:(.*)\s+SN:\s+(.*)\s+DATE:\s+(.*)/", $line, $matches)
        ) {
            $result[$current_interface]['sfp']['vendor'] = $matches[1];
            $result[$current_interface]['sfp']['part_number'] = $matches[2];
            $result[$current_interface]['sfp']['serial_number'] = $matches[3];
            $result[$current_interface]['sfp']['manufacturing_date'] = $matches[4];
        } elseif (
            isset($result[$current_interface]['sfp']) &&
            preg_match("/module temperature:\s+(.*)\s+voltage:\s+(.*)Volts/", $line, $matches)
        ) {
            $result[$current_interface]['sfp']['temperature'] = $matches[1];
            $result[$current_interface]['sfp']['voltage'] = $matches[2];
        } elseif (
            isset($result[$current_interface]['sfp']) &&
            preg_match("/lane\s+(.*):\s+RX power:\s+(.*)\s+TX bias:\s+(.*)/", $line, $matches)
        ) {
            $result[$current_interface]['sfp'][sprintf('lane_%s_rx_power', $matches[1])] = $matches[2];
            $result[$current_interface]['sfp'][sprintf('lane_%s_tx_bias', $matches[1])] = $matches[3];
        } elseif (preg_match("/status: (.*)$/", $line, $matches)) {
            $result[$current_interface]['status'] = $matches[1];
        } elseif (preg_match("/channel (\S*)/", $line, $matches)) {
            $result[$current_interface]['channel'] = $matches[1];
        } elseif (preg_match("/ssid (\".*?\"|\S*)/", $line, $matches)) {
            $result[$current_interface]['ssid'] = $matches[1];
        } elseif (preg_match("/laggproto (\S+) lagghash (\S+)$/", $line, $matches)) {
            $result[$current_interface]['laggproto'] = $matches[1];
            $result[$current_interface]['lagghash'] = $matches[2];
        } elseif (preg_match("/laggproto (.*)$/", $line, $matches)) {
            $result[$current_interface]['laggproto'] = $matches[1];
        } elseif (preg_match("/lagg options:(.*)$/", $line, $matches)) {
            $next_line = $ifconfig_data[$lineid + 1];
            $result[$current_interface]['laggoptions'] = ['flags' => explode(
                ',',
                strtolower(substr($next_line, strpos($next_line, '<') + 1, -1))
            )];
            $next_line = $ifconfig_data[$lineid + 2];
            $result[$current_interface]['laggoptions']['flowid_shift'] = trim(explode(":", $next_line)[1]);
            $next_line = $ifconfig_data[$lineid + 3];
            if (strpos($next_line, "rr_limit:") !== false) {
                $result[$current_interface]['laggoptions']['rr_limit'] = trim(explode(":", $next_line)[1]);
            }
        } elseif (preg_match("/lagg statistics:(.*)$/", $line, $matches)) {
            $result[$current_interface]['laggstatistics'] = [];
            $next_line = $ifconfig_data[$lineid + 1];
            $result[$current_interface]['laggstatistics']['active ports'] = trim(explode(":", $next_line)[1]);
            $next_line = $ifconfig_data[$lineid + 2];
            $result[$current_interface]['laggstatistics']['flapping'] = trim(explode(":", $next_line)[1]);
        } elseif (
            preg_match("/laggport: (.*)\Wflags=\d+<(.*)> state=\d+<(.*)>$/", $line, $matches) ||
            preg_match("/laggport: (.*)\Wflags=\d+<(.*)>.*/", $line, $matches)
        ) {
            if (empty($result[$current_interface]['laggport'])) {
                $result[$current_interface]['laggport'] = [];
            }
            $result[$current_interface]['laggport'][trim($matches[1])] = [
                "flags" => explode(",", strtolower($matches[2])),
                "state" => isset($matches[3]) ? explode(",", strtolower($matches[3])) : [],
            ];
        } elseif (strpos($line, "\tgroups: ") !== false) {
            array_shift($line_parts);
            $result[$current_interface]['groups'] = $line_parts;
        } elseif (preg_match("/vlan: (.*)\Wvlanproto:\W(.*) vlanpcp:\W(.*) parent interface:\W(.*)$/", $line, $matches)) {
            $result[$current_interface]['vlan'] = [
                "tag" => $matches[1],
                "proto" => $matches[2],
                "pcp" => $matches[3],
                "parent" => $matches[4],
            ];
        } elseif (strpos($line, "\tcarp: ") !== false) {
            if (empty($result[$current_interface]["carp"])) {
                $result[$current_interface]["carp"] = [];
            }
            $result[$current_interface]["carp"][$line_parts[3]] = array(
                "status" => $line_parts[1],
                "vhid" => $line_parts[3],
                "advbase" => $line_parts[5],
                "advskew" => $line_parts[7],
                "peer" => null,
                "peer6" => null,
            );
            if (isset($ifconfig_data[$lineid + 1]) && strpos($ifconfig_data[$lineid + 1], "peer6") !== false) {
                preg_match("/\Wpeer (.*)\Wpeer6 (.*)$/", $ifconfig_data[$lineid + 1], $matches);
                $result[$current_interface]["carp"][$line_parts[3]]['peer'] = $matches[1];
                $result[$current_interface]["carp"][$line_parts[3]]['peer6'] = $matches[2];
            }
        } elseif (strpos($line, "\tvxlan") !== false) {
            if (empty($result[$current_interface]["vxlan"])) {
                $result[$current_interface]["vxlan"] = [];
            }
            $result[$current_interface]["vxlan"]["vni"] = $line_parts[2];
            $result[$current_interface]["vxlan"]["local"] = $line_parts[4];
            if ($line_parts[5] == "group") {
                $result[$current_interface]["vxlan"]["group"] = $line_parts[6];
                $result[$current_interface]['vxlan']['remote'] = null;
            } else {
                $result[$current_interface]['vxlan']['group'] = null;
                $result[$current_interface]["vxlan"]["remote"] = $line_parts[6];
            }
        } elseif (preg_match("/member: (.*)\Wflags=\w+<(.*)>.*/", $line, $matches)) {
            if (empty($result[$current_interface]["members"])) {
                $result[$current_interface]["members"] = [];
            }
            $result[$current_interface]["members"][$matches[1]] = [
                "flags" => explode(",", strtolower($matches[2]))
            ];
        } elseif (preg_match("/\tnd6 options=\w+<(.*)>/", $line, $matches)) {
            $result[$current_interface]["nd6"] = [
                "flags" => explode(",", strtolower($matches[1]))
            ];
        } elseif (preg_match("/\tid ([\w\:]+) priority (\d+) hellotime (\d+) fwddelay (\d+).*/", $line, $matches)) {
            $result[$current_interface]["priority"] = $matches[2];
            $result[$current_interface]["hellotime"] = $matches[3];
            $result[$current_interface]["fwddelay"] = $matches[4];
        } elseif (preg_match("/\tmaxage (\d+) holdcnt (\d+) proto (\w+) maxaddr (\d+) timeout (\d+).*/", $line, $matches)) {
            $result[$current_interface]["maxage"] = $matches[1];
            $result[$current_interface]["holdcnt"] = $matches[2];
            $result[$current_interface]["proto"] = $matches[3];
            $result[$current_interface]["maxaddr"] = $matches[4];
            $result[$current_interface]["timeout"] = $matches[5];
        }
    }

    return $result;
}

/**
 * fetch interface details for one interface
 * @param $intf string interface name
 * @return array list of interface specifics
 */
function legacy_interface_details($intf)
{
    $result = [];

    $details = legacy_interfaces_details($intf);
    if (isset($details[$intf])) {
        $result = $details[$intf];
    }

    return $result;
}

/**
 * configure interface hardware settings
 * @param string $ifs interface name
 */
function configure_interface_hardware($ifs, $intf_details = null)
{
    global $config;

    $hwsettings = $config['system'];

    /* XXX this needs fixing, lots of devices are not capable */
    if (
        strstr($ifs, 'vlan') || strpos($ifs, 'qinq') === 0 ||
        strpos($ifs, 'lo') === 0 || strpos($ifs, 'vxlan') === 0 ||
        strpos($ifs, 'ipsec') === 0 || strpos($ifs, '/') === 0
    ) {
        /* skip checksumming  */
        return;
    }

    if ($intf_details !== null) {
        $intf_details = $intf_details[$ifs];
    } else {
        $intf_details = legacy_interface_details($ifs);
    }

    /* interface overwrites */
    foreach ($config['interfaces'] as $iface => $ifconf) {
        if (!empty($ifconf['if']) && $ifconf['if'] == $ifs) {
            if (!empty($ifconf['hw_settings_overwrite'])) {
                $hwsettings = $ifconf;
            }
            break;
        }
    }

    if (!empty($intf_details)) {
        // get current settings
        $csum_set = in_array('rxcsum', $intf_details['options']) || in_array('txcsum', $intf_details['options']);
        $csumv6_set = in_array('rxcsum_ipv6', $intf_details['options']) || in_array('txcsum_ipv6', $intf_details['options']);
        $tso_set = in_array('tso4', $intf_details['options']) || in_array('tso6', $intf_details['options']);
        $lro_set = in_array('lro', $intf_details['options']);

        // hardware checksum offloading offloading
        if (isset($hwsettings['disablechecksumoffloading']) && $csum_set) {
            legacy_interface_flags($ifs, '-txcsum -rxcsum', false);
        } elseif (!isset($hwsettings['disablechecksumoffloading']) && !$csum_set) {
            legacy_interface_flags($ifs, 'txcsum rxcsum', false);
        }
        if (isset($hwsettings['disablechecksumoffloading']) && $csumv6_set) {
            legacy_interface_flags($ifs, '-txcsum6 -rxcsum6', false);
        } elseif (!isset($hwsettings['disablechecksumoffloading']) && !$csumv6_set) {
            legacy_interface_flags($ifs, 'txcsum6 rxcsum6', false);
        }

        // TCP segmentation offloading
        if (isset($hwsettings['disablesegmentationoffloading']) && $tso_set) {
            legacy_interface_flags($ifs, '-tso', false);
        } elseif (!isset($hwsettings['disablesegmentationoffloading']) && !$tso_set) {
            legacy_interface_flags($ifs, 'tso', false);
        }

        // large receive offload
        if (isset($hwsettings['disablelargereceiveoffloading']) && $lro_set) {
            legacy_interface_flags($ifs, '-lro', false);
        } elseif (!isset($hwsettings['disablelargereceiveoffloading']) && !$lro_set) {
            legacy_interface_flags($ifs, 'lro', false);
        }

        // disable/enable hardware VLAN tags, will be skipped when "Leave default" (option 2) is selected
        if (!isset($hwsettings['disablevlanhwfilter']) || $hwsettings['disablevlanhwfilter'] == 1) {
            // probe already selected options
            $selected_opts = [];
            foreach ($intf_details['options'] as $opt) {
                if ($opt == 'vlan_hwtagging') {
                    $selected_opts[] = 'vlanhwtag';
                } else {
                    $selected_opts[] = str_replace('_', '', $opt);
                }
            }
            // set one tag at a time to avoid driver issues
            foreach (array('vlanhwtag', 'vlanhwfilter', 'vlanhwtso', 'vlanhwcsum') as $tag) {
                if (!isset($hwsettings['disablevlanhwfilter']) && !in_array($tag, $selected_opts)) {
                    legacy_interface_flags($ifs, $tag);
                } elseif (isset($hwsettings['disablevlanhwfilter']) && in_array($tag, $selected_opts)) {
                    legacy_interface_flags($ifs, '-' . $tag);
                }
            }
        }
    }
}

function interfaces_virtual_patterns()
{
    /* list of virtual device type patterns */
    return [
        '_stf',
        '_vlan',
        '_wlan',
        'bridge',
        'carp',
        'enc',
        'gif',
        'gre',
        'ipfw', /* ipfw logging device, not enabled by default */
        'ipsec',
        'l2tp',
        'lagg',
        'lo',
        'ng',
        'ovpnc',
        'ovpns',
        'pflog',
        'pfsync',
        'plip',
        'ppp',
        'pppoe',
        'pptp',
        'qinq',
        'tap',
        'tun',
        'vlan',
        'vxlan',
        'wg',
    ];
}

/*
 *   get_interface_list() - Return a list of all physical interfaces
 *   along with MAC, IPv4 and status.
 *
 *   $only_active = false -- interfaces that are available in the system
 *                  true  -- interfaces that are physically connected
 *
 *   $include_dmesg = false -- skip probing dmesg for more information
 *                    true  -- probe dmesg for more information
 */
function get_interface_list($only_active = false, $include_dmesg = false)
{
    $dmesg_arr = [];
    $iflist = [];

    if ($include_dmesg) {
        exec('/sbin/dmesg', $dmesg_arr);
    }

    $vfaces = interfaces_virtual_patterns();

    $ifnames_wlan = legacy_interface_listget('wlan');
    $ifnames_up = legacy_interface_listget('up');
    $ifnames = legacy_interface_listget();

    $interface_data = legacy_interfaces_details();

    if ($only_active) {
        $_ifnames = [];
        $all_stats = legacy_interface_stats();
        foreach ($ifnames as $ifname) {
            $ifinfo = $all_stats[$ifname];
            if (!empty($ifinfo['link state']) && $ifinfo['link state'] == '2') {
                $_ifnames[] = $ifname;
            }
        }
        $ifnames = $_ifnames;
    }

    foreach ($ifnames as $ifname) {
        $tmp_ifnames = preg_split('/\d+/', $ifname);
        if (count(array_intersect($tmp_ifnames, $vfaces))) {
            continue;
        }
        if (in_array($ifname, $ifnames_wlan)) {
            continue;
        }

        $ifdata = !empty($interface_data[$ifname]) ? $interface_data[$ifname] : [];
        $toput = [
          'up' => in_array($ifname, $ifnames_up),
          'ipaddr' => !empty($ifdata['ipv4'][0]['ipaddr']) ? $ifdata['ipv4'][0]['ipaddr'] : null,
          'mac' => !empty($ifdata['macaddr']) ? $ifdata['macaddr'] : null,
          'descr' => '',
        ];

        foreach ($dmesg_arr as $dmesg_line) {
            $descr = [];
            if (preg_match("/\b{$ifname}: <(.*?)>/i", $dmesg_line, $descr) == 1) {
                $toput['descr'] = $descr[1];
                break;
            }
        }

        $iflist[$ifname] = $toput;
    }

    return $iflist;
}
