<?php

/*
 * Copyright (c) 2015-2021 Franco Fichtner <franco@opnsense.org>
 * Copyright (c) 2015-2018 Deciso B.V.
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

namespace OPNsense\Core\Api;

use OPNsense\Base\ApiControllerBase;
use OPNsense\Core\Backend;
use OPNsense\Core\Config;

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
    protected function formatBytes($bytes)
    {
        if (preg_match('/[^0-9]/', $bytes)) {
            /* already processed */
            return $bytes;
        }
        if ($bytes >= (1024 * 1024 * 1024)) {
            return sprintf('%.1F%s', $bytes / (1024 * 1024 * 1024), 'GiB');
        } elseif ($bytes >= 1024 * 1024) {
            return sprintf('%.1F%s', $bytes / (1024 * 1024), 'MiB');
        } elseif ($bytes >= 1024) {
            return sprintf('%.1F%s', $bytes / 1024, 'KiB');
        } else {
            return sprintf('%d%s', $bytes, 'B');
        }
    }

    /**
     * Run check for updates
     * @return array
     */
    public function checkAction()
    {
        $this->sessionClose(); // long running action, close session
        $response = [];

        if ($this->request->isPost()) {
            $backend = new Backend();
            $response['msg_uuid'] = trim($backend->configdRun('firmware check', true));
            $response['status'] = 'ok';
        } else {
            $response['status'] = 'failure';
        }

        return $response;
    }

    /**
     * retrieve available updates
     * @return array
     */
    public function statusAction()
    {
        $active_array = [];
        $active_count = 0;
        $active_size = '';
        $active_status = '';
        $backend = new Backend();
        $target = null;

        $this->sessionClose(); // long running action, close session

        if ($this->request->isPost()) {
            $backend->configdRun('firmware check');
        }

        $response = json_decode(trim($backend->configdRun('firmware product')), true);
        if ($response != null && $response['product_check'] != null) {
            $response = $response['product_check'];

            if ($response['product_id'] != $response['product_target']) {
                $target = $response['product_target'];
            }

            $download_size = !empty($response['download_size']) ? $response['download_size'] : 0;

            $upgrade_size = 0;
            $update_size = 0;

            if (!empty($response['upgrade_packages'])) {
                foreach ($response['upgrade_packages'] as $listing) {
                    if (!empty($listing['size'])) {
                        $update_size += $listing['size'];
                    }
                }
            }

            foreach (explode(',', $download_size) as $size) {
                if (preg_match('/\s*(\d+)\s*([a-z])/i', $size, $matches)) {
                    $factor = 1;
                    switch (isset($matches[2]) ? strtolower($matches[2]) : 'b') {
                        case 'g':
                            $factor *= 1024;
                            /* FALLTROUGH */
                        case 'm':
                            $factor *= 1024;
                            /* FALLTROUGH */
                        case 'k':
                            $factor *= 1024;
                            /* FALLTROUGH */
                        default:
                            break;
                    }
                    $update_size += $factor * $matches[1];
                }
            }

            $sorted = [];

            foreach (
                array('new_packages', 'reinstall_packages', 'upgrade_packages',
                'downgrade_packages', 'remove_packages') as $pkg_type
            ) {
                if (isset($response[$pkg_type])) {
                    foreach ($response[$pkg_type] as $value) {
                        switch ($pkg_type) {
                            case 'downgrade_packages':
                                $sorted[$value['name']] = array(
                                    'reason' => gettext('downgrade'),
                                    'old' => $value['current_version'],
                                    'new' => $value['new_version'],
                                    'name' => $value['name'],
                                    'repository' => $value['repository'],
                                );
                                break;
                            case 'new_packages':
                                $sorted[$value['name']] = array(
                                    'new' => $value['version'],
                                    'reason' => gettext('new'),
                                    'name' => $value['name'],
                                    'repository' => $value['repository'],
                                    'old' => gettext('N/A'),
                                );
                                break;
                            case 'reinstall_packages':
                                $sorted[$value['name']] = array(
                                    'reason' => gettext('reinstall'),
                                    'new' => $value['version'],
                                    'old' => $value['version'],
                                    'repository' => $value['repository'],
                                    'name' => $value['name'],
                                );
                                break;
                            case 'remove_packages':
                                $sorted[$value['name']] = array(
                                    'reason' => gettext('obsolete'),
                                    'new' => gettext('N/A'),
                                    'old' => $value['version'],
                                    'name' => $value['name'],
                                    'repository' => $value['repository'],
                                );
                                break;
                            case 'upgrade_packages':
                                $sorted[$value['name']] = array(
                                    'reason' => gettext('upgrade'),
                                    'old' => empty($value['current_version']) ?
                                        gettext('N/A') : $value['current_version'],
                                    'new' => $value['new_version'],
                                    'repository' => $value['repository'],
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

            $active_count = count($response['all_packages']);
            $active_array = &$response['all_packages'];
            $active_size = $update_size;
            $active_status = 'update';

            $sorted = [];

            if (isset($response['upgrade_sets'])) {
                foreach ($response['upgrade_sets'] as $value) {
                    if (!empty($value['size'])) {
                        $upgrade_size += $value['size'];
                    }
                    $sorted[$value['name']] = array(
                        'reason' => gettext('upgrade'),
                        'old' => empty($value['current_version']) ?
                            gettext('N/A') : $value['current_version'],
                        'new' => $value['new_version'],
                        'repository' => $value['repository'],
                        'name' => $value['name'],
                    );
                }
            }

            uksort($sorted, function ($a, $b) {
                return strnatcasecmp($a, $b);
            });

            $response['all_sets'] = $sorted;

            if ($active_count == 0) {
                $active_count = count($response['all_sets']);
                $active_array = &$response['all_sets'];
                $active_size = $upgrade_size;
                $active_status = 'upgrade';
            }

            if (array_key_exists('connection', $response) && $response['connection'] == 'unresolved') {
                $response['status_msg'] = gettext('No address record found for the selected mirror.');
                $response['status'] = 'error';
            } elseif (array_key_exists('connection', $response) && $response['connection'] == 'unauthenticated') {
                $response['status_msg'] = gettext('Could not authenticate the selected mirror.');
                $response['status'] = 'error';
            } elseif (array_key_exists('connection', $response) && $response['connection'] == 'misconfigured') {
                $response['status_msg'] = gettext('The current package configuration is invalid.');
                $response['status'] = 'error';
            } elseif (array_key_exists('connection', $response) && $response['connection'] != 'ok') {
                $response['status_msg'] = gettext('An error occurred while connecting to the selected mirror.');
                $response['status'] = 'error';
            } elseif (array_key_exists('repository', $response) && $response['repository'] == 'untrusted') {
                $response['status_msg'] = gettext('Could not verify the repository fingerprint.');
                $response['status'] = 'error';
            } elseif (array_key_exists('repository', $response) && $response['repository'] == 'revoked') {
                $response['status_msg'] = gettext('The repository fingerprint has been revoked.');
                $response['status'] = 'error';
            } elseif (array_key_exists('repository', $response) && $response['repository'] == 'unsigned') {
                $response['status_msg'] = gettext('The repository has no fingerprint.');
                $response['status'] = 'error';
            } elseif (array_key_exists('repository', $response) && $response['repository'] == 'incomplete' && !empty($target)) {
                $response['status_msg'] = sprintf(gettext('The release type "%s" is not available on this repository.'), $target);
                $response['status'] = 'error';
            } elseif (array_key_exists('repository', $response) && $response['repository'] != 'ok') {
                $response['status_msg'] = gettext('Could not find the repository on the selected mirror.');
                $response['status'] = 'error';
            } elseif ($active_count) {
                if ($active_count == 1) {
                    /* keep this dynamic for template translation even though %s is always '1' */
                    $response['status_msg'] = sprintf(
                        gettext('There is %s update available, total download size is %s.'),
                        $active_count,
                        $this->formatBytes($active_size)
                    );
                } else {
                    $response['status_msg'] = sprintf(
                        gettext('There are %s updates available, total download size is %s.'),
                        $active_count,
                        $this->formatBytes($active_size)
                    );
                }
                if ($response['upgrade_needs_reboot'] == 1) {
                    $response['status_msg'] = sprintf(
                        '%s %s',
                        $response['status_msg'],
                        gettext('This update requires a reboot.')
                    );
                }
                $response['status'] = $active_status;
            } elseif (!$active_count) {
                $response['status_msg'] = gettext('There are no updates available on the selected mirror.');
                $response['status'] = 'none';
            } else {
                $response['status_msg'] = gettext('Unknown firmware status encountered.');
                $response['status'] = 'error';
            }
        } else {
            $response = array(
                'status_msg' => gettext('Firmware status check was aborted internally. Please try again.'),
                'status' => 'error',
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
        $response = ['status' => 'failure'];

        if (!$this->request->isPost()) {
            return $response;
        }

        $this->sessionClose(); // long running action, close session

        $filter = new \Phalcon\Filter();
        $filter->add('version', function ($value) {
            return preg_replace('/[^0-9a-zA-Z\.]/', '', $value);
        });
        $version = $filter->sanitize($version, 'version');

        $backend = new Backend();
        $html = trim($backend->configdRun(sprintf('firmware changelog html %s', $version)));
        if (!empty($html)) {
            $response['status'] = 'ok';
            $response['html'] = $html;
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
                return preg_replace('/[^0-9a-zA-Z._-]/', '', $value);
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
     * perform (stable) update
     * @return array status
     * @throws \Exception
     */
    public function updateAction()
    {
        $backend = new Backend();
        $response = array();
        if ($this->request->isPost()) {
            $response['msg_uuid'] = trim($backend->configdRun('firmware update', true));
            $response['status'] = 'ok';
        } else {
            $response['status'] = 'failure';
        }

        return $response;
    }

    /**
     * perform (major) upgrade
     * @return array status
     * @throws \Exception
     */
    public function upgradeAction()
    {
        $backend = new Backend();
        $response = array();
        if ($this->request->isPost()) {
            $response['msg_uuid'] = trim($backend->configdRun('firmware upgrade', true));
            $response['status'] = 'ok';
        } else {
            $response['status'] = 'failure';
        }

        return $response;
    }

    /**
     * run a connection check
     * @return array status
     * @throws \Exception
     */
    public function connectionAction()
    {
        $this->sessionClose(); // long running action, close session
        $backend = new Backend();
        $response = array();

        if ($this->request->isPost()) {
            $response['status'] = 'ok';
            $response['msg_uuid'] = trim($backend->configdRun("firmware connection", true));
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
                return preg_replace('/[^0-9a-zA-Z._-]/', '', $value);
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
     * install missing configured plugins
     * @return array status
     * @throws \Exception
     */
    public function syncPluginsAction()
    {
        $this->sessionClose(); // long running action, close session
        $backend = new Backend();
        $response = array();

        if ($this->request->isPost()) {
            $response['status'] = strtolower(trim($backend->configdRun('firmware sync')));
        } else {
            $response['status'] = 'failure';
        }

        return $response;
    }

    /**
     * reset missing configured plugins
     * @return array status
     * @throws \Exception
     */
    public function resyncPluginsAction()
    {
        $this->sessionClose(); // long running action, close session
        $backend = new Backend();
        $response = array();

        if ($this->request->isPost()) {
            $response['status'] = strtolower(trim($backend->configdRun('firmware resync')));
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
                return preg_replace('/[^0-9a-zA-Z._-]/', '', $value);
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
                return preg_replace('/[^0-9a-zA-Z._-]/', '', $value);
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
                return preg_replace('/[^0-9a-zA-Z._-]/', '', $value);
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
                return preg_replace('/[^0-9a-zA-Z._-]/', '', $value);
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
     * @throws \Exception
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
                return preg_replace('/[^0-9a-zA-Z._-]/', '', $value);
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

        $config = Config::getInstance()->object();
        $configPlugins = array();
        if (isset($config->system->firmware->plugins)) {
            $configPlugins = explode(",", $config->system->firmware->plugins);
        }

        $keys = array('name', 'version', 'comment', 'flatsize', 'locked', 'license', 'repository', 'origin');
        $backend = new Backend();
        $response = array();

        $version = explode(' ', trim(shell_exec('opnsense-version -nv')));
        foreach (array('product_id' => 0, 'product_version' => 1) as $result => $index) {
            $response[$result] = !empty($version[$index]) ? $version[$index] : 'unknown';
        }

        /* allows us to select UI features based on product state */
        $devel = explode('-', $response['product_id']);
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
                    if (empty($translated[$key])) {
                        $translated[$key] = gettext('N/A');
                    } elseif ($key == 'flatsize') {
                        $translated[$key] = $this->formatBytes($translated[$key]);
                    }
                }

                /* mark remote packages as "provided", local as "installed" */
                $translated['provided'] = $type == 'remote' ? '1' : '0';
                $translated['installed'] = $type == 'local' ? '1' : '0';
                if (isset($packages[$translated['name']])) {
                    /* local iteration, mark package provided */
                    $translated['provided'] = '1';
                }
                $translated['path'] = "{$translated['repository']}/{$translated['origin']}";
                $translated['configured'] = in_array($translated['name'], $configPlugins) ? '1' : '0';
                $packages[$translated['name']] = $translated;

                /* figure out local and remote plugins */
                $plugin = explode('-', $translated['name']);
                if (count($plugin)) {
                    if ($plugin[0] == 'os') {
                        if (
                            $type == 'local' || ($type == 'remote' &&
                            ($devel || end($plugin) != 'devel'))
                        ) {
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

        foreach ($configPlugins as $missing) {
            if (!array_key_exists($missing, $plugins)) {
                $plugins[$missing] = [];
                foreach ($keys as $key) {
                    $plugins[$missing][$key] = gettext('N/A');
                }
                $plugins[$missing]['path'] = gettext('N/A');
                $plugins[$missing]['configured'] = '1';
                $plugins[$missing]['installed'] = '0';
                $plugins[$missing]['provided'] = '0';
                $plugins[$missing]['name'] = $missing;
            }
        }

        uasort($plugins, function ($a, $b) {
            return strnatcasecmp(
                ($a['configured'] && !$a['installed'] ? '0' : '1') . ($a['installed'] ? '0' : '1') . $a['name'],
                ($b['configured'] && !$b['installed'] ? '0' : '1') . ($b['installed'] ? '0' : '1') . $b['name']
            );
        });

        $response['plugin'] = array();
        foreach ($plugins as $plugin) {
            $response['plugin'][] = $plugin;
        }

        /* also pull in changelogs from here */
        $changelogs = json_decode(trim($backend->configdRun('firmware changelog list')), true);
        if ($changelogs == null) {
            $changelogs = [];
        } else {
            /* development strategy for changelog slightly differs from above */
            $devel = preg_match('/^\d+\.\d+\.[a-z]/i', $response['product_version']) ? true : false;

            foreach ($changelogs as $index => &$changelog) {
                /* skip development items */
                if (!$devel && preg_match('/^\d+\.\d+\.[a-z]/i', $changelog['version'])) {
                    unset($changelogs[$index]);
                    continue;
                }

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

        $product = json_decode(trim($backend->configdRun('firmware product')), true);
        if ($product == null) {
            $product = [];
        }

        $response['product'] = $product;

        return $response;
    }

    /**
     * list firmware mirror and flavour options
     * @return array
     */
    public function getFirmwareOptionsAction()
    {
        $families = [];
        $families_has_subscription = [];
        $flavours = [];
        $flavours_allow_custom = false;
        $flavours_has_subscription = [];
        $mirrors = [];
        $mirrors_allow_custom = false;
        $mirrors_has_subscription = [];

        $this->sessionClose(); // long running action, close session

        foreach (glob(__DIR__ . "/repositories/*.xml") as $xml) {
            $repositoryXml = simplexml_load_file($xml);
            if ($repositoryXml === false || $repositoryXml->getName() != 'firmware') {
                syslog(LOG_ERR, 'unable to parse firmware file ' . $xml);
            } else {
                if (isset($repositoryXml->mirrors->mirror)) {
                    if (isset($repositoryXml->mirrors->attributes()->allow_custom)) {
                        $mirrors_allow_custom = (strtolower($repositoryXml->mirrors->attributes()->allow_custom) == "true");
                    }
                    foreach ($repositoryXml->mirrors->mirror as $mirror) {
                        $mirrors[(string)$mirror->url] = (string)$mirror->description;
                        $attr = $mirror->attributes();
                        if (isset($attr->has_subscription) && strtolower($attr->has_subscription) == "true") {
                            $mirrors_has_subscription[] = (string)$mirror->url;
                        }
                    }
                }
                if (isset($repositoryXml->flavours->flavour)) {
                    if (isset($repositoryXml->flavours->attributes()->allow_custom)) {
                        $flavours_allow_custom = (strtolower($repositoryXml->flavours->attributes()->allow_custom) == "true");
                    }
                    foreach ($repositoryXml->flavours->flavour as $flavour) {
                        $flavours[(string)$flavour->name] = (string)$flavour->description;
                        $attr = $flavour->attributes();
                        if (isset($attr->has_subscription) && strtolower($attr->has_subscription) == "true") {
                            $flavours_has_subscription[] = (string)$flavour->name;
                        }
                    }
                }
                if (isset($repositoryXml->families->family)) {
                    foreach ($repositoryXml->families->family as $family) {
                        $families[(string)$family->name] = (string)$family->description;
                        $attr = $family->attributes();
                        if (isset($attr->has_subscription) && strtolower($attr->has_subscription) == "true") {
                            $families_has_subscription[] = (string)$family->name;
                        }
                    }
                }
            }
        }
        return [
            /* provide a full set of data even though the frontend does not use it */
            'families' => $families,
            'families_allow_custom' => 0,
            'families_has_subscription' => $families_has_subscription,
            'flavours' => $flavours,
            'flavours_allow_custom' => $flavours_allow_custom,
            'flavours_has_subscription' => $flavours_has_subscription,
            'mirrors' => $mirrors,
            'mirrors_allow_custom' => $mirrors_allow_custom,
            'mirrors_has_subscription' => $mirrors_has_subscription,
        ];
    }

    /**
     * Validate firmware options
     * @param $selectedMirror selected mirror url
     * @param $selectedFlavour selected flavour
     * @param $selectedType selected family type
     * @param $selSubscription selected subscription id
     * @return array with validation failure messages
     */
    private function validateFirmwareOptions($selectedMirror, $selectedFlavour, $selectedType, $selSubscription)
    {
        $validOptions = $this->getFirmwareOptionsAction();
        $invalid_msgs = [];

        if (!$validOptions['mirrors_allow_custom'] && !isset($validOptions['mirrors'][$selectedMirror])) {
            $invalid_msgs[] = sprintf(gettext('Unable to set invalid firmware mirror: %s'), $selectedMirror);
        }

        if (!$validOptions['flavours_allow_custom'] && !isset($validOptions['flavours'][$selectedFlavour])) {
            $invalid_msgs[] = sprintf(gettext('Unable to set invalid firmware flavour: %s'), $selectedFlavour);
        }

        if (!isset($validOptions['families'][$selectedType])) {
            $invalid_msgs[] = sprintf(gettext('Unable to set invalid firmware release type: %s'), $validOptions['families'][$selectedType]);
        }

        if (in_array($selectedMirror, $validOptions['mirrors_has_subscription'])) {
            if (!preg_match('/[a-z0-9]{8}(-[a-z0-9]{4}){3}-[a-z0-9]{12}/i', $selSubscription)) {
                $invalid_msgs[] = gettext('A valid subscription is required for this firmware mirror.');
            }
            if (!in_array($selectedFlavour, $validOptions['flavours_has_subscription'])) {
                $invalid_msgs[] = sprintf(gettext('Subscription requires the following flavour: %s'), $validOptions['flavours'][$validOptions['flavours_has_subscription'][0]]);
            }
            if (!in_array($selectedType, $validOptions['families_has_subscription'])) {
                $invalid_msgs[] = sprintf(gettext('Subscription requires the following type: %s'), $validOptions['families'][$validOptions['families_has_subscription'][0]]);
            }
        } else {
            if (!empty($selSubscription)) {
                $invalid_msgs[] = gettext('Subscription cannot be set for non-subscription firmware mirror.');
            }
        }

        return $invalid_msgs;
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
        $response = ['status' => 'failure'];

        if ($this->request->isPost()) {
            $selectedMirror = filter_var($this->request->getPost("mirror", null, ""), FILTER_SANITIZE_URL);
            $selectedFlavour = filter_var($this->request->getPost("flavour", null, ""), FILTER_SANITIZE_URL);
            $selectedType = filter_var($this->request->getPost("type", null, ""), FILTER_SANITIZE_URL);
            $selSubscription = filter_var($this->request->getPost("subscription", null, ""), FILTER_SANITIZE_URL);

            $ret = $this->validateFirmwareOptions($selectedMirror, $selectedFlavour, $selectedType, $selSubscription);
            if (count($ret)) {
                $response['status_msgs'] = $ret;
            } else {
                $config = Config::getInstance()->object();
                $response['status'] = 'ok';

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

                $this->sessionClose(); // long running action, close session

                $backend = new Backend();
                $backend->configdRun("firmware configure");
            }
        }

        return $response;
    }
}
