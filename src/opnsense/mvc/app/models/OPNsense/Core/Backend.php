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
    private $configdSocket = "/var/run/check_reload_status";

    /**
     * init Backend component
     */
    public function __construct()
    {
    }

    /**
     * send event to backend
     * @param $event event string
     * @param int $timeout timeout in seconds
     * @return string
     * @throws \Exception
     */
    public function sendEvent($event, $timeout = 60)
    {
        $stream = stream_socket_client('unix://'.$this->configdSocket, $errorNumber, $errorMessage, $timeout);
        if ($stream === false) {
            throw new \Exception("Failed to connect: $errorMessage");
        }

        stream_set_timeout($stream, $timeout);
        fwrite($stream, $event);
        $resp = stream_get_contents($stream);
        $info = stream_get_meta_data($stream);

        if ($info['timed_out'] == 1) {
            throw new \Exception("Timeout (".$timeout.") executing :".$event);
        }

        return $resp;
    }
}
