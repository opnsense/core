#!/usr/local/bin/php
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

// initialize phalcon components for our script
error_reporting(E_ALL);
require_once('script/load_phalcon.php');

use OPNsense\Core\Config;

$classprefix = !empty($argv[1]) ? str_replace('/', '\\', $argv[1]) : '';

$class_info = new \ReflectionClass("OPNsense\\Base\\BaseModel");
$executed_migration = false;
$model_dir = dirname($class_info->getFileName()) . "/../../";
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($model_dir)) as $x) {
    if (strtolower(substr($x->getPathname(), -4)) == '.php') {
        $classname = str_replace('/', '\\', explode('.', str_replace($model_dir, '', $x->getPathname()))[0]);
        /* XXX we match the prefix here, but should eventually switch to component exploded by "\" */
        if (!empty($classprefix) && strpos($classname, $classprefix) !== 0) {
            /* not our requested class */
            continue;
        }
        try {
            $mdl_class_info = new \ReflectionClass($classname);
            $parent = $mdl_class_info->getParentClass();
            if ($parent && $parent->name == 'OPNsense\Base\BaseModel') {
                $mdl = $mdl_class_info->newInstance();
                $version_pre = $mdl->getVersion();
                $mig_performed = $mdl->runMigrations();
                $version_post = $mdl->getVersion();
                if ($version_pre != $version_post) {
                    if ($mig_performed) {
                        $version_pre = !empty($version_pre) ? $version_pre : '<unversioned>';
                        echo "Migrated " .  $mdl_class_info->getName() .
                            " from " . $version_pre .
                            " to " . $version_post . "\n";
                        $executed_migration = true;
                    } else {
                        echo "*** " .  $mdl_class_info->getName() . " Migration failed, check log for details\n";
                    }
                } elseif (!empty($version_post)) {
                    echo "Keep version " . $mdl_class_info->getName() . " (" . $version_post . ")\n";
                } else {
                    echo "Keep unversioned " . $mdl_class_info->getName() . "\n";
                }
            }
        } catch (\ReflectionException $e) {
            null; // cannot construct, skip
        }
    }
}

if ($executed_migration) {
    // make changes persistent
    Config::getInstance()->save();
}
