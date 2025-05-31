<?php

/*
 * Copyright (C) 2022-2024 Deciso B.V.
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

namespace OPNsense\System;

/**
 * SystemStatus: Crawl through the \OPNsense\System\Status namespace and
 * instantiate every class that correctly extends AbstractStatus. Every created
 * object is responsible for detecting problems in its own defined category.
 */
class SystemStatus
{
    /**
     * @throws \Exception
     */
    private function collectClasses()
    {
        $objectMap = [];
        $all = glob(__DIR__ . '/Status/*.php');
        $classes = array_map(function ($file) {
            if (strpos($file, 'Status') !== false) {
                return '\\OPNsense\\System\\Status\\' . basename($file, '.php');
            }
        }, $all);

        $statuses = array_filter($classes, function ($class) {
            return class_exists($class) && is_subclass_of($class, '\\OPNsense\\System\\AbstractStatus');
        });

        foreach ($statuses as $statusClass) {
            $obj = new $statusClass();
            $reflect = new \ReflectionClass($obj);
            $shortName = strtolower(str_replace('Status', '', $reflect->getShortName()));

            if ($shortName == 'System') {
                /* reserved for front-end usage */
                throw new \Exception("SystemStatus classname is reserved");
            }

            $objectMap[$shortName] = $obj;
        }

        return $objectMap;
    }

    public function collectStatus($scope = null)
    {
        $result = [];
        $objectMap = $this->collectClasses();
        foreach ($objectMap as $shortName => $obj) {
            $objScope = $obj->getScope();
            if (!empty($objScope) && !$this->matchPath($scope, $objScope)) {
                /* don't probe if unnecessary */
                continue;
            }

            $obj->collectStatus();

            if ($obj->getStatus() == SystemStatusCode::OK) {
                continue;
            }

            $result[$shortName] = [
                'title' => $obj->getTitle(),
                'statusCode' => $obj->getStatus(),
                'message' => $obj->getMessage(),
                'location' => $obj->getLocation(),
                'timestamp' => $obj->getTimestamp(),
                'persistent' => $obj->getPersistent(),
                'isBanner' => $obj->isBanner(),
                'priority' => $obj->getPriority(),
                'scope' => $obj->getScope(),
            ];
        }

        return $result;
    }

    public function dismissStatus($subsystem)
    {
        $objectMap = $this->collectClasses();
        if (array_key_exists($subsystem, $objectMap) && !$objectMap[$subsystem]->getPersistent()) {
            $objectMap[$subsystem]->dismissStatus();
        }
    }

    private function matchPath($input, $paths)
    {
        foreach ($paths as $path) {
            $pattern = preg_quote($path, '/');
            $pattern = str_replace('\*', '.*', $pattern);

            if (preg_match('/^' . $pattern . '$/', $input)) {
                return true;
            }
        }
        return false;
    }
}
