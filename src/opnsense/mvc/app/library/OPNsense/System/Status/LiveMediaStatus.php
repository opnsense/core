<?php

/*
 * Copyright (C) 2024 Deciso B.V.
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
use OPNsense\Core\Config;

class LiveMediaStatus extends AbstractStatus
{
    public function __construct()
    {
        $this->internalPriority = 2;
        $this->internalPersistent = true;
        $this->internalIsBanner = true;
        $this->internalTitle = gettext('Live Media');
    }

    public function collectStatus()
    {
        /*
         * Despite unionfs underneath, / is still not writeable,
         * making the following the perfect test for install media.
         */
        $file = '/.probe.for.readonly';

        if (file_exists($file)) {
            return;
        }

        $fd = @fopen($file, 'w');
        if ($fd) {
            fclose($fd);
            return;
        }

        $this->internalStatus = SystemStatusCode::NOTICE;
        $this->internalMessage = gettext('You are currently running in live media mode. A reboot will reset the configuration.');
        if (empty(Config::getInstance()->object()->system->ssh->noauto)) {
            exec('/bin/pgrep -anx sshd', $output, $retval); /* XXX portability shortcut */
            if (intval($retval) == 0) {
                $this->internalMessage .= ' ' . gettext('SSH remote login is enabled for the users "root" and "installer" using the same password.');
            }
        }
    }
}
