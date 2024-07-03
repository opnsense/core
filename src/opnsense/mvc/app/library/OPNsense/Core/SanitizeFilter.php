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

class SanitizeFilter
{
    protected $filters = [];

    public function __construct($callbacks = null)
    {
        if (is_array($callbacks)) {
            foreach ($callbacks as $key => $func) {
                $this->filters[$key] = $func;
            }
        }
    }

    protected function sanitize_item($payload, $type)
    {
        if (isset($this->filters[$type])) {
            return $this->filters[$type]($payload);
        } elseif (method_exists($this, "filter_{$type}")) {
            return $this->{"filter_{$type}"}($payload);
        }
        throw new \InvalidArgumentException(sprintf("Sanitizer %s not registered", $type));
    }

    public function sanitize($payload, $type)
    {
        if (is_array($payload)) {
            foreach ($payload as $key => $value) {
                $payload[$key] = $this->sanitize_item($value, $type);
            }
            return $payload;
        } else {
            return $this->sanitize_item($payload, $type);
        }
    }

    protected function filter_int($input)
    {
        return (int) filter_var($input, FILTER_SANITIZE_NUMBER_INT);
    }

    protected function filter_string($input)
    {
        return htmlspecialchars($input, ENT_QUOTES | ENT_HTML401);
    }

    protected function filter_alnum($input)
    {
        return preg_replace("/[^A-Za-z0-9]/", "", $input);
    }

    protected function filter_hexval($input)
    {
        return preg_replace("/[^A-Za-z0-9]/", "", $input);
    }

    protected function filter_version($input)
    {
        return preg_replace('/[^0-9a-zA-Z\.]/', '', $input);
    }

    protected function filter_query($input)
    {
        return preg_replace("/[^0-9,a-z,A-Z, ,*,\-,_,.,\#]/", "", $input);
    }

    protected function filter_pkgname($input)
    {
        return preg_replace('/[^0-9a-zA-Z._-]/', '', $input);
    }

    protected function filter_striptags($input)
    {
        return strip_tags($input);
    }
}
