<?php

/*
 * Copyright (C) 2022 Deciso B.V.
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

namespace OPNsense\Interfaces\Api;

use OPNsense\Base\ApiMutableModelControllerBase;
use OPNsense\Core\Backend;
use OPNsense\Interfaces\FieldTypes\ArrayField;

class SettingsController extends ApiMutableModelControllerBase
{
    protected static $internalModelName = 'interface';
    protected static $internalModelClass = 'OPNsense\Interfaces\Iface';

    public function searchItemAction()
    {
        return $this->searchBase('interface', ['if', 'descr', 'ipaddr', 'subnet', 'ipaddrv6', 'subnetv6'], 'if');
    }

    public function setItemAction($uuid)
    {
        $post = $this->request->getPost('interface');
        $node = $this->getModel()->getNodeByReference('interface.'.$uuid);
        $old_if = null != $node ? (string) $node->if : null;
        $new_if = $post['if'];

        if (null == $node) {
            return [
                'result' => 'failed',
                'validations' => [
                    'interface.if' => gettext('Interface has not yet been assigned.'),
                ],
            ];
        } elseif ($old_if != $new_if) {
            return [
                'result' => 'failed',
                'validations' => [
                    'interface.if' => gettext('Interface name cannot be changed.'),
                ],
            ];
        } else {
            $result = $this->setBase('interface', 'interface', $uuid);

            if ('saved' == $result['result']) {
                $this->updateApplyList($uuid);
            }
        }

        return $result;
    }

    public function assignItemAction()
    {
        $result = $this->addBase('interface', 'interface');

        if ('saved' == $result['result']) {
            $this->updateApplyList($result['uuid']);
        }

        return $result;
    }

    public function getItemAction($uuid = null)
    {
        $result = $this->getBase('interface', 'interface', $uuid);

        $this->normalizeNode($result['interface']);

        return $result;
    }

    public function delItemAction($uuid)
    {
        $node = $this->getModel()->getNodeByReference('interface.'.$uuid);
        $old_if = null != $node ? (string) $node->if : null;

        $result = $this->delBase('interface', $uuid);
        /* store interface name for apply action */
        if ('failed' != $result['result']) {
            file_put_contents('/tmp/.interfaces.removed', "{$old_if}\n", FILE_APPEND | LOCK_EX);
        }

        return $result;
    }

    public function reconfigureAction()
    {
        $result = ['status' => 'failed'];
        if ($this->request->isPost()) {
            $result['status'] = strtolower(trim((new Backend())->configdRun('interface configure')));
        }

        return $result;
    }

    protected function getModelNodes()
    {
        $nodes = &$this->getModel()->getNodes();

        foreach ($nodes as &$iface_nodes) {
            foreach ($iface_nodes as $uuid => $node) {
                // remove loopback interfaces
                if (!empty($node['internal_dynamic'])) {
                    unset($iface_nodes[$uuid]);
                    continue;
                }

                $this->normalizeNode($iface_nodes[$uuid]);
            }
        }

        return $nodes;
    }

    // log changes for apply action
    private function updateApplyList($uuid)
    {
        $applyLogPath = '/tmp/.interfaces.apply';
        $dirtyPath = '/tmp/interfaces.dirty';

        if (file_exists($applyLogPath)) {
            $toapplylist = unserialize(file_get_contents($applyLogPath));
        } else {
            $toapplylist = [];
        }

        $if = ArrayField::decodeUUID($uuid);

        $node = $this->getModel()->getNodeByReference('interface.'.$uuid);
        $iface = $node->getNodes();
        unset($iface['if']);

        foreach ($iface as $key => $value) {
            if ('' == $value) {
                unset($iface[$key]);
            }
        }

        $toapplylist[$if]['ifcfg'] = $iface;
        $toapplylist[$if]['ifcfg']['realif'] = (string) $node->if;
        $toapplylist[$if]['ifcfg']['realifv6'] = (string) $node->if;

        file_put_contents($applyLogPath, serialize($toapplylist));

        touch($dirtyPath);
    }

    private function normalizeNode(&$node)
    {
        foreach ($node as $key => $value) {
            if (empty($value)) {
                unset($node[$key]);
            }
        }
    }
}
