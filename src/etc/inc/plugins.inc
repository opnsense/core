<?php

/*
 * Copyright (C) 2016 Franco Fichtner <franco@opnsense.org>
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
 * scan plugins for legacy system
 * @return array
 */
function plugins_scan()
{
    $path = '/usr/local/etc/inc/plugins.inc.d/';
    $ext = '.inc';

    $ret = array();

    $plugins = glob($path . '*' . $ext);
    if (!is_array($plugins)) {
        return $ret;
    }

    sort($plugins);

    foreach ($plugins as $plugin) {
        $name = preg_replace('/' . preg_quote($path, '/') . '/', '', $plugin);
        $name = preg_replace('/' . preg_quote($ext, '/') . '/', '', $name);
        $ret[$name] = $plugin;
    }

    return $ret;
}

function plugins_services()
{
    $services = array();

    foreach (plugins_scan() as $name => $path) {
        require_once $path;
        $func = sprintf('%s_services', $name);
        if (function_exists($func)) {
            $workers = $func();
            foreach ($workers as $work) {
                $services[] = $work;
            }
        }
    }

    return $services;
}

function plugins_syslog()
{
    $syslogs = array();

    foreach (plugins_scan() as $name => $path) {
        require_once $path;
        $func = sprintf('%s_syslog', $name);
        if (function_exists($func)) {
            $workers = $func();
            foreach ($workers as $plugin_syslog => $plugin_details) {
                $syslogs[$plugin_syslog] = $plugin_details;
            }
        }
    }

    return $syslogs;
}

/**
 * Register new or changed interfaces into config's interfaces section.
 * Every <plugin>_interface should return a named array containing the interface unique identifier and properties.
 *
 */
function plugins_interfaces($write_allowed = true)
{
    global $config;

    $stale_interfaces = array();
    $write_required = false;

    // mark previous dynamic registrations stale
    if (isset($config['interfaces'])) {
        foreach ($config['interfaces'] as $intf_ref => $intf_data) {
            if (isset($intf_data[0]['internal_dynamic']) || isset($intf_data['internal_dynamic'])) {
                $stale_interfaces[$intf_ref] = 1;
            }
        }
    }

    // register / update interfaces
    foreach (plugins_scan() as $name => $path) {
        require_once $path;
        $func = sprintf('%s_interfaces', $name);
        if (function_exists($func)) {
            foreach ($func() as $intf_ref => $intf_data) {
                if (is_array($intf_data)) {
                    // mark interface used
                    if (isset($stale_interfaces[$intf_ref])) {
                        unset($stale_interfaces[$intf_ref]);
                    }
                    if (empty($config['interfaces'][$intf_ref])) {
                        $config['interfaces'][$intf_ref] = array();
                    }
                    if (isset($config['interfaces'][$intf_ref][0])) {
                        // undo stupid listags() turning our item into a new array, see src/etc/inc/xmlparse.inc
                        $intf_config = &$config['interfaces'][$intf_ref][0];
                    } else {
                        $intf_config = &$config['interfaces'][$intf_ref];
                    }
                    $intf_config['internal_dynamic'] = true;
                    // traverse and diff interface properties with known configuration
                    foreach ($intf_data as $prop_name => $prop_value) {
                        if ((empty($intf_config[$prop_name]) && !empty($prop_value)) || $intf_config[$prop_name] != $prop_value) {
                            $intf_config[$prop_name] = $prop_value;
                            $write_required = true;
                        }
                    }
                }
            }
        }
    }

    // cleanup registrations
    foreach ($stale_interfaces as $intf_ref => $no_data) {
        if (isset($config['interfaces'][$intf_ref])) {
            unset($config['interfaces'][$intf_ref]);
            $write_required = true;
        }
    }

    // configuration changed, materialize
    if ($write_allowed && $write_required) {
        write_config();
    }
}

function plugins_configure()
{
    foreach (plugins_scan() as $name => $path) {
        require_once $path;
        $func = sprintf('%s_configure', $name);
        if (function_exists($func)) {
            $workers = $func();
            foreach ($workers as $worker) {
                $worker();
            }
        }
    }
}
