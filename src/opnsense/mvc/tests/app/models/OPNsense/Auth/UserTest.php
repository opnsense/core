<?php

/*
 * Copyright (C) 2026 Janik Besendorf
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

namespace tests\OPNsense\Auth;

use OPNsense\Auth\Local;
use OPNsense\Auth\User;
use OPNsense\Base\ValidationException;
use OPNsense\Core\AppConfig;
use OPNsense\Core\Config;

class UserTest extends \PHPUnit\Framework\TestCase
{
    protected function setUp(): void
    {
        (new AppConfig())->update('application.configDir', __DIR__ . '/UserTest');
        Config::getInstance()->forceReload();
        Config::getInstance()->object()->system->user->password =
            password_hash('existing-password', PASSWORD_BCRYPT, ['cost' => 4]);
    }

    public static function missingPasswordProvider(): array
    {
        return [
            'missing password, missing UUID' => [false, false, false],
            'empty password, missing UUID' => [true, false, false],
            'missing password, existing UUID' => [false, true, false],
            'empty password, existing UUID' => [true, true, false],
            'disabled, missing password, missing UUID' => [false, false, true],
            'disabled, empty password, missing UUID' => [true, false, true],
            'disabled, missing password, existing UUID' => [false, true, true],
            'disabled, empty password, existing UUID' => [true, true, true],
        ];
    }

    /**
     * @dataProvider missingPasswordProvider
     */
    public function testMigrateMissingPassword(bool $emptyPassword, bool $hasUuid, bool $disabled): void
    {
        $config = Config::getInstance();
        $system = $config->object()->system;
        $originalHash = (string)$system->user->password;
        $user = $system->addChild('user');
        $user->addChild('uid', '2000');
        $user->addChild('name', 'legacy');
        $user->addChild('scope', 'user');
        $user->addChild('disabled', $disabled ? '1' : '0');
        $user->addChild('apikeys', 'legacy-key|legacy-secret-hash');
        if ($emptyPassword) {
            $user->addChild('password');
        }
        if ($hasUuid) {
            $user->addAttribute('uuid', '00000000-0000-4000-8000-000000002000');
        }

        $model = new User(true);
        $reference = $model->getUserByName('legacy')->__reference;
        $this->assertTrue($model->runMigrations());

        $reloaded = new User(true);
        $migrated = $reloaded->getUserByName('legacy');
        $this->assertSame('*', $migrated->password->getValue());
        $this->assertFalse(password_verify('', $migrated->password->getValue()));
        $this->assertFalse(password_verify('*', $migrated->password->getValue()));
        $this->assertSame($disabled ? '1' : '0', (string)$migrated->disabled);
        $this->assertSame('2000', (string)$migrated->uid);
        $this->assertSame('user', (string)$migrated->scope);
        $this->assertSame($reference, $migrated->__reference);
        $this->assertSame($migrated, $reloaded->getNodeByReference($reference));
        $this->assertSame($originalHash, $reloaded->getUserByName('root')->password->getValue());
        $this->assertSame('legacy-secret-hash', $reloaded->getApiKeySecret('legacy-key')['secret']);
        $this->assertSame('existing-secret-hash', $reloaded->getApiKeySecret('existing-key')['secret']);
        $this->assertSame('0,2000', (string)$config->object()->system->group->member);

        $serialized = (string)$config;
        $this->assertFalse($reloaded->runMigrations());
        $this->assertSame($serialized, (string)$config);
    }

    public function testMigrateValidUserWithoutUuid(): void
    {
        $config = Config::getInstance();
        unset($config->object()->system->user['uuid']);
        $originalHash = (string)$config->object()->system->user->password;
        $model = new User(true);
        $reference = $model->getUserByName('root')->__reference;

        $this->assertTrue($model->runMigrations());
        $reloaded = new User(true);
        $this->assertSame($reference, $reloaded->getUserByName('root')->__reference);
        $this->assertSame($originalHash, $reloaded->getUserByName('root')->password->getValue());
        $this->assertTrue((new Local())->authenticate('root', 'existing-password'));
        $this->assertFalse($reloaded->runMigrations());
    }

    public function testMigrateUserWithoutPasswordKeepsLocalLoginUnavailable(): void
    {
        $config = Config::getInstance();
        unset($config->object()->system->user->password);
        $model = new User(true);

        $this->assertTrue($model->runMigrations());
        $this->assertFalse((new Local())->authenticate('root', '*'));
    }

    public function testNewUserStillRequiresPassword(): void
    {
        $model = new User(true);
        $user = $model->user->Add();
        $user->name = 'newuser';

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('A password is required');
        $model->serializeToConfig();
    }

    public function testMigrationStillValidatesOtherFields(): void
    {
        $config = Config::getInstance();
        $config->object()->system->user->name = 'renamed-root';
        unset($config->object()->system->user->password);
        $before = (string)$config;

        try {
            (new User(true))->runMigrations();
            $this->fail('An invalid root username must fail validation.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('The name of the root user can not be changed', $e->getMessage());
            $this->assertSame($before, (string)$config);
        }
    }
}
