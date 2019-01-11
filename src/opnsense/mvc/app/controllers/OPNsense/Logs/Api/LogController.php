<?php

/*
 * Copyright (C) 2019 Deciso B.V.
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

namespace OPNsense\Logs\Api;

use \OPNsense\Base\ApiControllerBase;
use OPNsense\Core\Backend;
use OPNsense\Core\Config;


/**
 * Class LogController
 * @package OPNsense\Diagnostics\Api
 */
class LogController extends ApiControllerBase
{
    /** @var array List of log files that are circular. Please keep this sorted alphabetically. */
    const CLOG_FILES = [
        '/var/log/configd.log',
        '/var/log/dhcpd.log',
        '/var/log/dnsmasq.log',
        '/var/log/filter.log',
        '/var/log/gateways.log',
        '/var/log/ipsec.log',
        '/var/log/lighttpd.log',
        '/var/log/ntpd.log',
        '/var/log/openvpn.log',
        '/var/log/pkg.log',
        '/var/log/ppps.log',
        '/var/log/portalauth.log',
        '/var/log/resolver.log',
        '/var/log/routing.log',
        '/var/log/suricata.log',
        '/var/log/system.log',
        '/var/log/wireless.log',
    ];
    /** @var array List of log files that are linear. Please keep this sorted alphabetically. */
    const LOG_FILES = [
        '/var/log/squid/access.log',
        '/var/log/squid/cache.log',
        '/var/log/squid/store.log',
    ];

    public function viewAction()
    {
        $logname = $this->request->getPost('logfile');
        $filter = $this->request->hasPost('filter') ? $this->request->getPost('filter') : '';
        $lineCharLimit = $this->request->hasPost('lineCharLimit') ? intval($this->request->getPost('lineCharLimit')) : 0;

        if (in_array($logname, self::CLOG_FILES)) {
            $response =  $this->fetchLog($logname, 'clog', $filter, $lineCharLimit);
        }
        elseif (in_array($logname, self::LOG_FILES)) {
            $response = $this->fetchLog($logname, 'log', $filter, $lineCharLimit);
        }
        else {
            throw new \Exception('Non-whitelisted log file!');
        }
        return $response;
    }

    protected function fetchLog(string $logname, string $logtype, string $filter, int $lineCharLimit): array
    {
        $backend = new Backend();
        $retVal = json_decode($backend->configdpRun('log view', [$logname, $logtype, $filter]), true);
        if (!empty($retVal['logLines'])) {
            // Split date and message.
            foreach ($retVal['logLines'] as $key => $logLine) {
                $strpos = strpos($logLine, 'OPNsense');
                $retVal['logLines'][$key] = [
                    'date' => substr($logLine, 0, $strpos),
                    'message' => substr($logLine, $strpos + 8),
                ];
            }
        }
        return $retVal;
    }

    protected static function limitLineLength(string $line, int $lineCharLimit)
    {
        if ($lineCharLimit != 0 && strlen($line) > $lineCharLimit) {
            return substr($line, 0, $lineCharLimit - 1) . '…';
        } else {
            return $line;
        }
    }

    public function clearAction()
    {
        $logname = $this->request->getPost('logfile');
        $backend = new Backend();

        if (in_array($logname, self::CLOG_FILES)) {
            $backend->configdpRun('log delete', [$logname, 'clog']);
        }
        elseif (in_array($logname, self::LOG_FILES)) {
            $backend->configdpRun('log delete', [$logname, 'log']);
        }
        else {
            throw new \Exception('Non-whitelisted log file!');
        }

        return [
            'status' => 'ok',
        ];
    }
}
