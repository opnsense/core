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
namespace OPNsense\Core\Api;

use \OPNsense\Base\ApiControllerBase;
use \OPNsense\Core\Backend;

/**
 * Class FirmwareController
 * @package OPNsense\Core
 */
class FirmwareController extends ApiControllerBase
{
    /**
     * retrieve available updates
     * @return array
     */
    public function statusAction()
    {
        $this->sessionClose(); // long running action, close session
        $backend = new Backend();
        $response = json_decode(trim($backend->configdRun("firmware pkgstatus")), true);

        if ($response != null) {
            if (array_key_exists("connection", $response) && $response["connection"]=="error") {
                $response["status"] = "error";
                $response["status_msg"] = "Connection Error";
            } elseif (array_key_exists("repository", $response) && $response["repository"]=="error") {
                $response["status"] = "error";
                $response["status_msg"] = "Repository Problem";
            } elseif (array_key_exists("updates", $response) && $response['updates'] == 0) {
                $response["status"] = "none";
                $response["status_msg"] = "no updates found";
            } elseif (array_key_exists(0, $response["upgrade_packages"]) &&
                $response["upgrade_packages"][0]["name"] == "pkg") {
                $response["status"] = "ok";
                $response["status_upgrade_action"] = "pkg";
                $response["status_msg"] = "There is a mandatory update for the package manager. ".
                    "Please install and check for updates again.";
            } elseif (array_key_exists("updates", $response)) {
                $response["status"] = "ok";
                $response["status_upgrade_action"] = "all";
                $response["status_msg"] = sprintf("A total of %s update(s) are available.", $response["updates"]);
            }
        } else {
            $response = array("status" => "unknown","status_msg" => "Current status is unknown");
        }

        return $response;
    }

    /**
     * perform actual upgrade
     * @return array status
     * @throws \Exception
     */
    public function upgradeAction()
    {
        $backend = new Backend();
        $response =array();
        if ($this->request->hasPost("upgrade")) {
            $response['status'] = 'ok';
            if ($this->request->getPost("upgrade") == "pkg") {
                $action = "firmware upgrade pkg";
            } else {
                $action = "firmware upgrade all";
            }
            $response['msg_uuid'] = trim($backend->configdRun($action, true));
        } else {
            $response['status'] = 'failure';
        }

        return $response;
    }

    /**
     * retrieve upgrade status (and log file of current process)
     */
    public function upgradestatusAction()
    {
        $backend = new Backend();
        $result = array("status"=>"running");
        $cmd_result = trim($backend->configdRun("firmware upgrade_status"));

        $result['log'] = $cmd_result;

        if (trim($cmd_result) == "Execute error") {
            $result["status"] = "error";
        } elseif (strpos($cmd_result, '***DONE***') !== false) {
            $result["status"] = "done";
        } elseif (strpos($cmd_result, '***REBOOT***') !== false) {
            $result["status"] = "reboot";
        }


        return $result;
    }

    /**
     * list local and remote packages
     * @return array
     */
    public function infoAction()
    {
        $this->sessionClose(); // long running action, close session

        $response = array('local' => array(), 'remote' => array());

        $backend = new Backend();
        $remote = $backend->configdRun('firmware remote');
        $local = $backend->configdRun('firmware local');

        /*
         * pkg(8) returns malformed json by simply outputting each
         * indivudual package json block... fix it up for now.
         */
        $local = str_replace("\n}\n", "\n},\n", trim($local));
        $local = json_decode('[' . $local . ']', true);
        if ($local != null) {
            $keep = array('name', 'version', 'comment', 'www', 'flatsize', 'licenses', 'desc', 'categories');
            foreach ($local as $infos) {
                $stripped = array();
                foreach ($infos as $key => $info) {
                    if (in_array($key, $keep)) {
                        $stripped[$key] = $info;
                    }
                }
                $response['local'][] = $stripped;
            }
        }

        /* Remote packages are only a flat list */
        $remote = explode("\n", trim($remote));
        foreach ($remote as $name) {
            /* keep layout compatible with the above */
            $response['remote'][] = array('name' => $name);
        }

        return $response;
    }
}
