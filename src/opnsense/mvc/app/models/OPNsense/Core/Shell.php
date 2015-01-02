<?php
/*
    # Copyright (C) 2014 Deciso B.V.
    #
    # All rights reserved.
    #
    # Redistribution and use in source and binary forms, with or without
    # modification, are permitted provided that the following conditions are met:
    #
    # 1. Redistributions of source code must retain the above copyright notice,
    #    this list of conditions and the following disclaimer.
    #
    # 2. Redistributions in binary form must reproduce the above copyright
    #    notice, this list of conditions and the following disclaimer in the
    #    documentation and/or other materials provided with the distribution.
    #
    # THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
    # INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
    # AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
    # AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
    # OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
    # SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
    # INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
    # CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
    # ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
    # POSSIBILITY OF SUCH DAMAGE.

    --------------------------------------------------------------------------------------
    package : Core
    function: shell/command handling routines

*/

namespace OPNsense\Core;


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
    function __construct()
    {
        // init, set simulation mode / debug autoput
        $this->simulate = \Phalcon\DI\FactoryDefault::getDefault()->get('config')->globals->simulate_mode;
        $this->debug = \Phalcon\DI\FactoryDefault::getDefault()->get('config')->globals->debug;

    }

    /**
     * execute shell command
     * @param string $command command to execute
     * @param bool $mute
     * @param bool $clearsigmask
     */
    private function _exec($command, $mute = false, $clearsigmask = false,&$output=null){
        $oarr = array();
        $retval = 0;

        // debug output
        if ($this->debug) {
            print("Shell->exec : " . $command . " \n");
        }

        // only execute actual command if not in simulation mode
        if (!$this->simulate) {
            if ($clearsigmask) {
                $oldset = array();
                pcntl_sigprocmask(SIG_SETMASK, array(), $oldset);
            }

            $garbage = exec("$command 2>&1", $output, $retval);

            if (($retval <> 0) && ($mute === false)) {
                //log_error(sprintf(gettext("The command '%1\$s' returned exit code '%2\$d', the output was '%3\$s' "),  implode(" ", $output);
                // TODO: log
                unset($output);
            }


            if ($clearsigmask) {
                pcntl_sigprocmask(SIG_SETMASK, $oldset);
            }

            unset($oarr);
            return $retval;
        }

        return null;
    }

    /**
     * execute command or list of commands
     *
     * @param string/Array() $command command to execute
     * @param bool $mute
     * @param bool $clearsigmask
     * @param Array() &$output
     */
    function exec($command, $mute = false, $clearsigmask = false,&$output=null)
    {
        if (is_array($command)){
            foreach($command as $comm ){
                $this->_exec($comm,$mute, $clearsigmask ,$output);
            }
        }
        else{
            $this->_exec($command,$mute, $clearsigmask ,$output);
        }

    }

}
