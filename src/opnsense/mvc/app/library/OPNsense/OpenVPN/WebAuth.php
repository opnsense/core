<?php

/*
 * Copyright (C) 2026 Jakub Duchek <jakduch@seznam.cz>
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

namespace OPNsense\OpenVPN;

use OPNsense\Auth\AuthenticationFactory;
use OPNsense\Auth\SSOProviders\Provider;

class WebAuth
{
    /**
     * List SSO providers which implement the OpenVPN web authentication contract.
     *
     * @return array<string, Provider&IWebAuthProvider>
     */
    public static function listProviders(): array
    {
        $result = [];
        foreach ((new AuthenticationFactory())->listSSOproviders('OpenVPN') as $provider) {
            if ($provider instanceof Provider && $provider instanceof IWebAuthProvider) {
                $result[$provider->id] = $provider;
            }
        }
        return $result;
    }

    /**
     * Return a configured web authentication provider by its stable identifier.
     *
     * @return Provider&IWebAuthProvider|null
     */
    public static function getProvider(string $id): ?IWebAuthProvider
    {
        return self::listProviders()[$id] ?? null;
    }

    /**
     * Convert a provider into the small, explicitly supported OpenVPN option set.
     *
     * Commands are tokenised by the provider and deliberately restricted to an
     * absolute executable path and shell-safe arguments. This avoids accepting a
     * free-form OpenVPN directive from an extension.
     *
     * @throws \InvalidArgumentException
     */
    public static function getConfigOptions(IWebAuthProvider $provider): array
    {
        $command = $provider->getCommand();
        if (empty($command) || !str_starts_with($command[0] ?? '', '/')) {
            throw new \InvalidArgumentException('OpenVPN web authentication requires an absolute command path.');
        }
        foreach ($command as $argument) {
            if (!is_string($argument) || preg_match('/^[A-Za-z0-9_@%+=:,\.\/\-]+$/D', $argument) !== 1) {
                throw new \InvalidArgumentException('OpenVPN web authentication contains an invalid command argument.');
            }
        }

        $method = $provider->getMethod();
        if (!in_array($method, ['via-env', 'via-file'], true)) {
            throw new \InvalidArgumentException('OpenVPN web authentication requires via-env or via-file.');
        }

        $result = [
            'auth-user-pass-verify' => sprintf('"%s" %s', implode(' ', $command), $method),
        ];
        if ($provider->isUsernameOptional()) {
            $result['auth-user-pass-optional'] = null;
        }
        return $result;
    }
}
