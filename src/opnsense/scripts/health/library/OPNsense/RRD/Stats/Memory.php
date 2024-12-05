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

class Memory extends Base
{
    public function run()
    {
        $sysctls = [
            'vm.stats.vm.v_page_count',
            'vm.stats.vm.v_active_count',
            'vm.stats.vm.v_inactive_count',
            'vm.stats.vm.v_free_count',
            'vm.stats.vm.v_cache_count',
            'vm.stats.vm.v_wire_count'
        ];

        $memory = $this->shellCmd('/sbin/sysctl ' . implode(' ', $sysctls));
        if (!empty($memory)) {
            $percentages = [];
            $data = [];
            foreach ($memory as $idx => $item) {
                // strip vm.stats.vm.v_ and collect into $result
                $tmp = explode(':', substr($item, 14));
                $data[$tmp[0]] = trim($tmp[1]);
                if ($idx > 0) {
                    $percentages[explode('_', $tmp[0])[0]] = ($data[$tmp[0]] / $data['page_count']) * 100.0;
                }
            }
            return $percentages;
        }
        return [];
    }
}
