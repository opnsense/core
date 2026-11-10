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

namespace tests\OPNsense\Interfaces;

use OPNsense\Core\AppConfig;
use OPNsense\Core\Config;
use OPNsense\Interfaces\NetworkInterface;

class NetworkInterfaceTest extends \PHPUnit\Framework\TestCase
{
    protected function setUp(): void
    {
        (new AppConfig())->update('application.configDir', __DIR__ . '/config/');
        Config::getInstance()->forceReload();
    }

    /**
     * simply test if updating one single config entry leads to one (the same) interface pending for apply.
     */
    public function testSingleChange()
    {
        $test_targetfilename = '/tmp/unittest.interfaces.apply';
        @unlink($test_targetfilename);
        NetworkInterface::setTodoFilename($test_targetfilename);
        $mdl = new NetworkInterface();
        $mdl->interface->lan->mtu = '1300';
        $mdl->serializeToConfig();
        $payload = json_decode(file_get_contents($test_targetfilename), true);
        $this->assertEquals(1, count($payload), "Changed interfaces don't match pending ones");
        $this->assertEquals('lan', array_keys($payload)[0], 'lan expected');
    }
}
