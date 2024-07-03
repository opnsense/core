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

use Exception;

class Response
{
    private Headers $headers;
    private mixed $content = '';
    private bool $sent = false;

    public function __construct()
    {
        $this->headers = new Headers();
    }

    public function getHeaders(): Headers
    {
        return $this->headers;
    }

    public function setContent(mixed $content): void
    {
        $this->content = $content;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    /**
     * @param string $contentType content type to offer the client
     * @param string|null $charset optional characterset
     * @return void
     */
    public function setContentType(string $contentType, ?string $charset): void
    {
        if (!empty($charset)) {
            $contentType .= '; charset=' . $charset;
        }
        $this->headers->set('Content-Type', $contentType);
    }

    /**
     * @param int $statusCode http status code
     * @param string|null $message backwards compatibility, messages are ignored
     * @return void
     */
    public function setStatusCode(int $statusCode, ?string $message = null): void
    {
        $this->headers->setResponseCode($statusCode);
    }

    /**
     * @return int|null status code
     */
    public function getStatusCode(): int|null
    {
        return $this->headers->getResponseCode();
    }

    /**
     * @return void
     * @throws Exception when already send
     */
    public function send(): void
    {
        if ($this->sent) {
            throw new Exception('Response Already Sent');
        }
        $this->headers->send();

        if (is_resource($this->content)) {
            /* Never allow output compression on streams */
            ini_set('zlib.output_compression', 'Off');
            while (ob_get_level() > 0) {
                ob_end_flush();
            }
            fpassthru($this->content);
            @fclose($this->content);
        } elseif (!empty($this->content)) {
            echo $this->content;
        }

        $this->sent = true;
    }

    /**
     * @return bool if response was already sent to the client
     */
    public function isSent(): bool
    {
        return $this->sent;
    }

    /**
     * @param string $location location to forward request to
     * @param bool $externalRedirect backwards compatibility
     * @param int $statusCode HTTP status code
     * @return void
     */
    public function redirect(string $location, bool $externalRedirect = true, int $statusCode = 302): void
    {
        $this->setStatusCode($statusCode);
        $this->headers->set('Location', $location);
    }

    public function setHeader(string $name, string $value): void
    {
        $this->headers->set($name, $value);
    }

    /**
     * XXX: backwards compatibility, remove in a future version
     * @param string $header combined header
     * @return void
     */
    public function setRawHeader(string $header): void
    {
        $parts = explode(':', $header, 2);
        $this->setHeader($parts[0], ltrim($parts[1]));
    }
}
