<?php

/*
 * Copyright (C) 2016-2024 Franco Fichtner <franco@opnsense.org>
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
function plugins_scan($glob = '*')
{
    $path = '/usr/local/etc/inc/plugins.inc.d/';
    $clash = '/usr/local/etc/inc/';
    $ext = '.inc';

    $ret = [];

    $plugins = glob($path . preg_replace('/[.\/]/', '', $glob) . $ext);
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
        } catch (\Error $e) {
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
    $devices = [];
    $defaults = [
        'configurable' => true, /* allow to configure IP connectivity */
        'pattern' => '^impossiblecontrolmatch$', /* never match by default */
        'spoofmac' => true, /* allow to change the MAC address */
        'volatile' => false, /* device known to persist */
    ];

    foreach (plugins_scan() as $name => $path) {
        try {
            include_once $path;
        } catch (\Error $e) {
            error_log($e);
        }
        $func = sprintf('%s_devices', $name);
        if (function_exists($func)) {
            foreach ($func() as $work) {
                foreach ($defaults as $key => $val) {
                    if (!isset($work[$key])) {
                        $work[$key] = $val;
                    }
                }
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
        } catch (\Error $e) {
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

/**
 * list of known applications for syslog-ng to use
 * Only to be used to register syslog-ng custom destinations (facility is program in syslog-ng's context)
 */
function plugins_syslog()
{
    $syslogs = array();

    foreach (plugins_scan() as $name => $path) {
        try {
            include_once $path;
        } catch (\Error $e) {
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

    $stale_interfaces = [];
    $changed = false;

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
        } catch (\Error $e) {
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
                    if (!isset($intf_config['internal_dynamic'])) {
                        $intf_config['internal_dynamic'] = true;
                        $changed = true;
                    }
                    // traverse and diff interface properties with known configuration
                    foreach ($intf_data as $prop_name => $prop_value) {
                        if ((empty($intf_config[$prop_name]) && !empty($prop_value)) || ($intf_config[$prop_name] ?? '') != $prop_value) {
                            if (is_array($prop_value) && !count($prop_value) && isset($intf_config[$prop_name]) && $intf_config[$prop_name] === '') {
                                /* if value is '' in config and property is [] force a match (OpenVPN group networks) */
                                continue;
                            }
                            $intf_config[$prop_name] = $prop_value;
                            $changed = true;
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
            $changed = true;
        }
    }

    return $changed;
}

function plugins_firewall($fw)
{
    foreach (plugins_scan() as $name => $path) {
        try {
            include_once $path;
        } catch (\Error $e) {
            error_log($e);
        }
        $func = sprintf('%s_firewall', $name);
        if (function_exists($func)) {
            $func($fw);
        }
    }

    return $fw;
}

function plugins_configure($hook, $verbose = false, $args = [])
{
    $logargs = [];
    $glob = '*';

    array_unshift($args, $verbose);

    foreach ($args as $arg) {
        if (is_null($arg)) {
            $logargs[] = 'null';
        } elseif (is_array($arg)) {
            $logargs[] = '[' . implode(',', $arg) . ']';
        } else {
            $logargs[] = $arg;
        }
    }

    log_msg(sprintf('plugins_configure %s (%s)', $hook, implode(',', $logargs)), LOG_INFO);

    if (substr_count($hook, ':')) {
        list($hook, $glob) = explode(':', $hook);
    }

    foreach (plugins_scan($glob) as $name => $path) {
        try {
            include_once $path;
        } catch (\Error $e) {
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
                         *
                         * Otherwise the default is assumed to be ":1"
                         * only, which is the $verbose flag, see below.
                         */
                        if (substr_count($task, ':')) {
                            list($argf, $argc) = explode(':', $task);
                        } else {
                            $argf = $task;
                            $argc = null;
                        }
                        if (empty($argc) || !is_numeric($argc)) {
                            $argc = 1;
                        }
                        if ($argc > count($args)) {
                            $argc = count($args);
                        }
                        log_msg(sprintf(
                            'plugins_configure %s (execute task : %s(%s))',
                            $hook,
                            $argf,
                            implode(',', array_slice($logargs, 0, $argc))
                        ), LOG_DEBUG);
                        try {
                            call_user_func_array($argf, array_slice($args, 0, $argc));
                        } catch (\Error $e) {
                            error_log($e);
                        }
                    }
                }
            }
        }
    }
}

function plugins_run($hook, $args = [])
{
    $glob = '*';
    $ret = [];

    if (substr_count($hook, ':')) {
        list($hook, $glob) = explode(':', $hook);
    }

    foreach (plugins_scan($glob) as $name => $path) {
        try {
            include_once $path;
        } catch (\Error $e) {
            error_log($e);
        }
        $func = sprintf('%s_run', $name);
        if (function_exists($func)) {
            foreach ($func() as $when => $task) {
                if ($hook == $when) {
                    try {
                        $result = call_user_func_array($task, $args);
                        if (!empty($result)) {
                            $ret[$name] = $result;
                        }
                    } catch (\Error $e) {
                        error_log($e);
                    }
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
        } catch (\Error $e) {
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

function plugins_argument_map(&$arg)
{
    /* record the caller for a sensible log message */
    $caller = !empty(debug_backtrace()[1]['function']) ? debug_backtrace()[1]['function'] : 'unknown';

    /* return value signals we have work to do */
    $ret = true;
    $tmp = $arg;

    if (is_array($arg)) {
        /* no need to deal with this now */
    } elseif (is_string($arg)) {
        /* backwards compat with single string arguments */
        $tmp = explode(',', $arg);
        $ret = !!strlen($arg);
    } elseif (!is_null($arg)) {
        log_msg(sprintf('%s: plugin argument map type "%s" not supported', $caller, gettype($arg)), LOG_WARNING);
        $ret = false;
    }

    if (is_array($tmp)) {
        /* remove empty values or duplicates */
        $tmp = array_unique(array_filter($tmp));
        $ret = !!count($tmp);
    }

    if ($ret) {
        /* only modify argument if there is work to do */
        $arg = $tmp;
    }

    return $ret;
}
