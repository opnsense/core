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
            $realif = get_real_interface($vip['interface']);
            if (!does_interface_exist($realif)) {
                return false;
            }
            break;
        case "proxyarp":
            /* XXX: Implement this */
        default:
            return false;
    }

    foreach (array_keys(interfaces_addresses($realif, true)) as $vipips) {
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
            // do not mutate $path for siblings
            $currentPath = empty($path) ? $cnf_key : sprintf('%s.%s', $path, $cnf_key);
            $no_sync_items = [];
            if (
                !isset($cnf_dest[$cnf_key]) || !is_array($cnf_dest[$cnf_key]) || // new
                (count($cnf_dest[$cnf_key]) > 0 && isset($cnf_dest[$cnf_key][0])) || // sequenced item
                (count($cnf_dest[$cnf_key]) > 0 && isset($cnf_dest[$cnf_key]['@attributes']['uuid'])) // mvc array
            ) {
                /* gather items with nosync set, exclude records with implicit order (legacy) */
                $exclude_nosync = in_array($currentPath, ['filter.rule', 'nat.rule', 'nat.outbound.rule']);
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
            merge_config_attributes($cnf_value, $cnf_dest[$cnf_key], $currentPath);
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

    // Filter incoming VIPs: remove nosync and invalid entries in one pass
    if (isset($new_config['virtualip']['vip'])) {
        $new_config['virtualip']['vip'] = filter_valid_vips(
            $new_config['virtualip']['vip'],
            $strict = true,
            $filter_nosync = true
        );
        
        // Build map for quick lookups
        $vhidVipsInNewConfig = build_vip_map($new_config['virtualip']['vip']);
    } else {
        $vhidVipsInNewConfig = [];
    }

    $vipbackup = [];
    $oldvips = [];
    
    if (isset($new_config['virtualip']['vip']) && isset($config['virtualip']['vip'])) {
        // Use non-strict validation for local VIPs to preserve legacy configs
        // Only check that mode and interface exist, not mode-specific fields
        $config['virtualip']['vip'] = filter_valid_vips($config['virtualip']['vip'], $strict = false);
        
        foreach ($config['virtualip']['vip'] as $vipindex => $vip) {
            $vipKey = get_unique_vip_key($vip);
            
            if (!empty($vip['vhid']) && empty($vip['nosync'])) {
                // rc.filter_synchronize only sends CARP VIPs and IP Aliases with a VHID
                $oldvips[$vipKey] = $vip;
                // Remove entries that are present locally, but not in new config
                if (!array_key_exists($vipKey, $vhidVipsInNewConfig)) {
                    unset($config['virtualip']['vip'][$vipindex]);
                }
            } elseif (!isset($vhidVipsInNewConfig[$vipKey])) {
                // Keep local-only VIPs (including local nosync)
                $vipbackup[$vipKey] = $vip;
            }
        }
    }

    // merge config attributes
    merge_config_attributes($new_config, $config);

    if (!empty($vipbackup)) {
        // Prepend original local VIPs (flatten correctly)
        $config['virtualip']['vip'] = array_merge(
            array_values($vipbackup), 
            $config['virtualip']['vip'] ?? []
        );
    }

    // Deduplicate VIPs by uuid or unique key
    if (isset($config['virtualip']['vip']) && is_array($config['virtualip']['vip'])) {
        $vip_map = [];
        $duplicate_count = 0;
        
        foreach ($config['virtualip']['vip'] as $vip) {
            $k = !empty($vip['uuid']) ? 'uuid:' . $vip['uuid'] : 'key:' . get_unique_vip_key($vip);
            
            if (!isset($vip_map[$k])) {
                $vip_map[$k] = $vip;
            } else {
                $duplicate_count++;
                syslog(LOG_INFO, "restore_config_section_xmlrpc: Duplicate VIP {$k} - keeping first (local), dropping duplicate");
            }
        }
        
        if ($duplicate_count > 0) {
            syslog(LOG_NOTICE, "restore_config_section_xmlrpc: Removed {$duplicate_count} duplicate VIP(s) during HA sync");
        }
        
        $config['virtualip']['vip'] = array_values($vip_map);
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
                // VIPs are already validated, safe to use directly
                $vipKey = get_unique_vip_key($vip);
                
                if (!empty($vip['vhid']) && isset($oldvips[$vipKey])) {
                    $is_changed = false;
                    // Check all critical fields for changes
                    $check_fields = ['password', 'advskew', 'subnet', 'subnet_bits', 'advbase', 'vhid'];
                    foreach ($check_fields as $chk_key) {
                        $old_val = $oldvips[$vipKey][$chk_key] ?? null;
                        $new_val = $vip[$chk_key] ?? null;
                        if ($old_val != $new_val) {
                            $is_changed = true;
                            syslog(LOG_INFO, "restore_config_section_xmlrpc: VIP {$vipKey} field '{$chk_key}' changed from '{$old_val}' to '{$new_val}'");
                            break;
                        }
                    }
                    if (!$is_changed) {
                        unset($oldvips[$vipKey]);
                        if (does_vip_exist($vip)) {
                            continue; // Skip reconfiguring this VIP since nothing has changed
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

/**
 * Validate that a VIP has the minimum required fields
 * @param array $vip VIP configuration array
 * @param bool $strict If true, performs mode-specific validation
 * @return bool true if valid, false otherwise
 */
function validate_vip_structure($vip, $strict = true)
{
    if (!is_array($vip)) {
        return false;
    }
    
    // Required fields for all VIPs
    if (!isset($vip['mode']) || $vip['mode'] === '' || 
        !isset($vip['interface']) || $vip['interface'] === '') {
        return false;
    }
    
    // Mode-specific validation (optional)
    if ($strict) {
        switch ($vip['mode']) {
            case 'carp':
                if (!isset($vip['vhid']) || !isset($vip['password'])) {
                    return false;
                }
                // Fall through to check subnet
            case 'ipalias':
            case 'proxyarp':
                if (!isset($vip['subnet']) || !isset($vip['subnet_bits'])) {
                    return false;
                }
                break;
        }
    }
    
    return true;
}

function get_unique_vip_key($vip)
{
    $mode = $vip['mode'] ?? 'unknown';
    $interface = $vip['interface'] ?? 'unknown';
    $vhid = $vip['vhid'] ?? '';
    
    if ($mode === 'carp') {
        $key = "{$mode}_{$interface}_vip{$vhid}";
    } else {
        $subnet = $vip['subnet'] ?? '';
        $subnet_bits = $vip['subnet_bits'] ?? '';
        $key = "{$mode}_{$interface}_vip{$vhid}_{$subnet}_{$subnet_bits}";
    }
    
    // If key is too generic (missing critical fields), add UUID or hash for uniqueness
    if ($key === "{$mode}_{$interface}_vip__" || $key === "{$mode}_{$interface}_vip") {
        if (!empty($vip['uuid'])) {
            $key .= '_' . $vip['uuid'];
        } else {
            // Fallback: hash the entire VIP for uniqueness
            $key .= '_' . substr(md5(json_encode($vip)), 0, 8);
        }
    }
    
    return $key;
}

/**
 * Filter and validate VIP array, removing invalid entries
 * @param array $vips Array of VIP configurations
 * @param bool $strict Perform strict validation
 * @param bool $filter_nosync Remove nosync VIPs
 * @return array Validated and filtered VIPs
 */
function filter_valid_vips($vips, $strict = true, $filter_nosync = false)
{
    if (!is_array($vips)) {
        return [];
    }
    
    return array_values(array_filter($vips, function ($vip) use ($strict, $filter_nosync) {
        if (!validate_vip_structure($vip, $strict)) {
            syslog(LOG_WARNING, 'filter_valid_vips: Filtering out invalid VIP: ' . json_encode($vip));
            return false;
        }
        
        if ($filter_nosync && !empty($vip['nosync'])) {
            return false;
        }
        
        return true;
    }));
}

/**
 * Build a map of VIPs indexed by their unique key
 * @param array $vips Array of VIP configurations
 * @return array Associative array with unique keys
 */
function build_vip_map($vips)
{
    $map = [];
    foreach ($vips as $vip) {
        // Assumes VIPs are already validated
        $map[get_unique_vip_key($vip)] = $vip;
    }
    return $map;
}