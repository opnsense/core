<?php

/*
 * Copyright (C) 2022 Deciso B.V.
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
    private $statuses;

    public function __construct()
    {
        $this->statuses = $this->collectStatus();
    }

    /**
     * @throws \Exception
     */
    private function collectStatus()
    {
        $result = array();
        $statusCodes = array();
        $all = scandir(__DIR__ . '/Status');
        $classes = array_map(function($file) {
            if (str_contains($file, 'Status')) {
                return '\\OPNsense\\System\\Status\\' . str_replace('.php', '', $file);
            }
        }, $all);

        $statuses = array_filter($classes, function($class) {
            return class_exists($class) && is_subclass_of($class, '\\OPNsense\\System\\AbstractStatus');
        });

        foreach ($statuses as $statusClass) {
            $obj = new $statusClass();
            $reflect = new \ReflectionClass($obj);
            $shortName = str_replace('Status', '', $reflect->getShortName());

            if ($shortName == 'System') {
                /* reserved */
                throw new \Exception("SystemStatus classname is reserved");
            }

            $statusCodes[] = $obj->getStatus();

            $result[$shortName] = [
                'status' => $this->parseStatus($obj->getStatus()),
                'message' => $obj->getMessage(),
                'logLocation' => $obj->getLogLocation()
            ];
        }

        /* Determine the most severe status type */
        sort($statusCodes);
        $result['System'] = [
            'status' => $this->parseStatus($statusCodes[0]),
        ];

        return $result;
    }

    private function parseStatus($statusCode)
    {
        switch ($statusCode) {
            case AbstractStatus::STATUS_ERROR:
                return 'Error';
            case AbstractStatus::STATUS_WARN:
                return 'Warning';
            case AbstractStatus::STATUS_NOTICE:
                return 'Notice';
            default:
                return 'OK';
        }
    }

    /**
     * @return array An array containing a parseable format of every status object
     */
    public function getSystemStatus()
    {
        return $this->statuses;
    }

}
