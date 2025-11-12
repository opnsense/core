<?php

/*
 * Copyright (C) 2023 Deciso B.V.
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

namespace OPNsense\Base\Constraints;

use OPNsense\Base\FieldTypes\ArrayField;
use OPNsense\Base\FieldTypes\ContainerField;
use OPNsense\Base\FieldTypes\TextField;

class UniqueTestContainer extends ArrayField
{
    private bool $valuesRequired = false;
    private array $internalNodes = [];
    private bool $caseInsensitive = false;

    /**
     * @param $nodes a single node or an array of nodes
     * a single node represent a single unique value across
     * multiple array elements, while multiple nodes represent
     * the usage of 'addFields'
     */
    public function addNode($nodes)
    {
        // UniqueConstraint requires a depth of 2, so add a container node
        $container = new ContainerField();
        $idx = count($this->internalNodes);
        $this->addChildNode('item' . $idx, $container);
        foreach ($nodes as $name => $value) {
            $node = new TextField(null, $name);
            $node->setRequired($this->valuesRequired ? "Y" : "N");
            $node->setValue((string)$value);
            $container->addChildNode($name, $node);
        }
        $this->internalNodes[] = $nodes;
    }

    public function setRequired($required)
    {
        $this->valuesRequired = $required;

        foreach ($this->iterateItems() as $records) {
            foreach ($records->iterateItems() as $node) {
                /* cover earlier set nodes */
                $node->setRequired($this->valuesRequired ? "Y" : "N");
            }
        }
    }

    public function setCaseInsensitive(bool $val)
    {
        $this->caseInsensitive = $val;
    }

    public function validate()
    {
        $uniqueConstraints = [];
        $validator = new \OPNsense\Base\Validation();
        foreach ($this->internalNodes as $idx => $nodes) {
            $addFields = [];
            $constraint = new UniqueConstraint();
            $constraint->setOption('caseInsensitive', $this->caseInsensitive ? "Y" : "N");

            foreach ($nodes as $name => $value) {
                if ($name === array_key_first($nodes)) {
                    $constraint->setOption('node', $this->{'item' . $idx}->$name);
                    $constraint->setOption('name', $name);
                    $constraint->setOption('ValidationMessage', 'Validation Failed');
                } else {
                    $addFields[] = $name;
                }
            }
            if ($addFields) {
                $constraint->setOption('addFields', $addFields);
            }
            $validator->add($idx, $constraint);
        }
        $msgs = $validator->validate([]);

        return $msgs;
    }
}

class UniqueConstraintTest extends \PHPUnit\Framework\Testcase
{
    public function testNonEqualAndEqualValues()
    {
        $container = new UniqueTestContainer();
        $container->addNode(['unique_test' => 'value1']);
        $container->addNode(['unique_test' => 'value2']);

        $msgs = $container->validate();
        $this->assertEquals(0, count($msgs));

        $container->addNode(['unique_test' => 'value1']);

        $msgs = $container->validate();

        $this->assertEquals(2, count($msgs));
    }

    public function testMultipleNonEqualAndEqualValues()
    {
        $container = new UniqueTestContainer();
        $container->addNode(['unique_test' => 'value1', 'unique_test2' => 'value2']);
        $container->addNode(['unique_test' => 'value1', 'unique_test2' => 'value3']);

        $msgs = $container->validate();
        $this->assertEquals(0, count($msgs));

        $container->addNode(['unique_test' => 'value1', 'unique_test2' => 'value2']);

        $msgs = $container->validate();
        $this->assertEquals(2, count($msgs));
    }

    public function testEmptyValuesNotRequiredAndRequired()
    {
        $container = new UniqueTestContainer();
        $container->addNode(['unique_test' => '']);
        $container->addNode(['unique_test' => '']);

        $msgs = $container->validate();

        $this->assertEquals(0, count($msgs));

        $container->setRequired(true);

        $msgs = $container->validate();

        $this->assertEquals(2, count($msgs));
    }

    public function testMultipleEmptyValuesNotRequiredAndRequired()
    {
        $container = new UniqueTestContainer();
        $container->addNode(['unique_test' => '', 'unique_test2' => '']);
        $container->addNode(['unique_test' => '', 'unique_test2' => '']);
        $msgs = $container->validate();

        $this->assertEquals(0, count($msgs));

        $container->setRequired(true);

        $msgs = $container->validate();

        $this->assertEquals(2, count($msgs));
    }

    public function testFirstValueEmptyPassAll()
    {
        $container = new UniqueTestContainer();
        $container->addNode(['unique_test1' => '', 'unique_test2' => 'value1']);
        $container->addNode(['unique_test1' => '', 'unique_test2' => 'value1']);

        $msgs = $container->validate();

        $this->assertEquals(0, count($msgs));

        $container->setRequired(true);

        $msgs = $container->validate();

        $this->assertEquals(2, count($msgs));
    }

    public function testCaseInsensitiveDuplicatesFail()
    {
        $container = new UniqueTestContainer();
        $container->setCaseInsensitive(true);

        $container->addNode(['unique_test' => 'Value1']);
        $container->addNode(['unique_test' => 'value1']);

        $msgs = $container->validate();

        $this->assertEquals(2, count($msgs));
    }

    public function testCaseSensitiveDifferentCasePasses()
    {
        $container = new UniqueTestContainer();
        $container->setCaseInsensitive(false);

        $container->addNode(['unique_test' => 'Value1']);
        $container->addNode(['unique_test' => 'value1']);

        $msgs = $container->validate();

        $this->assertEquals(0, count($msgs));
    }
}
