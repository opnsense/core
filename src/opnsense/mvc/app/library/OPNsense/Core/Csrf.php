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

namespace OPNsense\Core;

class Csrf
{
    /**
     * Generate a random URL-safe base64 string.
     * Usable base64 characters according to https://www.ietf.org/rfc/rfc3548.txt
     */
    public function base64Safe($len=16)
    {
        return rtrim(strtr(base64_encode(random_bytes($len)), "+/", "-_"), '=');
    }

    public function getToken()
    {
        // only request new token when session has none
        if (session_status() == PHP_SESSION_NONE) {
            // our session is not guaranteed to be started at this point.
            session_start();
        }
        if (empty($_SESSION['$PHALCON/CSRF/KEY$']) || empty($_SESSION['$PHALCON/CSRF$'])) {
            $_SESSION['$PHALCON/CSRF$'] = $this->base64Safe(16);
            $_SESSION['$PHALCON/CSRF/KEY$'] = $this->base64Safe(16);
        }
        return [
            'token' => $_SESSION['$PHALCON/CSRF$'],
            'key' => $_SESSION['$PHALCON/CSRF/KEY$']
        ];
    }
}