<?php

/**
 *    Copyright (C) 2024 Deciso B.V.
 *    All rights reserved.
 *
 *    Redistribution and use in source and binary forms, with or without
 *    modification, are permitted provided that the following conditions are met:
 *
 *    1. Redistributions of source code must retain the above copyright notice,
 *       this list of conditions and the following disclaimer.
 *
 *    2. Redistributions in binary form must reproduce the above copyright
 *       notice, this list of conditions and the following disclaimer in the
 *       documentation and/or other materials provided with the distribution.
 *
 *    THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
 *    INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 *    AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 *    AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 *    OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 *    SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 *    INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 *    CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 *    ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 *    POSSIBILITY OF SUCH DAMAGE.
 */

namespace tests\OPNsense\Base;

use OPNsense\Core\AppConfig;
use OPNsense\Core\Config;
use OPNsense\Core\ACL;

class AclTest extends \PHPUnit\Framework\TestCase
{
    private static $acl = null;

    /**
     * test construct
     */
    public function testCanBeCreated()
    {
        // switch config to test set for this type
        (new AppConfig())->update('globals.config_path', __DIR__ . '/AclConfig/');
        Config::getInstance()->forceReload();
        /* init after config reload */
        AclTest::$acl = new ACL();
        $this->assertInstanceOf('\OPNsense\Core\ACL', AclTest::$acl);
    }

    /**
     * @depends testCanBeCreated
     */
    public function test_root_hasPrivilege_not()
    {
        $this->assertFalse(AclTest::$acl->hasPrivilege('root', 'user-config-readonly'));
    }

    /**
     * @depends testCanBeCreated
     */
    public function test_test1_hasPrivilege()
    {
        $this->assertTrue(AclTest::$acl->hasPrivilege('test1', 'user-config-readonly'));
    }

    /**
     * @depends testCanBeCreated
     */
    public function test_test2_hasPrivilege_via_group()
    {
        $this->assertTrue(AclTest::$acl->hasPrivilege('test2', 'user-config-readonly'));
    }

    /**
     * @depends testCanBeCreated
     */
    public function test_test3_hasPrivilege_not()
    {
        $this->assertFalse(AclTest::$acl->hasPrivilege('test3', 'user-config-readonly'));
    }

    /**
     * @depends testCanBeCreated
     */
    public function test_root_isPageAccessible_known()
    {
        $this->assertTrue(AclTest::$acl->isPageAccessible('root', '/ui/diagnostics/interface/arp/'));
    }

    /**
     * @depends testCanBeCreated
     */
    public function test_root_isPageAccessible_unknown()
    {
        $this->assertTrue(AclTest::$acl->isPageAccessible('root', '/non_existing_page'));
    }

    /**
     * @depends testCanBeCreated
     */
    public function test_test2_isPageAccessible()
    {
        $this->assertTrue(AclTest::$acl->isPageAccessible('test2', '/ui/diagnostics/interface/arp/'));
    }

    /**
     * @depends testCanBeCreated
     */
    public function test_test4_isPageAccessible_via_group()
    {
        $this->assertTrue(AclTest::$acl->isPageAccessible('test4', '/ui/diagnostics/interface/arp/'));
    }

    /**
     * @depends testCanBeCreated
     */
    public function test_test1_isPageAccessible_unknown()
    {
        $this->assertFalse(AclTest::$acl->isPageAccessible('test1', '/ui/diagnostics/interface/arp/'));
    }
}
