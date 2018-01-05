<?php

/**
 *    Copyright (C) 2008 Shrew Soft Inc. <mgrooms@shrew.net>
 *    Copyright (C) 2014-2015 Deciso B.V.
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
 * Class AuthoritiesController
 * @package OPNsense\Trust\Api
 */
class AuthoritiesController extends TrustBase
{
    /**
     * Search CA list
     * @return array
     */
    public function searchAction()
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
        foreach ($mdlTrust->cas->ca->getChildren() as $uuid => $ca) {
            $name = htmlspecialchars($ca->descr->__toString());
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
            $issuer = htmlspecialchars(Certs::cert_get_issuer($ca->crt->__toString()));
            $subj = htmlspecialchars(Certs::cert_get_subject($ca->crt->__toString()));
            list($startdate, $enddate) = Certs::cert_get_dates($ca->crt->__toString());
            $issuer_name = ($subj == $issuer) ? gettext("self-signed") : gettext("external");

            $cauuid = $ca->cauuid->__toString();
            if (!empty($cauuid) && ($ca_issuer = $mdlTrust->cas->ca->{$cauuid})) {
                $issuer_name = $ca_issuer->descr->__toString();
            }

            $certcount = 0;

            foreach ($mdlTrust->certs->cert->getChildren() as $cert) {
                if ($cert->cauuid->__toString() == $uuid) {
                    $certcount++;
                }
            }

            foreach ($mdlTrust->cas->ca->getChildren() as $child_ca) {
                if ($child_ca->cauuid->__toString() == $uuid) {
                    $certcount++;
                }
            }
            $rows[] = [
                "uuid" => $uuid,
                "Name" => $name,
                "Internal" => !empty($ca->prv->__toString()) ? gettext("YES") : gettext("NO"),
                "Issuer" => $issuer_name,
                "Certificates" => $certcount,
                "Distinguished" => $subj,
                "startdate" => $startdate,
                "enddate" => $enddate
            ];
        }

        return ["rows" => $rows, "rowCount" => $rowCount, "total" => $count + 1, "current" => $current];
    }

    /**
     * Delete CA
     * @param $uuid
     * @return array
     * @throws \Phalcon\Validation\Exception
     */
    public function delAction($uuid)
    {

        if (!$this->request->isPost() || $uuid == null) {
            return ["result" => "failed"];
        }

        $mdlTrust = new Trust();

        foreach ($mdlTrust->certs->cert->getChildren() as $cert_uuid => $cert) {
            if ($cert->cauuid->__toString() == $uuid) {
                $mdlTrust->crl_certs->cert->del($cert_uuid);
            }
        }

        foreach ($mdlTrust->crl_certs->cert->getChildren() as $cert_uuid => $cert) {
            if ($cert->cauuid->__toString() == $uuid) {
                $mdlTrust->crl_certs->cert->del($cert_uuid);
            }
        }

        foreach ($mdlTrust->crls->crl->getChildren() as $crl_uuid => $crl) {
            if ($crl->cauuid->__toString() == $uuid) {
                $mdlTrust->crls->crl->del($crl_uuid);
            }
        }

        if ($mdlTrust->cas->ca->del($uuid)) {
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
     * Export CA
     * @param $uuid
     * @param $type
     * @return array
     */
    public function expAction($uuid, $type)
    {
        if ($uuid == null || $type == null) {
            return ["result" => "failed"];
        }

        if (!($ca = (new Trust())->cas->ca->{$uuid})) {
            return ["result" => "failed"];
        }

        switch ($type) {
            case "crt":
                $exp_name = urlencode("{$ca->descr}.crt");
                $exp_data = base64_decode($ca->crt);
                break;
            case "key":
                $exp_name = urlencode("{$ca->descr}.key");
                $exp_data = base64_decode($ca->prv);
                break;
            default:
                return ["result" => "failed"];
        }

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
     * Retrieve settings for edit existing CA form
     * @param null $uuid
     * @return array|bool
     */
    public function getExistingAction($uuid = null)
    {
        if ($uuid == null) {
            return ["Existing" => ["descr" => "", "cert" => "", "key" => "", "serial" => ""]];
        }
        $ca = (new Trust())->cas->ca->{$uuid};
        if (!$ca) {
            return false;
        }

        return [
            "Existing" => [
                "descr" => $ca->descr->__toString(),
                "cert" => base64_decode($ca->crt->__toString()),
                "key" => (!empty($ca->prv->__toString())) ? base64_decode($ca->prv->__toString()) : "",
                "serial" => (!empty($ca->serial->__toString())) ? $ca->serial->__toString() : ""
            ]
        ];
    }

    /**
     * Update settings for edit existing CA form
     * @param null $uuid
     * @return array
     * @throws \Phalcon\Validation\Exception
     */
    public function setExistingAction($uuid = null)
    {
        $this->sessionClose();
        if (!$this->request->isPost() || !$this->request->hasPost("Existing")) {
            return ["result" => "failed"];
        }

        $post = $this->request->getPost("Existing");

        $result = $this->Validation(["descr", "cert"], [], $post, "Existing");
        if (count($result["validations"]) > 0) {
            return $result;
        }
        if (!strstr($post['cert'],
                "BEGIN CERTIFICATE") || !strstr($post['cert'], "END CERTIFICATE")) {
            $result["validations"]["Existing.cert"] = gettext("This certificate does not appear to be valid.");
        }
        if (!empty($post['key']) && strstr($post['key'], "ENCRYPTED")) {
            $result["validations"]["Existing.key"] = gettext("Encrypted private keys are not yet supported.");
        }
        if (isset($post['serial']) && $post['serial'] !== '' &&
            ((string)((int)$post['serial']) != $post['serial'] || $post['serial'] < 1)) {
            $result["validations"]["Existing.serial"] = gettext('The serial number must be a number greater than zero or left blank.');
        }
        if (count($result["validations"]) > 0) {
            return $result;
        }

        $mdlTrust = new Trust();
        if ($uuid) {
            $ca = $mdlTrust->cas->ca->{$uuid};
            if (!$ca) {
                return ["result" => "failed"];
            }
            $ca->descr = $post['descr'];
            $ca->crt = base64_encode($post['cert']);
            if (!empty($post['key'])) {
                $ca->prv = base64_encode($post['key']);
            }
            if (!empty($post["serial"])) {
                $ca->serial = $post["serial"];
            }
        } else {

            $old_err_level = error_reporting(0); /* otherwise openssl_ functions throw warings directly to a page screwing menu tab */
            $mdlTrust->ca_import($post['descr'], $post['cert'], $post['key'], $post["serial"]);
            error_reporting($old_err_level);
        }

        $valMsgs = $mdlTrust->performValidation();
        foreach ($valMsgs as $field => $msg)
        {
            $fieldnm = str_replace($ca->__reference, "Existing", $msg->getField());
            $result["validations"][$fieldnm] = $msg->getMessage();
        }

        if (count($result['validations']) > 0)
            return $result;

        $mdlTrust->serializeToConfig();
        Config::getInstance()->save();
        return ["result" => "importeds"];
    }

    /**
     * Retrieve settings for create internal CA form
     * @return array
     */
    public function getInternalAction()
    {
        return [
            "Internal" => [
                "descr" => "",
                "keylen" => $this->keylens,
                "digest_alg" => $this->digest_algs,
                "lifetime" => "365",
                "dn_country" => Certs::get_country_codes(),
                "dn_state" => "",
                "dn_city" => "",
                "dn_organization" => "",
                "dn_email" => "",
                "dn_commonname" => "internal-ca"
            ]
        ];
    }

    /**
     * Update settings for create internal CA form
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

        $result = $this->Validation([
            "descr",
            "keylen",
            "lifetime",
            "dn_country",
            "dn_state",
            "dn_city",
            "dn_organization",
            "dn_email",
            "dn_commonname"
        ], [
            "keylen",
            "lifetime",
            "dn_country",
            "dn_state",
            "dn_city",
            "dn_organization"
        ], $post, "Internal");
        if (count($result["validations"]) > 0) {
            return $result;
        }

        $dn = [
            'countryName' => $post['dn_country'],
            'stateOrProvinceName' => $post['dn_state'],
            'localityName' => $post['dn_city'],
            'organizationName' => $post['dn_organization'],
            'emailAddress' => $post['dn_email'],
            'commonName' => $post['dn_commonname']
        ];

        $old_err_level = error_reporting(0); /* otherwise openssl_ functions throw warings directly to a page screwing menu tab */
        $mdlTrust = new Trust();
        if (!$mdlTrust->ca_create(
            $post['descr'],
            $post['keylen'],
            $post['lifetime'],
            $dn,
            $post['digest_alg']
        )) {
            $input_errors = "";
            while ($ssl_err = openssl_error_string()) {
                $input_errors .= " " . $ssl_err;
            }
            $result["validations"]["Internal.descr"] = gettext("openssl library returns:") . $input_errors;
            return $result;
        }
        error_reporting($old_err_level);

        $valMsgs = $mdlTrust->performValidation();
        foreach ($valMsgs as $field => $msg)
        {
            $fieldnm = str_replace($ca->__reference, "Internal", $msg->getField());
            $result["validations"][$fieldnm] = $msg->getMessage();
        }

        if (count($result['validations']) > 0)
            return $result;

        $mdlTrust->serializeToConfig();
        Config::getInstance()->save();
        return ["result" => "created"];
    }

    /**
     * Retrieve settings for create intermediate CA form
     * @return array
     */
    public function getIntermediateAction()
    {
        return [
            "Intermediate" => [
                "descr" => "",
                "cauuid" => (new Trust())->list_ca(),
                "keylen" => $this->keylens,
                "digest_alg" => $this->digest_algs,
                "lifetime" => "365",
                "dn_country" => Certs::get_country_codes(),
                "dn_state" => "",
                "dn_city" => "",
                "dn_organization" => "",
                "dn_email" => "",
                "dn_commonname" => "internal-ca"
            ]
        ];
    }

    /**
     * Update settings for create intermediate CA form
     * @return array
     * @throws \Phalcon\Validation\Exception
     */
    public function setIntermediateAction()
    {
        $this->sessionClose();
        if (!$this->request->isPost() || !$this->request->hasPost("Intermediate")) {
            return ["result" => "failed"];
        }

        $post = $this->request->getPost("Intermediate");

        $result = $this->Validation([
            "descr",
            "cauuid",
            "keylen",
            "lifetime",
            "dn_country",
            "dn_state",
            "dn_city",
            "dn_organization",
            "dn_email",
            "dn_commonname"
        ], [
            "keylen",
            "cauuid",
            "lifetime",
            "dn_country",
            "dn_state",
            "dn_city",
            "dn_organization"
        ], $post, "Intermediate");
        if (count($result["validations"]) > 0) {
            return $result;
        }

        $dn = [
            'countryName' => $post['dn_country'],
            'stateOrProvinceName' => $post['dn_state'],
            'localityName' => $post['dn_city'],
            'organizationName' => $post['dn_organization'],
            'emailAddress' => $post['dn_email'],
            'commonName' => $post['dn_commonname']
        ];

        $old_err_level = error_reporting(0); /* otherwise openssl_ functions throw warings directly to a page screwing menu tab */
        $mdlTrust = new Trust();
        if (!($ca = $mdlTrust->ca_inter_create(
            $post['descr'],
            $post["cauuid"],
            $post['keylen'],
            $post['lifetime'],
            $dn,
            $post['digest_alg']
        ))) {
            $input_errors = "";
            while ($ssl_err = openssl_error_string()) {
                $input_errors .= " " . $ssl_err;
            }
            $result["validations"]["Intermediate.descr"] = gettext("openssl library returns:") . $input_errors;
            return $result;
        }
        error_reporting($old_err_level);

        $valMsgs = $mdlTrust->performValidation();
        foreach ($valMsgs as $field => $msg)
        {
            $fieldnm = str_replace($ca->__reference, "Intermediate", $msg->getField());
            $result["validations"][$fieldnm] = $msg->getMessage();
        }

        if (count($result['validations']) > 0)
            return $result;

        $mdlTrust->serializeToConfig();
        Config::getInstance()->save();
        return ["result" => "created"];
    }
}

