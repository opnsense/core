<?php

/*
 * Copyright (C) 2024 Deciso B.V.
 * Copyright (C) 2024 Sheridan Computers Limited
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
use OPNsense\Base\UserException;

class SnapshotsController extends ApiControllerBase
{
    private array $environments = [];

    /**
     * @param string $fieldname property to search
     * @param string $value value the property should have
     * @return array|null
     */
    private function find($fieldname, $value)
    {
        if (empty($this->environments)) {
            $this->environments = json_decode(trim((new Backend())->configdRun('zfs snapshot list')), true) ?? [];
        }
        foreach ($this->environments as $record) {
            if (isset($record[$fieldname]) && $record[$fieldname] == $value) {
                return $record;
            }
        }
        return null;
    }

    /**
     * @param string $uuid generated uuid to search (calculated by name)
     * @return array|null
     */
    private function findByUuid($uuid)
    {
        return $this->find('uuid', $uuid);
    }

    /**
     * @param string $name snapshot name, the actual key of the record
     * @return array|null
     */
    private function findByName($name)
    {
        return $this->find('name', $name);
    }

    /**
     * allow all but whitespaces for now
     * @param string $name snapshot name
     */
    private function isValidName($name)
    {
        return !preg_match('/\s/', $name);
    }

    /**
     * @return boolean is this a supported feature (ZFS enabled)
     */
    public function isSupportedAction()
    {
        $result = json_decode((new Backend())->configdRun('zfs snapshot supported'), true) ?? [];
        return ['supported' => !empty($result) && $result['status'] == 'OK'];
    }

    /**
     * search snapshots
     * @return array
     */
    public function searchAction()
    {
        $records = json_decode((new Backend())->configdRun('zfs snapshot list'), true) ?? [];
        return $this->searchRecordsetBase($records);
    }

    /**
     * fetch an environment by uuid, return new when not found or $uuid equals null
     * @param string $uuid
     * @return array
     */
    public function getAction($uuid = null)
    {
        if (!empty($uuid)) {
            $result = $this->findByUuid($uuid);
            if (!empty($result)) {
                return $result;
            }
        }
        // new or not found
        return ['name' => date('YmdHis'), 'uuid' => ''];
    }

    /**
     * create a new snapshot
     * @param string $uuid uuid to save
     * @return array status
     */
    public function setAction($uuid)
    {
        if ($this->request->isPost() && $this->request->hasPost('name')) {
            $name =  $this->request->getPost('name', 'string', null);

            $be = $this->findByUuid($uuid);
            $new_be = $this->findByName($name);

            if (!empty($be) && $be['name'] == $name) {
                /* skip, unchanged */
                return ['status' => 'ok'];
            } elseif (!empty($be) && empty($new_be) && $this->isValidName($name)) {
                return json_decode(
                    (new Backend())->configdpRun("zfs snapshot rename", [$be['name'], $name]),
                    true
                );
            } else {
                if (!empty($new_be)) {
                    $msg = gettext('A snapshot already exists by this name');
                } elseif (!$this->isValidName($name)) {
                    $msg = gettext('Invalid name specified');
                } else {
                    $msg = gettext('Snapshot not found');
                }
                return [
                    'status' => 'failed',
                    'validations' => [
                        'name' => $msg
                    ]
                ];
            }
        }

        return ['status' => 'failed'];
    }

    /**
     * add or clone a snapshot
     * @return array status
     */
    public function addAction()
    {
        if ($this->request->isPost()) {
            $uuid =  $this->request->getPost('uuid', 'string', '');
            $name =  $this->request->getPost('name', 'string', '');

            $msg = null;
            if ($this->findByName($name)) {
                $msg = gettext('A snapshot already exists by this name');
            } elseif (!$this->isValidName($name)) {
                $msg = gettext('Invalid name specified');
            }
            if (!empty($uuid) && empty($msg)) {
                /* clone environment */
                $be = $this->findByUuid($uuid);
                if (empty($be)) {
                    $msg = gettext('Snapshot not found');
                } else {
                    return json_decode(
                        (new Backend())->configdpRun('zfs snapshot clone', [$name, $be['name']]),
                        true
                    );
                }
            } elseif (empty($msg)) {
                return (new Backend())->configdpRun("zfs snapshot create", [$name]);
            }

            if ($msg) {
                return [
                    'status' => 'failed',
                    'validations' => [
                        'name' => $msg
                    ]
                ];
            }
        }
        return ['status' => 'failed'];
    }

    /**
     * delete an environment by uuid
     * @param string $uuid
     * @return array
     * @throws UserException when not found (or possible)
     */
    public function delAction($uuid)
    {
        if ($this->request->isPost()) {
            $be = $this->findByUuid($uuid);
            if (empty($be)) {
                throw new UserException(gettext("Snapshot not found"), gettext("Snapshots"));
            }
            if ($be['active'] != '-') {
                throw new UserException(gettext("Cannot delete active snapshot"), gettext("Snapshots"));
            }
            return (json_decode((new Backend())->configdpRun("zfs snapshot destroy", [$be['name']]), true));
        }
        return ['status' => 'failed'];
    }
    /**
     * activate a snapshot by uuid
     * @param string $uuid
     * @return array
     * @throws UserException when not found (or possible)
     */
    public function activateAction($uuid)
    {
        if ($this->request->isPost()) {
            $be = $this->findByUuid($uuid);
            if (empty($be)) {
                throw new UserException(gettext("Snapshot not found"), gettext("Snapshots"));
            }
            return json_decode((new Backend())->configdpRun("zfs snapshot activate", [$be['name']]), true);
        }
        return ['status' => 'failed'];
    }
}
