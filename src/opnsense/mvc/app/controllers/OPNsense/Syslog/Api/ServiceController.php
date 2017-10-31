<?php

/**
 *    Copyright (C) 2017 E.Bevz & Deciso B.V.
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

namespace OPNsense\Syslog\Api;

use \OPNsense\Base\ApiControllerBase;
use \OPNsense\Core\Backend;
use \OPNsense\Syslog\Syslog;
use \OPNsense\Core\Config;
use \Phalcon\Filter;

/**
 * Class ServiceController
 * @package OPNsense\Syslog
 */
class ServiceController extends ApiControllerBase
{

    /**
     * restart syslog service
     * @return array
     */
    public function reloadAction()
    {
        if ($this->request->isPost()) {
            // close session for long running action
            $this->sessionClose();

            $backend = new Backend();

            // generate template
            $backend->configdRun("template reload OPNsense.Syslog");

            // (res)start daemon
            $backend->configdRun("syslog stop");
            $status = $backend->configdRun("syslog start");
            $message = chop($status) == "OK" ? gettext("Service reloaded") : gettext("Syslog service start error");

            return array("status" => $status, "message" => $message);
        } else {
            return array("status" => "failed", "message" => gettext("Wrong request"));
        }
    }

    /**
     * delete log files
     * @return array
     */
    public function resetLogFilesAction()
    {
        if ($this->request->isPost()) {

            $this->sessionClose();

            $backend = new Backend();
            $backend->configdRun("syslog stop");

            $mdl = new Syslog();
            $logsize = intval($mdl->RotationFileSize->__toString()) * 1024;
            $result = array();
            $deleted = array();
            foreach($mdl->LogTargets->Target->__items as $uuid => $target) {
                if($target->ActionType == 'file' || $target->ActionType == 'clog') {
                    $pathname = $target->Target->__toString();
                    if(!in_array($pathname, $deleted)) {
                        if($target->ActionType == 'file') {
                            $status = $backend->configdRun("syslog clearlog {$pathname}");
                        }
                        if($target->ActionType == 'clog') {
                            $status = $backend->configdRun("syslog clearclog {$logsize} {$pathname}");
                        }
                        $result[] = array('name' => $pathname, 'status' => $status);
                        $deleted[] = $pathname;
                    }
                }
            }

            $backend->configdRun("syslog start");
            $backend->configdRun("syslog restart_dhcpd"); // restart dhcpd in legacy way. logic from legacy code, does it needed ?

            return array("status" => "ok", "message" => gettext("The log files have been reset."), "details" => $result);
        } else {
            return array("status" => "failed", "message" => gettext("Wrong request"));
        }
    }

    /**
     * clear custom log
     * @return array
     */
    public function clearLogAction()
    {
        if ($this->request->isPost()) {

            $this->sessionClose();

            $filter = new Filter();
            $filter->add('logfilename', function($value){ return preg_replace("/[^0-9a-zA-Z_]/", "", $value);});

            $name = $this->request->getPost('logname');
            $name = $filter->sanitize($name, 'logfilename');

            $backend = new Backend();
            $mdl = new Syslog();
            $logsize = intval($mdl->RotationFileSize->__toString()) * 1024;
            $fullname = $mdl->getLogFileName($name);
            foreach($mdl->LogTargets->Target->__items as $uuid => $target) {
                if($target->Target == $fullname) {
                    if($target->ActionType == 'file') {
                        $backend->configdRun("syslog clearlog {$fullname}");
                    }
                    if($target->ActionType == 'clog') {
                        $backend->configdRun("syslog clearclog {$logsize} {$fullname}");
                    }
                }
            }

            $backend->configdRun("syslog start");

            return array("status" => "ok", "message" => gettext("The log file has been reset."));
        } else {
            return array("status" => "failed", "message" => gettext("Wrong request"));
        }
    }

    /**
     * dump log
     * @return array
     */
    public function getlogAction()
    {
        if ($this->request->isPost()) {

            $logname = $this->request->getPost('logname');
            $filter = $this->request->getPost('filter');

            $this->sessionClose();

            $mdl = new Syslog();
            $filename = $mdl->getLogFileName($logname);
            $reverse = ($mdl->Reverse->__toString() == '1');
            $numentries = intval($mdl->NumEntries->__toString());
            $hostname = Config::getInstance()->toArray()['system']['hostname'];

            if(!file_exists($filename)) {
                return array("status" => "ok", "data" => array(array('time' => gettext("No data found"), 'filter' => "", 'message' => "")), 'filters' => '');
            }

            $logtype = 'file';
            foreach($mdl->LogTargets->Target->__items as $uuid => $target) {
                if($target->Target == $filename) {
                    $logtype = $target->ActionType->__toString();
                    break;
                }
            }

            $dump_filter = "";
            $filters = preg_split('/\s+/', trim(preg_quote($filter,'/')));
            foreach ($filters as $key => $pattern) {
                if(trim($pattern) == '')
                    continue;
                if ($key > 0)
                    $dump_filter .= "&&";
                $dump_filter .= "/$pattern/";
            }

            $logdata = array();
            $formatted = array();
            if($filename != '') {
                $backend = new Backend();
                $limit = $this->getMemoryLimit() / 16;
                if($logtype == 'file') {
                    $logdatastr = $backend->configdRun("syslog dumplog {$filename} {$numentries} {$dump_filter}");
                }
                if($logtype == 'clog') {
                    $logdatastr = $backend->configdRun("syslog dumpclog {$filename} {$numentries} {$dump_filter}");
                }
                $logdata = explode("\n", $logdatastr);
            }

            if($reverse) {
                $logdata = array_reverse($logdata);
            }

            foreach ($logdata as $logent) {
                if(trim($logent) == '') {
                    continue;
                }

                $logent = preg_split("/\s+/", $logent, 6);
                $entry_date_time = join(" ", array_slice($logent, 0, 3));
                $entry_text = isset($logent[3]) ? (($logent[3] == $hostname) ? "" : $logent[3] . " ") : "";
                $entry_text .= (isset($logent[4]) ?  $logent[4] : '') . (isset($logent[5]) ? " " . $logent[5] : '');
                $formatted[] = array('time' => utf8_encode($entry_date_time), 'filter' => $filter, 'message' => utf8_encode($entry_text));
            }

            if(count($formatted) == 0) {
                return array("status" => "ok", "data" => array(array('time' => gettext("No data found"), 'filter' => "", 'message' => "")), 'filters' => '');
            }

            return array("status" => "ok", "data" => $formatted, 'filters' => $filters);

        } else {
            return array("status" => "failed", "message" => gettext("Wrong request"));
        }
    }

    private function getMemoryLimit()
    {
        $size = ini_get("memory_limit");
        if($size == -1) {
            return 0;
        }

        switch (substr($size, -1))
        {
            case 'M':
            case 'm':
                $size = (int)$size * 1048576;
                break;
            case 'K':
            case 'k':
                $size = (int)$size * 1024;
                break;
            case 'G':
            case 'g':
                $size = (int)$size * 1073741824;
                break;
        }

        return $size;
    }
}

