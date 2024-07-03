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

namespace OPNsense\Mvc;

class Security
{
    private Session $session;
    private Request $request;

    /**
     * Generate a random URL-safe base64 string.
     * Usable base64 characters according to https://www.ietf.org/rfc/rfc3548.txt
     */
    public function base64Safe($len = 16)
    {
        return rtrim(strtr(base64_encode(random_bytes($len)), "+/", "-_"), '=');
    }

    /**
     * @param Session $session session object to use
     * @param Request $request request object
     */
    public function __construct(Session $session, Request $request)
    {
        $this->session = $session;
        $this->request = $request;
    }

    /**
     * @param bool $new when not found, create new
     * @return string|null token
     */
    public function getToken(bool $new = true): ?string
    {
        $token = $this->session->get('$PHALCON/CSRF$');
        if (empty($token) && $new) {
            $token = $this->base64Safe();
            $this->session->set('$PHALCON/CSRF$', $token);
        }
        return $token;
    }

    /**
     * @param bool $new when not found, create new
     * @return string|null name of the token
     */
    public function getTokenKey(bool $new = true): ?string
    {
        $token = $this->session->get('$PHALCON/CSRF/KEY$');
        if (empty($token) && $new) {
            $token = $this->base64Safe();
            $this->session->set('$PHALCON/CSRF/KEY$', $token);
        }
        return $token;
    }

    /**
     * @param string|null $tokenKey parameter name used to store the csrf token
     * @param string|null $tokenValue value to check against
     * @return bool true when CSRF token is valid
     */
    public function checkToken(?string $tokenKey = null, ?string $tokenValue = null): bool
    {
        $key = $tokenKey ?? $this->getTokenKey(false);
        if (empty($key)) {
            return false;
        }
        $value = $tokenValue ?? $_POST[$tokenKey];
        return !empty($value) && $value === $this->getToken();
    }
}
