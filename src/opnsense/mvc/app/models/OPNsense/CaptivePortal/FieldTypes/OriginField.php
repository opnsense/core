<?php

/*
 * Copyright (C) 2026 Deciso B.V.
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

namespace OPNsense\CaptivePortal\FieldTypes;

use OPNsense\Base\FieldTypes\CSVListField;
use OPNsense\Base\Validators\CallbackValidator;

/**
 * List of HTTP origins used for Captive Portal CORS responses.
 */
class OriginField extends CSVListField
{
    /**
     * Return the canonical representation of an HTTP origin, or null when invalid.
     */
    public static function normalizeOrigin(string $origin): ?string
    {
        try {
            $parts = parse_url($origin);
        } catch (\ValueError $e) {
            return null;
        }
        if (
            $parts === false ||
            empty($parts['scheme']) ||
            empty($parts['host']) ||
            count(array_diff(array_keys($parts), ['scheme', 'host', 'port'])) !== 0
        ) {
            return null;
        }

        $scheme = strtolower($parts['scheme']);
        if (!in_array($scheme, ['http', 'https'], true)) {
            return null;
        }

        $host = strtolower($parts['host']);
        if (str_starts_with($host, '[') || str_ends_with($host, ']')) {
            if (
                !str_starts_with($host, '[') ||
                !str_ends_with($host, ']') ||
                filter_var(substr($host, 1, -1), FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) === false
            ) {
                return null;
            }
        } elseif (
            filter_var($host, FILTER_VALIDATE_IP) === false &&
            filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) === false
        ) {
            return null;
        }

        $port = $parts['port'] ?? null;
        if ($port !== null && ($port < 1 || $port > 65535)) {
            return null;
        }

        $result = "{$scheme}://{$host}";
        if (
            $port !== null &&
            !(($scheme === 'http' && $port === 80) || ($scheme === 'https' && $port === 443))
        ) {
            $result .= ":{$port}";
        }
        return $result;
    }

    /**
     * {@inheritdoc}
     */
    protected function defaultValidationMessage()
    {
        return gettext('Enter an HTTP origin using scheme, hostname and optional port without a path.');
    }

    /**
     * {@inheritdoc}
     */
    public function getValidators()
    {
        $validators = parent::getValidators();
        if ($this->internalValue != null) {
            $validators[] = new CallbackValidator(['callback' => function ($data) {
                foreach ($this->iterateInput($data) as $origin) {
                    if (self::normalizeOrigin($origin) === null) {
                        return [sprintf(
                            gettext('"%s" is invalid. %s'),
                            $origin,
                            $this->getValidationMessage()
                        )];
                    }
                }
                return [];
            }]);
        }
        return $validators;
    }
}
