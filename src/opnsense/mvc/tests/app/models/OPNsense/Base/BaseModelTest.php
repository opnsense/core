<?php

/**
 *    Copyright (C) 2016 Deciso B.V.
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

use \OPNsense\Core\Config;

require_once 'BaseModel/TestModel.php';

class BaseModelTest extends \PHPUnit\Framework\TestCase
{
    private static $model = null;

    public function testResetConfig()
    {
        // reset version, force migrations
        if (!empty(Config::getInstance()->object()->tests) &&
            !empty(Config::getInstance()->object()->tests->OPNsense) &&
            !empty(Config::getInstance()->object()->tests->OPNsense->TestModel)) {
            Config::getInstance()->object()->tests->OPNsense->TestModel['version'] = '0.0.0';
            Config::getInstance()->object()->tests->OPNsense->TestModel->general->FromEmail = "sample@example.com";
        }
        BaseModelTest::$model = new BaseModel\TestModel();
        $this->assertEquals((string)BaseModelTest::$model->general->FromEmail, 'sample@example.com');
    }

    /**
     * @depends testResetConfig
     */
    public function testCanBeCreated()
    {
        BaseModelTest::$model = new BaseModel\TestModel();
        $this->assertInstanceOf('tests\OPNsense\Base\BaseModel\TestModel', BaseModelTest::$model);
    }

    /**
     * @depends testCanBeCreated
     */
    public function testGeneralAvailable()
    {
        $this->assertNotNull(BaseModelTest::$model->general);
    }

    /**
     * @depends testCanBeCreated
     */
    public function testRunMigrations()
    {
        BaseModelTest::$model->runMigrations();
        // migration should have prefixed our default email address
        $this->assertEquals((string)BaseModelTest::$model->general->FromEmail, '100_001_sample@example.com');
    }

    /**
     * @depends testRunMigrations
     */
    public function testCanSetStringValue()
    {
        BaseModelTest::$model->general->FromEmail = "test@test.nl";
        $this->assertEquals(BaseModelTest::$model->general->FromEmail, "test@test.nl");
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage FromEmailXXX not an attribute of general
     * @depends testRunMigrations
     */
    public function testCannotSetNonExistingField()
    {
        BaseModelTest::$model->general->FromEmailXXX = "test@test.nl";
    }

    /**
     * @depends testRunMigrations
     */
    public function testCanAssignArrayType()
    {
        // purge test items (if any)
        foreach (BaseModelTest::$model->arraytypes->item->iterateItems() as $nodeid => $node) {
            BaseModelTest::$model->arraytypes->item->Del($nodeid);
        }
        // generate new items
        for ($i = 1; $i <= 10; $i++) {
            $node = BaseModelTest::$model->arraytypes->item->Add();
            $node->number = $i;
        }
        // flush to disk
        BaseModelTest::$model->serializeToConfig();
        Config::getInstance()->save();

        // load from disk
        Config::getInstance()->forceReload();
        BaseModelTest::$model = new BaseModel\TestModel();

        // read items, logically the sequence should be the same as the generated items
        $i = 1;
        foreach (BaseModelTest::$model->arraytypes->item->iterateItems() as $node) {
            $this->assertEquals($i, (string)$node->number);
            $i++;
        }
    }

    /**
     * @depends testCanAssignArrayType
     */
    public function testCanDeleteSpecificItem()
    {
        foreach (BaseModelTest::$model->arraytypes->item->iterateItems() as $nodeid => $node) {
            if ((string)$node->number == 5) {
                BaseModelTest::$model->arraytypes->item->Del($nodeid);
            }
        }
        // item with number 5 should be deleted
        foreach (BaseModelTest::$model->arraytypes->item->iterateItems() as $nodeid => $node) {
            $this->assertNotEquals((string)$node->number, 5);
        }
        // 9 items left
        $this->assertEquals(count(iterator_to_array(BaseModelTest::$model->arraytypes->item->iterateItems())), 9);
    }

    /**
     * @depends testCanAssignArrayType
     */
    public function testArrayIsKeydByUUID()
    {
        foreach (BaseModelTest::$model->arraytypes->item->iterateItems() as $nodeid => $node) {
            $this->assertCount(5, explode('-', $nodeid));
        }
    }

    /**
     * @depends testCanAssignArrayType
     */
    public function testValidationOk()
    {
        // nothing changed, valid config
        BaseModelTest::$model->serializeToConfig();
        $this->assertInstanceOf('tests\OPNsense\Base\BaseModel\TestModel', BaseModelTest::$model);
    }

    /**
     * @depends testCanAssignArrayType
     * @expectedException \Phalcon\Validation\Exception
     * @expectedExceptionMessage not a valid number
     */
    public function testValidationNOK()
    {
        // replace all numbers
        foreach (BaseModelTest::$model->arraytypes->item->iterateItems() as $nodeid => $node) {
            $node->number = "XXX";
        }
        BaseModelTest::$model->serializeToConfig();
    }

    /**
     * @depends testRunMigrations
     */
    public function testsetNodeByReferenceFound()
    {
        $this->assertEquals(BaseModelTest::$model->setNodeByReference('general.FromEmail', 'test@test.com'), true);
    }

    /**
     * @depends testRunMigrations
     */
    public function testsetNodeByReferenceNotFound()
    {
        $this->assertEquals(BaseModelTest::$model->setNodeByReference('general.FromEmailxx', 'test@test.com'), false);
    }

    /**
     * @depends testCanDeleteSpecificItem
     */
    public function testGenerateXML()
    {
        $xml = BaseModelTest::$model->toXML();
        $this->assertInstanceOf(\SimpleXMLElement::class, $xml);
        $this->assertNotEquals(count($xml->OPNsense), 0);
        $this->assertNotEquals(count($xml->OPNsense->TestModel), 0);
        $this->assertNotEquals(count($xml->OPNsense->TestModel->general), 0);
        // expect 9 detail items at this point
        $this->assertEquals(count($xml->OPNsense->TestModel->arraytypes->item), 9);
    }

    /**
     * @depends testCanDeleteSpecificItem
     */
    public function testSetNode()
    {
        $data = array();
        $i = 100;
        foreach (BaseModelTest::$model->arraytypes->item->iterateItems() as $nodeid => $node) {
            $data[$node->__reference] = $i;
            $i++;
        }
        foreach ($data as $key => $value) {
            $node = BaseModelTest::$model->getNodeByReference($key);
            $node->setNodes(array('number' => $value));
        }

        foreach (BaseModelTest::$model->arraytypes->item->iterateItems() as $nodeid => $node) {
            $this->assertGreaterThan(99, (string)$node->number);
        }
    }

    /**
     * @depends testCanDeleteSpecificItem
     */
    public function testGetNodes()
    {
        $data = BaseModelTest::$model->arraytypes->item->getNodes();
        $this->assertEquals(count($data), 9);
    }

    /**
     * @depends testGetNodes
     * @expectedException \Phalcon\Validation\Exception
     * @expectedExceptionMessage number should be unique
     */
    public function testConstraintsNok()
    {
        $count = 2;
        foreach (BaseModelTest::$model->arraytypes->item->iterateItems() as $nodeid => $node) {
            $count--;
            if ($count >= 0) {
                $node->number = 999;
            }
        }
        BaseModelTest::$model->serializeToConfig();
    }

    /**
     * @depends testConstraintsNok
     */
    public function testConstraintsOk()
    {
        $count = 1;
        foreach (BaseModelTest::$model->arraytypes->item->iterateItems() as $nodeid => $node) {
            $count++;
            $node->number = $count;
        }
        BaseModelTest::$model->serializeToConfig();
        $this->assertInstanceOf('tests\OPNsense\Base\BaseModel\TestModel', BaseModelTest::$model);
    }

    /**
     * @depends testRunMigrations
     */
    public function testAllOrNoneInitial()
    {
        BaseModelTest::$model->AllOrNone->value1 = "";
        BaseModelTest::$model->AllOrNone->value2 = "";
        BaseModelTest::$model->AllOrNone->value3 = "";
        BaseModelTest::$model->serializeToConfig();
        $this->assertEquals(Config::getInstance()->object()->tests->OPNsense->TestModel->AllOrNone->value1, '');
        $this->assertEquals(Config::getInstance()->object()->tests->OPNsense->TestModel->AllOrNone->value2, '');
        $this->assertEquals(Config::getInstance()->object()->tests->OPNsense->TestModel->AllOrNone->value3, '');
    }

    /**
     * @expectedException Exception
     * @expectedExceptionMessage All fields should contain data or none of them
     * @depends testAllOrNoneInitial
     */
    public function testAllOrNoneNok()
    {
        BaseModelTest::$model->AllOrNone->value1 = "";
        BaseModelTest::$model->AllOrNone->value2 = "X";
        BaseModelTest::$model->AllOrNone->value3 = "";
        BaseModelTest::$model->serializeToConfig();
        $this->assertEquals(Config::getInstance()->object()->tests->OPNsense->TestModel->AllOrNone->value1, '');
        $this->assertEquals(Config::getInstance()->object()->tests->OPNsense->TestModel->AllOrNone->value2, 'X');
        $this->assertEquals(Config::getInstance()->object()->tests->OPNsense->TestModel->AllOrNone->value3, '');
    }

    /**
     * @depends testAllOrNoneNok
     */
    public function testAllOrNoneOk()
    {
        BaseModelTest::$model->AllOrNone->value1 = "X1";
        BaseModelTest::$model->AllOrNone->value2 = "X2";
        BaseModelTest::$model->AllOrNone->value3 = "X3";
        BaseModelTest::$model->serializeToConfig();
        $this->assertEquals(Config::getInstance()->object()->tests->OPNsense->TestModel->AllOrNone->value1, "X1");
        $this->assertEquals(Config::getInstance()->object()->tests->OPNsense->TestModel->AllOrNone->value2, "X2");
        $this->assertEquals(Config::getInstance()->object()->tests->OPNsense->TestModel->AllOrNone->value3, "X3");
    }

    /**
     * @depends testRunMigrations
     */
    public function testDependConstraintInitial()
    {
        BaseModelTest::$model->DependConstraint->value1 = "0";
        BaseModelTest::$model->DependConstraint->value2 = "";
        BaseModelTest::$model->serializeToConfig();
        $this->assertEquals(Config::getInstance()->object()->tests->OPNsense->TestModel->DependConstraint->value1, "0");
        $this->assertEquals(
            Config::getInstance()->object()->tests->OPNsense->TestModel->DependConstraint->value2,
            ""
        );
    }

    /**
     * @depends testDependConstraintInitial
     * @expectedException Exception
     * @expectedExceptionMessage when value1 is enabled value2 is required
     */
    public function testDependConstraintNok()
    {
        BaseModelTest::$model->DependConstraint->value1 = "1";
        BaseModelTest::$model->DependConstraint->value2 = "";
        BaseModelTest::$model->serializeToConfig();
        $this->assertEquals(Config::getInstance()->object()->tests->OPNsense->TestModel->DependConstraint->value1, "0");
    }

    /**
     * @depends testDependConstraintInitial
     */
    public function testDependConstraintOk1()
    {
        BaseModelTest::$model->DependConstraint->value1 = "1";
        BaseModelTest::$model->DependConstraint->value2 = "xxx";
        BaseModelTest::$model->serializeToConfig();
        $this->assertEquals(Config::getInstance()->object()->tests->OPNsense->TestModel->DependConstraint->value1, "1");
        $this->assertEquals(
            Config::getInstance()->object()->tests->OPNsense->TestModel->DependConstraint->value2,
            "xxx"
        );
    }

    /**
     * @depends testDependConstraintInitial
     */
    public function testDependConstraintOk2()
    {
        BaseModelTest::$model->DependConstraint->value1 = "0";
        BaseModelTest::$model->DependConstraint->value2 = "xxx";
        BaseModelTest::$model->serializeToConfig();
        $this->assertEquals(Config::getInstance()->object()->tests->OPNsense->TestModel->DependConstraint->value1, "0");
        $this->assertEquals(
            Config::getInstance()->object()->tests->OPNsense->TestModel->DependConstraint->value2,
            "xxx"
        );
    }
}
