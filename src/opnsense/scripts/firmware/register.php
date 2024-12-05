#!/usr/local/bin/php
<?php

/*
 * Copyright (c) 2021-2024 Franco Fichtner <franco@opnsense.org>
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
$obsolete = []; /* empty default */
$type = ''; /* empty default */

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
    } elseif (!empty($config->system->firmware->plugins)) {
        $plugins = explode(',', (string)$config->system->firmware->plugins);
    }

    return array_flip($plugins);
}

function plugins_config_set($config, $plugins)
{
    ksort($plugins);

    $config->system->firmware->plugins = implode(',', array_keys($plugins));

    Config::getInstance()->save();
}

function plugins_disk_found($name)
{
    global $found;

    $bare = preg_replace('/^os-|-devel$/', '', $name);

    return isset($found[$bare]) && $found[$bare] == $name;
}

function plugins_disk_get()
{
    global $type, $obsolete;

    $found = [];

    foreach (glob('/usr/local/opnsense/version/*') as $name) {
        $filename = basename($name);
        $prefix = explode('.', $filename)[0];

        /* do not register from set-provided metadata */
        if ($prefix == 'base' || $prefix == 'kernel' || $prefix == 'pkgs') {
            continue;
        }

        /* do not register for business additions */
        if ($prefix == 'OPNBEcore' || $filename == 'core.license') {
            continue;
        }

        $ret = json_decode(@file_get_contents($name), true);
        if ($ret == null || !isset($ret['product_id'])) {
            echo "Ignoring invalid metadata: $name" . PHP_EOL;
            continue;
        }

        if (!empty($ret['product_conflicts'])) {
            foreach (preg_split('/\s+/', $ret['product_conflicts']) as $conflict) {
                $obsolete[$conflict] = "I'm not even here";
            }
        }

        if ($prefix == 'core') {
            if (strpos($ret['product_id'], '-') !== false) {
                $type = preg_replace('/[^-]+-/', '', $ret['product_id']);
            }
            continue;
        }

        $found[$filename] = $ret['product_id'];
    }

    $obsolete = array_keys($obsolete);

    return $found;
}

$plugins = plugins_config_get($config);
$found = plugins_disk_get();
$changed = false;

switch ($action) {
    case 'install':
        if (plugins_disk_found($name) && !isset($plugins[$name])) {
            $plugins[$name] = "hello, it's me";
            $changed = true;
        }
        break;
    case 'remove':
        if (!plugins_disk_found($name) && isset($plugins[$name])) {
            unset($plugins[$name]);
            $changed = true;
        }
        break;
    case 'sync':
        /*
         * Short mode without 'resync' during normal operation
         * also ensures firmware type and obsolete removal below.
         */
        if (!isset($config->trigger_initial_wizard)) {
            break;
        }

        /* FALLTHROUGH */
    case 'resync':
        foreach (array_keys($plugins) as $name) {
            if (!plugins_disk_found($name) && isset($plugins[$name])) {
                echo "Unregistering plugin: $name" . PHP_EOL;
                unset($plugins[$name]);
                $changed = true;
            }
        }
        foreach ($found as $name) {
            if (!isset($plugins[$name])) {
                echo "Registering plugin: $name" . PHP_EOL;
                $plugins[$name] = 'yep';
                $changed = true;
            }
        }
        break;
    default:
        echo "Registering nothing." . PHP_EOL;
        exit(1);
}

/* fixup missing firmware type if necessary */
if (!isset($config->system->firmware->type)) {
    $config->system->firmware->addChild('type');
    $changed = true;
}
if ($config->system->firmware->type != $type) {
    $config->system->firmware->type = $type;
    $changed = true;
}

/* scrub obsolete plugins if necessary */
foreach ($obsolete as $name) {
    if (isset($plugins[$name])) {
        unset($plugins[$name]);
        $changed = true;
    }
}

if ($changed) {
    plugins_config_set($config, $plugins);
}
