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

namespace OPNsense\IPsec\Api;

use OPNsense\Base\ApiControllerBase;
use OPNsense\Core\Backend;
use OPNsense\Core\Config;
use OPNsense\IPsec\Swanctl;

/**
 * Class SadController
 * @package OPNsense\IPsec\Api
 */
class SadController extends ApiControllerBase
{
    /**
     * Search SAD entries
     * @return array
     */
    public function searchAction()
    {
        $data = json_decode((new Backend())->configdRun('ipsec list sad'), true);
        $records = (!empty($data) && !empty($data['records'])) ? $data['records'] : [];

        // link IPsec phase1/2 references
        $config = Config::getInstance()->object();
        $reqids = [];
        $phase1s = [];
        if (!empty($config->ipsec->phase1)) {
            foreach ($config->ipsec->phase1 as $p1) {
                if (!empty((string)$p1->ikeid)) {
                    $phase1s[(string)$p1->ikeid] = $p1;
                }
            }
        }
        if (!empty($config->ipsec->phase2)) {
            foreach ($config->ipsec->phase2 as $p2) {
                if (!empty((string)$p2->reqid) && !empty($phase1s[(string)$p2->ikeid])) {
                    $p1 = $phase1s[(string)$p2->ikeid];
                    $reqids[(string)$p2->reqid] = [
                        "ikeid" => (string)$p2->ikeid,
                        "phase1desc" => (string)$p1->descr,
                        "phase2desc" => (string)$p2->descr
                    ];
                }
            }
        }
        // merge MVC request id's when set
        $mdl = new Swanctl();
        foreach ($mdl->children->child->iterateItems() as $node_uuid => $node) {
            if (!empty((string)$node->reqid) && empty($reqids[(string)$node->reqid])) {
                $conn = $mdl->getNodeByReference('Connections.Connection.' . (string)$node->connection);
                $reqids[(string)$node->reqid] = [
                    'ikeid' => (string)$node->connection,
                    'phase1desc' => !empty($conn) ? (string)$conn->description : '',
                    'phase2desc' => (string)$node->description
                ];
            }
        }

        foreach ($records as &$record) {
            if (!empty($record['reqid']) && !empty($reqids[$record['reqid']])) {
                $record = array_merge($record, $reqids[$record['reqid']]);
            } else {
                $record['ikeid'] = null;
                $record['phase1desc'] = null;
                $record['phase2desc'] = null;
            }
        }


        return $this->searchRecordsetBase($records);
    }
    /**
     * Remove an SPD entry
     * @param string $id md 5 hash to identify the spd entry
     * @return array
     */
    public function deleteAction($id)
    {
        if ($this->request->isPost()) {
            $data = json_decode((new Backend())-> configdpRun('ipsec saddelete', [$id]), true);
            if ($data) {
                $data['result'] = "ok";
                return $data;
            }
        }
        return ["result" => "failed"];
    }
}
