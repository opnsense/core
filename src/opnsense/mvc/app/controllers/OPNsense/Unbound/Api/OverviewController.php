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

namespace OPNsense\Unbound\Api;

use OPNsense\Base\ApiControllerBase;
use OPNsense\Core\Backend;
use OPNsense\Core\Config;

class OverviewController extends ApiControllerBase
{
    public function isEnabledAction()
    {
        $this->sessionClose();
        $config = Config::getInstance()->object();
        return [
            'enabled' => isset($config->unbound->stats) ? 1 : 0
        ];
    }

    public function RollingAction($timeperiod)
    {
        $this->sessionClose();
        // Sanitize input
        $interval = preg_replace("/^(?:(?!1|12|24).)*$/", "24", $timeperiod) == 1 ? 60 : 300;
        $response = (new Backend())->configdpRun('unbound qstats rolling', [$interval, $timeperiod]);
        return json_decode($response, true);
    }

    public function totalsAction($maximum)
    {
        $this->sessionClose();
        $max = preg_replace("/^(?:(?![0-9]).)*$/", "10", $maximum);
        $response = (new Backend())->configdpRun('unbound qstats totals', [$max]);
        $parsed = json_decode($response, true);

        /* Map the blocklist type keys to their corresponding description */
        $nodes = (new \OPNsense\Unbound\Unbound())->getNodes()['dnsbl']['type'];
        foreach ($parsed['top_blocked'] as $domain => $props) {
            if (array_key_exists($props['blocklist'], $nodes)) {
                $parsed['top_blocked'][$domain]['blocklist'] = $nodes[$props['blocklist']]['value'];
            }
        }

        return $parsed;
    }
}
