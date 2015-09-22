<?php

/**
 *    Copyright (C) 2015 Deciso B.V.
 *
 *    All rights reserved.
 *
 *    Redistribution and use in source and binary forms, with or without
 *    modification, are permitted provided that the following conditions are met:
 *
 *    1. Redistributions of source code must retain the above copyright notice,
 *       this list of conditions and the following disclaimer.
 *
 *    2. Redistributions in binary form must reproduce the above copyright
 *       notice, this list of conditions and the following disclaimer in the
 *       documentation and/or other materials provided with the distribution.
 *
 *    THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
 *    INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 *    AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 *    AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 *    OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 *    SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 *    INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 *    CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 *    ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 *    POSSIBILITY OF SUCH DAMAGE.
 *
 */
namespace OPNsense\Core;

/**
 * Class Backend
 * @package OPNsense\Core
 */
class Backend
{

    /**
     * @var string location of configd socket
     */
    private $configdSocket = "/var/run/configd.socket";

    /**
     * init Backend component
     */
    public function __construct()
    {
    }

    /**
     * send event to backend
     * @param string $event event string
     * @param bool $detach detach process
     * @param int $timeout timeout in seconds
     * @return string
     * @throws \Exception
     */
    public function configdRun($event, $detach = false, $timeout = 120)
    {
        $endOfStream = chr(0).chr(0).chr(0);
        $poll_timeout = 2 ; // poll timeout interval

        // wait until socket exist for a maximum of $timeout
        $timeout_wait = $timeout;
        while (!file_exists($this->configdSocket)) {
            sleep(1);
            $timeout_wait -= 1;
            if ($timeout_wait <= 0) {
                throw new \Exception("failed waiting for configd (doesn't seem to be running)");
            }
        }

        $resp = "";
        $stream = stream_socket_client('unix://'.$this->configdSocket, $errorNumber, $errorMessage, $poll_timeout);
        if ($stream === false) {
            throw new \Exception("Failed to connect: $errorMessage");
        }

        stream_set_timeout($stream, $poll_timeout);
        // send command
        if ($detach) {
            fwrite($stream, "&".$event);
        } else {
            fwrite($stream, $event);
        }

        // read response data
        $starttime = time() ;
        while (true) {
            $resp = $resp . stream_get_contents($stream);

            if (strpos($resp, $endOfStream) !== false) {
                // end of stream detected, exit
                break;
            }

            // handle timeouts
            if ((time() - $starttime) > $timeout) {
                throw new \Exception("Timeout (".$timeout.") executing :".$event);
            }

        }

        return  str_replace($endOfStream, "", $resp);
    }

    /**
     * send event to backend using command parameter list (which will be quoted for proper handling)
     * @param string $event event string
     * @param array $params list of parameters to send with command
     * @param bool $detach detach process
     * @param int $timeout timeout in seconds
     * @return string
     * @throws \Exception
     */
    public function configdpRun($event, $params = array(), $detach = false, $timeout = 120)
    {
        foreach ($params as $param) {
            // quote parameters
            $event .= ' "' . str_replace('"', '\\"', $param) . '"';
        }

        return $this->configdRun($event, $detach, $timeout);
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
