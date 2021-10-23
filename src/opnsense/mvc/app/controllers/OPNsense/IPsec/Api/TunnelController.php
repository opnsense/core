<?php

/*
 * Copyright (C) 2021 Deciso B.V.
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

/**
 * Class TunnelController
 * @package OPNsense\IPsec\Api
 */
class TunnelController extends ApiControllerBase
{
    private function search($records)
    {
        $itemsPerPage = intval($this->request->getPost('rowCount', 'int', 9999));
        $currentPage = intval($this->request->getPost('current', 'int', 1));
        $offset = ($currentPage - 1) * $itemsPerPage;
        $entry_keys = array_keys($records);
        if ($this->request->hasPost('searchPhrase') && $this->request->getPost('searchPhrase') !== '') {
            $searchPhrase = (string)$this->request->getPost('searchPhrase');
            $entry_keys = array_filter($entry_keys, function ($key) use ($searchPhrase, $records) {
                foreach ($records[$key] as $itemval) {
                    if (stripos($itemval, $searchPhrase) !== false) {
                        return true;
                    }
                }
                return false;
            });
        }
        $formatted = array_map(function ($value) use (&$records) {
            foreach ($records[$value] as $ekey => $evalue) {
                $item[$ekey] = $evalue;
            }
            return $item;
        }, array_slice($entry_keys, $offset, $itemsPerPage));

        if ($this->request->hasPost('sort') && is_array($this->request->getPost('sort'))) {
            $keys = array_keys($this->request->getPost('sort'));
            $order = $this->request->getPost('sort')[$keys[0]];
            $keys = array_column($formatted, $keys[0]);
            array_multisort($keys, $order == 'asc' ? SORT_ASC : SORT_DESC, $formatted);
        }

        return [
           'total' => count($entry_keys),
           'rowCount' => $itemsPerPage,
           'current' => $currentPage,
           'rows' => $formatted,
        ];
    }
    public function searchPhase1Action()
    {
        $items = [];
        $this->sessionClose();
        $config = Config::getInstance()->object();
        if (!empty($config->ipsec->phase1)) {
            $idx = 0;
            $ifs = [];
            if ($config->interfaces->count() > 0) {
                foreach ($config->interfaces->children() as $key => $node) {
                    $ifs[(string)$node->if] = !empty((string)$node->descr) ? (string)$node->descr : $key;
                }
                if ($config->virtualip->count() > 0) {
                    foreach ($config->virtualip->children() as $node) {
                        if (!empty((string)$node->vhid)) {
                            $key = (string)$node->interface . '_vip' . (string)$node->vhid;
                        } else {
                            $key = (string)$node->subnet;
                        }
                        $ifs[$key] = "$node->subnet ({$node->descr})";
                    }
                }
            }
            foreach ($config->ipsec->phase1 as $p1) {
                $interface = (string)$p1->interface;
                $ph1type = ['ikev1' => 'IKE', 'ikev2' => 'IKEv2', 'ike' => 'auto'];
                $item = [
                    "id" => $idx,
                    "disabled" => !empty((string)$p1->disabled),
                    "protocol" => $p1->protocol == "inet" ? "IPv4" : "IPv6",
                    "iketype" => $ph1type[(string)$p1->iketype],
                    "interface" => !empty($ifs[$interface]) ? $ifs[$interface] : $interface,
                    "remote_gateway" => (string)$p1->{"remote-gateway"},
                    "mobile" => !empty((string)$p1->mobile),
                    "mode" => (string)$p1->mode,
                    "description" => (string)$p1->descr
                ];
                $item['type'] = "{$item['protocol']} {$item['iketype']}";
                $items[] = $item;
                $idx++;
            }
        }
        return $this->search($items);
    }
}
