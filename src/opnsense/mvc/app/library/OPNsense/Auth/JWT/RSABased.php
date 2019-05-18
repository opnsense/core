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


abstract class RSABased extends JWTToken
{

    private $private_key;
    private $public_key;
    function __construct($pem_private, $pem_public, $private_key_password = '')
    {
        if (!empty($pem_private)) {
            $this->private_key = openssl_pkey_get_private($pem_private, $private_key_password);
        }
        if (!empty($pem_public)) {
            $this->public_key = openssl_pkey_get_public($pem_public);
        }
    }

    /**
     * @return resource
     */
    public function getPrivateKey()
    {
        return $this->private_key;
    }

    /**
     * @param resource $private_key
     */
    public function setPrivateKey($private_key): void
    {
        $this->private_key = $private_key;
    }

    /**
     * @return resource
     */
    public function getPublicKey()
    {
        return $this->public_key;
    }

    /**
     * @param resource|string $public_key
     */
    public function setPublicKey($public_key): void
    {
        if (is_resource($public_key)) {
            $this->public_key = $public_key;
        } elseif (is_string($public_key)) {
            $this->public_key = openssl_pkey_get_public($public_key);
        }
    }
}