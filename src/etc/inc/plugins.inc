<?php

/*
 * Copyright (C) 2016-2019 Franco Fichtner <franco@opnsense.org>
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
    $clash = '/usr/local/etc/inc/';
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
        if (file_exists($clash . $name . '.inc') || file_exists($clash . $name . '.class')) {
            /*
             * Congratulations, you found the reason why your plugin doesn't
             * work!  It seems that you're using a name that is already taken
             * by the base system.  Please change the name of your plugin.
             *
             * A traceable call stack requires unique prefixes, which is what
             * will prevent this from working.  Do not remove this check
             * without discussing the consequences with the authors.
             */
            continue;
        }
        $ret[$name] = $plugin;
    }

    return $ret;
}

function plugins_services()
{
    $services = array();

    foreach (plugins_scan() as $name => $path) {
        try {
            include_once $path;
        } catch (ParseError $e) {
            error_log($e);
        }
        $func = sprintf('%s_services', $name);
        if (function_exists($func)) {
            foreach ($func() as $work) {
                $services[] = $work;
            }
        }
    }

    uasort($services, function ($a, $b) {
        return strcasecmp($a['name'], $b['name']);
    });

    return $services;
}

function plugins_devices()
{
    $devices = array();

    foreach (plugins_scan() as $name => $path) {
        try {
            include_once $path;
        } catch (ParseError $e) {
            error_log($e);
        }
        $func = sprintf('%s_devices', $name);
        if (function_exists($func)) {
            foreach ($func() as $work) {
                $devices[] = $work;
            }
        }
    }

    return $devices;
}

function plugins_cron()
{
    $jobs = array();

    foreach (plugins_scan() as $name => $path) {
        try {
            include_once $path;
        } catch (ParseError $e) {
            error_log($e);
        }
        $func = sprintf('%s_cron', $name);
        if (function_exists($func)) {
            foreach ($func() as $work) {
                $jobs[] = $work;
            }
        }
    }

    return $jobs;
}

function plugins_syslog()
{
    $syslogs = array();

    foreach (plugins_scan() as $name => $path) {
        try {
            include_once $path;
        } catch (ParseError $e) {
            error_log($e);
        }
        $func = sprintf('%s_syslog', $name);
        if (function_exists($func)) {
            foreach ($func() as $plugin_syslog => $plugin_details) {
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
function plugins_interfaces()
{
    global $config;

    $stale_interfaces = array();

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
        try {
            include_once $path;
        } catch (ParseError $e) {
            error_log($e);
        }
        $func = sprintf('%s_interfaces', $name);
        if (function_exists($func)) {
            foreach ($func() as $intf_ref => $intf_data) {
                if (is_array($intf_data)) {
                    // mark interface used
                    if (isset($stale_interfaces[$intf_ref])) {
                        unset($stale_interfaces[$intf_ref]);
                    }
                    if (isset($config['interfaces'][$intf_ref][0])) {
                        // undo stupid listags() turning our item into a new array, see src/etc/inc/xmlparse.inc
                        $intf_config = &config_read_array('interfaces', $intf_ref, 0);
                    } else {
                        $intf_config = &config_read_array('interfaces', $intf_ref);
                    }
                    $intf_config['internal_dynamic'] = true;
                    // traverse and diff interface properties with known configuration
                    foreach ($intf_data as $prop_name => $prop_value) {
                        if ((empty($intf_config[$prop_name]) && !empty($prop_value)) || $intf_config[$prop_name] != $prop_value) {
                            $intf_config[$prop_name] = $prop_value;
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
        }
    }
}

function plugins_firewall($fw)
{
    foreach (plugins_scan() as $name => $path) {
        try {
            include_once $path;
        } catch (ParseError $e) {
            error_log($e);
        }
        $func = sprintf('%s_firewall', $name);
        if (function_exists($func)) {
            $func($fw);
        }
    }

    return $fw;
}

function plugins_configure($hook, $verbose = false, $args = array())
{
    array_unshift($args, $verbose);

    syslog(LOG_NOTICE, sprintf('plugins_configure %s (%s)', $hook, implode(',', $args)));

    foreach (plugins_scan() as $name => $path) {
        try {
            include_once $path;
        } catch (ParseError $e) {
            error_log($e);
        }
        $func = sprintf('%s_configure', $name);
        if (function_exists($func)) {
            foreach ($func() as $when => $worker) {
                if ($hook == $when && is_array($worker)) {
                    foreach ($worker as $task) {
                        /*
                         * An optional argument count parameter can be
                         * given by the plugin, which allows to securely
                         * pull more info from the configure call spot.
                         */
                        list($argf, $argc) = explode(':', $task);
                        if (empty($argc) || !is_numeric($argc)) {
                            $argc = 1;
                        }
                        if ($argc > count($args)) {
                            $argc = count($args);
                        }
                        syslog(LOG_NOTICE, sprintf(
                            'plugins_configure %s (execute task : %s(%s))',
                            $hook,
                            $argf,
                            implode(',', array_slice($args, 0, $argc))
                        ));
                        call_user_func_array($argf, array_slice($args, 0, $argc));
                    }
                }
            }
        }
    }
}

function plugins_run($hook, $verbose = false, $args = array())
{
    array_unshift($args, $verbose);
    $ret = [];

    syslog(LOG_DEBUG, sprintf('plugins_run %s (%s)', $hook, implode(',', $args)));

    foreach (plugins_scan() as $name => $path) {
        try {
            include_once $path;
        } catch (ParseError $e) {
            error_log($e);
        }
        $func = sprintf('%s_run', $name);
        if (function_exists($func)) {
            foreach ($func() as $when => $task) {
                if ($hook == $when) {
                    /*
                     * An optional argument count parameter can be
                     * given by the plugin, which allows to securely
                     * pull more info from the configure call spot.
                     */
                    list($argf, $argc) = explode(':', $task);
                    if (empty($argc) || !is_numeric($argc)) {
                        $argc = 1;
                    }
                    if ($argc > count($args)) {
                        $argc = count($args);
                    }
                    syslog(LOG_DEBUG, sprintf(
                        'plugins_run %s (execute task : %s(%s))',
                        $hook,
                        $argf,
                        implode(',', array_slice($args, 0, $argc))
                    ));
                    $ret[$name] = call_user_func_array($argf, array_slice($args, 0, $argc));
                }
            }
        }
    }

    return $ret;
}

function plugins_xmlrpc_sync()
{
    $sync_settings = array();
    foreach (plugins_scan() as $name => $path) {
        try {
            include_once $path;
        } catch (ParseError $e) {
            error_log($e);
        }
        $func = sprintf('%s_xmlrpc_sync', $name);
        if (function_exists($func)) {
            foreach ($func() as $helper) {
                if (!empty($helper['id']) && !empty($helper['section'])) {
                    $sync_settings[$helper['id']] = $helper;
                    if (empty($helper['help'])) {
                        $sync_settings[$helper['id']]['help'] = sprintf(gettext('Synchronize the %s configuration to the other HA host.'), $helper['description']);
                    }
                    if (empty($helper['services'])) {
                        $sync_settings[$helper['id']]['services'] = [];
                    } elseif (!is_array($helper['services'])) {
                        $sync_settings[$helper['id']]['services'] = [$helper['services']];
                    }
                    unset($sync_settings[$helper['id']]['id']);
                }
            }
        }
    }
    return $sync_settings;
}
