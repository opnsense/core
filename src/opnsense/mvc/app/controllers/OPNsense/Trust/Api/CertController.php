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
use OPNsense\Core\ACL;
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
                    'v3_req',
                    $node->extns()
                );
                if (!empty($data['csr'])) {
                    $node->csr = base64_encode($data['csr']);
                    $node->prv = base64_encode($data['prv']);
                } else {
                    $error = $data['error'] ?? '';
                }
                break;
            case 'import':
                if (CertStore::parseX509((string)$node->crt_payload) === false) {
                    $error = gettext('Invalid X509 certificate provided');
                } else {
                    $node->crt = base64_encode((string)$node->crt_payload);
                    if (!empty(trim((string)$node->prv_payload))) {
                        if (openssl_pkey_get_private((string)$node->prv_payload) === false) {
                            $error = gettext('Invalid private key provided: cannot parse private key data');
                        } elseif (openssl_x509_check_private_key((string)$node->crt_payload, (string)$node->prv_payload) === false) {
                            $error = gettext('Invalid private key provided: private key does not match certificate data');
                        }
                    }
                }
                $this->getModel()->linkCaRefs($node->refid);
                break;
            case 'import_csr':
                /* certificate should be signed by something we trust */
                $tmp = CertStore::verify((string)$node->crt_payload);
                if ($tmp['exit_status'] === 0) {
                    $node->crt = base64_encode((string)$node->crt_payload);
                } else {
                    /* try to grab some useful feedback from stderr to append to the message */
                    $msg = '';
                    $parts = explode("\n", $tmp['stderr']);
                    if (count($parts) > 2) {
                        $msg = $parts[1];
                    }
                    $error = sprintf(gettext('Invalid X509 certificate provided : %s'), $msg);
                }
                break;
            case 'sign_csr':
                $data = CertStore::signCert(
                    (string)$node->key_type,
                    (string)$node->lifetime,
                    (string)$node->csr_payload,
                    (string)$node->digest,
                    (string)$node->caref,
                    (string)$node->cert_type,
                    $node->extns()
                );
                if (!empty($data['crt'])) {
                    $node->crt = base64_encode($data['crt']);
                    $node->csr = base64_encode((string)$node->csr_payload);
                } else {
                    $error = $data['error'] ?? '';
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
        $user = $this->request->get('user');
        $filter_funct = function ($record) use ($carefs, $user) {
            $match_ca = empty($carefs) || array_intersect(explode(',', $record->caref), $carefs);
            $match_user = empty($user) || (in_array($record->commonname, $user));
            return $match_ca && $match_user;
        };
        return $this->searchBase('cert', null, null, $filter_funct);
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
        if ($this->request->isPost() && !empty($uuid)) {
            Config::getInstance()->lock();
            $node = $this->getModel()->getNodeByReference('cert.' . $uuid);
            if ($node !== null) {
                $this->checkAndThrowValueInUse((string)$node->refid, false, false, ['cert', 'system.user']);
            }
            return $this->delBase('cert', $uuid);
        }
        return ['status' => 'failed'];
    }

    public function caInfoAction($caref = null)
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

    /**
     * @return list of users when the logged in user is allowed to query usermanagement
     */
    public function userListAction()
    {
        $result = [];
        if ($this->request->isGet() && (new ACL())->isPageAccessible($_SESSION['Username'], '/api/auth/user')) {
            $result['rows'] = [];
            if (isset(Config::getInstance()->object()->system->user)) {
                foreach (Config::getInstance()->object()->system->user as $user) {
                    if (isset($user->name)) {
                        $result['rows'][] = [
                            'name' => (string)$user->name
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
            $node = $this->getModel()->getNodeByReference('cert.' . $uuid);
            $result['descr'] = $node !== null ? (string)$node->descr : '';
            if ($node === null || (empty((string)$node->crt_payload)) && empty((string)$node->csr_payload)) {
                $result['error'] = gettext('Missing certificate');
            } elseif ($type == 'csr') {
                $result['status'] = 'ok';
                $result['payload'] = (string)$node->csr_payload;
            } elseif ($type == 'crt') {
                $result['status'] = 'ok';
                $result['payload'] = (string)$node->crt_payload;
            } elseif ($type == 'prv') {
                $result['status'] = 'ok';
                $result['payload'] = (string)$node->prv_payload;
            } elseif ($type == 'pkcs12') {
                $passphrase = $this->request->getPost('password', null, '');
                $tmp = CertStore::getPKCS12(
                    (string)$node->crt_payload,
                    (string)$node->prv_payload,
                    (string)$node->descr,
                    $passphrase
                );
                if (!empty($tmp['payload'])) {
                    // binary data, we need to encode it to deliver it to the client
                    $result['payload_b64'] = base64_encode($tmp['payload']);
                    $result['status'] = 'ok';
                } else {
                    $result['error'] = $tmp['error'] ?? '';
                }
            }
        }
        return $result;
    }
}
