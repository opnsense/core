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

namespace OPNsense\System\Status;

use OPNsense\System\AbstractStatus;
use OPNsense\Core\Config;

class CrashReporterStatus extends AbstractStatus
{
    public function __construct()
    {
        $this->internalLogLocation = '/crash_reporter.php';

        if ($this->hasCrashed()) {
            $this->internalMessage = gettext("A problem was detected.");
            $this->internalStatus = static::STATUS_ERROR;
        }
    }

    private function hasCrashed()
    {
        $has_crashed = file_exists('/tmp/PHP_errors.log') || count(glob('/var/crash/textdump*')) > 1;

        if (Config::getInstance()->object()->system->deployment == 'development') {
            if ($has_crashed) {
                $this->internalMessage = gettext(
                    "A problem was detected, but non-production deployment is configured so crash reports cannot be sent."
                );
                $this->internalStatus = static::STATUS_NOTICE;
            }
            return false;
        }

        return $has_crashed;
    }

    public function dismissStatus()
    {
        /* Same logic as crash_reporter */
        $files_to_upload = glob('/var/crash/*');
        foreach ($files_to_upload as $file_to_upload) {
            @unlink($file_to_upload);
        }
        @unlink('/tmp/PHP_errors.log');
    }
}
