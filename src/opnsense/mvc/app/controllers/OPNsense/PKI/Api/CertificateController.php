<?php

/*
 * Copyright (C) 2022 Manuel Faux <mfaux@conf.at>
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

namespace OPNsense\PKI\Api;

use OPNsense\Base\ApiControllerBase;
use OPNsense\Core\Backend;
use OPNsense\Core\Config;
use OPNsense\PKI\Util;

// require_once("certs.inc");

/**
 * Class CertificateController
 * @package OPNsense\PKI\Api
 */
class CertificateController extends ApiControllerBase
{

    public function searchAuthorityAction()
    {
        $items = [];
        $this->sessionClose();
        $config = Config::getInstance()->object();
        if (isset($config->ca)) {
            $idx = 0;
            foreach ($config->ca as $ca) {
                $cert_issuer = Util::cert_get_issuer($ca->crt);
                $cert_subject = Util::cert_get_subject($ca->crt);
                list($startdate, $enddate) = Util::cert_get_dates($ca->crt);

                if ($cert_issuer == $cert_subject) {
                    $issuer = gettext("self-signed");
                } else {
                    $issuer = gettext("external");
                }
                if (isset($ca->caref)) {
                    $issuer_ca = Util::lookup_ca($ca->caref);
                    if ($issuer_ca !== false) {
                        $issuer = (string)$issuer_ca->descr;
                    }
                }
                $item = [
                    "refid" => (string)$ca->refid,
                    "id" => $idx,
                    "internal" => isset($ca->prv) ? 1 : 0,
                    "certificate_count" => Util::count_certs_of_ca($ca->refid),
                    "issuer" => $issuer,
                    "subject" => $cert_subject,
                    "name" => (string)$ca->descr,
                    "valid_from" => $startdate,
                    "valid_until" => $enddate
                ];
                $items[] = $item;
                $idx++;
            }
        }
        return $this->search($items);
    }

    public function infoAuthorityAction($refid)
    {
        $this->sessionClose();
        $config = Config::getInstance()->object();
        if (isset($config->ca)) {
            foreach ($config->ca as $ca) {
                if ((string)$ca->refid === $refid) {
                    if (openssl_x509_export(base64_decode((string)$ca->crt), $data, false)) {
                        $data = [
                            "title" => gettext("Certificate Authority"),
                            "message" => "<pre>$data</pre>"
                        ];
                        // Prevent htmlspecialchars to be called from ApiControllerBase.afterExecuteRoute
                        $this->response->setStatusCode(200, "OK");
                        $this->response->setContentType('application/json', 'UTF-8');
                        $this->response->setContent(json_encode($data));
                        return $this->response->send();
                    }
                }
            }
        }
    }

    public function delAuthorityAction($refid)
    {
        if ($this->request->isPost()) {
            $this->sessionClose();
            Config::getInstance()->lock();
            $config = Config::getInstance()->object();
            $ids = [];
            // XXX system_camanager.php also deletes certs assigned to this ca - to be considered
            foreach (['refid' => $config->ca, 'caref' => $config->crl] as $typeref => $elem) {
                $ids[$typeref] = [];
                if (!empty($elem)) {
                    $idx = 0;
                    foreach ($elem as $e) {
                        if ((string)$e->{$typeref} == $refid) {
                            $ids[$typeref][] = $idx;
                        }
                        $idx++;
                    }
                    foreach (array_reverse($ids[$typeref]) as $idx) {
                        unset($elem[$idx]);
                    }
                }
            }
            Config::getInstance()->save();
            return [
              'status' => (count($ids['refid']) > 0) ? 'ok' : 'failed',
              'ca_count' => count($ids['refid']), // should be 1 as refid of ca is unique
              'crl_count' => count($ids['caref']),
            ];
        }
        return ['status' => 'failed'];
    }

    public function searchRevocationAction()
    {
        $caref = intval($this->request->getPost('caref', 'string', ""));
        $items = [];
        $this->sessionClose();
        $config = Config::getInstance()->object();
        if (isset($config->crl)) {
            $idx = 0;
            foreach ($config->crl as $crl) {
                if (isset($crl->caref) && $crl->caref != $caref) {
                    continue;
                }

                $item = [
                    "refid" => (string)$crl->refid,
                    "id" => $idx,
                    "internal" => (Util::is_crl_internal($crl)) ? 1 : 0,
                    "used" => (Util::is_openvpn_server_crl((string)$crl->refid)) ? 1 : 0,
                    "certificate_count" => (Util::is_crl_internal($crl)) ? Util::count_certs_of_crl($crl->refid) : gettext('unknown'),
                    "name" => (string)$crl->descr
                ];
                $items[] = $item;
                $idx++;
            }
        }
        return $this->search($items);
    }

    public function delRevocationAction($refid)
    {
        if ($this->request->isPost()) {
            $this->sessionClose();
            Config::getInstance()->lock();
            $config = Config::getInstance()->object();
            $deleted = 0;
            $idx = 0;
            foreach ($config->crl as $crl) {
                if ((string)$crl->refid === $refid) {
                    if (!Util::is_openvpn_server_crl($crl)) {
                        unset($config->crl[$idx]);
                        $deleted++;
                        break;
                    }
                }
                $idx++;
            }
            Config::getInstance()->save();
            return [
              'status' => ($deleted) ? 'ok' : 'failed',
              'count' => $deleted,
            ];
        }
        return ['status' => 'failed'];
    }

    private function delRevocation($refid)
    {
        $config = Config::getInstance()->object();
        return $deleted;
    }

    public function searchCertificateAction()
    {
        $items = [];
        $this->sessionClose();
        $config = Config::getInstance()->object();
        if (isset($config->cert)) {
            $idx = 0;
            foreach ($config->cert as $cert) {
                $cert_subject = "";
                $cert_issuer = "";
                list($startdate, $enddate) = [0, 0];

                if (isset($cert->crt)) {
                    $cert_issuer = Util::cert_get_issuer((string)$cert->crt);
                    $cert_subject = Util::cert_get_subject((string)$cert->crt);
                    list($startdate, $enddate) = Util::cert_get_dates($cert->crt);
                }
                if ($cert_issuer == $cert_subject) {
                    $issuer = "self-signed"; // translation in view
                } else {
                    $issuer = "external"; // translation in view
                }
                if (isset($cert->csr)) {
                    $cert_subject = Util::csr_get_subject((string)$cert->csr);
                    $issuer = "pending"; // translation in view
                }
                if (isset($cert->caref)) {
                    $issuer_ca = Util::lookup_ca($cert->caref);
                    if ($issuer_ca !== false) {
                        $issuer = (string)$issuer_ca->descr;
                    }
                }

                $item = [
                    "refid" => (string)$cert->refid,
                    "id" => $idx,
                    "internal" => isset($cert->prv) ? 1 : 0,
                    "issuer" => $issuer,
                    "subject" => $cert_subject,
                    "name" => (string)$cert->descr,
                    "csr" => isset($cert->csr) ? 1 : 0,
                    "valid_from" => $startdate,
                    "valid_until" => $enddate,
                    "purpose" => (isset($cert->crt)) ? Util::cert_get_purpose((string)$cert->crt, true, true) : "",
                    "usage" => $this->getCertificateUsage((string)$cert->refid),
                    "used" => (Util::cert_in_use((string)$cert->refid)) ? 1 : 0,
                    "validity" => (isset($cert->csr)) ? time() : ((Util::is_cert_revoked($cert)) ? -1 : $enddate), // for sorting only
                    "revoked" => (Util::is_cert_revoked($cert)) ? 1 : 0
                ];
                $items[] = $item;
                $idx++;
            }
        }
        return $this->search($items);
    }

    public function infoCertificateAction($refid)
    {
        $this->sessionClose();
        $config = Config::getInstance()->object();
        if (isset($config->cert)) {
            foreach ($config->cert as $cert) {
                if ((string)$cert->refid === $refid) {
                    if (openssl_x509_export(base64_decode((string)$cert->crt), $data, false)) {
                        $data = [
                            "title" => gettext("Certificate"),
                            "message" => "<pre>$data</pre>"
                        ];
                        // Prevent htmlspecialchars to be called from ApiControllerBase.afterExecuteRoute
                        $this->response->setStatusCode(200, "OK");
                        $this->response->setContentType('application/json', 'UTF-8');
                        $this->response->setContent(json_encode($data));
                        return $this->response->send();
                    }
                }
            }
        }
    }

    public function delCertificateAction($refid)
    {
        if ($this->request->isPost()) {
            $this->sessionClose();
            Config::getInstance()->lock();
            $config = Config::getInstance()->object();
            $deleted = 0;
            if (isset($config->cert)) {
                $ids = [];
                $idx = 0;
                foreach ($config->cert as $cert) {
                    if ((string)$cert->refid === $refid) {
                        $ids[] = $idx;
                    }
                    $idx++;
                }
                foreach (array_reverse($ids) as $idx) {
                    unset($config->cert[$idx]);
                    $deleted++;
                }
            }
            Config::getInstance()->save();
            return [
              'status' => 'ok',
              'count' => $deleted,
            ];
        }
        return ['status' => 'failed'];
    }

    /***
     * generic legacy search action, reads post variables for filters and page navigation.
     */
    private function search($records)
    {
        $itemsPerPage = intval($this->request->getPost('rowCount', 'int', 9999));
        $currentPage = intval($this->request->getPost('current', 'int', 1));
        $offset = ($currentPage - 1) * $itemsPerPage;
        $entry_keys = array_keys($records);
        if ($this->request->hasPost('sort') && is_array($this->request->getPost('sort'))) {
            $keys = array_keys($this->request->getPost('sort'));
            $order = $this->request->getPost('sort')[$keys[0]];
            $keys = array_column($records, $keys[0]);
            if (count($keys) == count($records)) {
                // Sort column actually exists
                array_multisort($keys, $order == 'asc' ? SORT_ASC : SORT_DESC, $records);
            }
        }
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

        return [
           'total' => count($entry_keys),
           'rowCount' => $itemsPerPage,
           'current' => $currentPage,
           'rows' => $formatted,
        ];
    }

    private function getCertificateUsage($certref)
    {
        // TODO: Consider plugin usage
        $purpose = [];
        if (Util::is_webgui_cert($certref)) {
            $purpose[] = gettext('Web GUI');
        }
        if (Util::is_user_cert($certref)) {
            $purpose[] = gettext('User Cert');
        }
        if (Util::is_openvpn_server_cert($certref)) {
            $purpose[] = gettext('OpenVPN Server');
        }
        if (Util::is_openvpn_client_cert($certref)) {
            $purpose[] = gettext('OpenVPN Client');
        }
        if (Util::is_ipsec_cert($certref)) {
            $purpose[] = gettext('IPsec Tunnel');
        }
        return $purpose;
    }
}
