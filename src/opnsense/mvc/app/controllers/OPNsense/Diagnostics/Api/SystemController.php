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

namespace OPNsense\Diagnostics\Api;

use OPNsense\Base\ApiControllerBase;
use OPNsense\Core\Config;
use OPNsense\Core\Backend;

/**
 * Class SystemController
 * @package OPNsense\Diagnostics\Api
 */
class SystemController extends ApiControllerBase
{
    public function memoryAction()
    {
        $data = json_decode((new Backend())->configdRun('system show vmstat_mem'), true);
        if (empty($data) || !is_array($data)) {
            return [];
        }
        if (!empty($data['malloc-statistics']) && !empty($data['malloc-statistics']['memory'])) {
            $data['malloc-statistics']['totals'] = ['used' => 0];
            foreach ($data['malloc-statistics']['memory'] as &$item) {
                $item['name'] = $item['type'];
                unset($item['type']);
                $data['malloc-statistics']['totals']['used'] += $item['memory-use'];
            }
            $tmp = number_format($data['malloc-statistics']['totals']['used']) . " k";
            $data['malloc-statistics']['totals']['used_fmt'] = $tmp;
        }
        if (!empty($data['memory-zone-statistics']) && !empty($data['memory-zone-statistics']['zone'])) {
            $data['memory-zone-statistics']['totals'] = ['used' => 0];
            foreach ($data['memory-zone-statistics']['zone'] as $item) {
                $data['memory-zone-statistics']['totals']['used'] += $item['used'];
            }
            $tmp = number_format($data['memory-zone-statistics']['totals']['used']) . " k";
            $data['memory-zone-statistics']['totals']['used_fmt'] = $tmp;
        }
        return $data;
    }
}
