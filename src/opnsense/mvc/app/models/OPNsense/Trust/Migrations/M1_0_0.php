<?php
/**
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

namespace OPNsense\Trust\Migrations;

use OPNsense\Base\BaseModelMigration;
use OPNsense\Core\Config;
use OPNsense\Proxy\Proxy;
use OPNsense\Trust\Trust;

class M1_0_0 extends BaseModelMigration
{
    public function run($model)
    {
        parent::run($model);

        // import settings from legacy config section
        $config = Config::getInstance()->object();

        foreach ($config->ca as $ca) {
            $new_ca = $model->cas->ca->add();
            $new_ca->refid = $ca->refid->__toString();
            $new_ca->descr = $ca->descr->__toString();
            $new_ca->crt = $ca->crt->__toString();
            if (isset($ca->prv)) {
                $new_ca->prv = $ca->prv->__toString();
            }
            if (isset($ca->serial)) {
                $new_ca->serial = $ca->serial->__toString();
            }
        }

        foreach ($config->ca as $ca) {
            if (isset($ca->caref)) {
                foreach ($config->OPNsense->Trust->cas->ca as $new_ca) {
                    if ($new_ca->refid->__toString() == $ca->refid->__toString()) {
                        break;
                    }
                }
                foreach ($config->OPNsense->Trust->cas->ca as $node) {
                    if ($node->refid->__toString() == $ca->caref->__toString()) {
                        $new_ca->cauuid = $node->cauuid;
                    }
                }
            }
        }

        $model->serializeToConfig();
        new Trust();

        foreach ($config->cert as $cert) {
            $new_cert = $model->certs->cert->add();
            $new_cert->refid = $cert->refid->__toString();
            $new_cert->descr = $cert->descr->__toString();
            $new_cert->crt = $cert->crt->__toString();
            if (isset($cert->prv)) {
                $new_cert->prv = $cert->prv->__toString();
            }
            if (isset($cert->csr)) {
                $new_cert->csr = $cert->csr->__toString();
            }

            if (isset($cert->caref)) {
                foreach ($model->cas->ca->getChildren() as $uuid => $ca) {
                    if ($ca->refid->__toString() == $cert->caref->__toString()) {
                        $new_cert->cauuid = $uuid;
                        break;
                    }
                }
            }
        }

        $model->serializeToConfig();
        new Trust();

        foreach ($config->crl as $crl) {
            $new_crl = $model->crls->crl->add();
            $new_crl->refid = $crl->refid->__toString();
            $new_crl->descr = $crl->descr->__toString();
            $new_crl->method = $crl->crlmethod->__toString();
            $new_crl->serial = $crl->serial->__toString();
            if (isset($crl->lifitime)) {
                $new_crl->lifitime = $crl->lifitime->__toString();
            }
            if (isset($crl->text)) {
                $new_crl->text = $crl->text->__toString();
            }

            foreach ($model->cas->ca->getChildren() as $uuid => $ca) {
                if ($ca->refid->__toString() == $crl->caref->__toString()) {
                    $new_crl->cauuid = $uuid;
                }
            }

            $model->serializeToConfig();
            new Trust();

            foreach ($crl->cert as $cert) {
                $new_cert_crl = $model->crl_certs->cert->add();
                $new_cert_crl->refid = $cert->refid->__toString();
                $new_cert_crl->descr = $cert->descr->__toString();
                $new_cert_crl->crt = $cert->crt->__toString();
                if (isset($cert->prv)) {
                    $new_cert_crl->prv = $cert->prv->__toString();
                }
                $new_cert_crl->reason = $cert->reason->__toString();
                $new_cert_crl->revoke_time = $cert->revoke_time->__toString();
                $new_cert_crl->crluuid = $new_crl->getAttributes()["uuid"];

                foreach ($model->cas->ca->getChildren() as $uuid => $ca) {
                    if ($ca->refid->__toString() == $cert->caref->__toString()) {
                        $new_cert_crl->cauuid = $uuid;
                    }
                }
            }
        }
        $model->serializeToConfig();
        new Trust();

        $sslcertificate = (new Proxy())->forward->sslcertificate->__toString();
        if (!empty($sslcertificate)) {
            foreach ($model->cas->ca->getChildren() as $uuid => $ca) {
                $refid = $ca->refid->__toString();
                if (!empty($refid) && $refid == $sslcertificate) {
                    Config::getInstance()->object()->OPNsense->proxy->forward->sslcertificate = $uuid;
                    break;
                }
            }
        }

        $config = Config::getInstance()->toArray(array_flip([
            "zone",
            "user",
            "cert",
            "openvpn-server",
            "openvpn-client",
            "phase1",
            "authserver"
        ]));

        foreach ($config["OPNsense"]["captiveportal"]["zones"]["zone"] as $key => $zone) {
            if (!empty($zone["certificate"])) {
                foreach ($model->certs->cert->getChildren() as $uuid => $cert) {
                    $refid = $cert->refid->__toString();
                    if (!empty($refid) && $refid == $zone["certificate"]) {
                        Config::getInstance()->object()->OPNsense->captiveportal->zones->zone[$key]->certificate = $uuid;
                        break;
                    }
                }
            }
        }

        $certref = Config::getInstance()->object()->system->webgui->{"ssl-certref"}->__toString();
        if (!empty($certref)) {
            foreach ($model->certs->cert->getChildren() as $uuid => $cert) {
                $refid = $cert->refid->__toString();
                if (!empty($refid) && $refid == $certref) {
                    Config::getInstance()->object()->system->webgui->{"ssl-certref"} = $uuid;
                    break;
                }
            }
        }

        foreach ($config["system"]["user"] as $user_id => $user) {
            if (isset($user["cert"])) {
                foreach ($user["cert"] as $cert_id => $cert_refid) {
                    foreach ($model->certs->cert->getChildren() as $uuid => $cert) {
                        $refid = $cert->refid->__toString();
                        if (!empty($refid) && $refid == $cert_refid) {
                            Config::getInstance()->object()->system->user[$user_id]->cert[$cert_id] = $uuid;
                            break;
                        }
                    }
                }
            }
        }

        if (isset($config["openvpn"]["openvpn-server"])) {
            foreach ($config["openvpn"]["openvpn-server"] as $key => $ovpns) {
                foreach ($model->certs->cert->getChildren() as $uuid => $cert) {
                    $refid = $cert->refid->__toString();
                    if (!empty($refid) && $refid == $ovpns["certref"]) {
                        Config::getInstance()->object()->openvpn->{"openvpn-server"}[$key]->certref = $uuid;
                        break;
                    }
                }
                foreach ($model->cas->ca->getChildren() as $uuid => $ca) {
                    $refid = $ca->refid->__toString();
                    if (!empty($refid) && $refid == $ovpns["caref"]) {
                        Config::getInstance()->object()->openvpn->{"openvpn-server"}[$key]->caref = $uuid;
                        break;
                    }
                }
                foreach ($model->crls->crl->getChildren() as $uuid => $crl) {
                    $refid = $crl->refid->__toString();
                    if (!empty($refid) && $refid == $ovpns["crlref"]) {
                        Config::getInstance()->object()->openvpn->{"openvpn-server"}[$key]->crlref = $uuid;
                        break;
                    }
                }
            }
        }

        if (isset($config["openvpn"]["openvpn-client"])) {
            foreach ($config["openvpn"]["openvpn-client"] as $key => $ovpnc) {
                foreach ($model->certs->cert->getChildren() as $uuid => $cert) {
                    $refid = $cert->refid->__toString();
                    if (!empty($refid) && $refid == $ovpnc["certref"]) {
                        Config::getInstance()->object()->openvpn->{"openvpn-client"}[$key]->certref = $uuid;
                        break;
                    }
                }
                foreach ($model->cas->ca->getChildren() as $uuid => $ca) {
                    $refid = $ca->refid->__toString();
                    if (!empty($refid) && $refid == $ovpnc["caref"]) {
                        Config::getInstance()->object()->openvpn->{"openvpn-client"}[$key]->caref = $uuid;
                        break;
                    }
                }
            }
        }

        if (isset($config["ipsec"]["phase1"])) {
            foreach ($config["ipsec"]["phase1"] as $key => $ipsec) {
                foreach ($model->certs->cert->getChildren() as $uuid => $cert) {
                    $refid = $cert->refid->__toString();
                    if (!empty($refid) && $refid == $ipsec["certref"]) {
                        Config::getInstance()->object()->ipsec->phase1[$key]->certref = $uuid;
                        break;
                    }
                }
                foreach ($model->cas->ca->getChildren() as $uuid => $ca) {
                    $refid = $ca->refid->__toString();
                    if (!empty($refid) && $refid == $ipsec["caref"]) {
                        Config::getInstance()->object()->ipsec->phase1[$key]->caref = $uuid;
                        break;
                    }
                }
            }
        }

        if (isset($config["OPNsense"]["AcmeClient"]["certificates"]["certificate"])) {
            foreach ($config["OPNsense"]["AcmeClient"]["certificates"]["certificate"] as $key => $acme) {
                foreach ($model->certs->cert->getChildren() as $uuid => $cert) {
                    $refid = $cert->refid->__toString();
                    if (!empty($refid) && $refid == $acme["certRefId"]) {
                        Config::getInstance()->object()->OPNsense->AcmeClient->certificates->certificate[$key]->certRefId = $uuid;
                        break;
                    }
                }
            }
        }

        if (isset($config["OPNsense"]["HAProxy"]["frontends"]["frontend"])) {
            foreach ($config["OPNsense"]["HAProxy"]["frontends"]["frontend"] as $key => $haproxy) {
                foreach ($model->certs->cert->getChildren() as $uuid => $cert) {
                    $refid = $cert->refid->__toString();
                    if (!empty($refid)) {
                        if ($refid == $haproxy["ssl_certificates"]) {
                            Config::getInstance()->object()->OPNsense->HAProxy->frontends->frontend[$key]->ssl_certificates = $uuid;
                        }
                        if ($refid == $haproxy["ssl_certificssl_default_certificateates"]) {
                            Config::getInstance()->object()->OPNsense->HAProxy->frontends->frontend[$key]->ssl_default_certificate = $uuid;
                        }
                    }
                }
            }
        }

        if (isset(Config::getInstance()->object()->OPNsense->freeradius->eap)) {
            $objEap = Config::getInstance()->object()->OPNsense->freeradius->eap;
            foreach ($model->cas->ca->getChildren() as $uuid => $ca) {
                $refid = $ca->refid->__toString();
                if (!empty($refid) && $refid == $objEap->ca->__toString()) {
                    Config::getInstance()->object()->OPNsense->freeradius->eap->ca = $uuid;
                }
            }
            foreach ($model->certs->cert->getChildren() as $uuid => $cert) {
                $refid = $cert->refid->__toString();
                if (!empty($refid) && $refid == $objEap->certificate->__toString()) {
                    Config::getInstance()->object()->OPNsense->freeradius->eap->certificate = $uuid;
                }
            }
            foreach ($model->crls->crl->getChildren() as $uuid => $crl) {
                $refid = $crl->refid->__toString();
                if (!empty($refid) && $refid == $objEap->crl->__toString()) {
                    Config::getInstance()->object()->OPNsense->freeradius->eap->crl = $uuid;
                }
            }
        }

        if (isset($config["authserver"])) {
            foreach ($config["authserver"] as $key => $authserver) {
                foreach ($model->cas->ca->getChildren() as $uuid => $ca) {
                    $refid = $ca->refid->__toString();
                    if (!empty($refid) && isset($authserver["ldap_caref"]) && $authserver["ldap_caref"] == $refid) {
                        Config::getInstance()->object()->authserver[$key]->ldap_caref = $uuid;
                        break;
                    }
                }
            }
        }

        Config::getInstance()->save();
    }
}
