<?php

/*
 * Copyright (C) 2015 Deciso B.V.
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

namespace OPNsense\Core;

use Phalcon\Logger;
use Phalcon\Logger\Adapter\Syslog;

/**
 * Class Backend
 * @package OPNsense\Core
 */
class Backend
{
    /**
     * @var string location of configd socket
     */
    private $configdSocket = '/var/run/configd.socket';

    /**
     * init Backend component
     */
    public function __construct()
    {
    }

    /**
     * get system logger
     * @param string $ident syslog identifier
     * @return Syslog log handler
     */
    protected function getLogger($ident = 'configd')
    {
        $logger = new Logger(
            'messages',
            [
                'main' => new Syslog($ident, array(
                    'option' => LOG_PID,
                    'facility' => LOG_LOCAL4
                ))
            ]
        );

        return $logger;
    }

    /**
     * send event to backend
     * @param string $event event string
     * @param bool $detach detach process
     * @param int $timeout timeout in seconds
     * @param int $connect_timeout connect timeout in seconds
     * @return string
     * @throws \Exception
     */
    public function configdRun($event, $detach = false, $timeout = 120, $connect_timeout = 10)
    {
        $endOfStream = chr(0) . chr(0) . chr(0);
        $errorOfStream = 'Execute error';
        $poll_timeout = 2; // poll timeout interval

        // wait until socket exist for a maximum of $connect_timeout
        $timeout_wait = $connect_timeout;
        $errorMessage = "";
        while (
            !file_exists($this->configdSocket) ||
            ($stream = @stream_socket_client('unix://' . $this->configdSocket, $errorNumber, $errorMessage, $poll_timeout)) === false
        ) {
            sleep(1);
            $timeout_wait -= 1;
            if ($timeout_wait <= 0) {
                if (file_exists($this->configdSocket)) {
                    $this->getLogger()->error("Failed to connect to configd socket: $errorMessage while executing " . $event);
                    return null;
                } else {
                    $this->getLogger()->error("failed waiting for configd (doesn't seem to be running)");
                }
                return null;
            }
        }

        $resp = '';

        stream_set_timeout($stream, $poll_timeout);
        // send command
        if ($detach) {
            fwrite($stream, '&' . $event);
        } else {
            fwrite($stream, $event);
        }

        // read response data
        $starttime = time();
        while (true) {
            $resp = $resp . stream_get_contents($stream);

            if (strpos($resp, $endOfStream) !== false) {
                // end of stream detected, exit
                break;
            }

            // handle timeouts
            if ((time() - $starttime) > $timeout) {
                $this->getLogger()->error("Timeout (" . $timeout . ") executing : " . $event);
                return null;
            } elseif (feof($stream)) {
                $this->getLogger()->error("Configd disconnected while executing : " . $event);
                return null;
            }
        }

        if (
            strlen($resp) >= strlen($errorOfStream) &&
            substr($resp, 0, strlen($errorOfStream)) == $errorOfStream
        ) {
            return null;
        }

        return str_replace($endOfStream, '', $resp);
    }

    /**
     * send event to backend using command parameter list (which will be quoted for proper handling)
     * @param string $event event string
     * @param array $params list of parameters to send with command
     * @param bool $detach detach process
     * @param int $timeout timeout in seconds
     * @param int $connect_timeout connect timeout in seconds
     * @return string
     * @throws \Exception
     */
    public function configdpRun($event, $params = array(), $detach = false, $timeout = 120, $connect_timeout = 10)
    {
        if (!is_array($params)) {
            /* just in case there's only one parameter */
            $params = array($params);
        }

        foreach ($params as $param) {
            $event .= ' ' . escapeshellarg($param);
        }

        return $this->configdRun($event, $detach, $timeout, $connect_timeout);
    }

    /**
     * check configd socket for last restart, return 0 socket not present.
     * @return int last restart timestamp
     */
    public function getLastRestart()
    {
        if (file_exists($this->configdSocket)) {
            return filemtime($this->configdSocket);
        } else {
            return 0;
        }
    }
}
