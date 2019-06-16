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

class WebUIKeyStore extends BaseObject implements TokenKeyStore
{
    private $config;
    private $jwt;
    const CERTICICATE_XML_NAME = 'crt';
    const PRIVATE_KEY_XML_NAME = 'prv';

    public function __construct()
    {
        $this->config = Config::getInstance()->toArray();
        $this->jwt = isset($this->config['system']['jwt']) ? $this->config['system']['jwt'] : null;
    }

    public function getAsymmetricPrivateKey()
    {
        if (empty($this->jwt)) {
            return null;
        }
        return array_key_exists('private', $this->jwt) ?
            $this->resolveCertificate($this->jwt['private'], WebUIKeyStore::PRIVATE_KEY_XML_NAME) : null;
    }
    public function getAsymmetricPublicKey()
    {
        if (empty($this->jwt)) {
            return null;
        }

        return array_key_exists('public', $this->jwt) ?
            $this->resolveCertificate($this->jwt['public'], WebUIKeyStore::CERTICICATE_XML_NAME) : null;
    }

    public function getSymmetricKey()
    {
        if (empty($this->jwt)) {
            return null;
        }
        return array_key_exists('symmetric', $this->jwt) ? $this->jwt['symmetric'] : null;
    }
}