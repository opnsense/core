<?php

/*
 * Copyright (C) 2018 Deciso B.V.
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

namespace tests\OPNsense\Base\FieldTypes;

// @CodingStandardsIgnoreStart
require_once 'Field_Framework_TestCase.php';
require_once __DIR__ . '/../BaseModel/TestModel.php';
// @CodingStandardsIgnoreEnd

use OPNsense\Base\FieldTypes\ModelRelationField;
use OPNsense\Core\AppConfig;
use OPNsense\Core\Config;

class ModelRelationFieldTest extends Field_Framework_TestCase
{
    protected function setUp(): void
    {
        (new AppConfig())->update('application.configDir', __DIR__ . '/ModelRelationFieldTest');
        Config::getInstance()->forceReload();
    }

    /**
     * test construct
     */
    public function testCanBeCreated()
    {
        $this->assertInstanceOf('\OPNsense\Base\FieldTypes\ModelRelationField', new ModelRelationField());
    }

    /**
     * Select single valid, defaults.
     */
    public function testSetSingleOk()
    {
        $field = new ModelRelationField();
        $field->setModel(array(
            "item" => array(
                "source" => "tests.OPNsense.Base.BaseModel.TestModel",
                "items" => "simpleList.items",
                "display" => "number"
            )
        ));
        $field->eventPostLoading();
        $field->setValue("5ea2a35c-b02b-485a-912b-d077e639bf9f");
        $this->assertEmpty($this->validate($field));
    }

    /**
     * Select multiple valid, required false (default), multiple false (default).
     */
    public function testSetMultipleNok()
    {
        $field = new ModelRelationField();
        $field->setModel(array(
            "item" => array(
                "source" => "tests.OPNsense.Base.BaseModel.TestModel",
                "items" => "simpleList.items",
                "display" => "number"
            )
        ));
        $field->eventPostLoading();
        $field->setValue("5ea2a35c-b02b-485a-912b-d077e639bf9f,60e1bc02-6817-4940-bbd3-61d0cf439a8a");
        $this->assertEquals($this->validate($field), ['CallbackValidator']);
    }

    /**
     * Selecting "none" option, required false (default), multiple false (default).
     */
    public function testSetNoneOk()
    {
        $field = new ModelRelationField();
        $field->setModel(array(
            "item" => array(
                "source" => "tests.OPNsense.Base.BaseModel.TestModel",
                "items" => "simpleList.items",
                "display" => "number"
            )
        ));
        $field->eventPostLoading();
        $field->setValue("");
        $this->assertEmpty($this->validate($field));
    }

    /**
     * Selecting "none" option, field required.
     */
    public function testSetNoneNok()
    {
        $field = new ModelRelationField();
        $field->setRequired("Y");
        $field->setModel(array(
            "item" => array(
                "source" => "tests.OPNsense.Base.BaseModel.TestModel",
                "items" => "simpleList.items",
                "display" => "number"
            )
        ));
        $field->eventPostLoading();
        $field->setValue("");
        $this->assertEquals($this->validate($field), ['PresenceOf']);
    }

    /**
     * Selecting "none" option, with multiple.
     */
    public function testSetNoneMultiNok()
    {
        $field = new ModelRelationField();
        $field->setMultiple("Y");
        $field->setModel(array(
            "item" => array(
                "source" => "tests.OPNsense.Base.BaseModel.TestModel",
                "items" => "simpleList.items",
                "display" => "number"
            )
        ));
        $field->eventPostLoading();
        $field->setValue("'',5ea2a35c-b02b-485a-912b-d077e639bf9f");
        $this->assertEquals($this->validate($field), ['CallbackValidator']);
    }

    /**
     * Selecting "none" option, using the blank description (BlankDesc) override.
     * Needs not required, and multiple.
     */
    public function testSetNoneBlankDescOk()
    {
        $field = new ModelRelationField();
        $field->setMultiple("Y");
        $field->setBlankDesc("No selection.");
        $field->setModel(array(
            "item" => array(
                "source" => "tests.OPNsense.Base.BaseModel.TestModel",
                "items" => "simpleList.items",
                "display" => "number"
            )
        ));
        $field->eventPostLoading();
        $field->setValue("");
        $this->assertEmpty($this->validate($field));
    }

    /**
     * Selecting a non-option, using the blank description (BlankDesc) override.
     * Needs not required, and multiple.
     */
    public function testSetNoneBlankDescNok()
    {
        $field = new ModelRelationField();
        $field->setMultiple("Y");
        $field->setBlankDesc("No selection.");
        $field->setModel(array(
            "item" => array(
                "source" => "tests.OPNsense.Base.BaseModel.TestModel",
                "items" => "simpleList.items",
                "display" => "number"
            )
        ));
        $field->eventPostLoading();
        $field->setValue("Not an option");
        $this->assertEquals($this->validate($field), ['CallbackValidator']);
    }

    /**
     * Selecting another option, using the blank description (BlankDesc) override.
     * Needs not required, and multiple.
     */
    public function testSetNoneBlankDescSingleOk()
    {
        $field = new ModelRelationField();
        $field->setMultiple("Y");
        $field->setBlankDesc("No selection.");
        $field->setModel(array(
            "item" => array(
                "source" => "tests.OPNsense.Base.BaseModel.TestModel",
                "items" => "simpleList.items",
                "display" => "number"
            )
        ));
        $field->eventPostLoading();
        $field->setValue("d1e7eda3-f940-42d9-8dfa-db7b1264b6e1");
        $this->assertEmpty($this->validate($field));
    }

    /**
     * Selecting none option, while blank description override.
     * Defined blank description should pass through to value, and show selected.
     * Needs not required (default), and multiple false (default)
     */
    public function testSetNoneBlankDescValueOk()
    {
        $field = new ModelRelationField();
        $field->setBlankDesc("No selection.");
        $field->setModel(array(
            "item" => array(
                "source" => "tests.OPNsense.Base.BaseModel.TestModel",
                "items" => "simpleList.items",
                "display" => "number"
            )
        ));
        $field->eventPostLoading();
        $field->setValue("");
        $node_data = $field->getNodeData();
        $this->assertEquals($node_data[""], array("value" => "No selection.","selected" => true));
    }

    /**
     * Selecting none option, while blank description override.
     * Empty Blank description should get override with word "none"
     * Needs not required (default), and multiple false (default)
     */
    public function testSetNoneBlankDescBlankValueOk()
    {
        $field = new ModelRelationField();
        $field->setBlankDesc("");
        $field->setModel(array(
            "item" => array(
                "source" => "tests.OPNsense.Base.BaseModel.TestModel",
                "items" => "simpleList.items",
                "display" => "number"
            )
        ));
        $field->eventPostLoading();
        $field->setValue("");
        $node_data = $field->getNodeData();
        $this->assertEquals($node_data[""], array("value" => "None","selected" => true));
    }

    /**
     * Selecting single invalid option, not required.
     */
    public function testSetSingleNok()
    {
        $field = new ModelRelationField();
        $field->setModel(array(
            "item" => array(
                "source" => "tests.OPNsense.Base.BaseModel.TestModel",
                "items" => "simpleList.items",
                "display" => "number"
            )
        ));
        $field->eventPostLoading();
        $field->setValue("XX5ea2a35c-b02b-485a-912b-d077e639bf9f");
        $this->assertEquals($this->validate($field), ['CallbackValidator']);
    }

    /**
     * Selecting multiple, with multiple enabled.
     */
    public function testSetMultiOk()
    {
        $field = new ModelRelationField();
        $field->setMultiple("Y");
        $field->setModel(array(
            "item" => array(
                "source" => "tests.OPNsense.Base.BaseModel.TestModel",
                "items" => "simpleList.items",
                "display" => "number"
            )
        ));
        $field->eventPostLoading();
        $field->setValue("4d0e2835-7a19-4a19-8c23-e12383827594,5ea2a35c-b02b-485a-912b-d077e639bf9f");
        $this->assertEmpty($this->validate($field));
    }

    /**
     * Selecting one invalid in multiple, with multiple enabled.
     */
    public function testSetMultiNok()
    {
        $field = new ModelRelationField();
        $field->setMultiple("Y");
        $field->setModel(array(
            "item" => array(
                "source" => "tests.OPNsense.Base.BaseModel.TestModel",
                "items" => "simpleList.items",
                "display" => "number"
            )
        ));
        $field->eventPostLoading();
        $field->setValue("x4d0e2835-7a19-4a19-8c23-e12383827594,5ea2a35c-b02b-485a-912b-d077e639bf9f");
        $this->assertEquals($this->validate($field), ['CallbackValidator']);
    }

    /**
     * Selecting valid multiple with multiple disabled.
     */
    public function testSetMultiNoNok()
    {
        $field = new ModelRelationField();
        $field->setMultiple("N");
        $field->setModel(array(
            "item" => array(
                "source" => "tests.OPNsense.Base.BaseModel.TestModel",
                "items" => "simpleList.items",
                "display" => "number"
            )
        ));
        $field->eventPostLoading();
        $field->setValue("4d0e2835-7a19-4a19-8c23-e12383827594,5ea2a35c-b02b-485a-912b-d077e639bf9f");
        $this->assertEquals($this->validate($field), ['CallbackValidator']);
    }

    /**
     * Selecting multiple, with multiple enabled, and sorting enabled.
     */
    public function testSortedOk()
    {
        $field = new ModelRelationField();
        $field->setMultiple("Y");
        $field->setModel(array(
            "item" => array(
                "source" => "tests.OPNsense.Base.BaseModel.TestModel",
                "items" => "simpleList.items",
                "display" => "number"
            )
        ));
        $field->eventPostLoading();
        $field->setValue("4d0e2835-7a19-4a19-8c23-e12383827594,5ea2a35c-b02b-485a-912b-d077e639bf9f");

        $node_data = array_keys($field->getNodeData());
        $this->assertEquals(array_search('5ea2a35c-b02b-485a-912b-d077e639bf9f', $node_data), 0);
        $this->assertEquals(array_search('4d0e2835-7a19-4a19-8c23-e12383827594', $node_data), 3);

        // set sorted by input and check again
        $field->setSorted('Y');
        $node_data = array_keys($field->getNodeData());
        $this->assertEquals(array_search('4d0e2835-7a19-4a19-8c23-e12383827594', $node_data), 0);
        $this->assertEquals(array_search('5ea2a35c-b02b-485a-912b-d077e639bf9f', $node_data), 1);
    }

    /**
     * Selecting none, with sorting, required false (default).
     */
    public function testSortedNoneOk()
    {
        $field = new ModelRelationField();
        $field->setModel(array(
            "item" => array(
                "source" => "tests.OPNsense.Base.BaseModel.TestModel",
                "items" => "simpleList.items",
                "display" => "number"
            )
        ));
        $field->eventPostLoading();
        $field->setValue("");

        $node_data = array_keys($field->getNodeData());
        $this->assertEquals(array_search('', $node_data), 0);
        $this->assertEquals(array_search('4d0e2835-7a19-4a19-8c23-e12383827594', $node_data), 4);

        // set sorted by input and check again
        $field->setSorted('Y');
        $node_data = array_keys($field->getNodeData());
        $this->assertEquals(array_search('', $node_data), 0);
        $this->assertEquals(array_search('4d0e2835-7a19-4a19-8c23-e12383827594', $node_data), 4);
    }

    /**
     * Selecting none and valid, with multiple, with sorting, required false (default).
     */
    public function testSortedNoneWithMultipleOk()
    {
        $field = new ModelRelationField();
        $field->setMultiple("Y");
        $field->setModel(array(
            "item" => array(
                "source" => "tests.OPNsense.Base.BaseModel.TestModel",
                "items" => "simpleList.items",
                "display" => "number"
            )
        ));
        $field->eventPostLoading();
        $field->setValue("4d0e2835-7a19-4a19-8c23-e12383827594,''");

        $node_data = array_keys($field->getNodeData());
        $this->assertEquals(array_search('48bea3c9-c563-4885-a593-dea0a6af2e1e', $node_data), 1);
        $this->assertEquals(array_search('60e1bc02-6817-4940-bbd3-61d0cf439a8a', $node_data), 4);
        $this->assertEquals(array_search('4d0e2835-7a19-4a19-8c23-e12383827594', $node_data), 3);

        // set sorted by input and check again
        $field->setSorted('Y');
        $node_data = array_keys($field->getNodeData());
        $this->assertEquals(array_search('48bea3c9-c563-4885-a593-dea0a6af2e1e', $node_data), 2);
        $this->assertEquals(array_search('60e1bc02-6817-4940-bbd3-61d0cf439a8a', $node_data), 4);
        $this->assertEquals(array_search('4d0e2835-7a19-4a19-8c23-e12383827594', $node_data), 0);
    }

    /**
     * Selecting all, with multiple, with sorting, required false (default).
     */
    public function testSortedSelectAllOk()
    {
        $field = new ModelRelationField();
        $field->setMultiple("Y");
        $field->setModel(array(
            "item" => array(
                "source" => "tests.OPNsense.Base.BaseModel.TestModel",
                "items" => "simpleList.items",
                "display" => "number"
            )
        ));
        $field->eventPostLoading();
        $field->setValue("5ea2a35c-b02b-485a-912b-d077e639bf9f," .
                         "48bea3c9-c563-4885-a593-dea0a6af2e1e," .
                         "d1e7eda3-f940-42d9-8dfa-db7b1264b6e1," .
                         "4d0e2835-7a19-4a19-8c23-e12383827594," .
                         "60e1bc02-6817-4940-bbd3-61d0cf439a8a," .
                         "3bf8fc9c-6f36-4821-9944-ed7dfed50ad6");

        $node_data = array_keys($field->getNodeData());
        $this->assertEquals(array_search('5ea2a35c-b02b-485a-912b-d077e639bf9f', $node_data), 0);
        $this->assertEquals(array_search('48bea3c9-c563-4885-a593-dea0a6af2e1e', $node_data), 1);
        $this->assertEquals(array_search('d1e7eda3-f940-42d9-8dfa-db7b1264b6e1', $node_data), 2);
        $this->assertEquals(array_search('4d0e2835-7a19-4a19-8c23-e12383827594', $node_data), 3);
        $this->assertEquals(array_search('60e1bc02-6817-4940-bbd3-61d0cf439a8a', $node_data), 4);
        $this->assertEquals(array_search('3bf8fc9c-6f36-4821-9944-ed7dfed50ad6', $node_data), 5);

        // set sorted by input and check again
        $field->setSorted('Y');
        $node_data = array_keys($field->getNodeData());
        $this->assertEquals(array_search('5ea2a35c-b02b-485a-912b-d077e639bf9f', $node_data), 0);
        $this->assertEquals(array_search('48bea3c9-c563-4885-a593-dea0a6af2e1e', $node_data), 1);
        $this->assertEquals(array_search('d1e7eda3-f940-42d9-8dfa-db7b1264b6e1', $node_data), 2);
        $this->assertEquals(array_search('4d0e2835-7a19-4a19-8c23-e12383827594', $node_data), 3);
        $this->assertEquals(array_search('60e1bc02-6817-4940-bbd3-61d0cf439a8a', $node_data), 4);
        $this->assertEquals(array_search('3bf8fc9c-6f36-4821-9944-ed7dfed50ad6', $node_data), 5);
    }

    /**
     * Selecting some, in order, with multiple, with sorting, required false (default).
     */
    public function testSortedSelectSomeOrderedOk()
    {
        $field = new ModelRelationField();
        $field->setMultiple("Y");
        $field->setModel(array(
            "item" => array(
                "source" => "tests.OPNsense.Base.BaseModel.TestModel",
                "items" => "simpleList.items",
                "display" => "number"
            )
        ));
        $field->eventPostLoading();
        $field->setValue("d1e7eda3-f940-42d9-8dfa-db7b1264b6e1," .
                         "4d0e2835-7a19-4a19-8c23-e12383827594," .
                         "60e1bc02-6817-4940-bbd3-61d0cf439a8a,");

        $node_data = array_keys($field->getNodeData());
        $this->assertEquals(array_search('5ea2a35c-b02b-485a-912b-d077e639bf9f', $node_data), 0);
        $this->assertEquals(array_search('48bea3c9-c563-4885-a593-dea0a6af2e1e', $node_data), 1);
        $this->assertEquals(array_search('d1e7eda3-f940-42d9-8dfa-db7b1264b6e1', $node_data), 2);
        $this->assertEquals(array_search('4d0e2835-7a19-4a19-8c23-e12383827594', $node_data), 3);
        $this->assertEquals(array_search('60e1bc02-6817-4940-bbd3-61d0cf439a8a', $node_data), 4);
        $this->assertEquals(array_search('3bf8fc9c-6f36-4821-9944-ed7dfed50ad6', $node_data), 5);

        // set sorted by input and check again
        $field->setSorted('Y');
        $node_data = array_keys($field->getNodeData());
        $this->assertEquals(array_search('5ea2a35c-b02b-485a-912b-d077e639bf9f', $node_data), 3);
        $this->assertEquals(array_search('48bea3c9-c563-4885-a593-dea0a6af2e1e', $node_data), 4);
        $this->assertEquals(array_search('d1e7eda3-f940-42d9-8dfa-db7b1264b6e1', $node_data), 0);
        $this->assertEquals(array_search('4d0e2835-7a19-4a19-8c23-e12383827594', $node_data), 1);
        $this->assertEquals(array_search('60e1bc02-6817-4940-bbd3-61d0cf439a8a', $node_data), 2);
        $this->assertEquals(array_search('3bf8fc9c-6f36-4821-9944-ed7dfed50ad6', $node_data), 5);
    }

    /**
     * Selecting some unordered, with multiple, with sorting, required false (default).
     */
    public function testSortedSelectSomeUnorderedOk()
    {
        $field = new ModelRelationField();
        $field->setMultiple("Y");
        $field->setModel(array(
            "item" => array(
                "source" => "tests.OPNsense.Base.BaseModel.TestModel",
                "items" => "simpleList.items",
                "display" => "number"
            )
        ));
        $field->eventPostLoading();
        $field->setValue("60e1bc02-6817-4940-bbd3-61d0cf439a8a," .
                         "48bea3c9-c563-4885-a593-dea0a6af2e1e," .
                         "4d0e2835-7a19-4a19-8c23-e12383827594,");

        $node_data = array_keys($field->getNodeData());
        $this->assertEquals(array_search('5ea2a35c-b02b-485a-912b-d077e639bf9f', $node_data), 0);
        $this->assertEquals(array_search('48bea3c9-c563-4885-a593-dea0a6af2e1e', $node_data), 1);
        $this->assertEquals(array_search('d1e7eda3-f940-42d9-8dfa-db7b1264b6e1', $node_data), 2);
        $this->assertEquals(array_search('4d0e2835-7a19-4a19-8c23-e12383827594', $node_data), 3);
        $this->assertEquals(array_search('60e1bc02-6817-4940-bbd3-61d0cf439a8a', $node_data), 4);
        $this->assertEquals(array_search('3bf8fc9c-6f36-4821-9944-ed7dfed50ad6', $node_data), 5);

        // set sorted by input and check again
        $field->setSorted('Y');
        $node_data = array_keys($field->getNodeData());
        $this->assertEquals(array_search('5ea2a35c-b02b-485a-912b-d077e639bf9f', $node_data), 3);
        $this->assertEquals(array_search('48bea3c9-c563-4885-a593-dea0a6af2e1e', $node_data), 1);
        $this->assertEquals(array_search('d1e7eda3-f940-42d9-8dfa-db7b1264b6e1', $node_data), 4);
        $this->assertEquals(array_search('4d0e2835-7a19-4a19-8c23-e12383827594', $node_data), 2);
        $this->assertEquals(array_search('60e1bc02-6817-4940-bbd3-61d0cf439a8a', $node_data), 0);
        $this->assertEquals(array_search('3bf8fc9c-6f36-4821-9944-ed7dfed50ad6', $node_data), 5);
    }

    /**
     * Selecting some unordered, with multiple, with invalid, with sorting, required false (default).
     * Invalid entry is ignored.
     */
    public function testSortedSelectSomeInvalidOk()
    {
        $field = new ModelRelationField();
        $field->setMultiple("Y");
        $field->setModel(array(
            "item" => array(
                "source" => "tests.OPNsense.Base.BaseModel.TestModel",
                "items" => "simpleList.items",
                "display" => "number"
            )
        ));
        $field->eventPostLoading();
        $field->setValue("60e1bc02-6817-4940-bbd3-61d0cf439a8a," .
                         "d3adb33f-c563-4885-a593-dea0a6af2e1e," .
                         "4d0e2835-7a19-4a19-8c23-e12383827594,");

        $node_data = array_keys($field->getNodeData());
        $this->assertEquals(array_search('5ea2a35c-b02b-485a-912b-d077e639bf9f', $node_data), 0);
        $this->assertEquals(array_search('48bea3c9-c563-4885-a593-dea0a6af2e1e', $node_data), 1);
        $this->assertEquals(array_search('d1e7eda3-f940-42d9-8dfa-db7b1264b6e1', $node_data), 2);
        $this->assertEquals(array_search('4d0e2835-7a19-4a19-8c23-e12383827594', $node_data), 3);
        $this->assertEquals(array_search('60e1bc02-6817-4940-bbd3-61d0cf439a8a', $node_data), 4);
        $this->assertEquals(array_search('3bf8fc9c-6f36-4821-9944-ed7dfed50ad6', $node_data), 5);

        // set sorted by input and check again
        $field->setSorted('Y');
        $node_data = array_keys($field->getNodeData());
        $this->assertEquals(array_search('5ea2a35c-b02b-485a-912b-d077e639bf9f', $node_data), 2);
        $this->assertEquals(array_search('48bea3c9-c563-4885-a593-dea0a6af2e1e', $node_data), 3);
        $this->assertEquals(array_search('d1e7eda3-f940-42d9-8dfa-db7b1264b6e1', $node_data), 4);
        $this->assertEquals(array_search('4d0e2835-7a19-4a19-8c23-e12383827594', $node_data), 1);
        $this->assertEquals(array_search('60e1bc02-6817-4940-bbd3-61d0cf439a8a', $node_data), 0);
        $this->assertEquals(array_search('3bf8fc9c-6f36-4821-9944-ed7dfed50ad6', $node_data), 5);
    }

    /**
     * type is not a container
     */
    public function testIsContainer()
    {
        $field = new ModelRelationField();
        $this->assertFalse($field->isContainer());
    }
}
