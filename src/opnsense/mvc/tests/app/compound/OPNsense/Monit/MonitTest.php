<?php

/*
 * Copyright (C) 2018 EURO-LOG AG
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

namespace tests\OPNsense\Monit\Api;

use \OPNsense\Core\Config;

class MonitTest extends \PHPUnit\Framework\TestCase
{
    /**
     * list with model node types
     */
    private $nodeTypes = array('alert', 'service', 'test');

    // holds the SettingsController object
    protected static $setMonit;

    public static function setUpBeforeClass()
    {
        self::$setMonit = new \OPNsense\Monit\Api\SettingsController;
    }

    private function cleanupNodes($nodeType = null)
    {
        $nodes = self::$setMonit->mdlMonit->$nodeType->getNodes();
        foreach ($nodes as $nodeUuid => $node) {
            self::$setMonit->mdlMonit->$nodeType->del($nodeUuid);
        }
    }

    /**
     * test getAction
     */
    public function testGet()
    {
        $this->assertInstanceOf('\OPNsense\Monit\Api\SettingsController', self::$setMonit);
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('unknown nodeType');
        $response = self::$setMonit->getAction('wrong_node_type');
        $testConfig = [];
        $response = self::$setMonit->getAction('general');
        $testConfig['general'] = $response['monit']['general'];
        
        $this->assertEquals($response['status'], 'ok');
        $this->assertArrayHasKey('enabled', $response['monit']['general']);
        
        return $testConfig;
    }

    /**
     * test searchAction
     * @depends testGet
     */
    public function testSearch($testConfig)
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = array('current' => '1', 'rowCount' => '7');
        
        foreach ($this->nodeTypes as $nodeType) {
            $response = self::$setMonit->searchAction($nodeType);
            $this->assertArrayHasKey('total', $response);
            $testConfig[$nodeType] = $response['rows'];
        }
        
        return $testConfig;
    }

    /**
     * test delAction
     * not really a test if the config is empty, but we will delete something later
     * @depends testSearch
     */
    public function testReset($testConfig)
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        foreach (array_reverse($this->nodeTypes) as $nodeType) {
            foreach ($testConfig[$nodeType] as $node) {
                $response = self::$setMonit->delAction($nodeType, $node['uuid']);
                $this->assertEquals($response['status'], 'ok');
            }
        }
        // need an assertion here to succeed this test on empty config
        $this->assertTrue(true);
    }

    /**
     * test setAction general
     * @depends testReset
     */
    public function testSetGeneral()
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        
        // interval too high
        $_POST = array('monit' => ['general' => ['interval' => '864000']]);
        $response = self::$setMonit->setAction('general');
        $this->assertCount(1, $response['validations']);
        $this->assertEquals($response['result'], 'failed');
        $this->assertNotEmpty($response['validations']['monit.general.interval']);
        
        // set correct interval
        $_POST = array('monit' => ['general' => ['interval' => '120', 'enabled' => '0']]);
        $response = self::$setMonit->setAction('general');
        $this->assertEquals($response['status'], 'ok');
    }

    /**
     * test dirtyAction
     * @depends testSetGeneral
     */
    public function testDirtyAction()
    {
        $this->assertInstanceOf('\OPNsense\Monit\Api\SettingsController', self::$setMonit);
        $response = self::$setMonit->dirtyAction();
        $this->assertEquals($response['status'], 'ok');
        $this->assertEquals($response['monit']['dirty'], true);
        
    }

    /**
     * test setAction alert
     * @depends testReset
     */
    public function testSetAlert()
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        
        // malformed email address
        $_POST = array('monit' => ['alert' => ['recipient' => '123456789']]);
        $response = self::$setMonit->setAction('alert');
        $this->assertCount(1, $response['validations']);
        $this->assertEquals($response['result'], 'failed');
        $this->assertNotEmpty($response['validations']['monit.alert.recipient']);
        $this->cleanupNodes('alert');
        
        // create alert for ServiceControllerTest
        $_POST = array('monit' => ['alert' => ['recipient' => 'root@localhost.local', 'enabled' => '1']]);
        $response = self::$setMonit->setAction('alert');
        $this->assertEquals($response['status'], 'ok');
    }

}
