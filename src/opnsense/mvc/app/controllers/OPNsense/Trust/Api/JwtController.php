<?php
/**
 *    Copyright (C) 2019 Fabian Franz
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


use OPNsense\Auth\JWT\HS256;
use OPNsense\Auth\JWT\HS384;
use OPNsense\Auth\JWT\HS512;
use OPNsense\Auth\JWT\JWTToken;
use OPNsense\Auth\JWT\RS256;
use OPNsense\Auth\JWT\RS384;
use OPNsense\Auth\JWT\RS512;
use OPNsense\Core\Config;
use OPNsense\Trust\JWT;

class JwtController extends \OPNsense\Base\ApiControllerBase {

    public function create_templateAction() {
        $jwt = new JWT();
        return array('jwt' => $jwt->creator->Add()->getNodes());
    }

    // some code has been taken over from ApiMutableControllerBase
    public function create_tokenAction() {
        $result = array("result" => "failed");
        if ($this->request->isPost() && $this->request->hasPost('jwt')) {
            $jwt = new JWT();
            $node = $jwt->creator->Add();
            $node->setNodes($this->request->getPost('jwt'));
            $messages = $jwt->performValidation();


            foreach ($messages as $msg) {
                if (!array_key_exists("validations", $result)) {
                    $result["validations"] = array();
                    $result["result"] = "failed";
                }
                $result['validations'][] = str_replace($node->__reference, 'jwt', $msg->getField());
            }

            if (!array_key_exists('validatons', $result)) {
                $token = $this->getTokenInstance($node);
                if ($token != null) {
                    $claims = $this->prepare_claims($node);
                    try {
                        $result['token'] = $token->sign($claims);
                        $result['result'] = 'success';
                    } catch (\Exception $e) {
                        // ignore
                    }
                }
            }
        }
        return $result;
    }

    private function getTokenInstance($node) : ?JWTToken {
        $type = (string)$node->signature_type;
        $key = (string)$node->key;
        $cert = $this->findCertificate($node->rsa_key);
        $private = '';
        $public = '';
        if ($cert != null) {
            $private = base64_decode((string)$cert->prv);
            $public = base64_decode((string)$cert->crt);
        }
        try {
            switch ($type) {
                case 'RS256':
                    return new RS256($private, $public);
                case 'RS384':
                    return new RS384($private, $public);
                case 'RS512':
                    return new RS512($private, $public);
                case 'HS256':
                    return new HS256($key);
                case 'HS384':
                    return new HS384($key);
                case 'HS512':
                    return new HS512($key);
                default:
                    return null;
            }
        } catch (\Exception $exception) {
            // do nothing
        }
        return null;
    }

    private function prepare_claims($node) : array
    {
        $claims = array();
        $this->prepare_claim_string($claims, 'iss', $node->issuer);
        $this->prepare_claim_string($claims, 'aud', $node->audience);
        $this->prepare_claim_string($claims, 'sub', $node->subject);
        $this->prepare_claim_date($claims, 'nbf', $node->not_before);
        $this->prepare_claim_date($claims, 'exp', $node->expire);
        $this->prepare_claim_string($claims, 'jti', $node->generateUUID());
        return $claims;
    }
    private function prepare_claim_string(array &$claims, string $claim_name, $field) {
        $str_field = (string)$field;
        if (!empty($str_field)) {
            $elements = explode(',', $str_field);
            if (count($elements) > 1) {
                $claims[$claim_name] = $elements;
            } else {
                $claims[$claim_name] = $str_field;
            }
        }
    }
    private function prepare_claim_date(array &$claims, string $claim_name, $field) {
        $str_field = (string)$field;
        if (!empty($str_field)) {
            $claims[$claim_name] = strtotime($str_field);
        }
    }

    private function findCertificate($ref) {
        $configObj = Config::getInstance()->object();
        foreach ($configObj->cert as $cert) {
            if ((string)$cert->refid == $ref) {
                return $cert;
            }
        }
    }

}