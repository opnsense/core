<?php

/*
 * Copyright (C) 2024 Deciso B.V.
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

namespace OPNsense\Trust\Api;

use OPNsense\Base\ApiMutableModelControllerBase;
use OPNsense\Base\UserException;
use OPNsense\Core\Backend;
use OPNsense\Core\Config;
use OPNsense\Trust\Store as CertStore;
use OPNsense\Trust\Cert;

/**
 * Class CaController
 * @package OPNsense\Trust\Api
 */
class CaController extends ApiMutableModelControllerBase
{
    protected static $internalModelName = 'ca';
    protected static $internalModelClass = 'OPNsense\Trust\Ca';

    private function compare_issuer($subject, $issuer)
    {
        return empty(array_diff(
            array_map('serialize', $subject),
            array_map('serialize', $issuer)
        ));
    }

    protected function setBaseHook($node)
    {
        if (empty((string)$node->refid)) {
            $node->refid = uniqid();
        }
        $error = false;
        if (!empty((string)$node->prv_payload)) {
            /** private key manually offered */
            $node->prv = base64_encode((string)$node->prv_payload);
        }
        switch ((string)$node->action) {
            case 'internal':
            case 'ocsp':
                $extns = [];
                if (!empty((string)$node->ocsp_uri)) {
                    $extns['authorityInfoAccess'] = "OCSP;URI:{$node->ocsp_uri}";
                }
                $data = CertStore::createCert(
                    (string)$node->key_type,
                    (string)$node->lifetime,
                    $node->dn(),
                    (string)$node->digest,
                    (string)$node->caref,
                    (string)$node->action == 'internal' ? 'v3_ca' : 'ocsp',
                    $extns
                );
                /**
                 * As createCert updates the config, we need to collect that change in order to push it back
                 * into the model. (increment serial)
                 **/
                if (!empty((string)$node->caref)) {
                    foreach (Config::getInstance()->object()->ca as $cert) {
                        if (isset($cert->refid) && (string)$node->caref == $cert->refid) {
                            $issuer = $this->getModel()->getByCaref($node->caref);
                            if ($issuer !== null) {
                                $issuer->serial = (string)$cert->serial;
                            }
                        }
                    }
                }
                if (!empty($data['crt']) && !empty($data['prv'])) {
                    $node->crt = base64_encode($data['crt']);
                    $node->prv = base64_encode($data['prv']);
                } else {
                    $error = $data['error'] ?? '';
                }
                break;
            case 'existing':
                $x509 = openssl_x509_parse((string)$node->crt_payload);
                if ($x509 === false) {
                    $error = gettext('Invalid X509 certificate provided');
                } else {
                    $node->crt = base64_encode((string)$node->crt_payload);
                    if (
                        !empty(trim((string)$node->prv_payload)) &&
                        openssl_pkey_get_private((string)$node->prv_payload) === false
                    ) {
                        $error = gettext('Invalid private key provided');
                    } else {
                        /* link certificates on ca import */
                        if ($x509['issuer'] != $x509['subject']) {
                            foreach ($this->getModel()->ca->iterateItems() as $ca) {
                                if (!empty((string)$ca->crt_payload)) {
                                    $x509_2 = openssl_x509_parse((string)$ca->crt_payload);
                                    if ($x509_2 !== false) {
                                        if ($this->compare_issuer($x509_2['subject'], $x509['issuer'])) {
                                            $node->caref = (string)$ca->refid;
                                        } elseif ($x509_2['issuer'] == $x509['subject']) {
                                            $ca->caref = (string)$node->refid;
                                        }
                                    }
                                }
                            }
                        }
                        $certmdl = new Cert();
                        foreach ($certmdl->cert->iterateItems() as $cert) {
                            $x509_2 = openssl_x509_parse((string)$cert->crt_payload);
                            if ($x509_2 !== false) {
                                if ($this->compare_issuer($x509_2['issuer'], $x509['subject'])) {
                                    $cert->caref = (string)$node->refid;
                                }
                            }
                        }
                        $certmdl->serializeToConfig();
                    }
                }
                break;
        }
        if ($error !== false) {
            throw new UserException($error, "Certificate error");
        }
    }

    public function searchAction()
    {
        return $this->searchBase(
            'ca',
            ['refid', 'descr', 'caref', 'name', 'refcount', 'valid_from', 'valid_to'],
        );
    }

    public function getAction($uuid = null)
    {
        return $this->getBase('ca', 'ca', $uuid);
    }

    public function addAction()
    {
        $response = $this->addBase('ca', 'ca');
        if ($response['result'] != 'failed') {
            (new Backend())->configdRun('system trust configure', true);
        }
        return $response;
    }

    public function setAction($uuid = null)
    {
        $response = $this->setBase('ca', 'ca', $uuid);
        if ($response['result'] != 'failed') {
            (new Backend())->configdRun('system trust configure', true);
        }
        return $response;
    }

    public function delAction($uuid)
    {
        $response = $this->delBase('ca', $uuid);
        (new Backend())->configdRun('system trust configure', true);
        return $response;
    }

    public function caInfoAction($caref)
    {
        if ($this->request->isGet()) {
            $ca = CertStore::getCACertificate($caref);
            if ($ca) {
                $payload = CertStore::parseX509($ca['cert']);
                if ($payload) {
                    return $payload;
                }
            }
        }
        return [];
    }

    public function rawDumpAction($uuid)
    {
        $payload = $this->getBase('ca', 'ca', $uuid);
        if (!empty($payload['ca'])) {
            if (!empty($payload['ca']['crt_payload'])) {
                return CertStore::dumpX509($payload['ca']['crt_payload']);
            }
        }
        return [];
    }

    public function caListAction()
    {
        $result = [];
        if ($this->request->isGet()) {
            $result['rows'] = [];
            if (isset(Config::getInstance()->object()->ca)) {
                foreach (Config::getInstance()->object()->ca as $cert) {
                    if (isset($cert->refid)) {
                        $result['rows'][] = [
                            'caref' => (string)$cert->refid,
                            'descr' => (string)$cert->descr
                        ];
                    }
                }
            }
            $result['count'] = count($result['rows']);
        }
        return $result;
    }

    /**
     * generate file download content
     * @param string $uuid certificate reference
     * @param string $type one of crt/prv/pkcs12,
     *                  $_POST['password'] my contain an optional password for the pkcs12 format
     * @return array
     */
    public function generateFileAction($uuid = null, $type = 'crt')
    {
        $result = ['status' => 'failed'];
        if ($this->request->isPost() && !empty($uuid)) {
            $node = $this->getModel()->getNodeByReference('ca.' . $uuid);
            if ($node === null || empty((string)$node->crt_payload)) {
                $result['error'] = gettext('Misssing certificate');
            } elseif ($type == 'crt') {
                $result['status'] = 'ok';
                $result['payload'] = (string)$node->crt_payload;
            } elseif ($type == 'prv') {
                $result['status'] = 'ok';
                $result['payload'] = (string)$node->prv_payload;
            }
        }
        return $result;
    }
}
