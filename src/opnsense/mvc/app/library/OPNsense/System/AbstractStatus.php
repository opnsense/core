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

abstract class AbstractStatus
{
    const STATUS_ERROR = -1;
    const STATUS_WARNING = 0;
    const STATUS_NOTICE = 1;
    const STATUS_OK = 2;

    protected $internalMessage = 'No problems were detected.';
    protected $internalLogLocation = '';
    protected $internalStatus = self::STATUS_OK;
    protected $internalTimestamp = '0';
    protected $statusStrings = ['notice', 'warning', 'error'];

    public function getStatus()
    {
        return $this->internalStatus;
    }

    public function getMessage($verbose = false)
    {
        return $this->internalMessage;
    }

    public function getLogLocation()
    {
        return $this->internalLogLocation;
    }

    public function getTimestamp()
    {
        return $this->internalTimestamp;
    }

    public function dismissStatus()
    {
        /* To be overridden by the child status classes */
    }
}
