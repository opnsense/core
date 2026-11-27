<?php

/*
 * Copyright (C) 2026 Deciso B.V.
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

namespace tests\OPNsense\Firewall\FieldTypes;

use OPNsense\Base\FieldTypes\ArrayField;
use OPNsense\Base\FieldTypes\ContainerField;
use OPNsense\Base\FieldTypes\TextField;
use OPNsense\Firewall\FieldTypes\ScheduleArrayField;

class ScheduleArrayFieldTest extends \PHPUnit\Framework\TestCase
{
    // A new schedule gets a UUID, an empty name and no time ranges, even when copied from one with ranges.
    public function testAddInitializesSchedule()
    {
        $field = new ScheduleArrayField('schedule', 'schedule');
        $template = new ContainerField('schedule.template', 'schedule');
        $template->addChildNode('name', new TextField('schedule.template.name', 'name'));
        $ranges = new ArrayField('schedule.template.timerange', 'timerange');
        $range = new ContainerField('schedule.template.timerange.template', 'timerange');
        $range->addChildNode('rangedescr', new TextField($range->__reference . '.rangedescr', 'rangedescr'));
        $ranges->addChildNode('template', $range);
        $template->addChildNode('timerange', $ranges);
        $field->addChildNode('template', $template);
        $field->eventPostLoading();

        $schedule = $field->add();
        $this->assertNotEmpty($schedule->getAttribute('uuid'));
        $this->assertTrue($schedule->name->isEmpty());
        $this->assertSame(0, $schedule->timerange->count());
    }
}
