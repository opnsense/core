<?php

/*
 * Copyright (C) 2016-2025 Deciso B.V.
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

namespace tests\OPNsense\Base;

use OPNsense\Core\AppConfig;
use OPNsense\Core\Config;

require_once 'BaseModel/TestModel.php';

class BaseModelTest extends \PHPUnit\Framework\TestCase
{
    private static $configDir = __DIR__ . '/BaseModelConfig';
    private static $model = null;

    public static function cleanupTestFiles()
    {
        @unlink(self::$configDir . '/config.xml');
    }

    public function testCanBeCreated()
    {
        self::cleanupTestFiles();

        (new AppConfig())->update('application.configDir', self::$configDir);
        Config::getInstance()->forceReload();

        self::$model = new BaseModel\TestModel();
        $this->assertInstanceOf('tests\OPNsense\Base\BaseModel\TestModel', self::$model);
    }

    /**
     * @depends testCanBeCreated
     */
    public function testGeneralAvailable()
    {
        $this->assertNotNull(self::$model->general);
    }

    /**
     * @depends testCanBeCreated
     */
    public function testRunMigrations()
    {
        self::$model->runMigrations();
        // migration should have prefixed our default email address
        $this->assertEquals(self::$model->general->FromEmail->getValue(), '100_001_sample@example.com');
    }

    /**
     * @depends testRunMigrations
     */
    public function testCanSetStringValue()
    {
        self::$model->general->FromEmail = 'test@test.nl';
        $this->assertEquals(self::$model->general->FromEmail->getValue(), 'test@test.nl');
    }

    /**
     * @depends testRunMigrations
     */
    public function testCannotSetNonExistingField()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('FromEmailXXX not an attribute of general');
        self::$model->general->FromEmailXXX = 'test@test.nl';
    }

    /**
     * @depends testRunMigrations
     */
    public function testCanAssignArrayType()
    {
        // purge test items (if any)
        foreach (self::$model->arraytypes->item->iterateItems() as $nodeid => $node) {
            self::$model->arraytypes->item->Del($nodeid);
        }
        // generate new items
        for ($i = 1; $i <= 10; $i++) {
            $node = self::$model->arraytypes->item->Add();
            $node->number = $i;
        }
        // flush to disk
        self::$model->serializeToConfig();
        Config::getInstance()->save(['username' => __CLASS__, 'description' => 'N/A', 'time' => '0'], false);
        $this->assertNotEquals(file_get_contents(self::$configDir . '/backup/config.xml'), file_get_contents(self::$configDir . '/config.xml'));

        // load from disk
        Config::getInstance()->forceReload();
        self::$model = new BaseModel\TestModel();

        // read items, logically the sequence should be the same as the generated items
        $i = 1;
        foreach (self::$model->arraytypes->item->iterateItems() as $node) {
            $this->assertEquals($i, (string)$node->number);
            $i++;
        }
    }

    /**
     * @depends testCanAssignArrayType
     */
    public function testCanDeleteSpecificItem()
    {
        foreach (self::$model->arraytypes->item->iterateItems() as $nodeid => $node) {
            if ($node->number->isEqual(5)) {
                self::$model->arraytypes->item->Del($nodeid);
            }
        }
        // item with number 5 should be deleted
        foreach (self::$model->arraytypes->item->iterateItems() as $nodeid => $node) {
            $this->assertNotEquals((string)$node->number, 5);
        }
        // 9 items left
        $this->assertEquals(count(iterator_to_array(self::$model->arraytypes->item->iterateItems())), 9);
    }

    /**
     * @depends testCanAssignArrayType
     */
    public function testArrayIsKeydByUUID()
    {
        foreach (self::$model->arraytypes->item->iterateItems() as $nodeid => $node) {
            $this->assertCount(5, explode('-', $nodeid));
        }
    }

    /**
     * @depends testCanAssignArrayType
     */
    public function testValidationOk()
    {
        // nothing changed, valid config
        self::$model->serializeToConfig();
        $this->assertInstanceOf('tests\OPNsense\Base\BaseModel\TestModel', self::$model);
    }

    /**
     * @depends testCanAssignArrayType
     */
    public function testValidationNOK()
    {
        $this->expectException(\OPNsense\Base\ValidationException::class);
        $this->expectExceptionMessage('Not a valid number.');
        // replace all numbers
        foreach (self::$model->arraytypes->item->iterateItems() as $nodeid => $node) {
            $node->number = "XXX";
        }
        self::$model->serializeToConfig();
    }

    /**
     * @depends testRunMigrations
     */
    public function testsetNodeByReferenceFound()
    {
        $this->assertEquals(self::$model->setNodeByReference('general.FromEmail', 'test@test.com'), true);
    }

    /**
     * @depends testRunMigrations
     */
    public function testsetNodeByReferenceNotFound()
    {
        $this->assertEquals(self::$model->setNodeByReference('general.FromEmailxx', 'test@test.com'), false);
    }

    /**
     * @depends testCanDeleteSpecificItem
     */
    public function testGenerateXML()
    {
        $xml = self::$model->toXML();
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
        foreach (self::$model->arraytypes->item->iterateItems() as $nodeid => $node) {
            $data[$node->__reference] = $i;
            $i++;
        }
        foreach ($data as $key => $value) {
            $node = self::$model->getNodeByReference($key);
            $node->setNodes(array('number' => $value));
        }

        foreach (self::$model->arraytypes->item->iterateItems() as $nodeid => $node) {
            $this->assertGreaterThan(99, (string)$node->number);
        }
    }

    /**
     * @depends testCanDeleteSpecificItem
     */
    public function testGetNodes()
    {
        $data = self::$model->arraytypes->item->getNodes();
        $this->assertEquals(count($data), 9);
    }

    /**
     * @depends testGetNodes
     */
    public function testConstraintsNok()
    {
        $this->expectException(\OPNsense\Base\ValidationException::class);
        $this->expectExceptionMessage('Number should be unique.');
        $count = 2;
        foreach (self::$model->arraytypes->item->iterateItems() as $nodeid => $node) {
            $count--;
            if ($count >= 0) {
                $node->number = 999;
            }
        }
        self::$model->serializeToConfig();
    }

    /**
     * @depends testConstraintsNok
     */
    public function testConstraintsOk()
    {
        $count = 1;
        foreach (self::$model->arraytypes->item->iterateItems() as $nodeid => $node) {
            $count++;
            $node->number = $count;
        }
        self::$model->serializeToConfig();
        $this->assertInstanceOf('tests\OPNsense\Base\BaseModel\TestModel', self::$model);
    }

    /**
     * @depends testRunMigrations
     */
    public function testAllOrNoneInitial()
    {
        self::$model->AllOrNone->value1 = '';
        self::$model->AllOrNone->value2 = '';
        self::$model->AllOrNone->value3 = '';
        self::$model->serializeToConfig();
        $this->assertEquals(Config::getInstance()->object()->tests->OPNsense->TestModel->AllOrNone->value1, '');
        $this->assertEquals(Config::getInstance()->object()->tests->OPNsense->TestModel->AllOrNone->value2, '');
        $this->assertEquals(Config::getInstance()->object()->tests->OPNsense->TestModel->AllOrNone->value3, '');
    }

    /**
     * @depends testAllOrNoneInitial
     */
    public function testAllOrNoneNok()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("All fields should contain data or none of them");
        self::$model->AllOrNone->value1 = '';
        self::$model->AllOrNone->value2 = 'X';
        self::$model->AllOrNone->value3 = '';
        self::$model->serializeToConfig();
    }

    /**
     * @depends testAllOrNoneNok
     */
    public function testAllOrNoneOk()
    {
        self::$model->AllOrNone->value1 = 'X1';
        self::$model->AllOrNone->value2 = 'X2';
        self::$model->AllOrNone->value3 = 'X3';
        self::$model->serializeToConfig();
        $this->assertEquals(Config::getInstance()->object()->tests->OPNsense->TestModel->AllOrNone->value1, "X1");
        $this->assertEquals(Config::getInstance()->object()->tests->OPNsense->TestModel->AllOrNone->value2, "X2");
        $this->assertEquals(Config::getInstance()->object()->tests->OPNsense->TestModel->AllOrNone->value3, "X3");
    }


    /**
     * @depends testRunMigrations
     */
    public function testSingleSelectInitial()
    {
        self::$model->SingleSelect->value1 = '';
        self::$model->SingleSelect->value2 = '';
        self::$model->SingleSelect->value3 = '';
        self::$model->serializeToConfig();
        $this->assertEquals(Config::getInstance()->object()->tests->OPNsense->TestModel->SingleSelect->value1, '');
        $this->assertEquals(Config::getInstance()->object()->tests->OPNsense->TestModel->SingleSelect->value2, '');
        $this->assertEquals(Config::getInstance()->object()->tests->OPNsense->TestModel->SingleSelect->value3, '');
    }

    /**
     * @depends testSingleSelectInitial
     */
    public function testSingleSelectNok()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("Only one option could be selected");
        self::$model->SingleSelect->value1 = 'x';
        self::$model->SingleSelect->value2 = 'x';
        self::$model->SingleSelect->value3 = '';
        self::$model->serializeToConfig();
    }


    /**
     * @depends testSingleSelectNok
     */
    public function testSingleSelectOk()
    {
        self::$model->SingleSelect->value1 = '';
        self::$model->SingleSelect->value2 = 'x';
        self::$model->SingleSelect->value3 = '';
        self::$model->serializeToConfig();
        $this->assertEquals(Config::getInstance()->object()->tests->OPNsense->TestModel->SingleSelect->value1, '');
        $this->assertEquals(Config::getInstance()->object()->tests->OPNsense->TestModel->SingleSelect->value2, 'x');
        $this->assertEquals(Config::getInstance()->object()->tests->OPNsense->TestModel->SingleSelect->value3, '');
    }

    /**
     * @depends testRunMigrations
     */
    public function testDependConstraintInitial()
    {
        self::$model->DependConstraint->value1 = '0';
        self::$model->DependConstraint->value2 = '';
        self::$model->serializeToConfig();
        $this->assertEquals(Config::getInstance()->object()->tests->OPNsense->TestModel->DependConstraint->value1, "0");
        $this->assertEquals(
            Config::getInstance()->object()->tests->OPNsense->TestModel->DependConstraint->value2,
            ""
        );
    }

    /**
     * @depends testDependConstraintInitial
     */
    public function testDependConstraintNok()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('When "value1" is enabled, "value2" is required.');
        self::$model->DependConstraint->value1 = '1';
        self::$model->DependConstraint->value2 = '';
        self::$model->serializeToConfig();
        $this->assertEquals(Config::getInstance()->object()->tests->OPNsense->TestModel->DependConstraint->value1, "0");
    }

    /**
     * @depends testDependConstraintInitial
     */
    public function testDependConstraintOk1()
    {
        self::$model->DependConstraint->value1 = '1';
        self::$model->DependConstraint->value2 = 'xxx';
        self::$model->serializeToConfig();
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
        self::$model->DependConstraint->value1 = '0';
        self::$model->DependConstraint->value2 = 'xxx';
        self::$model->serializeToConfig();
        $this->assertEquals(Config::getInstance()->object()->tests->OPNsense->TestModel->DependConstraint->value1, "0");
        $this->assertEquals(
            Config::getInstance()->object()->tests->OPNsense->TestModel->DependConstraint->value2,
            "xxx"
        );
    }

    /**
     * @afterClass
     */
    public static function postCleanup()
    {
        self::cleanupTestFiles();
    }
}
