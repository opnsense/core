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
use OPNsense\Core\Config;
use OPNsense\Trust\Store as CertStore;

/**
 * Class CertController
 * @package OPNsense\Trust\Api
 */
class CertController extends ApiMutableModelControllerBase
{
    protected static $internalModelName = 'cert';
    protected static $internalModelClass = 'OPNsense\Trust\Cert';

    /**
     * @var private key data when not stored locally
     */
    private $response_priv_key = null;


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
                $data = CertStore::createCert(
                    (string)$node->key_type,
                    (string)$node->lifetime,
                    $node->dn(),
                    (string)$node->digest,
                    (string)$node->caref,
                    (string)$node->cert_type,
                    $node->extns()
                );
                if (!empty($data['crt']) && !empty($data['prv'])) {
                    $node->crt = base64_encode($data['crt']);
                    if ((string)$node->private_key_location == 'local') {
                        /* return only in volatile storage */
                        $node->prv_payload = $data['prv'];
                        $this->response_priv_key = $data['prv'];
                    } else {
                        $node->prv = base64_encode($data['prv']);
                    }
                } else {
                    $error = $data['error'] ?? '';
                }
                break;
            case 'external':
                $data = CertStore::createCert(
                    (string)$node->key_type,
                    (string)$node->lifetime,
                    $node->dn(),
                    (string)$node->digest,
                    false,
                    (string)$node->cert_type,
                    $node->extns()
                );
                if (!empty($data['csr'])) {
                    $node->csr = base64_encode($data['csr']);
                } else {
                    $error = $data['error'] ?? '';
                }
                break;
            case 'import':
                if (CertStore::parseX509((string)$node->crt_payload) === false) {
                    $error = gettext('Invalid X509 certificate provided');
                } else {
                    $node->crt = base64_encode((string)$node->crt_payload);
                    if (
                        !empty(trim((string)$node->prv_payload)) &&
                        openssl_pkey_get_private((string)$node->prv_payload) === false
                    ) {
                        $error = gettext('Invalid private key provided');
                    }
                }
                break;
            case 'import_csr':
                if (CertStore::parseX509((string)$node->crt_payload) === false) {
                    $error = gettext('Invalid X509 certificate provided');
                } else {
                    $node->crt = base64_encode((string)$node->crt_payload);
                }
                break;
            case 'reissue':
                $data = CertStore::reIssueCert(
                    (string)$node->key_type,
                    (string)$node->lifetime,
                    $node->dn(),
                    (string)$node->prv_payload,
                    (string)$node->digest,
                    (string)$node->caref,
                    (string)$node->cert_type,
                    $node->extns()
                );
                if (!empty($data['crt'])) {
                    $node->crt = base64_encode($data['crt']);
                } else {
                    $error = $data['error'] ?? '';
                }
                break;
            case 'manual':
                if (!empty((string)$node->crt_payload)) {
                    $node->crt = base64_encode((string)$node->crt_payload);
                }
                break;
        }
        if ($error !== false) {
            throw new UserException($error, "Certificate error");
        }
    }

    public function searchAction()
    {
        $carefs = $this->request->get('carefs');
        $filter_funct = function ($record) use ($carefs) {
            return empty($carefs) || array_intersect(explode(',', $record->caref), $carefs);
        };
        return $this->searchBase(
            'cert',
            ['descr', 'caref', 'rfc3280_purpose', 'name', 'valid_from', 'valid_to'],
            null,
            $filter_funct
        );
    }

    public function getAction($uuid = null)
    {
        return $this->getBase('cert', 'cert', $uuid);
    }
    public function addAction()
    {
        $response = $this->addBase('cert', 'cert');
        if ($response['result'] == 'saved' && !empty($this->response_priv_key)) {
            $response['private_key'] = $this->response_priv_key;
        }
        return $response;
    }
    public function setAction($uuid = null)
    {
        return $this->setBase('cert', 'cert', $uuid);
    }
    public function delAction($uuid)
    {
        return $this->delBase('cert', $uuid);
    }
    public function toggleAction($uuid, $enabled = null)
    {
        return $this->toggleBase('cert', $uuid, $enabled);
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
        $payload = $this->getBase('cert', 'cert', $uuid);
        if (!empty($payload['cert'])) {
            if (!empty($payload['cert']['crt_payload'])) {
                return CertStore::dumpX509($payload['cert']['crt_payload']);
            } elseif (!empty($payload['cert']['csr_payload'])) {
                return CertStore::dumpCSR($payload['cert']['csr_payload']);
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
}
