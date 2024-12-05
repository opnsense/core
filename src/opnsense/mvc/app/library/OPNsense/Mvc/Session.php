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

use OPNsense\Core\Config;

/**
 * Session wrapper, when initiated it clones the current session data and closes the session to prevent
 * other connections from being locked out, but only when we initiated the session.
 */
class Session
{
    private array $payload = [];
    private array $changed_fields = [];

    /**
     * clone session data
     */
    public function __construct()
    {
        $shouldClose = false;
        // copy session data on construct to prevent locking issues
        if (session_status() == PHP_SESSION_NONE) {
            // XXX: One could argue these settings actually should be set in php.ini, but since legacy code is also
            //      enforcing secure options in the session handling, let's keep this consistent for now.
            //      see also https://www.php.net/manual/en/session.security.ini.php
            $cparams = session_get_cookie_params();
            session_set_cookie_params(
                $cparams["lifetime"],
                $cparams["path"],
                null,
                (Config::getInstance())->object()->system->webgui->protocol == 'https',
                true
            );
            session_set_cookie_params(["SameSite" => "Lax"]);
            session_start();
            $shouldClose = true;
        }
        $this->payload = $_SESSION;
        if ($shouldClose) {
            session_abort();
        }
    }

    /**
     * @param string $name parameter name
     * @return bool when found
     */
    public function has(string $name): bool
    {
        return isset($this->payload[$name]);
    }

    /**
     * @param string $name parameter name
     * @param string|null $default_value default value when not found
     * @return string|null
     */
    public function get(string $name, ?string $default_value = null): string|null
    {
        return $this->payload[$name] ?? $default_value;
    }

    /**
     * update (cached) session and keep track of changes for concurrency.
     * @param $name
     * @param $value
     * @return void
     */
    public function set($name, $value): void
    {
        $this->payload[$name] = $value;
        $this->changed_fields[] = $name;
    }

    /**
     * Remove parameter from (cached) session and keep track of changes
     * @param string $name parameter name
     * @return void
     */
    public function remove(string $name): void
    {
        unset($this->payload[$name]);
        $this->changed_fields[] = $name;
    }

    /**
     * destroy session object and flush changes to disk
     */
    function close(): void
    {
        if (!empty($this->changed_fields)) {
            if (session_status() == PHP_SESSION_NONE) {
                session_start();
            }
            foreach ($this->changed_fields as $name) {
                if (!isset($this->payload[$name])) {
                    unset($_SESSION[$name]);
                } else {
                    $_SESSION[$name] = $this->payload[$name];
                }
            }
            session_write_close();
        }
    }
}
