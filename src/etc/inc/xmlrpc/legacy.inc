<?php

/*
 * Copyright (C) 2015-2025 Deciso B.V.
 * Copyright (C) 2004-2010 Scott Ullrich <sullrich@gmail.com>
 * Copyright (C) 2005 Colin Smith <ethethlay@gmail.com>
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

/**
 * request functions which may be registered by the xmlrpc system
 * @return array
 */
function xmlrpc_publishable_legacy()
{
    $publish = [
       'filter_configure_xmlrpc',
       'firmware_version_xmlrpc',
       'restore_config_section_xmlrpc',
    ];

    return $publish;
}

/*
 * does_vip_exist($vip): return true or false if a vip is
 * configured.
 */
function does_vip_exist($vip)
{
    if (!$vip) {
        return false;
    }

    switch ($vip['mode']) {
        case "carp":
        case "ipalias":
            /* XXX: Make proper checks? */
            $device = get_real_interface($vip['interface']);
            if (!does_interface_exist($device)) {
                return false;
            }
            break;
        case "proxyarp":
            /* XXX: Implement this */
        default:
            return false;
    }

    foreach (array_keys(interfaces_addresses($device, true)) as $vipips) {
        if ($vipips == "{$vip['subnet']}/{$vip['subnet_bits']}") {
            return true;
        }
    }

    return false;
}

/**
 * merge attributes from source array to destination
 * updates leaves and overwrites sequenced arrays (container types).
 * @param array $cnf_source source data
 * @param array $cnf_dest target
 */
function merge_config_attributes(&$cnf_source, &$cnf_dest, $path = '')
{
    foreach ($cnf_source as $cnf_key => &$cnf_value) {
        if (is_array($cnf_value)) {
            $path = empty($path) ? $cnf_key : sprintf('%s.%s', $path, $cnf_key);
            $no_sync_items = [];
            if (
                !isset($cnf_dest[$cnf_key]) || !is_array($cnf_dest[$cnf_key]) || // new
                (count($cnf_dest[$cnf_key]) > 0 && isset($cnf_dest[$cnf_key][0])) || // sequenced item
                (count($cnf_dest[$cnf_key]) > 0 && isset($cnf_dest[$cnf_key]['@attributes']['uuid'])) // mvc array
            ) {
                /* gather items with nosync set, exclude records with implicit order (legacy) */
                $exclude_nosync = in_array($path, ['filter.rule', 'nat.rule', 'nat.outbound.rule']);
                if (!$exclude_nosync && isset($cnf_dest[$cnf_key]) && is_array($cnf_dest[$cnf_key])) {
                    foreach ($cnf_dest[$cnf_key] as $record) {
                        if (!empty($record['nosync'])) {
                            $no_sync_items[] = $record;
                        }
                    }
                }
                // (re)set destination array when new or containing a sequenced list of items
                $cnf_dest[$cnf_key] = [];
            }
            merge_config_attributes($cnf_value, $cnf_dest[$cnf_key], $path);
            if (!empty($no_sync_items)) {
                /* append nosync items, local ones on-top */
                $cnf_dest[$cnf_key] = array_merge($no_sync_items, $cnf_dest[$cnf_key]);
            }
        } else {
            $cnf_dest[$cnf_key] = $cnf_value;
        }
    }
}

/**
 * retrieve firmware version
 * @return array
 */
function firmware_version_xmlrpc()
{
    return [
        'base' => ['version' => shell_safe('opnsense-version -v base')],
        'firmware' => ['version' => shell_safe('opnsense-version -v core')],
        'kernel' => ['version' => shell_safe('opnsense-version -v kernel')],
    ];
}

/**
 * filter reconfigure
 * @return mixed
 */
function filter_configure_xmlrpc()
{
    require_once("filter.inc");
    require_once("system.inc");
    require_once("util.inc");
    require_once("interfaces.inc");

    system_routing_configure();
    system_resolver_configure();
    filter_configure();

    return true;
}

/**
 * restore config section
 * @param $new_config
 * @return bool
 */
function restore_config_section_xmlrpc($new_config)
{
    global $config;
    // lock config write during merge
    OPNsense\Core\Config::getInstance()->lock();

    require_once("interfaces.inc");
    require_once("filter.inc");

    // save old config
    $old_config = $config;

    $vhidVipsInNewConfig = [];
    if (isset($new_config['virtualip']['vip'])) {
        foreach ($new_config['virtualip']['vip'] as $vip) {
            $vhidVipsInNewConfig[get_unique_vip_key($vip)] = $vip;
        }
    }

    $vipbackup = [];
    $oldvips = [];
    if (isset($new_config['virtualip']['vip']) && isset($config['virtualip']['vip'])) {
        foreach ($config['virtualip']['vip'] as $vipindex => $vip) {
            $vipKey = get_unique_vip_key($vip);
            if (!empty($vip['vhid']) && empty($vip['nosync'])) {
                // rc.filter_synchronize only sends CARP VIPs and IP Aliases with a VHID. Keep the rest like it was.
                $oldvips[$vipKey] = $vip;
                // Remove entries that are present locally, but are not present in the new config.
                if (!array_key_exists($vipKey, $vhidVipsInNewConfig)) {
                    unset($config['virtualip']['vip'][$vipindex]);
                }
            } elseif (!empty($vip['vhid']) && !empty($vip['nosync'])) {
                null; /* merge_config_attributes() will preserve these records */
            } elseif (!isset($vhidVipsInNewConfig[$vipKey])) {
                $vipbackup[$vipKey] = $vip;
            }
        }
    }

    // merge config attributes.
    merge_config_attributes($new_config, $config);

    if (count($vipbackup) > 0) {
        // if $new_config contained VIPs and the old config contained VIPs with no VHID, prepend original VIPs
        foreach ($vipbackup as $vip) {
            array_unshift($config['virtualip']['vip'], $vip);
        }
    }

    /* Log what happened */
    $mergedkeys = implode(",", array_merge(array_keys($new_config)));
    write_config(sprintf('Merged %s config sections from XMLRPC client.', $mergedkeys));

    /*
     * Handle virtual ips
     */
    if (isset($new_config['virtualip']['vip'])) {
        $proxyarp = false;
        $pfsync = false;

        if (isset($config['virtualip']['vip'])) {
            foreach ($config['virtualip']['vip'] as $vip) {
                $vipKey = get_unique_vip_key($vip);
                if (!empty($vip['vhid']) && isset($oldvips[$vipKey])) {
                    $is_changed = false;
                    foreach (['password', 'advskew', 'subnet', 'subnet_bits', 'advbase'] as $chk_key) {
                        if ($oldvips[$vipKey][$chk_key] != $vip[$chk_key]) {
                            $is_changed = true;
                            break;
                        }
                    }
                    if (!$is_changed) {
                        unset($oldvips[$vipKey]);
                        if (does_vip_exist($vip)) {
                            continue; // Skip reconfiguring this VIP since nothing has changed.
                        }
                    }
                }

                switch ($vip['mode']) {
                    case "proxyarp":
                        $proxyarp = true;
                        break;
                    case "ipalias":
                        interface_ipalias_configure($vip);
                        break;
                    case "carp":
                        interface_carp_configure($vip);
                        $pfsync = true;
                        break;
                }
            }
        }

        // remove old (carp/ipalias-with-vhid) virtual ip's
        foreach ($oldvips as $oldvip) {
            interface_vip_bring_down($oldvip);
        }

        if ($pfsync) {
            interfaces_pfsync_configure();
        }

        if ($proxyarp) {
            interface_proxyarp_configure();
        }
    }

    if (isset($old_config['ipsec']['enable']) !== isset($config['ipsec']['enable'])) {
        plugins_configure('ipsec');
    }

    unset($old_config);

    return true;
}

function get_unique_vip_key($vip)
{
    if ($vip['mode'] === 'carp') {
        return "{$vip['mode']}_{$vip['interface']}_vip{$vip['vhid']}";
    } else {
        return "{$vip['mode']}_{$vip['interface']}_vip{$vip['vhid']}_{$vip['subnet']}_{$vip['subnet_bits']}";
    }
}
