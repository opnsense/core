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

use OPNsense\Core\SanitizeFilter;
use stdClass;

class Request
{
    private string $rawBody = '';

    /**
     * @param string $header retrieve request header by name
     * @return string content
     */
    public function getHeader(string $header): string
    {
        $name = strtoupper(strtr($header, "-", "_"));
        if (isset($_SERVER[$name])) {
            return $_SERVER[$name];
        } elseif (isset($_SERVER["HTTP_$name"])) {
            return $_SERVER["HTTP_$name"];
        }
        return '';
    }

    /**
     * @return string method name (GET, POST, PUT, ...)
     */
    public function getMethod(): string
    {
        return $_SERVER["REQUEST_METHOD"];
    }

    public function isPost(): bool
    {
        return $this->getMethod() == 'POST';
    }

    public function isGet(): bool
    {
        return $this->getMethod() == 'GET';
    }

    public function isPut(): bool
    {
        return $this->getMethod() == 'PUT';
    }

    public function isDelete(): bool
    {
        return $this->getMethod() == 'DELETE';
    }

    public function isHead(): bool
    {
        return $this->getMethod() == 'HEAD';
    }

    public function isPatch(): bool
    {
        return $this->getMethod() == 'PATCH';
    }

    public function isOptions(): bool
    {
        return $this->getMethod() == 'OPTIONS';
    }

    public function getRawBody(): string
    {
        if (empty($this->rawBody)) {
            $this->rawBody = file_get_contents("php://input");
        }
        return $this->rawBody;
    }

    public function has(string $name): bool
    {
        return isset($_REQUEST[$name]);
    }

    public function hasPost(string $name): bool
    {
        return isset($_POST[$name]);
    }

    private function getHelper(array $source, ?string $name = null, ?string $filter = null, mixed $defaultValue = null)
    {
        if ($name === null) {
            $value = $source;
        } else {
            $value = isset($source[$name]) ? $source[$name] : $defaultValue;
        }
        if ($filter !== null && $value !== null) {
            $value = (new SanitizeFilter())->sanitize($value, $filter);
        }
        return $value;
    }

    public function getPost(?string $name = null, ?string $filter = null, mixed $defaultValue = null)
    {
        return $this->getHelper($_POST, $name, $filter, $defaultValue);
    }

    public function get(?string $name = null, ?string $filter = null, mixed $defaultValue = null)
    {
        return $this->getHelper($_REQUEST, $name, $filter, $defaultValue);
    }

    public function getQuery(?string $name = null, ?string $filter = null, mixed $defaultValue = null)
    {
        return $this->getHelper($_GET, $name, $filter, $defaultValue);
    }

    public function getJsonRawBody(): stdClass| array| bool
    {
        return json_decode($this->getRawBody(), true) ?? false;
    }

    public function getClientAddress()
    {
        return explode(",", $_SERVER['REMOTE_ADDR'] ?? '')[0];
    }
}
