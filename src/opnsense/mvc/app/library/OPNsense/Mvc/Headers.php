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

class Headers
{
    private array $headers = [];
    private ?int $http_response_code = null;

    /**
     * set a new response header
     * @param string $name header name
     * @param string $value header value
     * @return $this
     */
    public function set(string $name, string $value): Headers
    {
        $this->headers[$name] = $value;
        return $this;
    }

    /**
     * https://www.iana.org/assignments/http-status-codes/http-status-codes.xhtml
     * @param int $http_response_code response code according to the iana list
     * @return $this
     */
    public function setResponseCode(int $http_response_code): Headers
    {
        $this->http_response_code = $http_response_code;
        return $this;
    }

    /**
     * @return http response code
     */
    public function getResponseCode(): int|null
    {
        return $this->http_response_code;
    }

    /**
     * @param string $header to unset
     * @return $this
     */
    public function remove(string $header): Headers
    {
        if (isset($this->headers[$header])) {
            unset($this->headers[$header]);
        }
        return $this;
    }

    /**
     * @return array list of all provided headers
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * @param string $header header by name
     * @return mixed|null
     */
    public function get(string $header): string|null
    {
        return $this->headers[$header] ?? null;
    }

    /**
     * flush all headers
     * @return $this
     */
    public function reset(): Headers
    {
        $this->headers = [];
        return $this;
    }

    /**
     * send headers to the client including http response code
     * @return void
     */
    public function send(): void
    {
        if (!headers_sent()) {
            if ($this->http_response_code !== null) {
                http_response_code($this->http_response_code);
            }
            foreach ($this->headers as $name => $value) {
                header("$name: $value");
            }
        }
    }
}
