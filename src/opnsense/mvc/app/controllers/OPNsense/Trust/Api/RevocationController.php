<?php

/**
 *    Copyright (C) 2014-2015 Deciso B.V.
 *    Copyright (C) 2008 Shrew Soft Inc. <mgrooms@shrew.net>
 *    Copyright (C) 2017 Smart-Soft
 *
 *    All rights reserved.
 *
 *    Redistribution and use in source and binary forms, with or without
 *    modification, are permitted provided that the following conditions are met:
 *
 *    1. Redistributions of source code must retain the above copyright notice,
 *       this list of conditions and the following disclaimer.
 *
 *    2. Redistributions in binary form must reproduce the above copyright
 *       notice, this list of conditions and the following disclaimer in the
 *       documentation and/or other materials provided with the distribution.
 *
 *    THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
 *    INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 *    AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 *    AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 *    OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 *    SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 *    INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 *    CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 *    ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 *    POSSIBILITY OF SUCH DAMAGE.
 *
 */

namespace OPNsense\Trust\Api;

use \OPNsense\Core\Certs;
use \OPNsense\Core\Config;
use \OPNsense\Trust\Trust;

/**
 * Class RevocationController
 * @package OPNsense\Trust\Api
 */
class RevocationController extends TrustBase
{
    /**
     * Search CRL list
     * @return array
     */
    public function searchCrlAction()
    {
        $this->sessionClose();

        if (!$this->request->isPost()) {
            return [];
        }

        $post = $this->request->getPost();
        if (isset($post["rowCount"])) {
            $rowCount = $post["rowCount"];
        } else {
            $rowCount = -1;
        }
        if (isset($post["current"])) {
            $current = $post["current"];
        } else {
            $current = -1;
        }

        if (isset($post["searchPhrase"])) {
            $search = preg_split('/\s+/', trim(stripslashes($post['searchPhrase'])));
        } else {
            $search = [];
        }

        $rows = [];
        $mdlTrust = new Trust();
        $count = -1;
        foreach ($mdlTrust->crls->crl->getChildren() as $uuid => $crl) {
            $name = htmlspecialchars($crl->descr->__toString());
            $found = true;
            foreach ($search as $pattern) {
                if (trim($pattern == '')) {
                    continue;
                }
                $found = $found && preg_match("/$pattern/", $name);
            }
            if (!$found) {
                continue;
            }
            $count++;
            if ($count < ($current - 1) * $rowCount || $count >= $current * $rowCount) {
                continue;
            }

            $certs = $mdlTrust->get_crls_cert($uuid);
            $internal = Certs::is_crl_internal($crl);
            $inuse = Certs::is_openvpn_server_crl($crl->refid->__toString());

            $caname = "";
            $cauuid = $crl->cauuid->__toString();
            if (!empty($cauuid) && ($ca = $mdlTrust->cas->ca->{$cauuid})) {
                $caname = $ca->descr->__toString();
            }

            $rows[] = [
                "uuid" => $uuid,
                "CA" => $caname,
                "Name" => $name,
                "InternalBool" => $internal,
                "Internal" => $internal ? gettext("YES") : gettext("NO"),
                "Certificates" => $internal ? count($certs) : gettext("Unknown (imported)"),
                "InUse" => $inuse ? gettext("YES") : gettext("NO")
            ];
        }

        return ["rows" => $rows, "rowCount" => $rowCount, "total" => $count + 1, "current" => $current];
    }

    /**
     * Retrieve settings for create internal CRL form
     * @return array
     */
    public function getInternalAction()
    {
        return [
            "Internal" => [
                "descr" => "",
                "cauuid" => (new Trust())->certs->cert->Add()->getNodes()["cauuid"],
                "lifetime" => "9999",
                "serial" => "0"
            ]
        ];
    }

    /**
     * Update settings for create internal CRL form
     * @return array
     * @throws \Phalcon\Validation\Exception
     */
    public function setInternalAction()
    {
        $this->sessionClose();
        if (!$this->request->isPost() || !$this->request->hasPost("Internal")) {
            return ["result" => "failed"];
        }

        $post = $this->request->getPost("Internal");

        $result = $this->Validation(["descr", "cauuid"], [], $post, "Internal");
        if (count($result["validations"]) > 0) {
            return $result;
        }

        $mdlTrust = new Trust();
        $crl = $mdlTrust->crls->crl->Add();
        foreach (["descr", "cauuid"] as $fieldname) {
            if (isset($post[$fieldname])) {
                $crl->{$fieldname} = $post[$fieldname];
            }
        }
        $crl->method = "internal";
        $crl->serial = empty($post['serial']) ? 9999 : $post['serial'];
        $crl->lifetime = empty($post['lifetime']) ? 9999 : $post['lifetime'];

        $valMsgs = $mdlTrust->performValidation();
        foreach ($valMsgs as $field => $msg)
        {
            $fieldnm = str_replace($crl->__reference, "Internal", $msg->getField());
            $result["validations"][$fieldnm] = $msg->getMessage();
        }

        if (count($result['validations']) > 0)
            return $result;

        $mdlTrust->serializeToConfig();
        Config::getInstance()->save();
        return ["result" => "created"];
    }

    /**
     * Retrieve settings for create existing CRL form
     * @param null $uuid
     * @return array|bool
     */
    public function getExistingAction($uuid = null)
    {
        $mdlTrust = new Trust();

        if ($uuid == null) {
            return [
                "Existing" => [
                    "descr" => "",
                    "cauuid" => $mdlTrust->certs->cert->Add()->getNodes()["cauuid"],
                    "text" => ""
                ]
            ];
        }

        if (!($crl = $mdlTrust->crls->crl->{$uuid})) {
            return false;
        }

        return [
            "Existing" => [
                "descr" => $crl->descr->__toString(),
                "cauuid" => $mdlTrust->certs->cert->Add()->getNodes()["cauuid"],
                "text" => base64_decode($crl->text->__toString())
            ]
        ];
    }

    /**
     * Update settings for create existing CRL form
     * @return array
     * @throws \Phalcon\Validation\Exception
     */
    public function setExistingAction()
    {
        $this->sessionClose();
        if (!$this->request->isPost() || !$this->request->hasPost("Existing")) {
            return ["result" => "failed"];
        }

        $post = $this->request->getPost("Existing");

        $result = $this->Validation(["descr", "cauuid"], [], $post, "Existing");
        if (count($result["validations"]) > 0) {
            return $result;
        }

        $mdlTrust = new Trust();
        $crl = $mdlTrust->crls->crl->Add();
        foreach (["descr", "cauuid"] as $fieldname) {
            if (isset($post[$fieldname])) {
                $crl->{$fieldname} = $post[$fieldname];
            }
        }
        $crl->text = base64_encode($post['text']);
        $crl->method = "existing";

        $valMsgs = $mdlTrust->performValidation();
        foreach ($valMsgs as $field => $msg)
        {
            $fieldnm = str_replace($crl->__reference, "Existing", $msg->getField());
            $result["validations"][$fieldnm] = $msg->getMessage();
        }

        if (count($result['validations']) > 0)
            return $result;

        $mdlTrust->serializeToConfig();
        Config::getInstance()->save();
        return ["result" => "created"];
    }

    /**
     * Export CRL
     * @param $uuid
     * @return array
     */
    public function expAction($uuid)
    {
        if ($uuid == null) {
            return ["result" => "failed"];
        }

        $mdlTrust = new Trust();

        if (!($crl = $mdlTrust->crls->crl->{$uuid})) {
            return ["result" => "failed"];
        }

        $crl = $mdlTrust->crl_update($crl);
        $exp_name = urlencode("{$crl->descr->__toString()}.crl");
        $exp_data = base64_decode($crl->text->__toString());

        $this->view->disable();
        $this->response->setContentType("application/octet-stream");
        $this->response->setHeader("Cache-Control", 'must-revalidate, post-check=0, pre-check=0');
        $this->response->setHeader("Content-Disposition", "attachment; filename={$exp_name}");
        $this->response->setHeader("Content-Length", strlen($exp_data));
        $this->response->setContent($exp_data);
        $this->response->send();
        die();
    }

    /**
     * Delete CRL
     * @param $uuid
     * @return array
     * @throws \Phalcon\Validation\Exception
     */
    public function delCrlAction($uuid)
    {
        if (!$this->request->isPost() || $uuid == null) {
            return ["result" => "failed"];
        }

        $mdlTrust = new Trust();
        if (!($crl = $mdlTrust->crls->crl->{$uuid})) {
            return ["result" => "failed"];
        }
        if (Certs::is_crl_internal($crl)) {
            foreach ($mdlTrust->crl_certs->cert->getChildren() as $cert_uuid => $cert) {
                if ($cert->crluuid->__toString() == $uuid) {
                    $mdlTrust->crl_certs->cert->del($cert_uuid);
                }
            }
        }
        if ($mdlTrust->crls->crl->del($uuid)) {
            // if item is removed, serialize to config and save
            $mdlTrust->serializeToConfig();
            Config::getInstance()->save();
            $result['result'] = 'deleted';
        } else {
            $result['result'] = 'not found';
        }

        return $result;
    }

    /**
     * Search revocation certificate list
     * @param null $uuid
     * @return array
     */
    public function searchCertsAction($uuid = null)
    {
        if (!$uuid) {
            return [];
        }

        $this->sessionClose();

        if (!$this->request->isPost()) {
            return [];
        }

        $post = $this->request->getPost();
        if (isset($post["rowCount"])) {
            $rowCount = $post["rowCount"];
        } else {
            $rowCount = -1;
        }
        if (isset($post["current"])) {
            $current = $post["current"];
        } else {
            $current = -1;
        }

        if (isset($post["searchPhrase"])) {
            $search = preg_split('/\s+/', trim(stripslashes($post['searchPhrase'])));
        } else {
            $search = [];
        }

        $rows = [];
        $mdlTrust = new Trust();
        $count = -1;
        foreach ($mdlTrust->crl_certs->cert->getChildren() as $cert_uuid => $cert) {
            $name = $cert->descr->__toString();
            $found = true;
            foreach ($search as $pattern) {
                if (trim($pattern == '')) {
                    continue;
                }
                $found = $found && preg_match("/$pattern/", $name);
            }
            if (!$found) {
                continue;
            }
            $count++;
            if ($count < ($current - 1) * $rowCount || $count >= $current * $rowCount) {
                continue;
            }
            if ($cert->crluuid->__toString() != $uuid) {
                continue;
            }

            $rows[] = [
                "uuid" => $cert_uuid,
                "Name" => $name,
                "Reason" => Certs::$openssl_crl_status[$cert->reason->__toString()],
                "Revoked" => date("D M j G:i:s T Y", $cert->revoke_time->__toString())
            ];
        }

        $count = count($rows);
        return ["rows" => $rows, "rowCount" => $rowCount, "total" => $count + 1, "current" => $current];
    }

    /**
     * Retrieve settings for revocation certificate form
     * @param null $uuid
     * @return array
     */
    public function getCertAction($uuid = null)
    {
        if (!$uuid) {
            return [];
        }

        $mdlTrust = new Trust();
        if (!($crl = $mdlTrust->crls->crl->{$uuid})) {
            return [];
        }

        $cauuid = $crl->cauuid->__toString();

        $certs = [];
        foreach ($mdlTrust->certs->cert->getChildren() as $cert_uuid => $cert) {
            if ($cert->cauuid->__toString() == $cauuid && !$mdlTrust->is_cert_revoked($cert)) {
                $certs[$cert_uuid] = ["value" => $cert->descr->__toString(), "selected" => "0"];
            }
        }
        
        $reasons = [];
        foreach (Certs::$openssl_crl_status as $code => $reason) {
            $reasons[$code] = ["value" => $reason, "selected" => "0"];
        }
        $reasons[-1]["selected"] = "1";
        return [
            "Revocation" =>
                [
                    "certuuid" => $certs,
                    "reason" => $reasons
                ]
        ];
    }

    /**
     * Update settings for revocation certificate form
     * @param null $uuid
     * @return array
     * @throws \Phalcon\Validation\Exception
     */
    public function addCertAction($uuid = null)
    {
        $this->sessionClose();
        if (!$uuid || !$this->request->isPost() || !$this->request->hasPost("Revocation")) {
            return ["result" => "failed"];
        }

        $post = $this->request->getPost("Revocation");

        $result = $this->Validation(["certuuid"], [], $post, "Revocation");
        if (count($result["validations"]) > 0) {
            return $result;
        }

        $mdlTrust = new Trust();
        if (!($crl = $mdlTrust->crls->crl->{$uuid})) {
            return ["result" => "failed"];
        }
        if (!($cert = $mdlTrust->certs->cert->{$post["certuuid"]})) {
            return ["result" => "failed"];
        }
        if (!$mdlTrust->cert_revoke($cert, $crl, $post["reason"])) {
            return ["result" => "failed"];
        }

        $valMsgs = $mdlTrust->performValidation();
        foreach ($valMsgs as $field => $msg)
        {
            $fieldnm = str_replace($crl->__reference, "Revocation", $msg->getField());
            $result["validations"][$fieldnm] = $msg->getMessage();
        }

        if (count($result['validations']) > 0)
            return $result;

        $mdlTrust->serializeToConfig();
        Config::getInstance()->save();
        return ["result" => "created"];
    }

    /**
     * Delete revocation certificate
     * @param $uuid
     * @return array
     * @throws \Phalcon\Validation\Exception
     */
    public function delCertAction($uuid)
    {
        if (!$this->request->isPost() || $uuid == null) {
            return ["result" => "failed"];
        }

        $mdlTrust = new Trust();
        if ($mdlTrust->crl_certs->cert->del($uuid)) {
            // if item is removed, serialize to config and save
            $mdlTrust->serializeToConfig();
            Config::getInstance()->save();
            $result['result'] = 'deleted';
        } else {
            $result['result'] = 'not found';
        }

        return $result;
    }
}

