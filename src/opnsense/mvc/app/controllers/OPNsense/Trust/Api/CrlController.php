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

use OPNsense\Base\ApiControllerBase;
use OPNsense\Base\UserException;
use OPNsense\Core\Config;
use OPNsense\Trust\Store as CertStore;

/**
 * Class CrlController
 * @package OPNsense\Trust\Api
 */
class CrlController extends ApiControllerBase
{
    private static $status_codes = [
        '0' => 'unspecified',
        '1' => 'keyCompromise',
        '2' => 'cACompromise',
        '3' => 'affiliationChanged',
        '4' => 'superseded',
        '5' => 'cessationOfOperation',
        '6' => 'certificateHold',
    ];

    private function phpseclib_autoload($namespace, $dir)
    {
        $split = '\\';
        $ns = trim($namespace, DIRECTORY_SEPARATOR . $split);

        return spl_autoload_register(
            function ($class) use ($ns, $dir, $split) {
                $prefix = $ns . $split;
                $base_dir = $dir . DIRECTORY_SEPARATOR;
                $len = strlen($prefix);
                if (strncmp($prefix, $class, $len)) {
                    return;
                }

                $relative_class = substr($class, $len);

                $file = $base_dir .
                    str_replace($split, DIRECTORY_SEPARATOR, $relative_class) .
                    '.php';

                if (file_exists($file)) {
                    require_once $file;
                }
            }
        );
    }

    public function initialize()
    {
        $this->phpseclib_autoload('ParagonIE\ConstantTime', '/usr/local/share/phpseclib/paragonie');
        $this->phpseclib_autoload('phpseclib3', '/usr/local/share/phpseclib');

        parent::initialize();
    }

    public function searchAction()
    {
        $this->sessionClose();
        $config = Config::getInstance()->object();
        $items = [];
        foreach ($config->ca as $node) {
            $items[(string)$node->refid] =  ['descr' => (string)$node->descr, 'refid' =>  (string)$node->refid];
        }
        foreach ($config->crl as $node) {
            if (isset($items[(string)$node->caref])) {
                $items[(string)$node->caref]['crl_descr'] = (string)$node->descr;
            }
        }
        return $this->searchRecordsetBase(array_values($items));
    }

    /**
     * fetch (a new) revocation list for a given autority.
     */
    public function getAction($caref)
    {
        if ($this->request->isGet() && !empty($caref)) {
            $config = Config::getInstance()->object();
            $found = false;
            foreach ($config->ca as $node) {
                if ((string)$node->refid == $caref) {
                    $found = true;
                    break;
                }
            }
            if ($found) {
                $result = ['caref' => $caref, 'descr' => '', 'serial' => '0', 'lifetime' => '9999'];
                foreach ($config->crl as $node) {
                    if ((string)$node->caref == $caref) {
                        $result['descr'] = (string)$node->descr;
                        $result['serial'] = (string)$node->serial ?? '0';
                        $result['lifetime'] = (string)$node->lifetime ?? '9999';
                    }
                }
                $certs = [];
                foreach ($config->cert as $node) {
                    if ((string)$node->caref == $caref) {
                        $certs[(string)$node->refid] = [
                            'code' => null,
                            'descr' => (string)$node->descr
                        ];
                    }
                }
                $crlmethod = 'internal';
                foreach ($config->crl as $node) {
                    if ((string)$node->caref == $caref) {
                        foreach ($node->cert as $cert) {
                            if (!empty((string)$cert->refid)) {
                                $certs[(string)$cert->refid] = [
                                    'code' => (string)$cert->reason == '-1' ? '0' : (string)$cert->reason,
                                    'descr' => (string)$cert->descr
                                ];
                            }
                        }
                        $crlmethod = (string)$node->crlmethod;
                        $result['text'] = !empty((string)$node->text) ? base64_decode((string)$node->text) : '';
                    }
                }
                $result['crlmethod'] = [
                    'internal' => [
                        'value' => gettext('Internal'),
                        'selected' => $crlmethod == 'internal' ? '1' : '0'
                    ],
                    'existing' => [
                        'value' => gettext('Import existing'),
                        'selected' => $crlmethod == 'existing' ? '1' : '0'
                    ],
                ];
                for ($i = 0; $i < count(self::$status_codes); $i++) {
                    $code = (string)$i;
                    $result['revoked_reason_' . $code] = [];
                    foreach ($certs as $ref => $data) {
                        $result['revoked_reason_' . $code][$ref] = [
                            'value' => $data['descr'],
                            'selected' => $data['code'] === $code ? '1' : '0'
                        ];
                    }
                }

                return ['crl' => $result];
            }
            return ['caref' => '', 'descr' => ''];
        }
    }

    /**
     * set crl for a certificate authority, mimicking standard model operations
     * (which we can not use due to the nested structure of the CRL's)
     */
    public function setAction($caref)
    {
        if ($this->request->isPost() && !empty($caref)) {
            Config::getInstance()->lock();
            $config = Config::getInstance()->object();
            $payload = $_POST['crl'] ?? [];
            $validations = [];
            if (!in_array($payload['crlmethod'], ['internal', 'existing'])) {
                $validations['crl.crlmethod'] = sprintf(gettext('Invalid method %s'), $payload['crlmethod']);
            }
            if (!preg_match('/^(.){1,255}$/', $payload['descr'] ?? '')) {
                $validations['crl.descr'] = gettext('Description should be a string between 1 and 255 characters.');
            }
            if ($payload['crlmethod'] == 'existing') {
                $x509 = new \phpseclib3\File\X509();
                if (empty($x509->loadCRL((string)$payload['text']))) {
                    $validations['crl.text'] = gettext('Invalid CRL provided.');
                }
            }

            $ca_crt_str = false;
            $ca_key_str = false;
            foreach ($config->ca as $node) {
                if ((string)$node->refid == $caref) {
                    $ca_crt_str = !empty((string)$node->prv) ? base64_decode((string)$node->crt) : false;
                    $ca_key_str = !empty((string)$node->prv) ? base64_decode((string)$node->prv) : false;
                    break;
                }
            }
            $ca_cert = new \phpseclib3\File\X509();
            if (!$ca_crt_str) {
                $validations['crl.caref'] = gettext('Certificate does not seem to exist');
            } elseif (!$ca_key_str) {
                $validations['crl.caref'] = gettext('Certificate private key missing');
            } else {
                /* Load in the CA's cert */
                $ca_cert->loadX509($ca_crt_str);
                if (!$ca_cert->validateDate()) {
                    $validations['crl.caref'] = gettext('Cert revocation error: CA certificate invalid: invalid date');
                } else {
                    /* get the private key to sign the new (updated) CRL */
                    try {
                        $ca_key = \phpseclib3\Crypt\PublicKeyLoader::loadPrivateKey($ca_key_str);
                        if (method_exists($ca_key, 'withPadding')) {
                            $ca_key = $ca_key->withPadding(
                                \phpseclib3\Crypt\RSA::ENCRYPTION_PKCS1 | \phpseclib3\Crypt\RSA::SIGNATURE_PKCS1
                            );
                        }
                        $ca_cert->setPrivateKey($ca_key);
                    } catch (\phpseclib3\Exception\NoKeyLoadedException $e) {
                        $validations['crl.caref'] = gettext('Cert revocation error: Unable to load CA private key');
                    }
                }
            }
            $x509_crl = new \phpseclib3\File\X509();
            if (empty($validations['crl.caref'])) {
                    /*
                    * create empty CRL. A quirk with phpseclib is that in order to correctly sign
                    * a new CRL, a CA must be loaded using a separate X509 container, which is passed
                    * to signCRL(). However, to validate the resulting signature, the original X509
                    * CRL container must load the same CA using loadCA() with a direct reference
                    * to the CA's public cert.
                    */
                    $x509_crl->loadCA($ca_crt_str);
                    $x509_crl->loadCRL($x509_crl->saveCRL($x509_crl->signCRL($ca_cert, $x509_crl)));

                    /* Now validate the CRL to see if everything went well */
                try {
                    if (!$x509_crl->validateSignature(false)) {
                        $validations['crl.caref'] = gettext('Cert revocation error: CRL signature invalid');
                    }
                } catch (Exception $e) {
                    $validations['crl.caref'] = gettext('Cert revocation error: CRL signature invalid') . " " . $e;
                }
            }

            if (!empty($validations)) {
                Config::getInstance()->unlock();
                return ['status' => 'failed', 'validations' => $validations];
            } else {
                $revoked_refs = [];
                if ($payload['crlmethod'] == 'internal') {
                    for ($i = 0; $i <= count(self::$status_codes); $i++) {
                        $fieldname = 'revoked_reason_' . $i;
                        foreach (explode(',', $payload[$fieldname] ?? '') as $refid) {
                            if (!empty($refid)) {
                                $revoked_refs[$refid] = (string)$i;
                            }
                        }
                    }
                }
                $crl = null;
                $to_delete = [];
                foreach ($config->crl as $node) {
                    if ((string)$node->caref == $caref) {
                        if ($crl !== null) {
                            /* When duplicate CRL's exist, remove all but the first */
                            $to_delete[] = $node;
                        } else {
                            $crl = $node;
                        }
                    }
                }
                foreach ($to_delete as $cert) {
                    $dom = dom_import_simplexml($cert);
                    $dom->parentNode->removeChild($dom);
                }

                $last_crl = null;
                if ($crl === null) {
                    $last_crl = current($config->xpath('//opnsense/crl[last()]'));
                    if ($last_crl) {
                        $crl = simplexml_load_string('<crl/>');
                    } else {
                        $crl = $config->addChild('crl');
                    }
                    $crl->refid = uniqid();
                }
                if ((string)$node->crlmethod == 'existing') {
                    $crl->text = base64_encode((string)$payload['text']);
                }
                $crl->caref = (string)$caref;
                $crl->lifetime = (string)$payload['lifetime'];
                $crl->descr = (string)$payload['descr'];
                $crl->serial = !empty($payload['serial']) ? $payload['serial'] : $crl->serial;
                $crl->serial = ((int)((string)$crl->serial)) + 1;
                $to_delete = [];
                $crl_certs = [];
                foreach ($crl->cert as $cert) {
                    if (!isset($revoked_refs[(string)$cert->refid])) {
                        $to_delete[] = $cert;
                    } else {
                        $cert->reason = $revoked_refs[(string)$cert->refid];
                        $crl_certs[] = $cert;
                        unset($revoked_refs[(string)$cert->refid]);
                    }
                }
                foreach ($to_delete as $cert) {
                    $dom = dom_import_simplexml($cert);
                    $dom->parentNode->removeChild($dom);
                }
                foreach ($config->cert as $cert) {
                    if (isset($revoked_refs[(string)$cert->refid])) {
                        $tmp = $crl->addChild('cert');
                        $tmp->refid = (string)$cert->refid;
                        $tmp->descr = (string)$cert->descr;
                        $tmp->caref = (string)$cert->caref;
                        $tmp->crt = (string)$cert->crt;
                        $tmp->prv = (string)$cert->prv;
                        $tmp->revoke_time = (string)time();
                        $tmp->reason = $revoked_refs[(string)$cert->refid];
                        $crl_certs[] = $tmp;
                    }
                }
                if ($payload['crlmethod'] == 'internal') {
                    /* add all cert serial numbers to crl */
                    foreach ($crl_certs as $cert) {
                        $tmp = @openssl_x509_parse(base64_decode((string)$cert->crt));
                        if ($tmp !== false && isset($tmp['serialNumber'])) {
                            $x509_crl->setRevokedCertificateExtension(
                                (string)$tmp['serialNumber'],
                                'id-ce-cRLReasons',
                                self::$status_codes[(string)$cert->reason]
                            );
                        }
                    }
                    $x509_crl->setSerialNumber((string)$crl->serial, 10);
                    /* consider dates after 2050 lifetime in GeneralizedTime format (rfc5280#section-4.1.2.5) */
                    $date = new \DateTimeImmutable(
                        '+' . (string)$crl->lifetime . ' days',
                        new \DateTimeZone(@date_default_timezone_get())
                    );
                    $x509_crl->setEndDate((int)$date->format("Y") < 2050 ? $date : 'lifetime');
                    $new_crl = $x509_crl->signCRL($ca_cert, $x509_crl);
                    $crl->text = base64_encode($x509_crl->saveCRL($new_crl) . PHP_EOL);
                }

                if ($last_crl) {
                    /* insert new item after last crl */
                    $target = dom_import_simplexml($last_crl);
                    $insert = $target->ownerDocument->importNode(dom_import_simplexml($crl), true);
                    if ($target->nextSibling) {
                        $target->parentNode->insertBefore($insert, $target->nextSibling);
                    } else {
                        $target->parentNode->appendChild($insert);
                    }
                }
                Config::getInstance()->save();
                return ['status' => 'saved'];
            }
        }
        return ['status' => 'failed'];
    }


    public function rawDumpAction($caref)
    {
        $payload = $this->getAction($caref);
        if (!empty($payload['crl'])) {
            if (!empty($payload['crl']['text'])) {
                return CertStore::dumpCRL($payload['crl']['text']);
            }
        }
        return [];
    }

    /**
     * for demonstration purposes, we need a CA index file as specified
     * at https://pki-tutorial.readthedocs.io/en/latest/cadb.html
     */
    function getOcspInfoDataAction($caref)
    {
        $config = Config::getInstance()->object();

        $revoked = [];
        foreach ($config->crl as $crl) {
            if ((string)$crl->caref == $caref) {
                foreach ($crl->cert as $cert) {
                    if (!empty((string)$cert->revoke_time)) {
                        $dt = new \DateTime("@" . $cert->revoke_time);
                        $revoked[(string)$cert->refid] = $dt->format("ymdHis") . "Z";
                    }
                }
            }
        }
        $result = '';
        foreach ($config->cert as $cert) {
            if ((string)$cert->caref == $caref) {
                $refid = (string)$cert->refid;
                $x509 = openssl_x509_parse(base64_decode($cert->crt));
                $valid_to = date('Y-m-d H:i:s', $x509['validTo_time_t']);
                $rev_date = '';
                if (!empty($revoked[$refid])) {
                    $status = 'R';
                    $rev_date = $revoked[$refid];
                } elseif ($x509['validTo_time_t'] < time()) {
                    $status = 'E';
                } else {
                    $status = 'V';
                }
                $result .= sprintf(
                    "%s\t%s\t%s\t%s\tunknown\t%s\n",
                    $status,                    // Certificate status flag (V=valid, R=revoked, E=expired).
                    $x509['validTo'],           // Certificate expiration date in YYMMDDHHMMSSZ format.
                    $rev_date,                  // Certificate revocation date in YYMMDDHHMMSSZ[,reason] format.
                    $x509['serialNumberHex'],   // Certificate serial number in hex.
                    $x509['name']               // Certificate distinguished name.
                );
            }
        }
        return ['payload' => $result];
    }
}
