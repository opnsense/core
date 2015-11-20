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
        $response = json_decode(trim($backend->configdRun('firmware pkgstatus')), true);

        if ($response != null) {
            if (array_key_exists('connection', $response) && $response['connection'] == 'error') {
                $response['status_msg'] = gettext('Connection error.');
                $response['status'] = 'error';
            } elseif (array_key_exists('repository', $response) && $response['repository'] == 'error') {
                $response['status_msg'] = gettext('Repository problem.');
                $response['status'] = 'error';
            } elseif (array_key_exists('updates', $response) && $response['updates'] == 0) {
                $response['status_msg'] = gettext('There are no updates available.');
                $response['status'] = 'none';
            } elseif (array_key_exists(0, $response['upgrade_packages']) &&
                $response['upgrade_packages'][0]['name'] == 'pkg') {
                $response['status_upgrade_action'] = 'pkg';
                $response['status'] = 'ok';
                $response['status_msg'] =
                    gettext(
                      'There is a mandatory update for the package manager available. ' .
                      'Please install and fetch updates again.'
                    );
            } elseif (array_key_exists('updates', $response)) {
                $response['status_upgrade_action'] = 'all';
                $response['status'] = 'ok';
                if ($response['updates'] == 1) {
                    /* keep this dynamic for template translation even though %s is always '1' */
                    $response['status_msg'] = sprintf('There is %s update available.', $response['updates']);
                } else {
                    $response['status_msg'] = sprintf('There are %s updates available.', $response['updates']);
                }
                if ($response['upgrade_needs_reboot'] == 1) {
                    $response['status_msg'] = sprintf(
                        '%s %s',
                        $response['status_msg'],
                        gettext('This update requires a reboot.')
                    );
                }
            }
        } else {
            $response = array('status' => 'unknown', 'status_msg' => gettext('Current status is unknown.'));
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

        $keys = array('name', 'version', 'comment', 'flatsize');
        $backend = new Backend();
        $response = array();

        /* package infos are flat lists with 3 pipes as delimiter */
        foreach (array('local', 'remote') as $type) {
            $current = $backend->configdRun("firmware ${type}");
            $current = explode("\n", trim($current));
            $response[$type] = array();
            foreach ($current as $line) {
                $expanded = explode('|||', $line);
                $translated = array();
                $index = 0;
                if (count($expanded) != count($keys)) {
                    continue;
                }
                foreach ($keys as $key) {
                    $translated[$key] = $expanded[$index++];
                }
                $response[$type][] = $translated;
            }
        }

        return $response;
    }
}
