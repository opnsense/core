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

namespace tests\OPNsense\OpenVPN;

use OPNsense\Auth\SSOProviders\Provider;
use OPNsense\OpenVPN\WebAuth;

class WebAuthTest extends \PHPUnit\Framework\TestCase
{
    private function newProvider(
        array $command,
        string $method = 'via-env',
        bool $optional = false
    ): \OPNsense\OpenVPN\IWebAuthProvider {
        return new class ($command, $method, $optional) extends Provider implements \OPNsense\OpenVPN\IWebAuthProvider {
            private array $command;
            private string $method;
            private bool $optional;

            public function __construct(array $command, string $method, bool $optional)
            {
                parent::__construct([
                    'id' => 'test',
                    'name' => 'Test provider',
                    'service' => 'OpenVPN',
                ]);
                $this->command = $command;
                $this->method = $method;
                $this->optional = $optional;
            }

            public function getCommand(): array
            {
                return $this->command;
            }

            public function getMethod(): string
            {
                return $this->method;
            }

            public function isUsernameOptional(): bool
            {
                return $this->optional;
            }
        };
    }

    public function testConfigOptionsViaFileWithOptionalUsername()
    {
        $provider = $this->newProvider(
            ['/usr/local/opnsense/scripts/Example/web-auth.sh', 'staff'],
            'via-file',
            true
        );

        $this->assertSame([
            'auth-user-pass-verify' =>
                '"/usr/local/opnsense/scripts/Example/web-auth.sh staff" via-file',
            'auth-user-pass-optional' => null,
        ], WebAuth::getConfigOptions($provider));
    }

    public function testConfigOptionsViaEnvWithoutOptionalUsername()
    {
        $provider = $this->newProvider(['/usr/local/opnsense/scripts/Example/web-auth.sh']);

        $this->assertSame([
            'auth-user-pass-verify' =>
                '"/usr/local/opnsense/scripts/Example/web-auth.sh" via-env',
        ], WebAuth::getConfigOptions($provider));
    }

    /**
     * @dataProvider invalidCommands
     */
    public function testInvalidCommandIsRejected(array $command)
    {
        $this->expectException(\InvalidArgumentException::class);
        WebAuth::getConfigOptions($this->newProvider($command));
    }

    public static function invalidCommands(): array
    {
        return [
            'empty' => [[]],
            'relative executable' => [['web-auth.sh']],
            'space in argument' => [['/usr/local/bin/web-auth', 'staff users']],
            'shell separator' => [['/usr/local/bin/web-auth', 'staff;reboot']],
            'non string' => [['/usr/local/bin/web-auth', 1]],
        ];
    }

    public function testInvalidMethodIsRejected()
    {
        $this->expectException(\InvalidArgumentException::class);
        WebAuth::getConfigOptions($this->newProvider(['/usr/local/bin/web-auth'], 'stdin'));
    }
}
