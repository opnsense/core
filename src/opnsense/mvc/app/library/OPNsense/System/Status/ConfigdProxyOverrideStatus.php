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

namespace OPNsense\System\Status;

use OPNsense\System\AbstractStatus;
use OPNsense\System\SystemStatusCode;

class ConfigdProxyOverrideStatus extends AbstractStatus
{
    public function __construct()
    {
        $this->internalPriority = 2;
        $this->internalPersistent = true;
        $this->internalIsBanner = true;
        $this->internalTitle = gettext('Configd proxy configured');
        // Only show in pages that are the most likely to have issues
        $this->internalScope = [
            '/ui/core/hasync',
            '/ui/core/firmware',
            '/ui/opncentral/host/admin',
        ];
    }

    public function collectStatus()
    {
        /*
         * We could also use the following without skimming files:
         *
         *   # configctl configd environment
         *
         * https://docs.opnsense.org/development/backend/configd.html
         */
        foreach (glob('/usr/local/opnsense/service/conf/configd.conf.d/*') as $file) {
            $contents = @file_get_contents($file);
            if ($contents !== false && stripos($contents, '_PROXY=') !== false) {
                $this->internalMessage = gettext(
                    'The configd environment contains custom proxy settings, these may interfere with the settings configured here.'
                );
                $this->internalStatus = SystemStatusCode::NOTICE;
                break;
            }
        }
    }
}
