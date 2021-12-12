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

    /***
     * generic legacy search action, reads post variables for filters and page navigation.
     */
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

    /***
     * search phase 1 entries in legacy config returning a standard structure as we use in the mvc variant
     */
    public function searchPhase1Action()
    {
        $ph1type = ['ikev1' => 'IKE', 'ikev2' => 'IKEv2', 'ike' => 'auto'];
        $ph1algos = [
              'aes' => 'AES',
              'aes128gcm16' => '128 bit AES-GCM with 128 bit ICV',
              'aes192gcm16' => '192 bit AES-GCM with 128 bit ICV',
              'aes256gcm16' => '256 bit AES-GCM with 128 bit ICV',
              'camellia' => 'Camellia',
              'blowfish' => 'Blowfish',
              '3des' => '3DES',
              'cast128' => 'CAST128',
              'des' => 'DES'
        ];
        $ph1authmethos = [
            'hybrid_rsa_server' => 'Hybrid RSA + Xauth',
            'xauth_rsa_server' => 'Mutual RSA + Xauth',
            'xauth_psk_server' => 'Mutual PSK + Xauth',
            'eap-tls' => 'EAP-TLS',
            'psk_eap-tls' => 'RSA (local) + EAP-TLS (remote)',
            'eap-mschapv2' => 'EAP-MSCHAPV2',
            'rsa_eap-mschapv2' => 'Mutual RSA + EAP-MSCHAPV2',
            'eap-radius' => 'EAP-RADIUS',
            'rsasig' => 'Mutual RSA',
            'pubkey' => 'Mutual Public Key',
            'pre_shared_key' => 'Mutual PSK'
        ];
        $items = [];
        $this->sessionClose();
        $config = Config::getInstance()->object();
        if (!empty($config->ipsec->phase1)) {
            $idx = 0;
            $ifs = [];
            if ($config->interfaces->count() > 0) {
                foreach ($config->interfaces->children() as $key => $node) {
                    $ifs[$key] = !empty((string)$node->descr) ? (string)$node->descr : strtoupper($key);
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
                $ph1proposal = $ph1algos[(string)$p1->{"encryption-algorithm"}->name];
                if (!empty((string)$p1->{"encryption-algorithm"}->keylen)) {
                    $ph1proposal .= sprintf(" ({$p1->{"encryption-algorithm"}->keylen} %s)", gettext("bits"));
                }
                $ph1proposal .= " + " . strtoupper((string)$p1->{"hash-algorithm"});
                if (!empty($p1->dhgroup)) {
                    $ph1proposal .= " + " . gettext("DH Group") . " {$p1->dhgroup}";
                }
                $item = [
                    "id" => intval((string)$p1->ikeid),   // ikeid should be unique
                    "seqid" => $idx,
                    "enabled" => empty((string)$p1->disabled) ? "1" : "0",
                    "protocol" => $p1->protocol == "inet" ? "IPv4" : "IPv6",
                    "iketype" => $ph1type[(string)$p1->iketype],
                    "interface" => !empty($ifs[$interface]) ? $ifs[$interface] : $interface,
                    "remote_gateway" => (string)$p1->{"remote-gateway"},
                    "mobile" => !empty((string)$p1->mobile),
                    "mode" => (string)$p1->mode,
                    "proposal" => $ph1proposal,
                    "authentication" => $ph1authmethos[(string)$p1->authentication_method],
                    "description" => (string)$p1->descr
                ];
                $item['type'] = "{$item['protocol']} {$item['iketype']}";
                $items[] = $item;
                $idx++;
            }
        }
        return $this->search($items);
    }

    /***
     * search phase 2 entries in legacy config returning a standard structure as we use in the mvc variant
     */
    public function searchPhase2Action()
    {
        $selected_ikeid = intval($this->request->getPost('ikeid', 'int', -1));
        $ph2algos = [
            'aes' => 'AES',
            'aes128gcm16' => 'aes128gcm16',
            'aes192gcm16' => 'aes192gcm16',
            'aes256gcm16' => 'aes256gcm16',
            'blowfish' => 'Blowfish',
            '3des' => '3DES',
            'cast128' => 'CAST128',
            'des' => 'DES',
            'null' => gettext("NULL (no encryption)")
        ];
        $ph2halgos = [
            'hmac_md5' => 'MD5',
            'hmac_sha1' => 'SHA1',
            'hmac_sha256' => 'SHA256',
            'hmac_sha384' => 'SHA384',
            'hmac_sha512' => 'SHA512',
            'aesxcbc' => 'AES-XCBC'
        ];
        $items = [];
        $this->sessionClose();
        $config = Config::getInstance()->object();
        if (!empty($config->ipsec->phase2)) {
            $p2idx = 0;
            $ifs = [];
            if ($config->interfaces->count() > 0) {
                foreach ($config->interfaces->children() as $key => $node) {
                    $ifs[$key] = !empty((string)$node->descr) ? (string)$node->descr : strtoupper($key);
                }
            }
            foreach ($config->ipsec->phase2 as $p2) {
                $ikeid = intval((string)$p2->ikeid);
                if ($ikeid != $selected_ikeid) {
                    $p2idx++;
                    continue;
                }
                $p2mode = array_search(
                    (string)$p2->mode,
                    [
                      "IPv4 tunnel" => "tunnel",
                      "IPv6 tunnel" => "tunnel6",
                      "transport" => "transport",
                      "Route-based" => "route-based"
                    ]
                );
                if (in_array((string)$p2->mode, ['tunnel', 'tunnel6'])) {
                    foreach (['localid', 'remoteid'] as $id) {
                        $content = (string)$p2->$id->type;
                        switch ((string)$p2->$id->type) {
                            case "address":
                                $content = "{$p2->$id->address}";
                                break;
                            case "network":
                                $content = "{$p2->$id->address}/{$p2->$id->netbits}";
                                break;
                            case "mobile":
                                $content = gettext("Mobile Client");
                                break;
                            case "none":
                                $content = gettext("None");
                                break;
                            default:
                                if (!empty($ifs[(string)$p2->$id->type])) {
                                    $content = $ifs[(string)$p2->$id->type];
                                }
                                break;
                        }
                        if ($id == 'localid') {
                            $local_subnet = $content;
                        } else {
                            $remote_subnet = $content;
                        }
                    }
                } elseif ((string)$p2->mode == "route-based") {
                    $local_subnet = (string)$p2->tunnel_local;
                    $remote_subnet = (string)$p2->tunnel_remote;
                } else {
                    $local_subnet = "";
                    $remote_subnet = "";
                }
                $ph2proposal = "";
                foreach ($p2->{"encryption-algorithm-option"} as $node) {
                    if (!empty($ph2proposal)) {
                        $ph2proposal .= " , ";
                    }
                    $ph2proposal .= $ph2algos[(string)$node->name];
                    if ((string)$node->keylen == 'auto') {
                        $ph2proposal .= " (auto) ";
                    } elseif (!empty((string)$node->keylen)) {
                        $ph2proposal .= sprintf(" ({$node->keylen} %s) ", gettext("bits"));
                    }
                }
                $ph2proposal .= " + ";
                $idx = 0;
                foreach ($p2->{"hash-algorithm-option"} as $node) {
                    $ph2proposal .= ($idx++) > 0 ? " , " : "";
                    $ph2proposal .= $ph2halgos[(string)$node];
                }
                if (!empty((string)$p2->pfsgroup)) {
                    $ph2proposal .= sprintf("+ %s %s", gettext("DH Group"), "{$p2->pfsgroup}");
                }
                $item = [
                    "id" => $p2idx,
                    "uniqid" => (string)$p2->uniqid, // XXX: a bit convoluted, should probably replace id at some point
                    "ikeid" => $ikeid,
                    "enabled" => empty((string)$p2->disabled) ? "1" : "0",
                    "protocol" => $p2->protocol == "esp" ? "ESP" : "AH",
                    "mode" => $p2mode,
                    "local_subnet" => $local_subnet,
                    "remote_subnet" => $remote_subnet,
                    "proposal" => $ph2proposal,
                    "description" => (string)$p2->descr
                ];
                $items[] = $item;
                $p2idx++;
            }
        }
        return $this->search($items);
    }

    /**
     * delete phase 1 including associated phase 2 entries
     */
    public function delPhase1Action($ikeid)
    {
        if ($this->request->isPost()) {
            $phase_ids = [];
            $this->sessionClose();
            Config::getInstance()->lock();
            $config = Config::getInstance()->object();
            foreach ([$config->ipsec->phase1, $config->ipsec->phase2] as $phid => $phase) {
                $phase_ids[$phid] = [];
                if (!empty($phase)) {
                    $idx = 0;
                    foreach ($phase as $p) {
                        if (intval((string)$p->ikeid) == intval($ikeid)) {
                            $phase_ids[$phid][] = $idx;
                        }
                        $idx++;
                    }
                    foreach (array_reverse($phase_ids[$phid]) as $idx) {
                        unset($phase[$idx]);
                    }
                }
            }
            Config::getInstance()->save();
            if (!empty($phase_ids[0])) {
                @touch("/tmp/ipsec.dirty");
            }
            return [
              'status' => 'ok',
              'phase1count' => count($phase_ids[0]), // should be 1 as ikeid is unique
              'phase2count' => count($phase_ids[1]),
            ];
        }
        return ['status' => 'failed'];
    }

    /**
     * toggle if phase 1 is enabled
     */
    public function togglePhase1Action($ikeid, $enabled = null)
    {
        if ($this->request->isPost()) {
            $this->sessionClose();
            Config::getInstance()->lock();
            $config = Config::getInstance()->object();
            if (!empty($config->ipsec->phase1)) {
                $idx = 0;
                foreach ($config->ipsec->phase1 as $p1) {
                    if (intval((string)$p1->ikeid) == intval($ikeid)) {
                        if ($enabled == "0" || $enabled == "1") {
                            $new_status = $enabled == "1" ? "0" : "1";
                        } else {
                            $new_status = $config->ipsec->phase1[$idx]->disabled == "1" ? "0" : "1";
                        }
                        if ($new_status == "1") {
                            $config->ipsec->phase1[$idx]->disabled = $new_status;
                        } elseif (isset($config->ipsec->phase1[$idx]->disabled)) {
                            unset($config->ipsec->phase1[$idx]->disabled);
                        }

                        Config::getInstance()->save();
                        @touch("/tmp/ipsec.dirty");
                        return ['status' => 'ok', 'disabled' => $new_status];
                    }
                    $idx++;
                }
            }
            return ['status' => 'not_found'];
        }
        return ['status' => 'failed'];
    }

    /**
     * delete phase 2 entry
     */
    public function delPhase2Action($seqid)
    {
        if ($this->request->isPost()) {
            $this->sessionClose();
            Config::getInstance()->lock();
            $config = Config::getInstance()->object();
            if ((string)intval($seqid) == $seqid && isset($config->ipsec->phase2[intval($seqid)])) {
                unset($config->ipsec->phase2[intval($seqid)]);
                Config::getInstance()->save();
                @touch("/tmp/ipsec.dirty");
                return ['status' => 'ok'];
            }
            return ['status' => 'not_found'];
        }
        return ['status' => 'failed'];
    }

    /**
     * toggle if phase 2 is enabled
     */
    public function togglePhase2Action($seqid, $enabled = null)
    {
        if ($this->request->isPost()) {
            $this->sessionClose();
            Config::getInstance()->lock();
            $config = Config::getInstance()->object();
            if ((string)intval($seqid) == $seqid && isset($config->ipsec->phase2[intval($seqid)])) {
                if ($enabled == "0" || $enabled == "1") {
                    $new_status = $enabled == "1" ? "0" : "1";
                } else {
                    $new_status = $config->ipsec->phase2[intval($seqid)]->disabled == "1" ? "0" : "1";
                }
                if ($new_status == "1") {
                    $config->ipsec->phase2[intval($seqid)]->disabled = $new_status;
                } elseif (isset($config->ipsec->phase2[intval($seqid)]->disabled)) {
                    unset($config->ipsec->phase2[intval($seqid)]->disabled);
                }

                Config::getInstance()->save();
                @touch("/tmp/ipsec.dirty");
                return ['status' => 'ok', 'disabled' => $new_status];
            }
            return ['status' => 'not_found'];
        }
        return ['status' => 'failed'];
    }

    /**
     * toggle if IPsec is enabled
     */
    public function toggleAction($enabled = null)
    {
        if ($this->request->isPost()) {
            $this->sessionClose();
            Config::getInstance()->lock();
            $config = Config::getInstance()->object();
            if ($enabled == "0" || $enabled == "1") {
                $new_status = $enabled == "1";
            } else {
                $new_status = !isset($config->ipsec->enable);
            }
            if ($new_status) {
                $config->ipsec->enable = true;
            } elseif (isset($config->ipsec->enable)) {
                unset($config->ipsec->enable);
            }
            Config::getInstance()->save();
            @touch("/tmp/ipsec.dirty");
            return ['status' => 'ok'];
        }
        return ['status' => 'failed'];
    }
}
