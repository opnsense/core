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
 * Class CaController
 * @package OPNsense\Trust\Api
 */
class CaController extends ApiMutableModelControllerBase
{
    protected static $internalModelName = 'ca';
    protected static $internalModelClass = 'OPNsense\Trust\Ca';

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
                break;
            case 'existing':
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
            case 'import':
                break;
            case 'ocsp':
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
            'ca',
            ['refid', 'descr', 'caref', 'name', 'valid_from', 'valid_to'],
            null,
            $filter_funct
        );
    }

    public function getAction($uuid = null)
    {
        return $this->getBase('ca', 'ca', $uuid);
    }

    public function addAction()
    {
        return $this->addBase('ca', 'ca');
    }

    public function setAction($uuid = null)
    {
        return $this->setBase('ca', 'ca', $uuid);
    }

    public function delAction($uuid)
    {
        if ($this->request->isPost() && !empty($uuid)) {
            $node = $this->getModel()->getNodeByReference('ca.' . $uuid);
            if ($node !== null) {
                $this->checkAndThrowValueInUse((string)$node->refid, false, false, ['ca']);
            }
            return $this->delBase('ca', $uuid);
        }
        return ['status' => 'failed'];
    }

    public function toggleAction($uuid, $enabled = null)
    {
        return $this->toggleBase('ca', $uuid, $enabled);
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
