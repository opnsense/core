<?php

/*
 * Copyright (C) 2019 Fabian Franz
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
use OPNsense\Base\FieldTypes\IntegerField;

/**
 * Class ComparedToFieldConstraintTest test code for the ComparedToFieldConstraint
 * @package OPNsense\Base\Constraints
 */
class ComparedToFieldConstraintTest extends \PHPUnit\Framework\TestCase
{
    // lesser then
    public function test_if_it_validates_number_ranges_correctly_with_lt_and_no_error()
    {
        $validator = new \OPNsense\Base\Validation();
        $validate = $this->make_validator(2, 3, 'test', 'lt');
        $ret = $validate->validate($validator, '');
        $messages = $validator->getMessages();
        $this->assertEquals(0, count($messages));
        $this->assertEquals(true, $ret);
    }

    public function test_if_it_validates_number_ranges_correctly_with_lt_and_error()
    {
        $validator = new \OPNsense\Base\Validation();
        $validate = $this->make_validator(3, 3, 'test', 'lt');
        $ret = $validate->validate($validator, '');
        $messages = $validator->getMessages();
        $this->assertEquals(1, count($messages));
        $this->assertEquals(true, $ret);
    }
    // greater then
    public function test_if_it_validates_number_ranges_correctly_with_gt_and_no_error()
    {
        $validator = new \OPNsense\Base\Validation();
        $validate = $this->make_validator(5, 3, 'test', 'gt');
        $ret = $validate->validate($validator, '');
        $messages = $validator->getMessages();
        $this->assertEquals(0, count($messages));
        $this->assertEquals(true, $ret);
    }

    public function test_if_it_validates_number_ranges_correctly_with_gt_and_error()
    {
        $validator = new \OPNsense\Base\Validation();
        $validate = $this->make_validator(2, 3, 'test', 'gt');
        $ret = $validate->validate($validator, '');
        $messages = $validator->getMessages();
        $this->assertEquals(1, count($messages));
        $this->assertEquals(true, $ret);
    }

    // zero values
    public function test_if_it_validates_zero_number_ranges_correctly_with_lt_and_error()
    {
        $validator = new \OPNsense\Base\Validation();
        $validate = $this->make_validator(2, 0, 'test', 'lt');
        $ret = $validate->validate($validator, '');
        $messages = $validator->getMessages();
        $this->assertEquals(1, count($messages));
        $this->assertEquals(true, $ret);
    }
    public function test_if_it_validates_zero_number_ranges_correctly_with_gt_and_error()
    {
        $validator = new \OPNsense\Base\Validation();
        $validate = $this->make_validator(0, 2, 'test', 'gt');
        $ret = $validate->validate($validator, '');
        $messages = $validator->getMessages();
        $this->assertEquals(1, count($messages));
        $this->assertEquals(true, $ret);
    }
    public function test_if_it_validates_zero_number_ranges_correctly_with_lt_and_no_error()
    {
        $validator = new \OPNsense\Base\Validation();
        $validate = $this->make_validator(0, 2, 'test', 'lt');
        $ret = $validate->validate($validator, '');
        $messages = $validator->getMessages();
        $this->assertEquals(0, count($messages));
        $this->assertEquals(true, $ret);
    }
    public function test_if_it_validates_zero_number_ranges_correctly_with_gt_and_no_error()
    {
        $validator = new \OPNsense\Base\Validation();
        $validate = $this->make_validator(2, 0, 'test', 'gt');
        $ret = $validate->validate($validator, '');
        $messages = $validator->getMessages();
        $this->assertEquals(0, count($messages));
        $this->assertEquals(true, $ret);
    }

    // Empty values
    public function test_if_it_validates_node_empty_values_correctly_with_gt_and_no_error()
    {
        $validator = new \OPNsense\Base\Validation();
        $validate = $this->make_validator('', 5, 'test', 'lt');
        $ret = $validate->validate($validator, '');
        $messages = $validator->getMessages();
        $this->assertEquals(0, count($messages));
        $this->assertEquals(true, $ret);
    }
    public function test_if_it_validates_other_node_empty_values_correctly_with_gt_and_no_error()
    {
        $validator = new \OPNsense\Base\Validation();
        $validate = $this->make_validator(5, '', 'test', 'gt');
        $ret = $validate->validate($validator, '');
        $messages = $validator->getMessages();
        $this->assertEquals(0, count($messages));
        $this->assertEquals(true, $ret);
    }

    // Null nodes
    public function test_if_it_validates_constraint_if_node_is_null_and_no_error()
    {
        $validator = new \OPNsense\Base\Validation();
        $validate = $this->make_validator(null, 2, 'test', 'eq');
        $ret = $validate->validate($validator, '');
        $messages = $validator->getMessages();
        $this->assertEquals(0, count($messages));
        $this->assertEquals(true, $ret);
    }
    public function test_if_it_validates_constraint_if_other_is_null_and_no_error()
    {
        $validator = new \OPNsense\Base\Validation();
        $validate = $this->make_validator(2, null, 'test', 'eq');
        $ret = $validate->validate($validator, '');
        $messages = $validator->getMessages();
        $this->assertEquals(0, count($messages));
        $this->assertEquals(true, $ret);
    }
    public function test_if_it_validates_constraint_if_both_are_null_and_no_error()
    {
        $validator = new \OPNsense\Base\Validation();
        $validate = $this->make_validator(null, null, 'test', 'eq');
        $ret = $validate->validate($validator, '');
        $messages = $validator->getMessages();
        $this->assertEquals(0, count($messages));
        $this->assertEquals(true, $ret);
    }

    /**
     * @param $node_value integer field content
     * @param $other_field_value integer field content
     * @param $field string name of the field
     * @param $operator string see the related documentation
     * @return ComparedToFieldConstraint the created constraint
     */
    private function make_validator($node_value, $other_field_value, $field, $operator)
    {
        $node = new IntegerField();
        $other_field = new IntegerField();
        $shared_parent = new ArrayField();
        $shared_parent->addChildNode('test_this', $node);
        $shared_parent->addChildNode($field, $other_field);
        $node->setValue($node_value);
        $other_field->setValue($other_field_value);
        $constraint = new ComparedToFieldConstraint();
        $constraint->setOption('node', $node);
        $constraint->setOption('name', 'test_this');
        $constraint->setOption('field', $field);
        $constraint->setOption('operator', $operator);
        $constraint->setOption('ValidationMessage', "Validation Failed");
        return $constraint;
    }
}
