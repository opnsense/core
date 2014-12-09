<?php
/**
 * Created by PhpStorm.
 * User: ad
 * Date: 08-12-14
 * Time: 08:41
 */

namespace Core;


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