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
use OPNsense\Core\Backend;

class DiskSpaceStatus extends AbstractStatus
{
    public function __construct()
    {
        $this->internalPriority = 5;
        $this->internalPersistent = true;
        $this->internalTitle = gettext('Disk Space');
    }

    public function collectStatus()
    {
        /**
         * If live media, disk space status should be muted,
         * use the same (inverted) logic as LiveMediaStatus
         */

        $file = '/.probe.for.readonly';

        if (!file_exists($file)) {
            return;
        }

        $fd = @fopen($file, 'w');
        if (!$fd) {
            return;
        }
        fclose($fd);

        $backend = new Backend();
        $output = json_decode($backend->configdRun('system diag disk'), true);

        if (!isset($output['storage-system-information']) || !isset($output['storage-system-information']['filesystem'])) {
            return;
        }

        foreach ($output['storage-system-information']['filesystem'] as $filesystem) {
            if ($filesystem['mounted-on'] === '/') {
                $used = $this->convertToGB($filesystem['used']);
                $available = $this->convertToGB($filesystem['available']);
                $usedPercent = intval($filesystem['used-percent']);
                $totalSpace = $used + $available;

                $warningThresholdGB = min(10, 0.2 * $totalSpace);
                $errorThresholdGB = min(5, 0.1 * $totalSpace);

                if ($available <= $warningThresholdGB && $available > $errorThresholdGB) {
                    $this->internalStatus = SystemStatusCode::WARNING;
                    $this->internalMessage = sprintf(
                        gettext('Disk space on the root filesystem is nearly full (' .
                                '%.2fG or %d%% used, %.2fG available). Please consider cleaning up or expanding storage.'),
                        $used,
                        $usedPercent,
                        $available
                    );
                } elseif ($available <= $errorThresholdGB) {
                    $this->internalStatus = SystemStatusCode::ERROR;
                    $this->internalMessage = sprintf(
                        gettext('Disk space on the root filesystem is critically full (' .
                                '%.2fG or %d%% used, %.2fG available). Please consider cleaning up or expanding storage.'),
                        $used,
                        $usedPercent,
                        $available
                    );
                }

                break;
            }
        }
    }

    private function convertToGB($value)
    {
        preg_match('/([0-9.]+)([a-zA-Z]+)/', $value, $matches);
        if (count($matches) < 3) {
            return floatval($value);
        }

        $number = floatval($matches[1]);
        $unit = strtoupper($matches[2]);

        switch ($unit) {
            case 'B':
                return $number / 1024 / 1024 / 1024;
            case 'K':
                return $number / 1024 / 1024;
            case 'M':
                return $number / 1024;
            case 'T':
                return $number * 1024;
            case 'P':
                return $number * 1024 * 1024;
            case 'E':
                return $number * 1024 * 1024 * 1024;
            default:
                return $number; // Default GB
        }
    }
}
