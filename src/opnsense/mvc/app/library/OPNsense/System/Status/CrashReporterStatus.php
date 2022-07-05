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

class CrashReporterStatus extends AbstractStatus {

    public function __construct()
    {
        if ($this->hasCrashed()) {
            $this->internalMessage = "A problem was detected.";
            $this->internalStatus = static::STATUS_ERROR;
            $this->internalLogLocation = '/crash_reporter.php';
        }
    }

    private function hasCrashed()
    {
        $config = Config::getInstance()->object();
        $deployment = $config->system->deployment;
        if (!empty($deployment)) {
            return false;
        }

        $skipFiles = array('.', '..', 'minfree', 'bounds', '');
        $errorLog = '/tmp/PHP_errors.log';
        $count = 0;

        if (file_exists($errorLog)) {
            $total = trim(shell_exec(sprintf(
                '/bin/cat %s | /usr/bin/wc -l | /usr/bin/awk \'{ print $1 }\'',
                $errorLog
            )));
            if ($total > 0) {
                $count++;
            }
        }

        $crashes = glob('/var/crash/*');
        foreach ($crashes as $crash) {
            if (!in_array(basename($crash), $skipFiles)) {
                $count++;
            }
        }

        return $count > 0;
    }
}
