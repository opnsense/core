#!/usr/local/bin/php
<?php

/*
 * Copyright (c) 2021 Franco Fichtner <franco@opnsense.org>
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

$action = $name = 'undefined';

if (count($argv) > 1) {
    $action = $argv[1];
}

if (count($argv) > 2) {
    $name = $argv[2];

    if (strpos($name, 'os-') !== 0) {
        /* not a plugin, don't care */
        exit();
    }
}

require_once('script/load_phalcon.php');

use OPNsense\Core\Config;

$config = Config::getInstance()->object();

function plugins_config_get($config)
{
    $plugins = [];

    if (!isset($config->system->firmware)) {
        $config->system->addChild('firmware');
    }

    if (!isset($config->system->firmware->plugins)) {
        $config->system->firmware->addChild('plugins');
    } else {
        $plugins = explode(',', (string)$config->system->firmware->plugins);
    }

    return array_flip($plugins);
}

function plugins_config_set($config, $plugins)
{
    $config->system->firmware->plugins = implode(',', array_keys($plugins));

    if (empty($config->system->firmware->plugins)) {
        unset($config->system->firmware->plugins);
    }

    if (!@count($config->system->firmware->children())) {
        unset($config->system->firmware);
    }

    Config::getInstance()->save();
}

function plugins_disk_found($name, $found)
{
    $bare = preg_replace('/^os-|-devel$/', '', $name);

    return isset($found[$bare]) && $found[$bare] == $name;
}

function plugins_remove_sibling($name, $plugins)
{
    $other = preg_replace('/-devel$/', '', $name);
    if ($other == $name) {
        $other .= '-devel';
    }

    if (isset($plugins[$other])) {
        unset($plugins[$other]);
    }

    return $plugins;
}

function plugins_disk_get()
{
    $found = [];

    foreach (glob('/usr/local/opnsense/version/*') as $name) {
        $filename = basename($name);
        if (strpos($filename, 'base') === 0) {
            continue;
        }
        if (strpos($filename, 'kernel') === 0) {
            continue;
        }
        if (strpos($filename, 'core') === 0) {
            continue;
        }

        $ret = json_decode(@file_get_contents($name), true);
        if ($ret == null || !isset($ret['product_id'])) {
            echo "Ignoring invalid metadata: $name" . PHP_EOL;
            continue;
        }

        $found[$filename] = $ret['product_id'];
    }

    return $found;
}

$plugins = plugins_config_get($config);
$found = plugins_disk_get();

switch ($action) {
    case 'install':
        if (!plugins_disk_found($name, $found)) {
            return;
        }
        $plugins = plugins_remove_sibling($name, $plugins);
        $plugins[$name] = 'hello';
        break;
    case 'remove':
        if (plugins_disk_found($name, $found)) {
            return;
        }
        if (isset($plugins[$name])) {
            unset($plugins[$name]);
        }
        break;
    case 'resync_factory':
        /* XXX handle core package */
        /* FALLTHROUGH */
    case 'resync':
        foreach (array_keys($plugins) as $name) {
            if (!plugins_disk_found($name, $found)) {
                echo "Unregistering missing plugin: $name" . PHP_EOL;
                unset($plugins[$name]);
            }
        }
        foreach ($found as $name) {
            if (!isset($plugins[$name])) {
                echo "Registering misconfigured plugin: $name" . PHP_EOL;
                $plugins[$name] = 'yep';
            }
            $plugins = plugins_remove_sibling($name, $plugins);
        }
        break;
    default:
        exit();
}

plugins_config_set($config, $plugins);
