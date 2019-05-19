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


abstract class HMACBased extends JWTToken
{
    private $key;


    function __construct($key)
    {
        if (empty($key)) {
            throw new \Exception("no key given");
        }
        $this->key = $key;
    }

    public function verify(): bool
    {
        $hmac = $this->createHMAC($this->verify_string);
        return $hmac == $this->signature_value && !empty($this->signature_value);
    }

    public function sign($claims): string
    {
        $prefix = $this->b64UrlEncode(json_encode(array('typ' => 'jwt', 'alg' => $this->getTypeName())));
        $claims = $this->b64UrlEncode(json_encode($claims));

        $to_sign = $prefix . '.' . $claims;
        $signature = $this->createHMAC($to_sign);
        return $to_sign . '.' . $this->b64UrlEncode($signature);

    }

    private function createHMAC($to_sign) {
        return hash_hmac($this->getHash() , $to_sign , $this->key , true );
    }

    abstract public function getHash();
    abstract public function getTypeName();
}