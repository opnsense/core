<?php

/*
 * Copyright (c) 2015-2021 Franco Fichtner <franco@opnsense.org>
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

function legacy_interface_listget($flag = '')
{
    $cmd_wlan = 'sysctl -n net.wlan.devices';
    $cmd = '/sbin/ifconfig -l';
    $ifs_wlan = null;
    $ifs = null;

    exec($cmd_wlan . ' 2>&1', $out_wlan, $ret_wlan);
    if (!$ret_wlan && !empty($out_wlan[0])) {
        $ifs_wlan = explode(' ', $out_wlan[0]);
    }

    if ($flag === 'up') {
        $cmd .= 'u';
    } elseif ($flag === 'down') {
        $cmd .= 'd';
    }

    exec($cmd . ' 2>&1', $out, $ret);
    if ($ret) {
        log_error('The command `' . $cmd . '\' failed to execute');
        return ($ifs);
    }

    if (isset($out[0])) {
        $ifs = explode(' ', $out[0]);
    }

    if ($ifs_wlan != null) {
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
        log_error('The command `' . $cmd . '\' failed to execute');
    }
}

function legacy_interface_rename($ifs, $name)
{
    $cmd = '/sbin/ifconfig ' . escapeshellarg($ifs) . ' name ' . escapeshellarg($name);

    exec($cmd . ' 2>&1', $out, $ret);
    if ($ret) {
        log_error('The command `' . $cmd . '\' failed to execute');
    }
}

function legacy_interface_create($ifs, $name = null)
{
    $cmd = '/sbin/ifconfig ' . escapeshellarg($ifs) . ' create';
    $new = null;

    exec($cmd . ' 2>&1', $out, $ret);
    if ($ret) {
        log_error('The command `' . $cmd . '\' failed to execute');
        return ($new);
    }

    if (isset($out[0])) {
        $new = $out[0];
    }

    if (!empty($name)) {
        legacy_interface_rename($new, $name);
        $new = $name;
    }

    return ($new);
}

function legacy_interface_destroy($ifs)
{
    $cmd = '/sbin/ifconfig ' . escapeshellarg($ifs) . ' destroy';

    exec($cmd . ' 2>&1', $out, $ret);
    if ($ret) {
        log_error('The command `' . $cmd . '\' failed to execute');
    }
}

function legacy_interface_setaddress($ifs, $addr, $family = 4)
{
    $cmd = implode(' ', ['/sbin/ifconfig', escapeshellarg($ifs), $family == 6 ? 'inet6' : 'inet', escapeshellarg($addr), 'alias']);

    exec($cmd . ' 2>&1', $out, $ret);
    if ($ret) {
        log_error('The command `' . $cmd . '\' failed to execute');
    }
}

function legacy_interface_deladdress($ifs, $addr, $family = 4)
{
    $cmd = implode(' ', ['/sbin/ifconfig', escapeshellarg($ifs), $family == 6 ? 'inet6' : 'inet', escapeshellarg($addr), '-alias']);

    exec($cmd . ' 2>&1', $out, $ret);
    if ($ret) {
        log_error('The command `' . $cmd . '\' failed to execute');
    }
}

function legacy_interface_mtu($ifs, $mtu)
{
    $cmd = '/sbin/ifconfig ' . escapeshellarg($ifs) . ' mtu ' . escapeshellarg($mtu);

    exec($cmd . ' 2>&1', $out, $ret);
    if ($ret) {
        log_error('The command `' . $cmd . '\' failed to execute');
    }
}

function legacy_bridge_member($ifs, $member)
{
    $cmd = '/sbin/ifconfig ' . escapeshellarg($ifs) . ' addm ' . escapeshellarg($member);

    exec($cmd . ' 2>&1', $out, $ret);
    if ($ret) {
        log_error('The command `' . $cmd . '\' failed to execute');
    }
}

function legacy_vlan_tag($ifs, $member, $tag, $pcp)
{
    $cmd = '/sbin/ifconfig ' . escapeshellarg($ifs) . ' vlandev ' . escapeshellarg($member) . ' vlan ' . escapeshellarg($tag) . ' vlanpcp ' . escapeshellarg($pcp);

    exec($cmd . ' 2>&1', $out, $ret);
    if ($ret) {
        log_error('The command `' . $cmd . '\' failed to execute');
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
    $stats = array();

    exec($cmd . ' 2>&1', $out, $ret);
    if ($ret) {
        log_error('The command `' . $cmd . '\' failed to execute');
        return $stats;
    }

    $current_interface = '';
    foreach ($out as $line) {
        if (strpos($line, 'Interface') === 0) {
            $current_interface = explode('(', explode(' ', $line)[1])[0];
            $stats[$current_interface] = array();
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
    $result = array();
    if (!empty($intf)) {
        $tmp_intf = escapeshellarg($intf);
    } else {
        $tmp_intf = '';
    }

    $cmd = '/sbin/ifconfig -m -v ' . $tmp_intf;
    exec($cmd . ' 2>&1', $ifconfig_data, $ret);
    if ($ret) {
        /* only error if no explicit interface was choosen */
        if (empty($intf)) {
            log_error('The command `' . $cmd . '\' failed to execute ' . implode(' ', $ifconfig_data));
        }
        return $result;
    }

    $current_interface = null;
    foreach ($ifconfig_data as $lineid => $line) {
        $line_parts = explode(' ', $line);
        if (strpos(trim($line), 'flags=') !== false && $line[0] != "\t") {
            $current_interface = explode(':', $line)[0];
            $result[$current_interface] = array();
            $result[$current_interface]["capabilities"] = array();
            $result[$current_interface]["options"] = array();
            $result[$current_interface]["macaddr"] = "00:00:00:00:00:00";
            $result[$current_interface]["ipv4"] = array();
            $result[$current_interface]["ipv6"] = array();
            if (preg_match("/ mtu ([0-9]*).*$/", $line, $matches)) {
                $result[$current_interface]["mtu"] = $matches[1];
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
        } elseif (strpos(trim($line), 'options=') !== false) {
            // parse options
            $options = substr($line, strpos($line, '<') + 1, -1);
            foreach (explode(',', $options) as $option) {
                $result[$current_interface]["options"][] = strtolower(trim($option));
            }
        } elseif (strpos($line, "\tether ") !== false) {
            // mac address
            $result[$current_interface]["macaddr"] = $line_parts[1];
        } elseif (strpos($line, "\tinet ") !== false) {
            // IPv4 information
            unset($mask);
            unset($vhid);
            for ($i = 0; $i < count($line_parts) - 1; ++$i) {
                if ($line_parts[$i] == 'netmask') {
                    $mask = substr_count(base_convert(hexdec($line_parts[$i + 1]), 10, 2), '1');
                } elseif ($line_parts[$i] == 'vhid') {
                    $vhid = $line_parts[$i + 1];
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
                'deprecated' => false,
                'ipaddr' => $addr,
                'link-local' => strpos($addr, 'fe80:') === 0,
                'tunnel' => false,
            ];
            for ($i = 0; $i < count($line_parts) - 1; ++$i) {
                if ($line_parts[$i] == 'prefixlen') {
                    $tmp['subnetbits'] = intval($line_parts[$i + 1]);
                } elseif ($line_parts[$i] == 'vhid') {
                    $tmp['vhid'] = $line_parts[$i + 1];
                } elseif ($line_parts[$i] == 'deprecated') {
                    $tmp['deprecated'] = true;
                }
                if ($line_parts[$i] == '-->') {
                    $tmp['tunnel'] = true;
                    $tmp['endpoint'] = $line_parts[$i + 1];
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
            $result[$current_interface]["tunnel"] = array();
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
                ",", strtolower(substr($next_line, strpos($next_line, '<') + 1, -1))
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
            $next_line = $ifconfig_data[$lineid + 1];
            $result[$current_interface]['laggstatistics']['flapping'] =  trim(explode(":", $next_line)[1]);
        } elseif (preg_match("/laggport: (.*)\Wflags=\d+<(.*)> state=\d+<(.*)>$/", $line, $matches)) {
            if (empty($result[$current_interface]['laggport'])) {
                $result[$current_interface]['laggport'] = array();
            }
            $result[$current_interface]['laggport'][trim($matches[1])] = [
                "flags" => explode(",", strtolower($matches[2])),
                "state" => explode(",", strtolower($matches[3])),
            ];
        } elseif (strpos($line, "\tgroups: ") !== false) {
            array_shift($line_parts);
            $result[$current_interface]['groups'] = $line_parts;
        } elseif (strpos($line, "\tcarp: ") !== false) {
            if (empty($result[$current_interface]["carp"])) {
                $result[$current_interface]["carp"] = array();
            }
            $result[$current_interface]["carp"][$line_parts[3]] = array(
                "status" => $line_parts[1],
                "vhid" => $line_parts[3],
                "advbase" => $line_parts[5],
                "advskew" => $line_parts[7]
            );
        } elseif (strpos($line, "\tvxlan") !== false) {
            if (empty($result[$current_interface]["vxlan"])) {
                $result[$current_interface]["vxlan"] = array();
            }
            $result[$current_interface]["vxlan"]["vni"] = $line_parts[2];
            $result[$current_interface]["vxlan"]["local"] = $line_parts[4];
            if ($line_parts[5] == "group") {
                $result[$current_interface]["vxlan"]["group"] = $line_parts[6];
            } else {
                $result[$current_interface]["vxlan"]["remote"] = $line_parts[6];
            }
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
    $result = array();

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
function configure_interface_hardware($ifs)
{
    global $config;
    $hwsettings = $config['system'];
    if (stristr($ifs, "_vlan")) {
        /* skip vlans for checksumming  */
        return;
    }
    $intf_details = legacy_interface_details($ifs);
    //  interface overwrites
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

        // disable/enable hardware vlan tags, will be skipped when "Leave default" (option 2) is selected
        if (!isset($hwsettings['disablevlanhwfilter']) || $hwsettings['disablevlanhwfilter'] == 1) {
            // probe already selected options
            $selected_opts = array();
            if (!empty($intf_details['options'])) {
                foreach ($intf_details['options'] as $opt) {
                    if ($opt == 'vlan_hwtagging') {
                        $selected_opts[] = 'vlanhwtag';
                    } else {
                        $selected_opts[] = str_replace('_', '', $opt);
                    }
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

function legacy_get_interface_addresses($ifs, $ifconfig_details = null)
{
    $intf_details = array();
    if (empty($ifconfig_details)) {
        $intf_details = legacy_interface_details($ifs);
    } elseif (!empty($ifconfig_details[$ifs])) {
        $intf_details = $ifconfig_details[$ifs];
    }

    $addrs = array("macaddr" => $intf_details['macaddr']);
    if (!empty($intf_details['ipv4'][0])) {
        $addrs['ipaddr'] = $intf_details['ipv4'][0]['ipaddr'];
        $addrs['subnetbits'] = $intf_details['ipv4'][0]['subnetbits'];
    }
    if (isset($intf_details['ipv6'])) {
        foreach ($intf_details['ipv6'] as $ipv6) {
            if (empty($addrs['ipaddr6']) && empty($ipv6['link-local'])) {
                $addrs['ipaddr6'] = $ipv6['ipaddr'];
                $addrs['subnetbits6'] = $ipv6['subnetbits'];
            } elseif (empty($addrs['ipaddr6_ll']) && !empty($ipv6['link-local'])) {
                $addrs['ipaddr6_ll'] = $ipv6['ipaddr'];
                $addrs['subnetbits6_ll'] = $ipv6['subnetbits'];
            }
        }
    }
    return $addrs;
}
