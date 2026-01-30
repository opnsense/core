<?php

/*
 * Copyright (C) 2023 Deciso B.V.
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

/**
 * Simple syslog wrapper class
 */
class Syslog
{
    private $name = null;
    private $option = null;
    private $facility = null;
    private static $echo_stdout = false;

    public function __construct($name, $option = null, $facility = null)
    {
        $this->name = $name;
        $this->option = $option ?? LOG_ODELAY | LOG_PID;
        $this->facility = $facility ?? LOG_USER;
    }

    private function send($level, $message)
    {
        openlog($this->name, $this->option, $this->facility);
        syslog($level, $message);
        if (self::$echo_stdout) {
            echo sprintf("[%s] %s", $level, $message);
        }
    }

    public function alert($message)
    {
        $this->send(LOG_ALERT, $message);
    }


    public function critical($message)
    {
        $this->send(LOG_CRIT, $message);
    }

    public function error($message)
    {
        $this->send(LOG_ERR, $message);
    }

    public function debug($message)
    {
        $this->send(LOG_DEBUG, $message);
    }

    public function emergency($message)
    {
        $this->send(LOG_EMERG, $message);
    }

    public function info($message)
    {
        $this->send(LOG_INFO, $message);
    }

    public function notice($message)
    {
        $this->send(LOG_NOTICE, $message);
    }

    public function warning($message)
    {
        $this->send(LOG_WARNING, $message);
    }

    /**
     * Enable local (stdout) logging for all syslog instances for this application, for debug purposes
     */
    public static function enableLocalEcho()
    {
        self::$echo_stdout = true;
    }

    /**
     * Disable local (stdout) logging for all syslog instances for this application
     */
    public static function disableLocalEcho()
    {
        self::$echo_stdout = false;
    }
}
