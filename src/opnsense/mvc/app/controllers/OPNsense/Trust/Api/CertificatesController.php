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
use \OPNsense\Core\Util;
use \OPNsense\Trust\Trust;

/**
 * Class CertificatesController
 * @package OPNsense\Trust\Api
 */
class CertificatesController extends TrustBase
{

    /**
     * Search the list of certificates
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
        foreach ($mdlTrust->certs->cert->getChildren() as $uuid => $cert) {
            $name = htmlspecialchars($cert->descr->__toString());
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
            $purpose = null;
            $caname = "";
            $subj = "";
            $startdate = "";
            $enddate = "";

            if (!empty($cert->crt->__toString())) {
                $subj = Certs::cert_get_subject($cert->crt->__toString());
                $issuer = Certs::cert_get_issuer($cert->crt->__toString());
                $purpose = Certs::cert_get_purpose($cert->crt->__toString());
                list($startdate, $enddate) = Certs::cert_get_dates($cert->crt->__toString());
                $caname = ($subj == $issuer) ? gettext("self-signed") : gettext("external");
                $subj = htmlspecialchars($subj);
            }
            $csr = $cert->csr->__toString();
            if (!empty($csr)) {
                $subj = htmlspecialchars(Certs::csr_get_subject($csr));
                $caname = gettext("external - signature pending");
            }

            $cauuid = $cert->cauuid->__toString();
            if (!empty($cauuid) && ($ca = $mdlTrust->cas->ca->{$cauuid})) {
                $caname = $ca->descr->__toString();
            }

            $rows[] = [
                "uuid" => $uuid,
                "csr" => !empty($csr),
                "prv" => !empty($cert->prv->__toString()),
                "Name" => $name,
                "Purpose" => ($purpose != []) ? gettext('CA: ') . $purpose['ca'] . ", " . gettext('Server: ') . $purpose['server'] : "",
                "Issuer" => $caname,
                "Distinguished" => $subj,
                "startdate" => $startdate,
                "enddate" => $enddate,
                "InUse" => $this->inUse($cert, $mdlTrust)
            ];
        }

        return ["rows" => $rows, "rowCount" => $rowCount, "total" => $count + 1, "current" => $current];
    }

    /**
     * Retrieve settings for import form
     * @param null $user
     * @return array
     */
    public function getImportAction($user = null)
    {
        return [
            "Import" => [
                "descr" => $user ? Config::getInstance()->object()->system->user->{(int)$user}->name->__toString() : "",
                "cert" => "",
                "key" => ""
            ]
        ];
    }

    /**
     * Update settings for import form
     * @param null $user
     * @return array
     * @throws \Phalcon\Validation\Exception
     */
    public function setImportAction($user = null)
    {
        $this->sessionClose();
        if (!$this->request->isPost() || !$this->request->hasPost("Import")) {
            return ["result" => "failed"];
        }

        $post = $this->request->getPost("Import");

        $result = $this->Validation(["descr", "cert", "key"], [], $post, "Import");
        if (count($result["validations"]) > 0) {
            return $result;
        }
        if (!strstr($post['cert'],
                "BEGIN CERTIFICATE") || !strstr($post['cert'], "END CERTIFICATE")) {
            $result["validations"]["Import.cert"] = gettext("This certificate does not appear to be valid.");
        }
        if (count($result["validations"]) > 0) {
            return $result;
        }

        $old_err_level = error_reporting(0); /* otherwise openssl_ functions throw warings directly to a page screwing menu tab */
        $mdlTrust = new Trust();
        $cert = $mdlTrust->cert_import($post['descr'], $post['cert'], $post['key']);
        error_reporting($old_err_level);

        if ($user != null) {
            Config::getInstance()->object()->system->user->{(int)$user}->addChild("cert",
                $cert->getAttributes()["uuid"]);
        }
        $mdlTrust->serializeToConfig();
        Config::getInstance()->save();
        return ["result" => "imported"];
    }

    /**
     * Delete certificate
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
        if (!($cert = $mdlTrust->certs->cert->{$uuid})) {
            return ["result" => "failed"];
        }
        if ($this->inUse($cert, $mdlTrust) != "") {
            return ["result" => "failed"];
        }
        if ($mdlTrust->certs->cert->del($uuid)) {
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
     * Export Certificate
     * @param null $uuid
     * @param null $type
     * @return array
     */
    public function expAction($uuid = null, $type = null)
    {
        if ($uuid == null || $type == null) {
            return ["result" => "failed"];
        }

        $mdlTrust = new Trust();
        if (!($cert = $mdlTrust->certs->cert->{$uuid})) {
            return ["result" => "failed"];
        }

        switch ($type) {
            case "crt":
                $exp_name = urlencode("{$cert->descr->__toString()}.crt");
                $cert = $cert->crt->__toString();
                if (empty($cert)) {
                    return ["result" => "failed"];
                }
                $exp_data = base64_decode($cert);
                break;
            case "key":
                $exp_name = urlencode("{$cert->descr->__toString()}.key");
                $exp_data = base64_decode($cert->prv->__toString());
                break;
            case "p12":
                $crt = $cert->crt->__toString();
                $prv = $cert->prv->__toString();
                if (empty($crt) || empty($prv)) {
                    return ["result" => "failed"];
                }
                $descr = $cert->descr->__toString();
                $exp_name = urlencode("{$descr}.p12");
                $args = [];
                $args['friendly_name'] = $descr;

                if (!empty($cauuid = $cert->cauuid->__toString())) {
                    if ($ca = $mdlTrust->cas->ca->{$cauuid}) {
                        $args['extracerts'] = openssl_x509_read(base64_decode($ca->crt->__toString()));
                    }
                }

                set_error_handler(
                    function () {
                        return;
                    }
                );

                $exp_data = "";
                $res_crt = openssl_x509_read(base64_decode($crt));
                $res_key = openssl_pkey_get_private([0 => base64_decode($prv), 1 => ""]);

                openssl_pkcs12_export($res_crt, $exp_data, $res_key, null, $args);
                restore_error_handler();
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
     * Retrieve certification information
     * @param null $uuid
     * @return array
     * @throws \Exception
     */
    public function infoAction($uuid = null)
    {
        if ($uuid == null) {
            return ["result" => "failed"];
        }

        $this->sessionClose();
        if (!($cert = (new Trust())->certs->cert->{$uuid})) {
            return ["result" => "failed"];
        }
        $crt = $cert->crt->__toString();
        if (empty($crt)) {
            return ["result" => "failed"];
        }
        $message = "";
        if (!openssl_x509_export(base64_decode($crt), $message, false)) {
            return ["result" => "failed"];
        }
        return ["title" => "created", "message" => $message];
    }

    /**
     * Retrieve settings for create internal certificate form
     * @param null $user
     * @return array
     */
    public function getInternalAction($user = null)
    {
        $mdlTrust = new Trust();

        $subject_items = ['C' => '', 'ST' => '', 'L' => '', 'O' => '', 'emailAddress' => '', 'CN' => ''];
        foreach ($mdlTrust->cas->ca->getChildren() as $ca) {
            $subject_items = ['C' => '', 'ST' => '', 'L' => '', 'O' => '', 'emailAddress' => '', 'CN' => ''];
            if ($ca->prv->__toString()) {
                $subject = Certs::cert_get_subject_array($ca->crt->__toString());
                foreach ($subject as $subject_item) {
                    $subject_items[$subject_item['a']] = $subject_item['v'];
                }
            }
        }

        return [
            "Internal" => [
                "descr" => $user ? Config::getInstance()->object()->system->user->{(int)$user}->name->__toString() : "",
                "cauuid" => $mdlTrust->list_ca(),
                "cert_type" => [
                    "usr_cert" => ["value" => gettext("Client Certificate"), "selected" => "1"],
                    "cert_type" => ["value" => gettext("Server Certificate"), "selected" => "0"],
                    "v3_ca" => ["value" => gettext("Certificate Authority"), "selected" => "0"]
                ],
                "keylen" => $this->keylens,
                "digest_alg" => $this->digest_algs,
                "lifetime" => "365",
                "dn_country" => Certs::get_country_codes($subject_items['C']),
                "dn_state" => $subject_items["ST"],
                "dn_city" => $subject_items["L"],
                "dn_organization" => $subject_items["O"],
                "dn_email" => $subject_items["emailAddress"],
                "dn_commonname" => "",
                "DNS" => [],
                "IP" => [],
                "email" => [],
                "URI" => []
            ]
        ];
    }

    /**
     * Update settings for create internal certificate form
     * @param null $user
     * @return array
     * @throws \Phalcon\Validation\Exception
     */
    public function setInternalAction($user = null)
    {
        $this->sessionClose();
        if (!$this->request->isPost() || !$this->request->hasPost("Internal")) {
            return ["result" => "failed"];
        }

        $post = $this->request->getPost("Internal");

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
            "cauuid",
            "keylen",
            "lifetime",
            "dn_country",
            "dn_state",
            "dn_city",
            "dn_organization"
        ], $post, "Internal");
        foreach (explode(",", $post["DNS"]) as $dns) {
            if ($dns != "" && !Util::is_hostname($dns)) {
                $result["validations"]["Internal.dns"] = gettext("DNS subjectAltName values must be valid hostnames or FQDNs");
            }
        }
        foreach (explode(",", $post["IP"]) as $ip) {
            if ($ip != "" && !Util::is_ipaddr($ip)) {
                $result["validations"]["Internal.ip"] = gettext("IP subjectAltName values must be valid IP Addresses");
            }
        }
        foreach (explode(",", $post["email"]) as $email) {
            if ($email != "" && preg_match("/[\!\#\$\%\^\(\)\~\?\>\<\&\/\\\,\"\']/", $email)) {
                $result["validations"]["Internal.email"] = gettext("The email provided in a subjectAltName contains invalid characters.");
            }
        }
        foreach (explode(",", $post["URI"]) as $uri) {
            if ($uri != "" && !Util::is_URL($uri)) {
                $result["validations"]["Internal.URI"] = gettext("URI subjectAltName types must be a valid URI");
            }
        }
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
        $altnames_tmp = [];
        foreach (explode(",", $post["DNS"]) as $dns) {
            if ($dns != "") {
                $altnames_tmp[] = "DNS:{$dns}";
            }
        }
        foreach (explode(",", $post["IP"]) as $ip) {
            if ($ip != "") {
                $altnames_tmp[] = "IP:{$ip}";
            }
        }
        foreach (explode(",", $post["email"]) as $email) {
            if ($email != "") {
                $altnames_tmp[] = "email:{$email}";
            }
        }
        foreach (explode(",", $post["URI"]) as $uri) {
            if ($uri != "") {
                $altnames_tmp[] = "URI:{$uri}";
            }
        }
        if ($altnames_tmp != []) {
            $dn['subjectAltName'] = implode(",", $altnames_tmp);
        }

        $old_err_level = error_reporting(0); /* otherwise openssl_ functions throw warings directly to a page screwing menu tab */
        $mdlTrust = new Trust();
        if (!($cert = $mdlTrust->cert_create(
            $post['descr'],
            $post['cauuid'],
            $post['keylen'],
            $post['lifetime'],
            $dn,
            $post['digest_alg'],
            $post['cert_type']
        ))) {
            $input_errors = "";
            while ($ssl_err = openssl_error_string()) {
                $input_errors .= " " . $ssl_err;
            }
            $result["validations"]["Internal.descr"] = gettext("openssl library returns:") . $input_errors;
            return $result;
        }
        error_reporting($old_err_level);
        if ($user != null) {
            Config::getInstance()->object()->system->user->{(int)$user}->addChild("cert",
                $cert->getAttributes()["uuid"]);
        }
        $mdlTrust->serializeToConfig();
        Config::getInstance()->save();
        return ["result" => "created"];
    }

    /**
     * Retrieve settings for create external certificate form
     * @param null $user
     * @return array
     */
    public function getExternalAction($user = null)
    {
        return [
            "External" => [
                "descr" => $user ? Config::getInstance()->object()->system->user->{(int)$user}->name->__toString() : "",
                "keylen" => $this->keylens,
                "digest_alg" => $this->digest_algs,
                "dn_country" => Certs::get_country_codes(),
                "dn_state" => "",
                "dn_city" => "",
                "dn_organization" => "",
                "dn_organizationalunit" => "",
                "dn_email" => "",
                "dn_commonname" => ""
            ]
        ];
    }

    /**
     * Update settings for create external certificate form
     * @param null $user
     * @return array
     * @throws \Phalcon\Validation\Exception
     */
    public function setExternalAction($user = null)
    {
        $this->sessionClose();
        if (!$this->request->isPost() || !$this->request->hasPost("External")) {
            return ["result" => "failed"];
        }

        $post = $this->request->getPost("External");

        $result = $this->Validation([
            "descr",
            "keylen",
            "dn_country",
            "dn_state",
            "dn_city",
            "dn_organization",
            "dn_email",
            "dn_commonname"
        ], [
            "keylen",
            "dn_country",
            "dn_state",
            "dn_city",
            "dn_organization",
            "dn_organization"
        ], $post, "External");
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
        if (!empty($post['dn_organizationalunit'])) {
            $dn['organizationalUnitName'] = $post['dn_organizationalunit'];
        }
        $old_err_level = error_reporting(0); /* otherwise openssl_ functions throw warings directly to a page screwing menu tab */
        $mdlTrust = new Trust();
        if (!($cert = $mdlTrust->csr_generate($post["descr"], $post['keylen'], $dn, $post['digest_alg']))) {
            $input_errors = "";
            while ($ssl_err = openssl_error_string()) {
                $input_errors .= " " . $ssl_err;
            }
            $result["validations"]["External.descr"] = gettext("openssl library returns:") . $input_errors;
            return $result;
        }
        error_reporting($old_err_level);

        if ($user != null) {
            Config::getInstance()->object()->system->user->{(int)$user}->addChild("cert",
                $cert->getAttributes()["uuid"]);
        }
        $mdlTrust->serializeToConfig();
        Config::getInstance()->save();
        return ["result" => "created"];
    }

    /**
     * Retrieve settings for sign certificate form
     * @param null $uuid
     * @return array
     */
    public function getCsrAction($uuid = null)
    {
        if (!$uuid) {
            return [];
        }

        if (!($cert = (new Trust())->certs->cert->{$uuid})) {
            return [];
        }

        return [
            "csr" => [
                "descr" => $cert->descr->__toString(),
                "csr" => base64_decode($cert->csr->__toString()),
                "cert" => ""
            ]
        ];
    }

    /**
     * Update settings for sign certificate form
     * @param null $uuid
     * @return array
     * @throws \Exception
     * @throws \Phalcon\Validation\Exception
     */
    public function setCsrAction($uuid = null)
    {
        $this->sessionClose();
        if (!$this->request->isPost() || !$this->request->hasPost("csr") || !$uuid) {
            return ["result" => "failed"];
        }

        $mdlTrust = new Trust();
        if (!($cert = $mdlTrust->certs->cert->{$uuid})) {
            return ["result" => "failed"];
        }

        $post = $this->request->getPost("csr");

        $result = $this->Validation(["cert"], [], $post, "csr");
        if (count($result["validations"]) > 0) {
            return $result;
        }
        if (!strstr($post['cert'],
                "BEGIN CERTIFICATE") || !strstr($post['cert'], "END CERTIFICATE")) {
            $result["validations"]["csr.cert"] = gettext("This certificate does not appear to be valid.");
        }
        $mod_cert = Certs::cert_get_modulus($post['cert'], false);
        $mod_csr = Certs::cert_get_modulus($cert->csr->__toString(), false);

        if (strcmp($mod_csr, $mod_cert)) {
            // simply: if the moduli don't match, then the private key and public key won't match
            $result["validations"]["csr.cert"] = gettext("The certificate modulus does not match the signing request modulus.");
        }
        if (count($result["validations"]) > 0) {
            return $result;
        }

        $cert->crt = base64_encode($post['cert']);
        $cert->csr = "";

        $mdlTrust->serializeToConfig();
        Config::getInstance()->save();
        return ["result" => "imported"];
    }

    /**
     * Retrieve settings for create existing user certificate form
     * @param null $user_id
     * @return array
     */
    public function getExistingAction($user_id = null)
    {
        $certs = [];
        foreach ((new Trust())->certs->cert->getNodes() as $uuid => $cert) {
            $certs[$uuid] = ["value" => $cert["descr"], "selected" => "0"];
        }
        return [
            "Existing" => [
                "certuuid" => $certs
            ]
        ];
    }

    /**
     * Update settings for create existing user certificate form
     * @param null $user
     * @return array
     */
    public function setExistingAction($user = null)
    {
        $this->sessionClose();
        if ($user == null || !$this->request->isPost() || !$this->request->hasPost("Existing")) {
            return ["result" => "failed"];
        }

        $post = $this->request->getPost("Existing");

        $result = $this->Validation(["certuuid"], [], $post, "Existing");
        if (count($result["validations"]) > 0) {
            return $result;
        }

        Config::getInstance()->object()->system->user->{(int)$user}->addChild("cert", $post["certuuid"]);
        Config::getInstance()->save();
        return ["result" => "saved"];
    }

    /**
     * Retrieve where certificate is used
     * @param $cert
     * @param $mdlTrust
     * @return string
     */
    private function inUse($cert, $mdlTrust)
    {
        $in_use = "";

        if ($mdlTrust->is_cert_revoked($cert)) {
            $in_use .= gettext('Revoked') . "\n";
        }

        if (Certs::is_webgui_cert($cert->getAttributes()["uuid"])) {
            $in_use .= gettext('Web GUI') . "\n";
        }

        if (Certs::is_user_cert($cert->getAttributes()["uuid"])) {
            $in_use .= gettext('User Cert') . "\n";
        }

        if (Certs::is_openvpn_server_cert($cert->getAttributes()["uuid"])) {
            $in_use .= gettext('OpenVPN Server') . "\n";
        }

        if (Certs::is_openvpn_client_cert($cert->getAttributes()["uuid"])) {
            $in_use .= gettext('OpenVPN Client') . "\n";
        }

        if (Certs::is_ipsec_cert($cert->getAttributes()["uuid"])) {
            $in_use .= gettext('IPsec Tunnel') . "\n";
        }
        return $in_use;
    }
}

