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

abstract class AbstractStatus
{
    protected $internalPriority = 100;
    protected $internalPersistent = false;
    protected $internalIsBanner = false;
    protected $internalTitle = null;
    protected $internalMessage = null;
    protected $internalLocation = null;
    protected $internalStatus = SystemStatusCode::OK;
    protected $internalTimestamp = null;
    protected $internalScope = [];

    public function getPriority()
    {
        return $this->internalPriority;
    }

    public function getPersistent()
    {
        return $this->internalPersistent;
    }

    public function isBanner()
    {
        return $this->internalIsBanner;
    }

    public function getTitle()
    {
        return $this->internalTitle;
    }

    public function getStatus()
    {
        return $this->internalStatus;
    }

    public function getMessage()
    {
        return $this->internalMessage ?? gettext('No problems were detected.');
    }

    public function getLocation()
    {
        return $this->internalLocation;
    }

    public function getTimestamp()
    {
        return $this->internalTimestamp;
    }

    /**
     * @return array list of paths to which this status applies, accepts wildcards
     */
    public function getScope()
    {
        return $this->internalScope;
    }

    public function collectStatus()
    {
        /* To be overridden by the child status classes */
    }

    public function dismissStatus()
    {
        /* To be overridden by the child status classes */
    }
}
