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

namespace OPNsense\RRD\Stats;

class Ntp extends Base
{
    public function run()
    {
        if (!self::$metadata['ntp_statsgraph']) {
            return;
        }
        $data = [];
        $ntpq = $this->shellCmd('/usr/local/sbin/ntpq -c rv');
        if (!empty($ntpq)) {
            $fieldmap = [
                'offset' => 'offset',
                'frequency' => 'freq',
                'sys_jitter' => 'sjit',
                'clk_jitter' => 'cjit',
                'clk_wander' => 'wander',
                'rootdisp' => 'disp'
            ];
            foreach ($ntpq as $idx => $item) {
                foreach (explode(',', $item) as $part) {
                    $tmp = explode('=', trim($part));
                    if (isset($fieldmap[$tmp[0]])) {
                        $data[$fieldmap[$tmp[0]]] = $tmp[1];
                    }
                }
            }
        }
        return $data;
    }
}
