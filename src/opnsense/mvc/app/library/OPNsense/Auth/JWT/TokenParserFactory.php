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

use OPNsense\Core\Config;


class TokenParserFactory extends BaseObject
{

    private $config;

    public function __construct()
    {
        $this->config = Config::getInstance()->toArray();
    }

    public function makeTokenInstance(string $token, TokenKeyStore $key_configuration) {
        $parts = explode(".", $token);
        if (count($parts) <= 1) {
            return null;
        }

        $b64_data = $this->b64UrlDecode($parts[0]);
        if (empty($b64_data)) {
            return null;
        }

        $format = json_decode($b64_data, true);
        if (!is_array($format) || empty($format['alg'])) {
            return null;
        }

        $token = null;
        $tokenparser = null;
        switch ($format['alg']) {
            case 'RS256':
            case 'RS384':
            case 'RS512':
                $tokenparser = $this->parseRSA($format['alg'], $key_configuration);
                break;
            case 'HS256':
            case 'HS384':
            case 'HS512':
                $tokenparser = $this->parseHMAC($format['alg']);
                break;
        }

        if ($tokenparser != null) {
            return $tokenparser->parseToken($token);
        }
    }

    private function parseRSA($format, TokenKeyStore $key_store) {
        $tokenparser = null;
        $private = $key_store->getAsymmetricPrivateKey();
        $public = $key_store->getAsymmetricPublicKey();
        if ($private == null || $public == null) {
            return null;
        }

        switch ($format) {
            case 'RS256':
                $tokenparser = new RS256($private, $public);
                break;
            case 'RS384':
                $tokenparser = new RS384($private, $public);
                break;
            case 'RS512':
                $tokenparser = new RS512($private, $public);
                break;
        }
        return $tokenparser;
    }
    private function parseHMAC($format, TokenKeyStore $key_configuration) {
        $tokenparser = null;
        $key = $key_configuration->getSymmetricKey();

        switch ($format) {
            case 'RS256':
                $tokenparser = new HS256($key);
                break;
            case 'RS384':
                $tokenparser = new HS384($key);
                break;
            case 'RS512':
                $tokenparser = new HS512($key);
                break;
        }
        return $tokenparser;
    }
}