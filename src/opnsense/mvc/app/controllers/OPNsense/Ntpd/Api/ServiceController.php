<?php

/*
 * Copyright (C) 2026 Greelan
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

namespace OPNsense\Ntpd\Api;

use OPNsense\Base\ApiMutableServiceControllerBase;
use OPNsense\Core\Backend;

/**
 * Class ServiceController
 * @package OPNsense\Ntpd
 */
class ServiceController extends ApiMutableServiceControllerBase
{
    protected static $internalServiceClass = '\OPNsense\Ntpd\Ntpd';
    protected static $internalServiceName = 'ntpd';

    private function keyMetadata()
    {
        return [
            'server' => gettext('The remote NTP server\'s address or reference (IP or hostname)'),
            'refid' => gettext('Reference ID of the server that the remote peer is synchronized to. This may be another server or a hardware device.'),
            'stratum' => gettext('Distance from the top of the time hierarchy (16 = unsynchronized, 1 = hardware device that provides true time)'),
            'type' => gettext('Connection type'),
            'when' => gettext('How many seconds ago the last NTP packet was received from this peer'),
            'poll' => gettext('Poll interval (in seconds) between requests to this peer. Typically increases automatically'),
            'reach' => gettext('An octal value showing an 8-bit shift register of the last 8 reachability checks'),
            'delay' => gettext('Round-trip delay (in milliseconds) to the server. Lower is better'),
            'offset' => gettext('The time difference between your system clock and the server clock (in milliseconds)'),
            'jitter' => gettext('The variation in offset over time (in milliseconds). Lower means a more stable connection')
        ];
    }

    private function symbolMetadata()
    {
        return [
            'status' => [
                '*' => [
                    'descr' => gettext('The current system peer your machine is syncing time from'),
                    'status' => gettext('Active Peer')
                ],
                '+' => [
                    'descr' => gettext('Candidates that could be used if the primary fails'),
                    'status' => gettext('Candidate')
                ],
                'o' => [
                    'descr' => gettext('Peer synchronized to a Pulse Per Second signal'),
                    'status' => gettext('PPS Peer')
                ],
                '#' => [
                    'descr' => gettext('A source that is selected as a "backup" in the pool'),
                    'status' => gettext('Selected')
                ],
                '.' => [
                    'descr' => gettext('Peer was considered, but rejected by the intersection algorithm'),
                    'status' => gettext('Excess Peer')
                ],
                'x' => [
                    'descr' => gettext('The peer is deemed to be delivering incorrect time, this peer is ignored'),
                    'status' => gettext('False Ticker')
                ],
                '-' => [
                    'descr' => gettext('Peer responded, but statistically out of sync with the main cluster'),
                    'status' => gettext('Outlier')
                ],
                '__pool' => [
                    'descr' => gettext('A DNS pool as configured in Network Time: Time Servers, contains a rotation of NTP servers providing time.'),
                    'status' => gettext('DNS Pool')
                ],
                ' ' => [
                    'descr' => gettext('Not currently considered'),
                    'status' => gettext('Not Considered')
                ]
            ],
            'connection_type' => [
                'u' => gettext('Unicast'),
                'b' => gettext('Broadcast'),
                'l' => gettext('Local'),
                'm' => gettext('Multicast'),
                's' => gettext('Symmetric Active, this machine and the peer can provide time to each other'),
                'p' => gettext('This peer was discovered via an NTP pool association'),
            ]
        ];
    }

    public function metaAction()
    {
        return [
            'key' => $this->keyMetadata(),
            'symbols' => $this->symbolMetadata()
        ];
    }

    public function gpsAction()
    {
        return json_decode((new Backend())->configdRun('ntpd ntpq'), true) ?? [];
    }

    public function searchPeersAction()
    {
        $status = json_decode((new Backend())->configdRun('ntpd ntpq'), true);
        return $this->searchRecordsetBase($status['ntpq_servers'] ?? []);
    }

    public function reconfigureAction()
    {
        if (!$this->request->isPost()) {
            return ['status' => 'failed'];
        }

        $backend = new Backend();
        /* also stops ntpd without a time source */
        $result = trim($backend->configdRun('ntpd configure'));
        /* client mode adds or removes the daily restart */
        $backend->configdRun('cron restart', true);

        if ($result !== 'OK') {
            $msg = sprintf(gettext('Service "%s" failed to %s.'), static::$internalServiceName, 'configure');
            return ['status' => 'failed', 'status_msg' => $msg];
        }

        return ['status' => 'ok'];
    }

    protected function serviceEnabled()
    {
        return $this->getModel()->isEnabled();
    }
}
