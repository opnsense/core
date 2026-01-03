<?php

/*
 * Copyright (C) 2025 Deciso B.V.
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

namespace OPNsense\Core;

use OPNsense\Core\AppConfig;
use OPNsense\Core\Syslog;

class ConfigMaintenance
{
    private $modelmap = [];
    private $foundrefs = [];

    public function __construct()
    {
        $this->modelmap = $this->loadModels();
    }

    /**
     * collect all installed modules
     */
    private function loadModels()
    {
        $modelfiles = [];
        $model_dir = dirname((new \ReflectionClass("OPNsense\\Base\\BaseModel"))->getFileName()) . "/../../";
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($model_dir)) as $x) {
            $pinfo = pathinfo(realpath($x->getPathname()));
            $xmlname = sprintf("%s/%s.xml", $pinfo['dirname'], $pinfo['filename']);
            $classname = str_replace('/', '\\', explode('.', str_replace($model_dir, '', $x->getPathname()))[0]);
            if (file_exists($xmlname) && isset($pinfo['extension']) && $pinfo['extension'] == 'php') {
                $parent = (new \ReflectionClass($classname))->getParentClass();
                if ($parent && $parent->name == 'OPNsense\Base\BaseModel') {
                    $modelfiles[$xmlname] = $classname;
                }
            }
        }
        $map = [];
        foreach ($modelfiles as $filename => $classname) {
            $model_xml = simplexml_load_file($filename);
            if ($model_xml !== false && str_starts_with($model_xml->mount, '/')) {
                $mount = str_replace('/', '.', ltrim(rtrim($model_xml->mount, '+'), '/'));
                $map[$mount] = [
                    'filename' => $filename,
                    'class' => $classname,
                    'description' => trim((string)$model_xml->description ?? '')
                ];
            }
        }
        return $map;
    }


    /**
     * collect all "flushable" configuration items (models)
     */
    public function traverseConfig($node = null, $path = '')
    {
        if ($node === null) {
            $node = (Config::getInstance())->object();
            $this->foundrefs = [];
        }
        foreach ($node->children() as $xmlNode) {
            if ($xmlNode->count() > 0 || !empty($xmlNode->attributes()['version'])) {
                $this_path = ltrim($path . '.' . $xmlNode->getName(), '.');
                if (in_array($this_path, $this->foundrefs)) {
                    continue;
                } elseif (isset($this->modelmap[$this_path]) || !empty($xmlNode->attributes()['version'])) {
                    /* found a registered model or a container with a version tag (likely model, but not installed) */
                    $this->foundrefs[] = $this_path;
                    if (isset($this->modelmap[$this_path])) {
                        yield [
                            'description' => $this->modelmap[$this_path]['description'],
                            'installed' => '1',
                            'id' => $this_path,
                        ];
                    } else {
                        $tmp = explode('.', $this_path);
                        $descr = (string)$xmlNode->attributes()['description'];
                        yield [
                            'description' => !empty($descr) ? $descr : ucfirst(end($tmp)),
                            'installed' => '0',
                            'id' => $this_path,
                        ];
                    }
                } else {
                    yield from $this->traverseConfig($xmlNode, $this_path);
                }
            }
        }
    }

    /**
     *  del model item, requires a version attribute to identify itself as a model.
     */
    public function delItem($item, $node = null, $path = '')
    {
        if ($node === null) {
            $node = (Config::getInstance())->object();
        }
        foreach ($node->children() as $xmlNode) {
            if ($xmlNode->count() > 0 || !empty($xmlNode->attributes()['version'])) {
                $this_path = ltrim($path . '.' . $xmlNode->getName(), '.');
                if (!empty($xmlNode->attributes()['version'])) {
                    if ($this_path == $item) {
                        unset($xmlNode[0]);
                        return true;
                    }
                } else {
                    $this->delItem($item, $xmlNode, $this_path);
                }
            }
        }
        return false;
    }

    /**
     * retrieve model map
     */
    public function getMap()
    {
        return $this->modelmap;
    }
}
