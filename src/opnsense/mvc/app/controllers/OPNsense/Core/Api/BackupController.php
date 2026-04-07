<?php

/*
 * Copyright (C) 2026 Konstantinos Spartalis <cspartalis@potatonetworks.com>
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

namespace OPNsense\Core\Api;

use OPNsense\Base\ApiControllerBase;
use OPNsense\Base\UserException;
use OPNsense\Core\ACL;
use OPNsense\Core\Backend;
use OPNsense\Core\Config;
use OPNsense\Core\Shell;
use OPNsense\Backup\Local;

/**
 * Class BackupController
 * @package OPNsense\Core\Api
 */
class BackupController extends ApiControllerBase
{
    /**
     * when the user-config-readonly privilege is set, raise an error
     */
    private function throwReadOnly()
    {
        if ((new ACL())->hasPrivilege($this->getUserName(), 'user-config-readonly')) {
            throw new UserException(
                sprintf("User %s denied for write access (user-config-readonly set)", $this->getUserName())
            );
        }
    }

    /**
     * return available providers and their backup locations
     * @return array
     */
    private function providers()
    {
        $result = [];
        $result['this'] = ['description' => gettext('This Firewall'), 'dirname' => '/conf/backup'];
        if (class_exists('\Deciso\OPNcentral\Central')) {
            $central = new \Deciso\OPNcentral\Central();
            $central->setUserScope($this->getUserName());
            $ctrHosts = [];
            foreach ($central->hosts->host->getNodes() as $itemid => $item) {
                $ctrHosts[$itemid] = ['description' => $item['description']];
            }
            foreach (glob('/conf/remote.backups/*') as $filename) {
                $dirname = basename($filename);
                if (isset($ctrHosts[$dirname])) {
                    $result[$dirname] = $ctrHosts[$dirname];
                    $result[$dirname]['dirname'] = $filename;
                }
            }
        }
        return $result;
    }

    /**
     * list available providers
     * @return array
     */
    public function providersAction()
    {
        return ['items' => $this->providers()];
    }

    /**
     * list available backups for selected host
     */
    public function backupsAction($host)
    {
        $result = ['items' => []];
        $providers = $this->providers();
        if (!empty($providers[$host])) {
            foreach (glob($providers[$host]['dirname'] . "/config-*.xml") as $filename) {
                $xmlNode = @simplexml_load_file($filename, "SimpleXMLElement", LIBXML_NOERROR | LIBXML_ERR_NONE);
                if (isset($xmlNode->revision)) {
                    $cfg_item = [
                        'time' => (string)$xmlNode->revision->time,
                        'time_iso' => date('c', (int)$xmlNode->revision->time),
                        'description' => (string)$xmlNode->revision->description,
                        'username' => (string)$xmlNode->revision->username,
                        'filesize' => filesize($filename),
                        'id' => basename($filename)
                    ];
                    $result['items'][] = $cfg_item;
                }
            }
            // sort newest first
            usort($result['items'], function ($item1, $item2) {
                return ($item1['time'] < $item2['time']) ? 1 : -1;
            });
        }
        return $result;
    }

    /**
     * diff two backups for selected host
     */
    public function diffAction($host, $backup1, $backup2)
    {
        $result = ['items' => []];
        $providers = $this->providers();
        if (!empty($providers[$host])) {
            $bckfilename1 = null;
            $bckfilename2 = null;
            foreach (glob($providers[$host]['dirname'] . "/config-*.xml") as $filename) {
                $bckfilename = basename($filename);
                if ($backup1 == $bckfilename) {
                    $bckfilename1 = $filename;
                } elseif ($backup2 == $bckfilename) {
                    $bckfilename2 = $filename;
                }
            }
            if (!empty($bckfilename1) && !empty($bckfilename2)) {
                $diff = Shell::shell_safe('/usr/bin/diff -u %s %s', [$bckfilename2, $bckfilename1], true);
                if (!empty($diff)) {
                    foreach ($diff as $line) {
                        $result['items'][] = htmlspecialchars($line, ENT_QUOTES | ENT_HTML401);
                    }
                }
            }
        }
        return $result;
    }

    /**
     * delete local backup
     */
    public function deleteBackupAction($backup)
    {
        $this->throwReadOnly();
        if (!$this->request->isPost()) {
            return ['status' => 'failed'];
        }
        foreach (glob("/conf/backup/config-*.xml") as $filename) {
            $bckfilename = basename($filename);
            if ($backup === $bckfilename) {
                @unlink($filename);
                return ['status' => 'deleted'];
            }
        }
        return ['status' => 'not_found'];
    }

    /**
     * revert to local backup from history
     */
    public function revertBackupAction($backup)
    {
        $this->throwReadOnly();
        if (!$this->request->isPost()) {
            return ['status' => 'failed'];
        }
        foreach (glob("/conf/backup/config-*.xml") as $filename) {
            $bckfilename = basename($filename);
            if ($backup === $bckfilename) {
                $cnf = Config::getInstance();
                $cnf->restoreBackup($filename);
                $cnf->save();
                return ['status' => 'reverted'];
            }
        }
        return ['status' => 'not_found'];
    }

    /**
     * download specified backup, when left empty the latest is offered
     */
    public function downloadAction($host, $backup = null)
    {
        $providers = $this->providers();
        if (!empty($providers[$host])) {
            foreach (array_reverse(glob($providers[$host]['dirname'] . "/config-*.xml")) as $filename) {
                if (empty($backup) || $backup == basename($filename)) {
                    $payload = @simplexml_load_file($filename);
                    $hostname = '';
                    if ($payload !== false && isset($payload->system) && isset($payload->system->hostname)) {
                        $hostname = $payload->system->hostname . "." . $payload->system->domain;
                    }
                    $target_filename = urlencode('config-' . $hostname . '-' . explode('/config-', $filename, 2)[1]);
                    $this->response->setContentType('application/octet-stream');
                    $this->response->setRawHeader("Content-Disposition: attachment; filename=" . $target_filename);
                    $this->response->setRawHeader("Content-length: " . filesize($filename));
                    $this->response->setRawHeader("Pragma: no-cache");
                    $this->response->setRawHeader("Expires: 0");
                    $this->response->setContent(fopen($filename, 'r'), true);
                    break;
                }
            }
        }
    }

    public function getSettingsAction()
    {
        $config = Config::getInstance()->object();

        return [
            'backup' => [
                'pushtime' => (string)($config->system->backuppushtime ?? '02:00'),
                'backupcount' => (string)($config->system->backupcount ?? '15'),
            ]
        ];
    }

    public function setSettingsAction()
    {
        $result = ['status' => 'failed'];
        if ($this->request->isPost()) {
            $post = $this->request->getPost('backup');

            $mdlBackup = new \OPNsense\Core\Backup();

            if (isset($post['pushtime'])) {
                $mdlBackup->backuppushtime = trim($post['pushtime']);
            }
            if (isset($post['backupcount'])) {
                $mdlBackup->backupcount = trim($post['backupcount']) === '' ? null : trim($post['backupcount']);
            }

            $valMsgs = $mdlBackup->performValidation();
            if (count($valMsgs) > 0) {
                $validations = [];
                foreach ($valMsgs as $msg) {
                    $field = $msg->getField();
                    if ($field === 'backuppushtime') {
                        $validations['backup.pushtime'] = $msg->getMessage();
                    } else {
                        $validations['backup.' . $field] = $msg->getMessage();
                    }
                }
                return ['status' => 'failed', 'validations' => $validations];
            }

            $config = Config::getInstance()->object();
            $configChanged = false;
            $logMessages = [];

            if (isset($post['backupcount'])) {
                $count = trim($post['backupcount']);
                if ($count === '') {
                    if (isset($config->system->backupcount)) {
                        unset($config->system->backupcount);
                        $configChanged = true;
                        $logMessages[] = 'Removed local backup count limits';
                    }
                } else {
                    if (!isset($config->system->backupcount) || (string)$config->system->backupcount !== $count) {
                        $config->system->backupcount = $count;
                        $configChanged = true;
                        $logMessages[] = "Changed local backup count to {$count}";
                    }
                }
            }

            if (isset($post['pushtime'])) {
                $pushtime = trim($post['pushtime']);
                if ($pushtime === '') {
                    if (isset($config->system->backuppushtime)) {
                        unset($config->system->backuppushtime);
                        $configChanged = true;
                        $logMessages[] = 'Removed remote backup push time';
                    }
                } else {
                    if (!isset($config->system->backuppushtime) || (string)$config->system->backuppushtime !== $pushtime) {
                        $config->system->backuppushtime = $pushtime;
                        $configChanged = true;
                        $logMessages[] = "Changed remote backup push time to {$pushtime}";
                    }
                }
            }

            if ($configChanged) {
                Config::getInstance()->save(implode(', ', $logMessages));
            }

            // CRON restart
            if (isset($post['pushtime'])) {
                require_once("config.inc");
                require_once("util.inc");
                require_once("system.inc");
                require_once("plugins.inc");

                global $config;
                $config = \parse_config(true);
                \system_cron_configure();
            }

            $result = ['status' => 'success'];
        }
        return $result;
    }

    public function downloadConfigAction()
    {
        if ($this->request->isPost()) {
            require_once("util.inc");
            require_once("rrd.inc");
            $config = Config::getInstance()->object();
            $hostname = "OPNsense";
            if (isset($config->system->hostname)) {
                $hostname = (string)$config->system->hostname . "." . (string)$config->system->domain;
            }
            $name = "config-" . $hostname . "-" . date("YmdHis") . ".xml";
            $data = file_get_contents('/conf/config.xml');

            if (empty($this->request->getPost('donotbackuprrd'))) {
                $rrd_data_xml = \rrd_export();
                $data = str_replace("</opnsense>", $rrd_data_xml . "</opnsense>", $data);
            }

            if (!empty($this->request->getPost('encrypt'))) {
                $password = $this->request->getPost('encrypt_password');
                $crypter = new Local();
                $data = $crypter->encrypt($data, $password);
            }

            $size = strlen($data);
            $this->response->setContentType('application/octet-stream');
            $this->response->setRawHeader("Content-Disposition: attachment; filename={$name}");
            $this->response->setRawHeader("Content-Length: $size");
            $this->response->setRawHeader("Pragma: private");
            $this->response->setRawHeader("Cache-Control: private, must-revalidate");
            $this->response->setContent($data);
            return null;
        }
        return ['status' => 'failed'];
    }

    public function restoreAction()
    {
        if ($this->request->isPost() && isset($_FILES['conffile']) && is_uploaded_file($_FILES['conffile']['tmp_name'])) {
            require_once("config.inc");
            global $config;
            $config = \parse_config();
            require_once("util.inc");
            require_once("interfaces.inc");
            require_once("rrd.inc");
            require_once("filter.inc");
            require_once("system.inc");
            require_once("console.inc");
            require_once("auth.inc");

            if ((new \OPNsense\Core\ACL())->hasPrivilege($this->getUserName(), 'user-config-readonly')) {
                return ['status' => 'failed', 'message' => gettext('You do not have sufficient privileges to restore the configuration.')];
            }

            $data = file_get_contents($_FILES['conffile']['tmp_name']);

            if (empty($data)) {
                return ['status' => 'failed', 'message' => sprintf(gettext("Warning, could not read file %s"), $_FILES['conffile']['name'])];
            }

            if (!empty($this->request->getPost('decrypt'))) {
                $password = $this->request->getPost('decrypt_password');
                $crypter = new Local();
                $data = $crypter->decrypt($data, $password);
                if (empty($data)) {
                    return ['status' => 'failed', 'message' => gettext('The uploaded file could not be decrypted.')];
                }
            }

            $post = $this->request->getPost();
            $restoreareas = !empty($post['restorearea']) ? $post['restorearea'] : [];
            $do_reboot = !empty($post['rebootafterrestore']);

            if (!empty($restoreareas)) {
                $ret = $this->restore_config_section($restoreareas, $data);
                if ($ret === false) {
                    return ['status' => 'failed', 'message' => gettext('The selected config file could not be parsed.')];
                } elseif (count($ret)) {
                    return ['status' => 'failed', 'message' => gettext('At least one requested restore area could not be found.')];
                } else {
                    global $config;
                    if (!empty($config['rrddata'])) {
                        \rrd_import();
                        unset($config['rrddata']);
                        Config::getInstance()->save('Restored configuration area (RRD data imported)');
                    }
                    if ($do_reboot) {
                        (new Backend())->configdRun('system reboot', true);
                    }
                    return ['status' => 'success', 'message' => gettext("The configuration area has been restored."), 'reboot' => $do_reboot];
                }
            } else {
                /* full config restore */
                global $config;
                $config = \parse_config();
                $cfieldnames = [
                    'usevirtualterminal',
                    'primaryconsole',
                    'secondaryconsole',
                    'serialspeed',
                    'serialusb',
                    'disableconsolemenu'
                ];
                $csettings = [];
                foreach ($cfieldnames as $fieldname) {
                    $csettings[$fieldname] = $config['system'][$fieldname] ?? null;
                }

                $filename = '/tmp/config_restore.xml';
                file_put_contents($filename, $data);
                $cnf = Config::getInstance();
                if ($cnf->restoreBackup($filename)) {
                    @unlink($filename);
                    $config = \parse_config();
                    $flush = false;
                    if (!empty($post['keepconsole'])) {
                        foreach ($csettings as $fieldname => $fieldcontent) {
                            if ($fieldcontent === null && isset($config[$fieldname])) {
                                unset($config[$fieldname]);
                            } else {
                                $config['system'][$fieldname] = $fieldcontent;
                            }
                        }
                        $flush = true;
                    }
                    if (!empty($config['rrddata'])) {
                        \rrd_import();
                        unset($config['rrddata']);
                        $flush = true;
                    }
                    if ($flush) {
                        Config::getInstance()->save('Restored full configuration');
                    }
                    if (!empty($post['flush_history'])) {
                        (new Backend())->configdRun('system flush config_history');
                        Config::getInstance()->save('System restore flushed local history');
                    }
                    if (\is_interface_mismatch(false)) {
                        $do_reboot = false;
                        return ['status' => 'success', 'message' => gettext("The interface configuration was restored but physical interfaces could not be matched. No automatic reboot was performed."), 'reboot' => false];
                    }
                    if ($do_reboot) {
                        (new Backend())->configdRun('system reboot', true);
                    }
                    return ['status' => 'success', 'message' => gettext("The configuration has been restored."), 'reboot' => $do_reboot];
                } else {
                    return ['status' => 'failed', 'message' => gettext("The configuration could not be restored.")];
                }
            }
        }
        return ['status' => 'failed', 'message' => 'No files uploaded'];
    }

    public function setupProviderAction($providerName)
    {
        if ($this->request->isPost()) {
            require_once("config.inc");
            require_once("util.inc");

            $backupFactory = new \OPNsense\Backup\BackupFactory();
            $provider = $backupFactory->getProvider($providerName);
            if (!$provider) {
                return ['status' => 'failed', 'message' => 'Provider not found.'];
            }

            $providerSet = array();
            $post = $this->request->getPost();

            foreach ($provider['handle']->getConfigurationFields() as $field) {
                if ($field['type'] == 'file') {
                    if (isset($_FILES[$field['name']]) && is_uploaded_file($_FILES[$field['name']]['tmp_name'])) {
                        $providerSet[$field['name']] = file_get_contents($_FILES[$field['name']]['tmp_name']);
                    } else {
                        $providerSet[$field['name']] = null;
                    }
                } else {
                    $providerSet[$field['name']] = isset($post[$field['name']]) ? $post[$field['name']] : '';
                }
            }

            $input_errors = $provider['handle']->setConfiguration($providerSet);

            if (count($input_errors) == 0) {

                require_once("system.inc");
                require_once("plugins.inc");
                global $config;
                $config = \parse_config();
                \system_cron_configure();

                if ($provider['handle']->isEnabled()) {
                    try {
                        $filesInBackup = $provider['handle']->backup();
                    } catch (\Exception $e) {
                        return ['status' => 'failed', 'message' => $e->getMessage()];
                    }
                    if (count($filesInBackup) == 0) {
                        return ['status' => 'success', 'message' => gettext('Saved settings, but remote backup returned no files.')];
                    } else {
                        $msg = gettext("Backup successful. Current file list: ") . implode(", ", $filesInBackup);
                        return ['status' => 'success', 'message' => $msg];
                    }
                }

                return ['status' => 'success', 'message' => gettext("Settings configured.")];
            } else {
                return ['status' => 'failed', 'message' => implode(", ", $input_errors)];
            }
        }
        return ['status' => 'failed', 'message' => 'Invalid request'];
    }

    private function restore_config_section($section_sets, $new_contents)
    {
        require_once("config.inc");
        global $config;

        $tmpxml = tempnam(sys_get_temp_dir(), 'opn_backup_');
        $xml = null;

        try {
            file_put_contents($tmpxml, $new_contents);
            $xml = \load_config_from_file($tmpxml);
        } catch (\Exception $e) {
            syslog(LOG_ERR, 'Backup restoration failed to parse XML: ' . $e->getMessage());
        } finally {
            @unlink($tmpxml);
        }

    if (!is_array($xml)) {
        return false;
    }

        $restored = [];
        $failed = [];

        foreach ($section_sets as $section_set) {
            $sections = explode(',', $section_set);
            $found = [];

            foreach ($sections as $section) {
                $new = &$xml;
                $path = explode('.', $section);
                $target = array_pop($path);

                foreach ($path as $node) {
                    if (!isset($new[$node])) {
                        continue 2;
                    }
                    $new = &$new[$node];
                }

                if (isset($new[$target])) {
                    $found[] = $section;
                }
            }

            if (!count($found)) {
                $failed[] = $section_set;
                continue;
            }

            foreach (array_diff($sections, $found) as $section) {
                $old = &$config;
                $path = explode('.', $section);
                $target = array_pop($path);

                foreach ($path as $node) {
                    if (!isset($old[$node])) {
                        continue 2;
                    }
                    $old = &$old[$node];
                }

                if (isset($old[$target])) {
                    unset($old[$target]);
                    $restored[] = $section;
                }
            }

            foreach ($found as $section) {
                $old = &$config;
                $new = &$xml;
                $path = explode('.', $section);
                $target = array_pop($path);

                foreach ($path as $node) {
                    if (!isset($new[$node])) {
                        continue 2;
                    }
                    $new = &$new[$node];
                    if (!isset($old[$node])) {
                        $old[$node] = [];
                    }
                    $old = &$old[$node];
                }

                if (isset($new[$target])) {
                    $old[$target] = $new[$target];
                    $restored[] = $section;
                }
            }
        }

        if (count($restored) && !count($failed)) {
            \OPNsense\Core\Config::getInstance()->save(sprintf('Restored sections (%s) of config file', join(',', $restored)));
            \convert_config();
        }

        return $failed;
    }
}
