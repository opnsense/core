<?php

/*
 * Copyright (C) 2016 Deciso B.V.
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
        $enabled = isset($config->unbound->stats);

        return [
            'enabled' => $enabled ? 1 : 0
        ];
    }

    public function RollingAction($timeperiod)
    {
        $this->sessionClose();
        // Sanitize input
        $interval = preg_replace("/^(?:(?!1|12|24).)*$/", "24", $timeperiod) == 1 ? 60 : 300;
        $response = (new Backend())->configdpRun('unbound qstats rolling', array($interval, $timeperiod));
        return json_decode($response, true);
    }

    public function totalsAction($maximum)
    {
        $this->sessionClose();
        $max = preg_replace("/^(?:(?![0-9]).)*$/", "10", $maximum);
        $response = (new Backend())->configdpRun('unbound qstats totals', array($max));
        return json_decode($response, true);
    }
}
