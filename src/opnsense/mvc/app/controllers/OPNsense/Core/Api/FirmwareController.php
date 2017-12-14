<?php

/**
 *    Copyright (c) 2015-2017 Franco Fichtner <franco@opnsense.org>
 *    Copyright (c) 2015-2016 Deciso B.V.
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
     * return bytes in human-readable form
     * @param integer $bytes bytes to convert
     * @return string
     */
    protected function format_bytes($bytes)
    {
        if ($bytes >= (1024 * 1024 * 1024)) {
            return sprintf("%d GB", $bytes / (1024 * 1024 * 1024));
        } elseif ($bytes >= 1024 * 1024) {
            return sprintf("%d MB", $bytes / (1024 * 1024));
        } elseif ($bytes >= 1024) {
            return sprintf("%d KB", $bytes / 1024);
        } else {
            return sprintf("%d bytes", $bytes);
        }
    }

    /**
     * retrieve available updates
     * @return array
     */
    public function statusAction()
    {
        $config = Config::getInstance()->object();
        $type_want = 'opnsense';
        if (!empty($config->system->firmware->type)) {
            $type_want .= '-' . (string)$config->system->firmware->type;
        }

        $this->sessionClose(); // long running action, close session

        $backend = new Backend();
        $type_have = trim($backend->configdRun('firmware type name'));
        $backend->configdRun('firmware changelog fetch');

        if (!empty($type_have) && $type_have !== $type_want) {
            $type_ver = trim($backend->configdRun('firmware type version ' . escapeshellarg($type_want)));
            return array(
                'status_msg' => gettext('The release type requires an update.'),
                'all_packages' => array($type_want => array(
                    'reason' => gettext('new'),
                    'old' => gettext('N/A'),
                    'name' => $type_want,
                    'new' => $type_ver,
                )),
                'status_upgrade_action' => 'rel',
                'status' => 'ok',
            );
        }

        $response = json_decode(trim($backend->configdRun('firmware check')), true);

        if ($response != null) {
            $packages_size = !empty($response['download_size']) ? $response['download_size'] : 0;
            $sets_size = 0;

            if (!empty($response['upgrade_packages'])) {
                foreach ($response['upgrade_packages'] as $listing) {
                    if (!empty($listing['size'])) {
                        $sets_size += $listing['size'];
                    }
                }
            }

            if (preg_match('/\s*(\d+)\s*([a-z])/i', $packages_size, $matches)) {
                $factor = 1;
                switch (isset($matches[2]) ? strtolower($matches[2]) : 'b') {
                    case 'g':
                        $factor *= 1024;
                    case 'm':
                        $factor *= 1024;
                    case 'k':
                        $factor *= 1024;
                    default:
                        break;
                }
                $packages_size = $factor * $matches[1];
            } else {
                $packages_size = 0;
            }

            $download_size = $this->format_bytes($packages_size + $sets_size);

            if (array_key_exists('connection', $response) && $response['connection'] == 'error') {
                $response['status_msg'] = gettext('Connection error.');
                $response['status'] = 'error';
            } elseif (array_key_exists('repository', $response) && $response['repository'] == 'error') {
                $response['status_msg'] = gettext('Could not find the repository on the selected mirror.');
                $response['status'] = 'error';
            } elseif (array_key_exists('updates', $response) && $response['updates'] == 0) {
                $response['status_msg'] = gettext('There are no updates available on the selected mirror.');
                $response['status'] = 'none';
            } elseif (array_key_exists(0, $response['upgrade_packages']) &&
                $response['upgrade_packages'][0]['name'] == 'pkg') {
                $response['status_upgrade_action'] = 'pkg';
                $response['status'] = 'ok';
                $response['status_msg'] = gettext('There is a mandatory update for the package manager available.');
            } elseif (array_key_exists('updates', $response)) {
                $response['status_upgrade_action'] = 'all';
                $response['status'] = 'ok';
                if ($response['updates'] == 1) {
                    /* keep this dynamic for template translation even though %s is always '1' */
                    $response['status_msg'] = sprintf(
                        gettext('There is %s update available, total download size is %s.'),
                        $response['updates'],
                        $download_size
                    );
                } else {
                    $response['status_msg'] = sprintf(
                        gettext('There are %s updates available, total download size is %s.'),
                        $response['updates'],
                        $download_size
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

            $sorted = array();

            /*
             * new_packages: array with { name: <package_name>, version: <package_version> }
             * reinstall_packages: array with { name: <package_name>, version: <package_version> }
             * upgrade_packages: array with { name: <package_name>,
             *     current_version: <current_version>, new_version: <new_version> }
             * downgrade_packages: array with { name: <package_name>,
             *     current_version: <current_version>, new_version: <new_version> }
             */
            foreach (array('new_packages', 'reinstall_packages', 'upgrade_packages', 'downgrade_packages') as $pkg_type) {
                if (isset($response[$pkg_type])) {
                    foreach ($response[$pkg_type] as $value) {
                        switch ($pkg_type) {
                            case 'downgrade_packages':
                                $sorted[$value['name']] = array(
                                    'reason' => gettext('downgrade'),
                                    'old' => $value['current_version'],
                                    'new' => $value['new_version'],
                                    'name' => $value['name'],
                                );
                                break;
                            case 'new_packages':
                                $sorted[$value['name']] = array(
                                    'new' => $value['version'],
                                    'reason' => gettext('new'),
                                    'name' => $value['name'],
                                    'old' => gettext('N/A'),
                                );
                                break;
                            case 'reinstall_packages':
                                $sorted[$value['name']] = array(
                                    'reason' => gettext('reinstall'),
                                    'new' => $value['version'],
                                    'old' => $value['version'],
                                    'name' => $value['name'],
                                );
                                break;
                            case 'upgrade_packages':
                                $sorted[$value['name']] = array(
                                    'reason' => gettext('upgrade'),
                                    'old' => empty($value['current_version']) ? gettext('N/A') : $value['current_version'],
                                    'new' => $value['new_version'],
                                    'name' => $value['name'],
                                );
                                break;
                            default:
                                /* undefined */
                                break;
                        }
                    }
                }
            }

            uksort($sorted, function ($a, $b) {
                return strnatcasecmp($a, $b);
            });

            $response['all_packages'] = $sorted;
        } else {
            $response = array(
                'status_msg' => gettext('Firmware status check was aborted internally. Please try again.'),
                'status' => 'unknown'
            );
        }

        return $response;
    }

    /**
     * Retrieve specific changelog in text and html format
     * @param string $version changelog to retrieve
     * @return array correspondng changelog in both formats
     * @throws \Exception
     */
    public function changelogAction($version)
    {
        $this->sessionClose(); // long running action, close session
        $backend = new Backend();
        $response = array();

        if (!$this->request->isPost()) {
            return $response;
        }

        // sanitize package name
        $filter = new \Phalcon\Filter();
        $filter->add('version', function ($value) {
            return preg_replace('/[^0-9a-zA-Z\.]/', '', $value);
        });
        $version = $filter->sanitize($version, 'version');

        if ($version == 'update') {
            $backend->configdRun('firmware changelog fetch');
        } else {
            $text = trim($backend->configdRun(sprintf('firmware changelog text %s', $version)));
            $html = trim($backend->configdRun(sprintf('firmware changelog html %s', $version)));
            if (!empty($text)) {
                $response['text'] = $text;
            }
            if (!empty($html)) {
                $response['html'] = $html;
            }
        }

        return $response;
    }

    /**
     * Retrieve specific license for package in text format
     * @param string $package package to retrieve
     * @return array with all possible licenses
     * @throws \Exception
     */
    public function licenseAction($package)
    {
        $this->sessionClose(); // long running action, close session
        $backend = new Backend();
        $response = array();

        if ($this->request->isPost()) {
            // sanitize package name
            $filter = new \Phalcon\Filter();
            $filter->add('scrub', function ($value) {
                return preg_replace('/[^0-9a-zA-Z\-]/', '', $value);
            });
            $package = $filter->sanitize($package, 'scrub');
            $text = trim($backend->configdRun(sprintf('firmware license %s', $package)));
            if (!empty($text)) {
                $response['license'] = $text;
            }
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
        $config = Config::getInstance()->object();
        $type_want = 'opnsense';
        if (!empty($config->system->firmware->type)) {
            $type_want .= '-' . (string)$config->system->firmware->type;
        }
        $this->sessionClose(); // long running action, close session
        $backend = new Backend();
        $response = array();
        if ($this->request->hasPost('upgrade')) {
            $response['status'] = 'ok';
            if ($this->request->getPost('upgrade') == 'pkg') {
                $action = 'firmware upgrade pkg';
            } elseif ($this->request->getPost('upgrade') == 'maj') {
                $action = 'firmware upgrade maj';
            } elseif ($this->request->getPost('upgrade') == 'rel') {
                $action = 'firmware type install ' . escapeshellarg($type_want);
            } else {
                $action = 'firmware upgrade all';
            }
            $response['msg_uuid'] = trim($backend->configdRun($action, true));
        } else {
            $response['status'] = 'failure';
        }

        return $response;
    }

    /**
     * run a health check
     * @return array status
     * @throws \Exception
     */
    public function healthAction()
    {
        $this->sessionClose(); // long running action, close session
        $backend = new Backend();
        $response = array();

        if ($this->request->isPost()) {
            $response['status'] = 'ok';
            $response['msg_uuid'] = trim($backend->configdRun("firmware health", true));
        } else {
            $response['status'] = 'failure';
        }

        return $response;
    }

    /*
     * run a security audit
     * @return array status
     * @throws \Exception
     */
    public function auditAction()
    {
        $this->sessionClose(); // long running action, close session
        $backend = new Backend();
        $response = array();

        if ($this->request->isPost()) {
            $response['status'] = 'ok';
            $response['msg_uuid'] = trim($backend->configdRun("firmware audit", true));
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
        $this->sessionClose(); // long running action, close session
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
        $this->sessionClose(); // long running action, close session
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
        $this->sessionClose(); // long running action, close session
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
        $this->sessionClose(); // long running action, close session
        $backend = new Backend();
        $response = array();

        if ($this->request->isPost()) {
            $filter = new \Phalcon\Filter();
            $filter->add('pkgname', function ($value) {
                return preg_replace('/[^0-9a-zA-Z-_]/', '', $value);
            });
            $pkg_name = $filter->sanitize($pkg_name, "pkgname");
        } else {
            $pkg_name = null;
        }

        if (!empty($pkg_name)) {
            $response['msg_uuid'] = trim($backend->configdpRun("firmware lock", array($pkg_name), true));
            $response['status'] = 'ok';
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
        $this->sessionClose(); // long running action, close session
        $backend = new Backend();
        $response = array();

        if ($this->request->isPost()) {
            $filter = new \Phalcon\Filter();
            $filter->add('pkgname', function ($value) {
                return preg_replace('/[^0-9a-zA-Z-_]/', '', $value);
            });
            $pkg_name = $filter->sanitize($pkg_name, "pkgname");
        } else {
            $pkg_name = null;
        }

        if (!empty($pkg_name)) {
            $response['msg_uuid'] = trim($backend->configdpRun("firmware unlock", array($pkg_name), true));
            $response['status'] = 'ok';
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
        $this->sessionClose(); // long running action, close session
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
        $this->sessionClose(); // long running action, close session
        $backend = new Backend();
        $result = array('status' => 'running');
        $cmd_result = trim($backend->configdRun('firmware status'));

        $result['log'] = $cmd_result;

        if ($cmd_result == null) {
            $result['status'] = 'error';
        } elseif (strpos($cmd_result, '***DONE***') !== false) {
            $result['status'] = 'done';
        } elseif (strpos($cmd_result, '***REBOOT***') !== false) {
            $result['status'] = 'reboot';
        }

        return $result;
    }

    /**
     * query package details
     * @return array
     */
    public function detailsAction($package)
    {
        $this->sessionClose(); // long running action, close session
        $backend = new Backend();
        $response = array();

        if ($this->request->isPost()) {
            // sanitize package name
            $filter = new \Phalcon\Filter();
            $filter->add('scrub', function ($value) {
                return preg_replace('/[^0-9a-zA-Z\-]/', '', $value);
            });
            $package = $filter->sanitize($package, 'scrub');
            $text = trim($backend->configdRun(sprintf('firmware details %s', $package)));
            if (!empty($text)) {
                $response['details'] = $text;
            }
        }

        return $response;
    }

    /**
     * list local and remote packages
     * @return array
     */
    public function infoAction()
    {
        $this->sessionClose(); // long running action, close session

        $keys = array('name', 'version', 'comment', 'flatsize', 'locked', 'license');
        $backend = new Backend();
        $response = array();

        /* allows us to select UI features based on product state */
        $response['product_version'] = trim(file_get_contents('/usr/local/opnsense/version/opnsense'));
        $response['product_name'] = trim(file_get_contents('/usr/local/opnsense/version/opnsense.name'));

        $devel = explode('-', $response['product_name']);
        $devel = count($devel) == 2 ? $devel[1] == 'devel' : false;

        /* need both remote and local, create array earlier */
        $packages = array();
        $plugins = array();

        /* package infos are flat lists with 3 pipes as delimiter */
        foreach (array('remote', 'local') as $type) {
            $current = $backend->configdRun("firmware ${type}");
            $current = explode("\n", trim($current));

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

                /* mark remote packages as "provided", local as "installed" */
                $translated['provided'] = $type == 'remote' ? "1" : "0";
                $translated['installed'] = $type == 'local' ? "1" : "0";
                if (isset($packages[$translated['name']])) {
                    /* local iteration, mark package provided */
                    $translated['provided'] = "1";
                }
                $packages[$translated['name']] = $translated;

                /* figure out local and remote plugins */
                $plugin = explode('-', $translated['name']);
                if (count($plugin)) {
                    if ($plugin[0] == 'os') {
                        if ($type == 'local' || ($type == 'remote' &&
                            ($devel || end($plugin) != 'devel'))) {
                            $plugins[$translated['name']] = $translated;
                        }
                    }
                }
            }
        }

        uksort($packages, function ($a, $b) {
            return strnatcasecmp($a, $b);
        });

        $response['package'] = array();
        foreach ($packages as $package) {
            $response['package'][] = $package;
        }

        uksort($plugins, function ($a, $b) {
            return strnatcasecmp($a, $b);
        });

        $response['plugin'] = array();
        foreach ($plugins as $plugin) {
            $response['plugin'][] = $plugin;
        }

        /* also pull in changelogs from here */
        $changelogs = json_decode(trim($backend->configdRun('firmware changelog list')), true);
        if ($changelogs == null) {
            $changelogs = array();
        } else {
            foreach ($changelogs as &$changelog) {
                /* rewrite dates as ISO */
                $date = date_parse($changelog['date']);
                $changelog['date'] = sprintf('%04d-%02d-%02d', $date['year'], $date['month'], $date['day']);
            }
            /* sort in reverse */
            usort($changelogs, function ($a, $b) {
                return strcmp($b['date'], $a['date']);
            });
        }

        $response['changelog'] = $changelogs;

        return $response;
    }

    /**
     * list firmware mirror and flavour options
     * @return array
     */
    public function getFirmwareOptionsAction()
    {
        $this->sessionClose(); // long running action, close session

        // todo: we might want to move these into configuration files later
        $mirrors = array();
        $mirrors[''] = '(default)';
        $mirrors['https://opnsense.aivian.org'] = 'Aivian (Shaoxing, CN)';
        $mirrors['https://opnsense-update.deciso.com'] = 'Deciso (NL, Commercial)';
        $mirrors['https://mirror.dns-root.de/opnsense'] = 'dns-root.de (Cloudflare CDN)';
        $mirrors['https://opnsense.c0urier.net'] = 'c0urier.net (Lund, SE)';
        $mirrors['https://ftp.yzu.edu.tw/opnsense'] = 'Dept. of CSE, Yuan Ze University (Taoyuan City, TW)';
        $mirrors['http://mirrors.dmcnet.net/opnsense'] = 'DMC Networks (Lincoln NE, US)';
        //$mirrors['https://fleximus.org/mirror/opnsense'] = 'Fleximus (Roubaix, FR)';
        $mirrors['https://fourdots.com/mirror/OPNSense'] = 'FourDots (Belgrade, RS)';
        $mirrors['https://opnsense-mirror.hiho.ch'] = 'HiHo (Zurich, CH)';
        $mirrors['https://opnsense.ieji.de'] = 'ieji.de (Frankfurt, DE)';
        $mirrors['http://mirror.ams1.nl.leaseweb.net/opnsense'] = 'LeaseWeb (Amsterdam, NL)';
        $mirrors['http://mirror.fra10.de.leaseweb.net/opnsense'] = 'LeaseWeb (Frankfurt, DE)';
        $mirrors['http://mirror.sfo12.us.leaseweb.net/opnsense'] = 'LeaseWeb (San Francisco, US)';
        $mirrors['http://mirror.wdc1.us.leaseweb.net/opnsense'] = 'LeaseWeb (Washington, D.C., US)';
        $mirrors['http://mirrors.nycbug.org/pub/opnsense'] = 'NYC*BUG (New York, US)';
        $mirrors['http://pkg.opnsense.org'] = 'OPNsense (Amsterdam, NL)';
        $mirrors['http://mirror.ragenetwork.de/opnsense'] = 'RageNetwork (Munich, DE)';
        $mirrors['http://mirror.upb.edu.co/opnsense'] = 'Universidad Pontificia Bolivariana (Medellin, CO)';
        $mirrors['http://mirror.venturasystems.tech/opnsense'] = 'Ventura Systems (Medellin, CO)';
        $mirrors['http://mirror.wjcomms.co.uk/opnsense'] = 'WJComms (London, GB)';

        $has_subscription = array();
        $has_subscription[] = 'https://opnsense-update.deciso.com';

        $flavours = array();
        $flavours[''] = '(default)';
        $flavours['libressl'] = 'LibreSSL';
        $flavours['latest'] = 'OpenSSL';

        $families = array();
        $families[''] = gettext('Production');
        $families['devel'] = gettext('Development');

        return array(
            'has_subscription' => $has_subscription,
            'flavours' => $flavours,
            'families' => $families,
            'mirrors' => $mirrors,
        );
    }

    /**
     * retrieve current firmware configuration options
     * @return array
     */
    public function getFirmwareConfigAction()
    {
        $config = Config::getInstance()->object();
        $result = array();

        $result['flavour'] = '';
        $result['mirror'] = '';
        $result['type'] = '';

        if (!empty($config->system->firmware->flavour)) {
            $result['flavour'] = (string)$config->system->firmware->flavour;
        }

        if (!empty($config->system->firmware->type)) {
            $result['type'] = (string)$config->system->firmware->type;
        }

        if (!empty($config->system->firmware->mirror)) {
            $result['mirror'] = (string)$config->system->firmware->mirror;
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
            $config = Config::getInstance()->object();

            $response['status'] = 'ok';

            $selectedMirror = filter_var($this->request->getPost("mirror", null, ""), FILTER_SANITIZE_URL);
            $selectedFlavour = filter_var($this->request->getPost("flavour", null, ""), FILTER_SANITIZE_URL);
            $selectedType = filter_var($this->request->getPost("type", null, ""), FILTER_SANITIZE_URL);
            $selSubscription = filter_var($this->request->getPost("subscription", null, ""), FILTER_SANITIZE_URL);

            // config data without model, prepare xml structure and write data
            if (!isset($config->system->firmware)) {
                $config->system->addChild('firmware');
            }

            if (!isset($config->system->firmware->mirror)) {
                $config->system->firmware->addChild('mirror');
            }
            if (empty($selSubscription)) {
                $config->system->firmware->mirror = $selectedMirror;
            } else {
                // prepend subscription
                $config->system->firmware->mirror = $selectedMirror . '/' . $selSubscription;
            }
            if (empty($config->system->firmware->mirror)) {
                unset($config->system->firmware->mirror);
            }

            if (!isset($config->system->firmware->flavour)) {
                $config->system->firmware->addChild('flavour');
            }
            $config->system->firmware->flavour = $selectedFlavour;
            if (empty($config->system->firmware->flavour)) {
                unset($config->system->firmware->flavour);
            }

            if (!isset($config->system->firmware->type)) {
                $config->system->firmware->addChild('type');
            }
            $config->system->firmware->type = $selectedType;
            if (empty($config->system->firmware->type)) {
                unset($config->system->firmware->type);
            }

            if (!@count($config->system->firmware->children())) {
                unset($config->system->firmware);
            }

            Config::getInstance()->save();

            $backend = new Backend();
            $backend->configdRun("firmware configure");
        }

        return $response;
    }
}
