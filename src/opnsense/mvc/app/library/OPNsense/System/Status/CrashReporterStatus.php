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
        $src_logs = array_merge(glob('/var/crash/textdump*'), glob('/var/crash/vmcore*'));
        $php_log = '/tmp/PHP_errors.log';

        $this->internalLogLocation = '/crash_reporter.php';

        $src_errors = count($src_logs) > 0;
        if ($src_errors) {
            foreach ($src_logs as $src_log) {
                $info = stat($src_log);
                if (!empty($info['mtime']) && $this->internalTimestamp < $info['mtime']) {
                    $this->internalTimestamp = $info['mtime'];
                }
            }
        }

        $php_errors = file_exists($php_log);
        if ($php_errors) {
            $info = stat($php_log);
            if (!empty($info['mtime']) && $this->internalTimestamp < $info['mtime']) {
                $this->internalTimestamp = $info['mtime'];
            }
        }

        if ($php_errors || $src_errors) {
            $this->internalMessage = gettext('An issue was detected and can be reviewed using the firmware crash reporter.');
            if ($php_errors) {
                $this->internalStatus = Config::getInstance()->object()->system->deployment != 'development' ? static::STATUS_ERROR : static::STATUS_NOTICE;
            }
            if ($src_errors && $this->internalStatus != static::STATUS_ERROR) {
                $this->internalStatus = static::STATUS_WARNING;
            }
        }
    }

    public function dismissStatus()
    {
        /* Same logic as crash_reporter */
        $files = glob('/var/crash/*');
        foreach ($files as $file) {
            @unlink($file);
        }
        @unlink('/tmp/PHP_errors.log');
    }
}
