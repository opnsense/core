<?php

/**
 *    Copyright (C) 2016 Deciso B.V.
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

use OPNsense\Base\FieldTypes\AutoNumberField;
use OPNsense\Base\FieldTypes\ContainerField;
use OPNsense\Base\FieldTypes\ArrayField;

class AutoNumberFieldTest extends Field_Framework_TestCase
{
    /**
     * test construct
     */
    public function testCanBeCreated()
    {
        $this->assertInstanceOf('\OPNsense\Base\FieldTypes\AutoNumberField', new AutoNumberField());
    }

    /**
     * test sequence new items
     */
    public function testNumberGenerated()
    {

        // construct a structure with 10 sequenced items, set identifier on the first and trust
        // AutoNumberField to create numbers for the rest.
        $fieldTopLevel = new ContainerField("root", "root");
        $fieldContainer = new ContainerField($fieldTopLevel->__reference . ".items", "items");
        $fieldTopLevel->addChildNode("items", $fieldContainer);
        $itemContainer = new ArrayField($fieldContainer->__reference . ".item", "item");
        $fieldContainer->addChildNode("item", $itemContainer);
        for ($i = 1; $i <= 10; $i++) {
            $item = new ContainerField($itemContainer->__reference . "." . $i, "item");
            $tmp = new AutoNumberField($item->__reference . ".id", "id");
            $item->addChildNode("id", $tmp);
            $itemContainer->addChildNode($i, $item);
            if ($i <= 1) {
                $tmp->setValue((string)$i);
            } else {
                $tmp->applyDefault();
            }
        }

        // validate if all items contain the expected sequence number
        for ($i = 1; $i <= 10; $i++) {
            $this->assertEquals((string)$fieldTopLevel->items->item->{$i}->id, $i);
        }
    }

    /**
     * test sequence new items after deletions
     */
    public function testFillGaps()
    {
        // construct a structure with 10 sequenced items, set identifier on some items and trust
        // AutoNumberField to create numbers for the rest.
        $fieldTopLevel = new ContainerField("root", "root");
        $fieldContainer = new ContainerField($fieldTopLevel->__reference . ".items", "items");
        $fieldTopLevel->addChildNode("items", $fieldContainer);
        $itemContainer = new ArrayField($fieldContainer->__reference . ".item", "item");
        $fieldContainer->addChildNode("item", $itemContainer);
        for ($i = 1; $i <= 10; $i++) {
            $item = new ContainerField($itemContainer->__reference . "." . $i, "item");
            $tmp = new AutoNumberField($item->__reference . ".id", "id");
            $item->addChildNode("id", $tmp);
            $itemContainer->addChildNode($i, $item);
            if ($i == 2 || $i == 4 || $i == 7) {
                $tmp->setValue((string)$i);
            }
        }

        // apply defaults for unsequenced (1,3,5,6,8,9,10)
        for ($i = 1; $i <= 10; $i++) {
            if ($fieldTopLevel->items->item->{$i}->id == "") {
                $fieldTopLevel->items->item->{$i}->id->applyDefault();
            }
        }

        // validate if all items contain the expected sequence number
        for ($i = 1; $i <= 10; $i++) {
            $this->assertEquals((string)$fieldTopLevel->items->item->{$i}->id, $i);
        }
    }


    public function testNotANumber()
    {
        $field = new AutoNumberField();
        $field->setValue("X");
        $this->assertContains('IntegerValidator', $this->validate($field));
    }


    public function testValueNotInRange()
    {
        $field = new AutoNumberField();
        $field->setMaximumValue(100);
        $field->setMinimumValue(1);
        $field->setValue("101");
        $this->assertContains('MinMaxValidator', $this->validate($field));
        $field->setValue("0");
        $this->assertContains('MinMaxValidator', $this->validate($field));
    }
}
