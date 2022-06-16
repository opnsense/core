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

class SystemStatus
{
    private $statuses;

    public function __construct()
    {
        $this->statuses = $this->collectStatus();
    }

    private function collectStatus()
    {
        $result = array();
        $all = glob('/tmp/status/*');
        foreach ($all as $categoryHandle) {
            $fileOpen  = fopen($categoryHandle, 'r');
            if ($fileOpen) {
                while (($status = unserialize(fgets($fileOpen))) !== false) {
                    $obj = '\\' . $status['className'];
                    if (class_exists($obj)) {
                        $result[$status['category']][] = new $obj($status['statusLevel'], $status['message'], false);
                    }
                }
                fclose($fileOpen);
                if ($result) {
                    foreach ($result as $category => $status) {
                        /* Sort by severity and re-assign - errors first */
                        usort($status, fn($a, $b) => $a->getStatus() - $b->getStatus());
                        $result[$category] = $status;
                    }
                }
            }
        }

        return $result;
    }

    public function getSystemStatus()
    {
        return $this->statuses;
    }

}
