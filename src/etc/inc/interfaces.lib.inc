<?php

/*
    Copyright (c) 2015-2016 Franco Fichtner <franco@opnsense.org>
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

function legacy_interface_listget($flag = '')
{
    $cmd = '/sbin/ifconfig -l';
    $ifs = null;

    if ($flag === 'up') {
        $cmd .= 'u';
    } else if ($flag === 'down') {
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

    return ($ifs);
}

function legacy_interface_flags($ifs, $flag, $report_errors=true)
{
    /* $flags isn't escaped because it can be an argument list */
    $cmd = '/sbin/ifconfig ' . escapeshellarg($ifs) . ' ' . $flag;

    exec($cmd . ' 2>&1', $out, $ret);
    if (!empty($ret) && $report_errors) {
        log_error('The command `' . $cmd . '\' failed to execute');
    }
}

function legacy_interface_create($ifs)
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

function legacy_interface_setaddress($ifs, $addr)
{
    $cmd = '/sbin/ifconfig ' . escapeshellarg($ifs) . ' alias ' . escapeshellarg($addr);

    exec($cmd . ' 2>&1', $out, $ret);
    if ($ret) {
        log_error('The command `' . $cmd . '\' failed to execute');
    }
}

function legacy_interface_deladdress($ifs, $addr)
{
    $cmd = '/sbin/ifconfig ' . escapeshellarg($ifs) . ' -alias ' . escapeshellarg($addr);

    exec($cmd . ' 2>&1', $out, $ret);
    if ($ret) {
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

function legacy_vlan_tag($ifs, $member, $tag)
{
    $cmd = '/sbin/ifconfig ' . escapeshellarg($ifs) . ' vlandev ' . escapeshellarg($member) . ' vlan ' . escapeshellarg($tag);

    exec($cmd . ' 2>&1', $out, $ret);
    if ($ret) {
        log_error('The command `' . $cmd . '\' failed to execute');
    }
}

function legacy_interface_stats($ifs=null)
{
    if ($ifs != null) {
        // only request data for selected interface
        $cmd = '/usr/local/sbin/ifinfo '. escapeshellarg($ifs);
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

    $current_interface = "";
    foreach ($out as $line) {
        if (strpos($line, 'Interface') === 0) {
            $current_interface = explode('(', explode(' ', $line)[1])[0];
            $stats[$current_interface] = array();
        } elseif ($current_interface != "") {
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
 * @param $intf interface name
 * @return array list of interface capabilities (in lowercase)
 */
function legacy_interface_details($intf)
{
    $result = array();
    $result["capabilities"] = array();
    $result["options"] = array();
    $process = proc_open('/sbin/ifconfig -m ' . escapeshellarg($intf), array(array("pipe", "r"), array("pipe", "w")), $pipes);
    if (is_resource($process)) {
        $ifconfig_data = stream_get_contents($pipes[1]);
        foreach (explode("\n", $ifconfig_data) as $line) {
            if (strpos(trim($line), 'capabilities=') !== false) {
                // parse capabilities
                $capabilities = substr($line, strpos($line, '<') + 1, -1);
                foreach (explode(',', $capabilities) as $capability) {
                    $result["capabilities"][] = strtolower(trim($capability));
                }
            } elseif (strpos(trim($line), 'options=') !== false) {
                // parse options
                $capabilities = substr($line, strpos($line, '<') + 1, -1);
                foreach (explode(',', $capabilities) as $capability) {
                    $result["options"][] = strtolower(trim($capability));
                }
            }

        }
        fclose($pipes[1]);
        proc_close($process);
    }
    return $result;
}

function legacy_netgraph_attach($ifs)
{
    mwexecf('/usr/local/sbin/ngattach %s', array($ifs));
}

function legacy_netgraph_detach($ifs)
{
    mwexecf('/usr/sbin/ngctl msg %s: detach', array($ifs), true);
}

function legacy_netgraph_rename($tmpifs, $ifs)
{
    mwexecf('/usr/sbin/ngctl name %s: %s', array($tmpifs, $ifs));
}

/**
 * configure interface hardware settings
 * @param string $ifs interface name
 */
function configure_interface_hardware($ifs)
{
    global $config;
    $intf_details = legacy_interface_details($ifs);
    /* skip vlans for checksumming  */
    if (!stristr($ifs, "_vlan") && is_array($intf_details)) {
        // get current settings
        $csum_set = in_array('rxcsum', $intf_details['options']) || in_array('txcsum', $intf_details['options']);
        $tso_set = in_array('tso4', $intf_details['options']) || in_array('tso6', $intf_details['options']);
        $lro_set = in_array('lro', $intf_details['options']);

        // hardware checksum offloading offloading
        if (isset($config['system']['disablechecksumoffloading']) && $csum_set) {
            legacy_interface_flags($ifs, '-txcsum -rxcsum', false);
        } elseif (!isset($config['system']['disablechecksumoffloading']) && !$csum_set) {
            legacy_interface_flags($ifs, 'txcsum rxcsum', false);
        }
        // TCP segmentation offloading
        if (isset($config['system']['disablesegmentationoffloading']) && $tso_set) {
            legacy_interface_flags($ifs, '-tso', false);
        } elseif (!isset($config['system']['disablesegmentationoffloading']) && !$tso_set) {
            legacy_interface_flags($ifs, 'tso', false);
        }

        // large receive offload
        if (isset($config['system']['disablelargereceiveoffloading']) && $lro_set) {
            legacy_interface_flags($ifs, '-lro', false);
        } elseif (!isset($config['system']['disablelargereceiveoffloading']) && !$lro_set) {
            legacy_interface_flags($ifs, 'lro', false);
        }
    }
}

function _legacy_getall_interface_addresses($ifs)
{
    $cmd = '/sbin/ifconfig ' . escapeshellarg($ifs);
    $addrs = array(
        'macaddr' => '00:00:00:00:00:00',
        'compat' => array(),
    );

    exec($cmd . ' 2>&1', $out, $ret);
    if ($ret) {
        log_error('The command `' . $cmd . '\' failed to execute');
        return $addrs;
    }

    if (count($out)) {
        foreach ($out as $line) {
            if ($line[0] != "\t") {
                continue;
            }
            $stat = explode(' ', $line);
            if (isset($stat[0])) {
                switch (trim($stat[0])) {
                    case 'inet':
                        $mask = substr_count(base_convert(hexdec($stat[3]), 10, 2), '1');
                        $addrs['compat'][] = $stat[1] . '/' . $mask;
                        if (!isset($addrs['ipaddr'])) {
                            $addrs['ipaddr'] = $stat[1];
                            $addrs['subnetbits'] = $mask;
                        }
                        break;
                    case 'inet6':
                        $addr = strtok($stat[1], '%');
                        /* throw error on ifconfig output scan fail: */
                        unset($mask);
                        for ($i = 0; $i < count($stat) - 1; ++$i) {
                            if ($stat[$i] == 'prefixlen') {
                                $mask = intval($stat[$i + 1]);
                            }
                        }
                        if (!isset($addrs['ipaddr6']) && strpos($addr, 'fe80:') !== 0) {
                            /* does not pick up link-local */
                            $addrs['subnetbits6'] = $mask;
                            $addrs['ipaddr6'] = $addr;
                        }
                        $addrs['compat'][] = $addr . '/' . $mask;
                        break;
                    case 'ether':
                        $addrs['macaddr'] = $stat[1];
                        break;
                    default:
                        /* not relevant to our interest */
                        break;
                }
            }
        }
    }

    return $addrs;
}

function legacy_getall_interface_addresses($ifs)
{
    $addrs = _legacy_getall_interface_addresses($ifs);
    return $addrs['compat'];
}

function legacy_get_interface_addresses($ifs)
{
    $addrs = _legacy_getall_interface_addresses($ifs);
    unset($addrs['compat']);
    return $addrs;
}

function legacy_serial_devices()
{
    // collect 3g/4g modems
    $dmesg = array();
    exec('/sbin/sysctl -a', $dmesg);
    $modems = array();
    foreach ($dmesg as $line) {
        if (strpos($line, 'dev.u3g.') === 0) {
            $portnum = explode('.', $line)[2];
            if (is_numeric($portnum)) {
                if (!isset($modems[$portnum])) {
                    $modems[$portnum] = array();
                }
                if (strpos($line, '%desc:') !== false) {
                    $modems[$portnum]['descr'] = explode('%desc:', $line)[1];
                } elseif (strpos($line, '%pnpinfo:') !== false) {
                    foreach (explode(' ', explode('%pnpinfo:', $line)[1]) as $prop) {
                        $tmp = explode('=', $prop);
                        if (count($tmp) == 2) {
                            $modems[$portnum][$tmp[0]] = $tmp[1];
                        }
                    }
                }
            }
        }
    }
    $serialports = array();
    foreach (glob("/dev/cua?[0-9]{,.[0-9]}", GLOB_BRACE) as $device) {
        $serialports[$device] = array('descr' => "");
        $tty = explode('.', explode('cua', $device)[1])[0];
        foreach ($modems as $modem) {
            if (isset($modem['ttyname']) && $modem['ttyname'] == $tty) {
                $serialports[$device] = $modem;
            }
        }
    }
    return $serialports;
}
