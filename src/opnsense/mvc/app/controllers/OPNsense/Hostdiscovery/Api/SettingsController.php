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

namespace OPNsense\Hostdiscovery\Api;

use OPNsense\Base\ApiMutableModelControllerBase;
use OPNsense\Core\Backend;
use OPNsense\Firewall\Util;

class SettingsController extends ApiMutableModelControllerBase
{
    protected static $internalModelName = 'hostdiscovery';
    protected static $internalModelClass = '\OPNsense\Hostdiscovery\Hostwatch';

    public function flushStatisticsCacheAction()
    {
        if (!$this->request->isPost()) {
            return ['status' => 'error', 'message' => gettext('Invalid request method')];
        }

        (new Backend())->configdRun('!filter diag top');

        return ['status' => 'ok'];
    }

    /**
     * Aggregate cached firewall state counters for the requested addresses.
     */
    public function stateStatisticsAction()
    {
        $statistics = [];
        if (!$this->request->isPost()) {
            return ['items' => $statistics];
        }

        foreach (explode(',', (string)$this->request->getPost('addresses', null, '')) as $address) {
            $address = trim($address);
            if (Util::isIpAddress($address)) {
                $statistics[$address] = ['bytes' => 0, 'states' => 0];
            }
        }

        if (!empty($statistics)) {
            $pftop = json_decode((new Backend())->configdpRun('filter diag top'), true) ?? [];
            foreach ($pftop['details'] ?? [] as $record) {
                $recordAddresses = array_unique([
                    $record['src_addr'] ?? null,
                    $record['dst_addr'] ?? null
                ]);
                foreach ($recordAddresses as $address) {
                    if ($address !== null && isset($statistics[$address])) {
                        $statistics[$address]['bytes'] += (int)($record['bytes'] ?? 0);
                        $statistics[$address]['states']++;
                    }
                }
            }
        }

        return ['items' => $statistics];
    }
}
