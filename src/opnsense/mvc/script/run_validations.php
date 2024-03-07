#!/usr/local/bin/php
<?php

/*
 * Copyright (C) 2023 Deciso B.V.
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

require_once('script/load_phalcon.php');

use OPNsense\Core\Config;

$classprefix = !empty($argv[1]) ? str_replace('/', '\\', $argv[1]) : '';
$class_info = new \ReflectionClass("OPNsense\\Base\\BaseModel");
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
                $name = $mdl_class_info->getName();
                $mdl = $mdl_class_info->newInstance();
                if (!$mdl->isVolatile()) {
                    $msgs = $mdl->validate(null, '', true);
                    foreach ($msgs as $key => $msg) {
                        echo sprintf('%s.%s => %s', $name, $key, $msg) . PHP_EOL;
                    }
                }
            }
        } catch (\ReflectionException $e) {
            /* cannot construct, skip */
        }
    }
}
