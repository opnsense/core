<?php

/*
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
use OPNsense\Core\ACL;
use OPNsense\Core\Backend;
use OPNsense\Core\Config;

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
                $diff = [];
                exec("/usr/bin/diff -u " . escapeshellarg($bckfilename2) . " " . escapeshellarg($bckfilename1), $diff);
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
}
