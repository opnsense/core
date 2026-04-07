<?php

/*
 * Copyright (C) 2026 Konstantinos Spartalis <cspartalis@potatonetworks.com>
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

namespace OPNsense\Core;

/**
 * Class BackupController
 * @package OPNsense\Core
 */
class BackupController extends \OPNsense\Base\IndexController
{
    public function indexAction()
    {
        require_once("system.inc");
        require_once("plugins.inc");

        $backupFactory = new \OPNsense\Backup\BackupFactory();
        $this->view->providers = $backupFactory->listProviders();

        $this->view->backupLocalForm = $this->getForm("backup_local");
        $this->view->backupRemoteForm = $this->getForm("backup_remote");

        $baksz = '0B';
        if (is_dir('/conf/backup')) {
            $files = glob("/conf/backup/*.xml");
            $bytes = $files ? array_sum(array_map('filesize', $files)) : 0;
            $baksz = round($bytes / 1024 / 1024, 2) . ' MB';
        }
        $this->view->backupSize = $baksz;

        $areas = [
            'bridges'   => gettext('Bridge Devices'),
            'gifs'      => gettext('GIF Devices'),
            'interfaces' => gettext('Interfaces'),
            'laggs'     => gettext('LAGG Devices'),
            'ppps'      => gettext('Point-to-Point Devices'),
            'rrddata'   => gettext('RRD Data'),
            'vlans'     => gettext('VLAN Devices'),
            'wireless'  => gettext('Wireless Devices'),
        ];
        foreach (plugins_xmlrpc_sync() as $area) {
            if (!empty($area['section'])) {
                $areas[$area['section']] = $area['description'];
            }
        }
        natcasesort($areas);
        $this->view->areas = $areas;

        $this->view->pick('OPNsense/Core/backup');
    }

    public function historyAction($selected_host = null)
    {
        $this->view->selected_host = $selected_host;
        $this->view->pick('OPNsense/Core/backup_history');
    }
}
