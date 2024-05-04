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

use OPNsense\Core\AppConfig;

/**
 * Class Shell shell/command handling routines
 * @package OPNsense\Core
 */
class Shell
{
    /**
     * simulation mode, only print commands, dom not execute
     * @var bool
     */
    private $simulate = false;

    /**
     * debug mode
     * @var bool
     */
    private $debug = false;

    /**
     * new shell object
     */
    public function __construct()
    {
        // init, set simulation mode / debug autoput
        $appconfig = new AppConfig();
        $this->simulate = $appconfig->globals->simulate_mode;
        $this->debug = $appconfig->globals->debug;
    }

    /**
     * execute command or list of commands
     *
     * @param string|array $command command to execute
     * @param bool $mute
     * @param array &$output
     */
    public function exec($command, $mute = false, &$output = null)
    {
        if (!is_array($command)) {
            $command = array($command);
        }

        foreach ($command as $comm) {
            $this->execSingle($comm, $mute, $output);
        }
    }

    /**
     * execute shell command
     * @param string $command command to execute
     * @param bool $mute
     * @param array &$output
     * @return int
     */
    private function execSingle($command, $mute = false, &$output = null)
    {
        $oarr = array();
        $retval = 0;

        // debug output
        if ($this->debug) {
            print("Shell->exec : " . $command . " \n");
        }

        // only execute actual command if not in simulation mode
        if (!$this->simulate) {
            exec("$command 2>&1", $output, $retval);

            if (($retval != 0) && ($mute === false)) {
                // TODO: log
                unset($output);
            }

            unset($oarr);
            return $retval;
        }

        return null;
    }
}
