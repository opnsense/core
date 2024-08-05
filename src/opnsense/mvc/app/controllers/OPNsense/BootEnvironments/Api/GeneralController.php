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
namespace OPNsense\BootEnvironments\Api;

use OPNsense\Base\ApiControllerBase;
use OPNsense\Core\Backend;
use OPNsense\Core\Config;
use OPNsense\BootEnvironments\BootEnvironments;


class GeneralController extends ApiControllerBase
{
    private function findByUuid($uuid)
    {
        $data = json_decode(trim((new Backend())->configdRun('bootenvironments list')), true) ?? [];
        foreach ($data as $record) {
            if ($record['uuid'] == $uuid) {
                return $record;
            }
        }
        return null;
    }

    public function searchAction()
    {
        $records = json_decode(trim((new Backend())->configdRun('bootenvironments list')), true) ?? [];
        return $this->searchRecordsetBase($records);
    }

    public function getAction($uuid = null)
    {
        if (!empty($uuid)) {
            $result = $this->findByUuid($uuid);
            if (!empty($result)) {
                return $result;
            }
        }
        // new or not found
        return ['name' => 'BE'.date("YmdHis"), 'uuid' => ''];
    }

    public function setAction($uuid)
    {
        if ($this->request->isPost() && $this->request->hasPost('name')) {
            /**
             * XXX:
             *      We need to fix this, name is actually the same as $uuid, which means input may either be invalid
             *      or already exist.
             */
            $name =  $this->request->getPost('name', 'string', null);

            $be = $this->findByUuid($uuid);
            if (empty($be)) {
                return ['status' => 'error', 'message' => 'Boot environment not found'];
            }

            // check boot environment name
            if ($be['name'] !== $name) {
                return json_decode(
                    trim((new Backend())->configdRun("bootenvironments rename {$be['name']} {$name}")),
                    true
                );
            }
        }

        return ['status' => 'failed'];
    }

    public function addAction()
    {

        $response = ['status' => 'failed', 'result' => 'error'];
        if ($this->request->isPost()) {
            $uuid =  $this->request->getPost('uuid', 'string', '');
            $name =  $this->request->getPost('name', 'string', '');

            $backend = new Backend();
            if (!empty($uuid)) {
                $be = $this->findByUuid($uuid);
                /**
                 * XXX:
                 *      Multiple things may go wrong here:
                 *         * we can't find the entry, which should likey raise a user exception
                 *         * the newly choosen name does already exist, in which case we should also abort
                 */

                if (empty($be)) {
                    return ['status' => 'error', 'message' => 'Boot environment not found'];
                }
                $cloneFrom = $be['name'];
                return json_decode(
                    trim($backend->configdRun("bootenvironments clone {$name} {$cloneFrom}")),
                    true
                );
            } else {
                /**
                 * XXX:
                 *    should check for existance of the name
                 */
                return $backend->configdRun("bootenvironments create {$name}");
            }
        }
        return ['status' => 'failed'];
    }

    public function delAction($uuid): array
    {
        if ($this->request->isPost()) {
            $be = $this->findByUuid($uuid);
            if (empty($be)) {
                return ['status' => 'error', 'message' => 'Boot environment not found'];
            }
            return (json_decode(trim((new Backend())->configdRun("bootenvironments destroy {$be['name']}")), true));
        }
        return ['status' => 'failed'];
    }


    public function activateAction($uuid): array
    {
        $response = ['status' => 'failed', 'result' => 'error'];
        if ($this->request->isPost()) {
            $be = $this->findByUuid($uuid);
            if (empty($be)) {
                /* XXX: likely better to throw a UserException here */
                return ['status' => false, 'message' => 'Boot environment not found'];
            }
            return json_decode(trim((new Backend())->configdRun("bootenvironments activate {$be['name']}")), true);
        }
        return $response;
    }
}
