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

use OPNsense\Base\FieldTypes\ContainerField;
use OPNsense\Base\FieldTypes\TextField;
use OPNsense\Firewall\FieldTypes\ScheduleListField;

class ScheduleListFieldTest extends \PHPUnit\Framework\TestCase
{
    private ContainerField $range;

    // The fields read and update their siblings, so provide a minimal parent tree without loading the model.
    protected function setUp(): void
    {
        $this->range = new ContainerField('timerange', 'timerange');
        foreach (['month', 'day', 'hour'] as $tag) {
            $this->range->addChildNode($tag, new TextField('timerange.' . $tag, $tag));
        }
        foreach (['dates', 'start_time', 'stop_time'] as $tag) {
            $this->range->addChildNode($tag, new ScheduleListField('timerange.' . $tag, $tag));
        }
    }

    // Parallel month/day lists become paired dates. Hour is split into start and stop values.
    public function testStorageProvideValues()
    {
        $this->range->setNodes(['month' => '12,12,1', 'day' => '24,25,1', 'hour' => '8:00-17:15']);
        $this->assertSame(
            ['12/24,12/25,1/1', '8:00', '17:15'],
            [$this->range->dates->getValue(), $this->range->start_time->getValue(), $this->range->stop_time->getValue()]
        );
    }

    // Setting the derived fields updates their backing values
    public function testValuesUpdateStorage()
    {
        $this->range->setNodes(['dates' => '02/29,12/25', 'start_time' => '09:15', 'stop_time' => '17:45']);
        $this->assertSame(
            ['02,12', '29,25', '09:15-17:45'],
            [$this->range->month->getValue(), $this->range->day->getValue(), $this->range->hour->getValue()]
        );
    }
}
