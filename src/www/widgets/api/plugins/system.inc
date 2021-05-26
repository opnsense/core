<?php

/*
 * Copyright (C) 2016 Deciso B.V.
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

function system_api_cpu_stats()
{
    $cpustats = array();
    // take a short snapshot to calculate cpu usage
    $diff = array('user', 'nice', 'sys', 'intr', 'idle');
    $cpuTicks1 = array_combine($diff, array_slice(explode(" ", get_single_sysctl('kern.cp_time')),0, 5));
    usleep(100000);
    $cpuTicks2 = array_combine($diff, array_slice(explode(" ", get_single_sysctl('kern.cp_time')),0, 5));
    $totalStart = array_sum($cpuTicks1);
    $totalEnd = array_sum($cpuTicks2);
    if ($totalEnd <= $totalStart) {
        // if for some reason the measurement is invalid, assume nothing has changed (all 0)
        $totalEnd = $totalStart;
    }
    $totalDiff = $totalEnd - $totalStart;
    if ($totalDiff != 0) {
        $cpustats['used'] =  floor(100 * ($totalDiff - ($cpuTicks2['idle'] - $cpuTicks1['idle'])) / $totalDiff);
        $cpustats['user'] = floor(100 * (($cpuTicks2['user'] - $cpuTicks1['user'])) / $totalDiff);
        $cpustats['nice'] = floor(100 * (($cpuTicks2['nice'] - $cpuTicks1['nice'])) / $totalDiff);
        $cpustats['sys'] = floor(100 * (($cpuTicks2['sys'] - $cpuTicks1['sys'])) / $totalDiff);
        $cpustats['intr'] = floor(100 * (($cpuTicks2['intr'] - $cpuTicks1['intr'])) / $totalDiff);
        $cpustats['idle'] = floor(100 * (($cpuTicks2['idle'] - $cpuTicks1['idle'])) / $totalDiff);
    } else {
        $cpustats['used'] = "0";
        $cpustats['user'] = "0";
        $cpustats['nice'] = "0";
        $cpustats['sys'] = "0";
        $cpustats['intr'] = "0";
        $cpustats['idle'] = "0";
    }

    // cpu model and count
    $cpustats['model'] = get_single_sysctl("hw.model");
    $cpustats['cpus'] = get_single_sysctl('kern.smp.cpus');

    // cpu frequency
    $tmp = get_single_sysctl('dev.cpu.0.freq_levels');
    $cpustats['max.freq'] = !empty($tmp) ? explode("/", explode(" ", $tmp)[0])[0] : "-";
    $tmp = get_single_sysctl('dev.cpu.0.freq');
    $cpustats['cur.freq'] = !empty($tmp) ? $tmp : "-";
    $cpustats['freq_translate'] = sprintf(gettext("Current: %s MHz, Max: %s MHz"), $cpustats['cur.freq'], $cpustats['max.freq']);

    // system load
    exec("/usr/bin/uptime | /usr/bin/sed 's/^.*: //'", $load_average);
    $cpustats['load'] = explode(',', $load_average[0]);

    return $cpustats;
}

function system_api_config()
{
    global $config;
    $result = array();
    $result['last_change'] = isset($config['revision']['time']) ? intval($config['revision']['time']) : 0;
    $result['last_change_frmt'] = date("D M j G:i:s T Y", $result['last_change']);
    return $result;
}

function system_api_kernel()
{
    global $config;
    $result = array();

    $result['pf'] = array();
    $result['pf']['maxstates'] = !empty($config['system']['maximumstates']) ? $config['system']['maximumstates'] : default_state_size();
    exec('/sbin/pfctl -si |grep "current entries" 2>/dev/null', $states);
    $result['pf']['states'] = count($states) >  0 ? filter_var($states[0], FILTER_SANITIZE_NUMBER_INT) : 0;

    $result['mbuf'] = array();
    exec('/usr/bin/netstat -mb | /usr/bin/grep "mbuf clusters in use"', $mbufs);
    $result['mbuf']['total'] = count($mbufs) > 0 ? explode('/', $mbufs[0])[2] : 0;
    $result['mbuf']['max'] = count($mbufs) > 0 ? explode(' ', explode('/', $mbufs[0])[3])[0] : 0;

    $totalMem = get_single_sysctl("vm.stats.vm.v_page_count");
    $inactiveMem = get_single_sysctl("vm.stats.vm.v_inactive_count");
    $cachedMem = get_single_sysctl("vm.stats.vm.v_cache_count");
    $freeMem = get_single_sysctl("vm.stats.vm.v_free_count");
    $result['memory']['total'] = get_single_sysctl('hw.physmem');
    if ($totalMem != 0) {
        $result['memory']['used'] = round(((($totalMem - ($inactiveMem + $cachedMem + $freeMem))) / $totalMem)*$result['memory']['total'], 0);
    } else {
        $result['memory']['used'] = gettext('N/A');
    }

    return $result;
}

function system_api_disk()
{
    $result = array();
    $result['swap'] = array();
    exec("/usr/sbin/swapinfo -k", $swap_info);
    foreach ($swap_info as $line) {
        if (strpos($line,'/dev/') !== false) {
            $parts = preg_split('/\s+/', $line);
            $swapItem = array();
            $swapItem['device'] = $parts[0];
            $swapItem['total'] = $parts[1];
            $swapItem['used'] = $parts[2];
            $result['swap'][] = $swapItem;
        }
    }

    $result['devices'] = array();
    exec("/bin/df -Tht ufs,tmpfs,zfs,cd9660", $disk_info);
    foreach ($disk_info as $line) {
        if (strpos($line,'Mounted on') === false) {
            $parts = preg_split('/\s+/', $line);
            $diskItem = array();
            $diskItem['device'] = $parts[0];
            $diskItem['type'] = $parts[1];
            $diskItem['size'] = $parts[2];
            $diskItem['used'] = $parts[3];
            $diskItem['available'] = $parts[4];
            $diskItem['capacity'] = $parts[5];
            $diskItem['mountpoint'] = $parts[6];
            $result['devices'][] = $diskItem;
        }
    }

    return $result;
}

function system_api_versions()
{
    global $g;

    $result = array();

    $result[] = sprintf('%s %s-%s', $g['product_name'], $g['product_version'], $g['product_arch']);
    $result[] = php_uname('s') . ' ' . php_uname('r');
    $result[] = trim(shell_exec('/usr/local/bin/openssl version'));

    return $result;
}

function system_api_firmware()
{
    $ret = gettext('Click to check for updates.');

    $product = json_decode(configd_run('firmware product'), true);
    if (
        !empty($product['product_check']['upgrade_sets']) ||
        !empty($product['product_check']['downgrade_packages']) ||
        !empty($product['product_check']['new_packages']) ||
        !empty($product['product_check']['reinstall_packages']) ||
        !empty($product['product_check']['remove_packages']) ||
        !empty($product['product_check']['upgrade_packages'])
    ) {
        $ret = gettext('Click to view pending updates.');
    }

    return $ret;
}

/**
 * widget system data
 */
function system_api()
{
    $result = array();

    $result['versions'] = system_api_versions();
    $result['cpu'] = system_api_cpu_stats();
    $result['date_frmt'] = date("D M j G:i:s T Y");
    $result['date_time'] = time();
    preg_match("/sec = (\d+)/", get_single_sysctl("kern.boottime"), $matches);
    $result['uptime'] = $result['date_time'] - $matches[1];
    $result['config'] = system_api_config();
    $result['kernel'] = system_api_kernel();
    $result['disk'] = system_api_disk();
    $result['firmware'] = system_api_firmware();

    return $result;
}
