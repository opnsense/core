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
use \OPNsense\Core\Config;

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
        $response = json_decode(trim($backend->configdRun('firmware check')), true);

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
            } elseif ((array_key_exists(0, $response['upgrade_packages']) &&
                $response['upgrade_packages'][0]['name'] == 'pkg') ||
                (array_key_exists(0, $response['reinstall_packages']) &&
                $response['reinstall_packages'][0]['name'] == 'pkg')) {
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
                    $response['status_msg'] = sprintf(
                        gettext('There is %s update available, total download size is %s.'),
                        $response['updates'],
                        $response['download_size']
                    );
                } else {
                    $response['status_msg'] = sprintf(
                        gettext('There are %s updates available, total download size is %s.'),
                        $response['updates'],
                        $response['download_size']
                    );
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

        /* XXX array isn't flat, need to refactor this */
        if (isset($response['upgrade_packages'])) {
            $sorted = array();
            foreach ($response['upgrade_packages'] as $key => $value) {
                $sorted[$value['name']] = $value;
            }
            uksort($sorted, function ($a, $b) {
                return strnatcmp($a, $b);
            });
            $response['upgrade_packages'] = $sorted;
        }

        return $response;
    }

    /**
     * perform reboot
     * @return array status
     * @throws \Exception
     */
    public function rebootAction()
    {
        $backend = new Backend();
        $response = array();
        if ($this->request->isPost()) {
            $response['status'] = 'ok';
            $response['msg_uuid'] = trim($backend->configdRun('firmware reboot', true));
        } else {
            $response['status'] = 'failure';
        }

        return $response;
    }

    /**
     * perform poweroff
     * @return array status
     * @throws \Exception
     */
    public function poweroffAction()
    {
        $backend = new Backend();
        $response = array();
        if ($this->request->isPost()) {
            $response['status'] = 'ok';
            $response['msg_uuid'] = trim($backend->configdRun('firmware poweroff', true));
        } else {
            $response['status'] = 'failure';
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
        $response = array();
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
     * reinstall package
     * @param string $pkg_name package name to reinstall
     * @return array status
     * @throws \Exception
     */
    public function reinstallAction($pkg_name)
    {
        $backend = new Backend();
        $response = array();

        if ($this->request->isPost()) {
            $response['status'] = 'ok';
            // sanitize package name
            $filter = new \Phalcon\Filter();
            $filter->add('pkgname', function ($value) {
                return preg_replace('/[^0-9a-zA-Z-_]/', '', $value);
            });
            $pkg_name = $filter->sanitize($pkg_name, "pkgname");
            // execute action
            $response['msg_uuid'] = trim($backend->configdpRun("firmware reinstall", array($pkg_name), true));
        } else {
            $response['status'] = 'failure';
        }

        return $response;
    }

    /**
     * install package
     * @param string $pkg_name package name to install
     * @return array status
     * @throws \Exception
     */
    public function installAction($pkg_name)
    {
        $backend = new Backend();
        $response = array();

        if ($this->request->isPost()) {
            $response['status'] = 'ok';
            // sanitize package name
            $filter = new \Phalcon\Filter();
            $filter->add('pkgname', function ($value) {
                return preg_replace('/[^0-9a-zA-Z-_]/', '', $value);
            });
            $pkg_name = $filter->sanitize($pkg_name, "pkgname");
            // execute action
            $response['msg_uuid'] = trim($backend->configdpRun("firmware install", array($pkg_name), true));
        } else {
            $response['status'] = 'failure';
        }

        return $response;
    }

    /**
     * remove package
     * @param string $pkg_name package name to remove
     * @return array status
     * @throws \Exception
     */
    public function removeAction($pkg_name)
    {
        $backend = new Backend();
        $response = array();

        if ($this->request->isPost()) {
            $response['status'] = 'ok';
            // sanitize package name
            $filter = new \Phalcon\Filter();
            $filter->add('pkgname', function ($value) {
                return preg_replace('/[^0-9a-zA-Z-_]/', '', $value);
            });
            $pkg_name = $filter->sanitize($pkg_name, "pkgname");
            // execute action
            $response['msg_uuid'] = trim($backend->configdpRun("firmware remove", array($pkg_name), true));
        } else {
            $response['status'] = 'failure';
        }

        return $response;
    }

    /**
     * lock package
     * @param string $pkg_name package name to lock
     * @return array status
     * @throws \Exception
     */
    public function lockAction($pkg_name)
    {
        $backend = new Backend();
        $response = array();

        if ($this->request->isPost()) {
            $response['status'] = 'ok';
            // sanitize package name
            $filter = new \Phalcon\Filter();
            $filter->add('pkgname', function ($value) {
                return preg_replace('/[^0-9a-zA-Z-_]/', '', $value);
            });
            $pkg_name = $filter->sanitize($pkg_name, "pkgname");
            // execute action
            $response['msg_uuid'] = trim($backend->configdpRun("firmware lock", array($pkg_name), true));
        } else {
            $response['status'] = 'failure';
        }

        return $response;
    }

    /**
     * unlock package
     * @param string $pkg_name package name to unlock
     * @return array status
     * @throws \Exception
     */
    public function unlockAction($pkg_name)
    {
        $backend = new Backend();
        $response = array();

        if ($this->request->isPost()) {
            $response['status'] = 'ok';
            // sanitize package name
            $filter = new \Phalcon\Filter();
            $filter->add('pkgname', function ($value) {
                return preg_replace('/[^0-9a-zA-Z-_]/', '', $value);
            });
            $pkg_name = $filter->sanitize($pkg_name, "pkgname");
            // execute action
            $response['msg_uuid'] = trim($backend->configdpRun("firmware unlock", array($pkg_name), true));
        } else {
            $response['status'] = 'failure';
        }

        return $response;
    }

    /**
     * retrieve exectution status
     */
    public function runningAction()
    {
        $backend = new Backend();

        $result = array(
            'status' => trim($backend->configdRun('firmware running'))
        );

        return $result;
    }
    /**
     * retrieve upgrade status (and log file of current process)
     */
    public function upgradestatusAction()
    {
        $backend = new Backend();
        $result = array('status' => 'running');
        $cmd_result = trim($backend->configdRun('firmware status'));

        $result['log'] = $cmd_result;

        if (trim($cmd_result) == 'Execute error') {
            $result['status'] = 'error';
        } elseif (strpos($cmd_result, '***DONE***') !== false) {
            $result['status'] = 'done';
        } elseif (strpos($cmd_result, '***REBOOT***') !== false) {
            $result['status'] = 'reboot';
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

        $keys = array('name', 'version', 'comment', 'flatsize', 'locked');
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

    /**
     * list firmware mirror and flavour options
     * @return array
     */
    public function getFirmwareOptionsAction()
    {
        // todo: we might want to move these into configuration files later
        $mirrors = array();
        $mirrors[''] = '(default)';
        $mirrors['https://opnsense.aivian.org'] = 'Aivian (Shaoxing, CN)';
        $mirrors['https://opnsense-update.deciso.com'] = 'Deciso (NL, Commercial)';
        $mirrors['https://mirror.auf-feindgebiet.de/opnsense'] = 'auf-feindgebiet.de (Karlsruhe, DE)';
        $mirrors['https://opnsense.c0urier.net'] = 'c0urier.net (Lund, SE)';
        //$mirrors['https://fleximus.org/mirror/opnsense'] = 'Fleximus (Roubaix, FR)';
        $mirrors['http://mirror.ams1.nl.leaseweb.net/opnsense'] = 'LeaseWeb (Amsterdam, NL)';
        $mirrors['http://mirror.fra10.de.leaseweb.net/opnsense'] = 'LeaseWeb (Frankfurt, DE)';
        $mirrors['http://mirror.sfo12.us.leaseweb.net/opnsense'] = 'LeaseWeb (San Francisco, US)';
        $mirrors['http://mirror.wdc1.us.leaseweb.net/opnsense'] = 'LeaseWeb (Washington, D.C., US)';
        $mirrors['http://mirrors.nycbug.org/pub/opnsense'] = 'NYC*BUG (New York, US)';
        $mirrors['http://pkg.opnsense.org'] = 'OPNsense (Amsterdam, NL)';
        $mirrors['http://mirror.ragenetwork.de/opnsense'] = 'RageNetwork (Munich, DE)';
        $mirrors['http://mirror.wjcomms.co.uk/opnsense'] = 'WJComms (London, GB)';

        $has_subscription = array();
        $has_subscription[] = 'https://opnsense-update.deciso.com';

        $flavours = array();
        $flavours[''] = '(default)';
        $flavours['libressl'] = 'LibreSSL';
        $flavours['latest'] = 'OpenSSL';

        return array("mirrors"=>$mirrors, "flavours" => $flavours, 'has_subscription' => $has_subscription);
    }

    /**
     * retrieve current firmware configuration options
     * @return array
     */
    public function getFirmwareConfigAction()
    {
        $result = array();
        $result['mirror'] = '';
        $result['flavour'] = '';

        if (!empty(Config::getInstance()->object()->system->firmware->mirror)) {
            $result['mirror'] = (string)Config::getInstance()->object()->system->firmware->mirror;
        }
        if (!empty(Config::getInstance()->object()->system->firmware->flavour)) {
            $result['flavour'] = (string)Config::getInstance()->object()->system->firmware->flavour;
        }

        return $result;
    }

    /**
     * set firmware configuration options
     * @return array status
     */
    public function setFirmwareConfigAction()
    {
        $response = array("status" => "failure");

        if ($this->request->isPost()) {
            $response['status'] = 'ok';
            $selectedMirror = filter_var($this->request->getPost("mirror", null, ""), FILTER_SANITIZE_URL);
            $selectedFlavour = filter_var($this->request->getPost("flavour", null, ""), FILTER_SANITIZE_URL);
            $selSubscription = filter_var($this->request->getPost("subscription", null, ""), FILTER_SANITIZE_URL);

            // config data without model, prepare xml structure and write data
            if (!isset(Config::getInstance()->object()->system->firmware)) {
                Config::getInstance()->object()->system->addChild('firmware');
            }

            if (!isset(Config::getInstance()->object()->system->firmware->mirror)) {
                Config::getInstance()->object()->system->firmware->addChild('mirror');
            }

            if (empty($selSubscription)) {
                Config::getInstance()->object()->system->firmware->mirror = $selectedMirror;
            } else {
                // prepend subscription
                Config::getInstance()->object()->system->firmware->mirror = $selectedMirror . '/' . $selSubscription;
            }

            if (!isset(Config::getInstance()->object()->system->firmware->flavour)) {
                Config::getInstance()->object()->system->firmware->addChild('flavour');
            }
            Config::getInstance()->object()->system->firmware->flavour = $selectedFlavour;

            Config::getInstance()->save();

            $backend = new Backend();
            $backend->configdRun("firmware configure");
        }

        return $response;
    }
}
