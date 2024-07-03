<?php

/**
 *    Copyright (C) 2019 Deciso B.V.
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

use OPNsense\Base\FieldTypes\InterfaceField;
use OPNsense\Core\AppConfig;
use OPNsense\Core\Config;

class InterfaceFieldTest extends Field_Framework_TestCase
{
    /**
     * test construct
     */
    public function testCanBeCreated()
    {
        $this->assertInstanceOf('\OPNsense\Base\FieldTypes\InterfaceField', new InterfaceField());
        // switch config to test set for this type
        (new AppConfig())->update('globals.config_path', __DIR__ . '/InterfaceFieldTest/');
        Config::getInstance()->forceReload();
    }

    /**
     *
     * @depends testCanBeCreated
     */
    public function testConfigItemsExists()
    {
        // init field
        $field = new InterfaceField();
        $field->eventPostLoading();

        $this->assertContains('lan', array_keys($field->getNodeData()));
        $this->assertContains('wan', array_keys($field->getNodeData()));
    }

    /**
     * @depends testCanBeCreated
     */
    public function testSelectHasNoParents()
    {
        $this->expectException(\OPNsense\Base\ValidationException::class);
        $this->expectExceptionMessage("InclusionIn");
        // init field
        $field = new InterfaceField();
        $field->eventPostLoading();
        $field->setValue("if006");
        $this->validateThrow($field);
    }

    /**
     * @depends testCanBeCreated
     */
    public function testSelectHasParents()
    {
        // init field
        $field = new InterfaceField();
        $field->setAddParentDevices("Y");
        $field->eventPostLoading();
        $this->assertContains('if006', array_keys($field->getNodeData()));
    }

    /**
     * @depends testCanBeCreated
     */
    public function testFilters()
    {
        // init field
        $field = new InterfaceField();
        $field->setFilters(["ipaddr" => "/^((?!dhcp).)*$/", "enable" => "/^(?!0).*$/"]);
        $field->eventPostLoading();
        $this->assertContains('lan', array_keys($field->getNodeData()));
        $this->assertContains('opt1', array_keys($field->getNodeData()));
        $this->assertFalse(!empty($field->getNodeData()['wan']));
    }


    /**
     * @depends testCanBeCreated
     */
    public function testSelectSetWithUnknownValue()
    {
        $this->expectException(\OPNsense\Base\ValidationException::class);
        $this->expectExceptionMessage("CsvListValidator");
        // init field
        $field = new InterfaceField();
        $field->eventPostLoading();
        $field->setMultiple("Y");
        $field->setValue('lan,wan,opt99');
        $this->validateThrow($field);
    }

    /**
     *
     * @depends testCanBeCreated
     */
    public function testSelectSetWithoutUnknownValue()
    {
        // init field
        $field = new InterfaceField();
        $field->eventPostLoading();
        $field->setMultiple("Y");
        $field->setValue('lan,opt1');
        $this->assertEmpty($this->validate($field));
    }

    /**
     * @depends testCanBeCreated
     */
    public function testSelectSetOnSingleValue()
    {
        $this->expectException(\OPNsense\Base\ValidationException::class);
        $this->expectExceptionMessage("InclusionIn");
        // init field
        $field = new InterfaceField();
        $field->eventPostLoading();
        $field->setMultiple("N");
        $field->setValue("lan,opt1");
        $this->validateThrow($field);
    }

    /**
     * @depends testCanBeCreated
     */
    public function testSelectSingleValue()
    {
        // init field
        $field = new InterfaceField();
        $field->eventPostLoading();
        $field->setMultiple("N");
        $field->setValue('lan');
        $this->assertEmpty($this->validate($field));
    }


    /**
     * type is not a container
     */
    public function testIsContainer()
    {
        $field = new InterfaceField();
        $this->assertFalse($field->isContainer());
    }
}
