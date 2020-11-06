<?php

/**
 *    Copyright (C) 2020 Deciso B.V.
 *
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
 *
 */

namespace tests\OPNsense\Base\FieldTypes;

// @CodingStandardsIgnoreStart
require_once 'Field_Framework_TestCase.php';
// @CodingStandardsIgnoreEnd

use OPNsense\Base\FieldTypes\VirtualIPField;
use Phalcon\DI\FactoryDefault;
use OPNsense\Core\Config;

class VirtualIPFieldTest extends Field_Framework_TestCase
{

    /**
     * test construct
     */
    public function testCanBeCreated()
    {
        $this->assertInstanceOf('\OPNsense\Base\FieldTypes\VirtualIPField', new VirtualIPField());
        // switch config to test set for this type
        FactoryDefault::getDefault()->get('config')->globals->config_path = __DIR__ . '/VirtualIPFieldTest/';
        Config::getInstance()->forceReload();
    }

    /**
     * @depends testCanBeCreated
     */
    public function testCarp()
    {
        // init field
        $field = new VirtualIPField();
        $field->setType("carp");
        $field->eventPostLoading();
        $this->assertContains('10.100.0.10', array_keys($field->getNodeData()));
        $this->assertNotContains('172.11.1.0', array_keys($field->getNodeData()));
    }

    /**
     * @depends testCanBeCreated
     */
    public function testIPAlias()
    {
        // init field
        $field = new VirtualIPField();
        $field->setType("ipalias");
        $field->eventPostLoading();
        $this->assertContains('172.11.1.0', array_keys($field->getNodeData()));
        $this->assertNotContains('10.207.0.1', array_keys($field->getNodeData()));
    }

    /**
     * @depends testCanBeCreated
     */
    public function testOther()
    {
        // init field
        $field = new VirtualIPField();
        $field->setType("other");
        $field->eventPostLoading();
        $this->assertNotContains('172.11.1.0', array_keys($field->getNodeData()));
        $this->assertContains('10.207.0.1', array_keys($field->getNodeData()));
    }


    /**
     * type is not a container
     */
    public function testIsContainer()
    {
        $field = new VirtualIPField();
        $this->assertFalse($field->isContainer());
    }
}
