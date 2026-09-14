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

        $disk_info = json_decode((new Backend())->configdRun('system diag disk'), true);

        if (!isset($disk_info['storage-system-information']) || !isset($disk_info['storage-system-information']['filesystem'])) {
            return;
        }

        foreach ($disk_info['storage-system-information']['filesystem'] as $filesystem) {
            if ($filesystem['mounted-on'] === '/') {
                $usedFormatted = $this->humanizeBlocks($filesystem['used-blocks'], true);
                $availableFormatted = $this->humanizeBlocks($filesystem['available-blocks'], true);
                $usedPercent = intval($filesystem['used-percent']);
                $availableBytes = $filesystem['available-blocks'] * 512;
                $totalBytes = $filesystem['total-blocks'] * 512;

                $warningThreshold = min(10 *(1024**3), 0.2 * $totalBytes);
                $errorThreshold = min(5 *(1024**3), 0.1 * $totalBytes);

                if ($availableBytes <= $warningThreshold && $availableBytes > $errorThreshold) {
                    $this->internalStatus = SystemStatusCode::WARNING;
                    $this->internalMessage = sprintf(
                        gettext('Disk space on the root filesystem is nearly full (' .
                                '%s or %d%% used, %s available). Please consider cleaning up or expanding storage.'),
                        $usedFormatted,
                        $usedPercent,
                        $availableFormatted
                    );
                } elseif ($availableBytes <= $errorThreshold) {
                    $this->internalStatus = SystemStatusCode::ERROR;
                    $this->internalMessage = sprintf(
                        gettext('Disk space on the root filesystem is critically full (' .
                                '%s or %d%% used, %s available). Please consider cleaning up or expanding storage.'),
                        $usedFormatted,
                        $usedPercent,
                        $availableFormatted
                    );
                }

                break;
            }
        }
    }

    public function humanizeBlocks($blocks, $showUnit = true)
    {
        $bytes = $blocks * 512;

        $units = ['B', 'K', 'M', 'G', 'T', 'P', 'E'];

        $unitIndex = 0;
        $value = $bytes;

        while ($value >= 1024 && $unitIndex < count($units) - 1) {
            $value /= 1024;
            $unitIndex++;
        }

        if ($value >= 100) {
            $formatted = number_format($value, 0);
        } elseif ($value >= 10) {
            $formatted = number_format($value, 1);
        } else {
            $formatted = number_format($value, 2);
        }

        if ($showUnit) {
            $formatted = $formatted . $units[$unitIndex];
        }

        return $formatted;
    }
}
