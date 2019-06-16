<?php
/*
 * Copyright (C) 2019 Fabian Franz
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

namespace OPNsense\Auth\JWT;

/**
 * Class JWTToken - Base class for all JSON Web Token classes
 * @package OPNsense\Auth\JWT
 */
abstract class JWTToken extends BaseObject
{
    protected $signature_value;
    protected $type_hash;
    protected $claims;
    protected $verify_string;
    private $verifier;

    /**
     * add a new verifier
     * @param ClaimVerifier $verifier a verifier used to validate claims
     */
    public function addVerifier(ClaimVerifier $verifier) {
        if (!is_array($this->verifier)) {
            $this->verifier = array();
        }
        $this->verifier[] = $verifier;
    }

    /**
     * @param $token string representation
     * @return bool if the token is valid
     * @throws \Exception when an invalid token is given, that cannot be verified (syntax error)
     */
    public function parseToken($token) {
        if (substr_count($token, '.') != 2){
            throw new \Exception("Invalid JWT given");
        }


        $split = explode('.', $token);
        $this->type_hash = json_decode($this->b64UrlDecode($split[0]), true);
        $this->claims = json_decode($this->b64UrlDecode($split[1]), true);
        if (empty($split[2])) {
            $this->signature_value = null;
        } else {
            $this->signature_value = $this->b64UrlDecode($split[2]);
        }

        $this->verify_string = $split[0] . '.' . $split[1];

        if ($this->verify()) {
            return $this->verify_claims();
        }
        return false;
    }

    public function get_claims()
    {
        return $this->claims;
    }

    /**
     * run the verifier
     * @return bool true if the claims are valid, otherwise false
     */
    public function verify_claims() : bool {
        $now = time();
        $c = &$this->claims; // short - don't want to write that

        if (array_key_exists('exp', $c) && $c['exp'] < $now) {
            return false;
        }
        if (array_key_exists('nbf', $c) && $c['nbf'] > $now) {
            return false;
        }

        if (is_array($this->verifier)) {
            foreach ($this->verifier as $verify) {
                if (!$verify->verify($this->claims)) {
                    return false;
                }
            }
        }

        return true;
    }


    public abstract function verify(): bool;
    public abstract function sign($claims): string;
}