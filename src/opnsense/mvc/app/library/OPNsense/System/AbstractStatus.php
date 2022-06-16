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

/* XXX: can be converted to an enum in PHP 8.1 */
abstract class StatusLevel
{
    const Error = 0;
    const Warning = 1;
    const Notice = 2;
    const Deprecated = 3;
    const Ok = 4;
}

abstract class AbstractStatus implements \JsonSerializable
{
    protected $statusLevel;
    protected $message;
    protected $category;
    protected $logLocation;
    protected $className;
    protected $timeStamp;

    public function __construct($statusLevel, $message, $category, $logLocation, $className, $serialize = true)
    {
        $this->statusLevel = $statusLevel;
        $this->message = $message;
        $this->category = $category;
        $this->logLocation = $logLocation;
        $this->className = $className;
        $this->timeStamp = hrtime(true);

        if ($serialize) {
            $this->serialize();
        }
    }

    private function serialize()
    {
        $file = '/tmp/status/' . $this->category . '.status';
        if (!file_exists(dirname($file))) {
            mkdir(dirname($file), 0750, true);
        }

        file_put_contents($file, serialize($this->jsonSerialize()) . "\n", FILE_APPEND | LOCK_EX);
    }

    public function jsonSerialize()
    {
        return [
            "statusLevel" => $this->statusLevel,
            "message" => $this->message,
            "category" => $this->category,
            "logLocation" => $this->logLocation,
            "className" => $this->className,
            "timeStamp" => $this->timeStamp
        ];
    }

    public function getStatus()
    {
        return $this->statusLevel;
    }

    public function getMessage($verbose = false)
    {
        if (!$verbose) {
            return $this->message;
        }

        // TODO: Get full dump
    }

    public function getLogLocation()
    {
        return $this->logLocation;
    }

    public function getCategory()
    {
        return $this->category;
    }
}
