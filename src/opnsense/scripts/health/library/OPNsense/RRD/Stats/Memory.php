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
        $frmt = [
            '/sbin/sysctl',
            'vm.stats.vm.v_page_count',
            'vm.stats.vm.v_active_count',
            'vm.stats.vm.v_inactive_count',
            'vm.stats.vm.v_free_count',
            'vm.stats.vm.v_wire_count',
            'vm.stats.vm.v_laundry_count',
            'hw.pagesize',
            'kstat.zfs.misc.arcstats.size'
        ];

        $memory = $this->shellCmd($frmt);

        if (!empty($memory)) {
            $data = [];
            foreach ($memory as $item) {
                $parts = explode(':', $item, 2);
                if (count($parts) === 2) {
                    $data[trim($parts[0])] = (float)trim($parts[1]);
                }
            }

            $page_count = max(1.0, (float)($data['vm.stats.vm.v_page_count'] ?? 1.0));
            $arc_size = $data['kstat.zfs.misc.arcstats.size'] ?? 0.0;
            $pagesize = max(1.0, (float)($data['hw.pagesize'] ?? 4096.0));
            $arc_pages = $arc_size / $pagesize;

            $laundry_pages = $data['vm.stats.vm.v_laundry_count'] ?? 0.0;
            $wire_pages = max(0.0, ($data['vm.stats.vm.v_wire_count'] ?? 0.0) - $arc_pages);
            $cache_pages = $laundry_pages + $arc_pages;

            return [
                'active' => (($data['vm.stats.vm.v_active_count'] ?? 0.0) / $page_count) * 100.0,
                'inactive' => (($data['vm.stats.vm.v_inactive_count'] ?? 0.0) / $page_count) * 100.0,
                'free' => (($data['vm.stats.vm.v_free_count'] ?? 0.0) / $page_count) * 100.0,
                'wire' => ($wire_pages / $page_count) * 100.0,
                'cache' => ($cache_pages / $page_count) * 100.0,
            ];
        }

        return [];
    }
}
