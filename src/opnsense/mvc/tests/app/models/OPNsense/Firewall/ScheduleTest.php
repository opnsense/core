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

namespace tests\OPNsense\Firewall;

use OPNsense\Core\AppConfig;
use OPNsense\Core\Config;
use OPNsense\Firewall\Schedule;

class ScheduleTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Compare the legacy payload, allowing MVC UUID attributes and empty optional tags.
     */
    private function payload($schedules)
    {
        $result = [];
        foreach ($schedules as $schedule) {
            $ranges = [];
            foreach ($schedule->timerange as $range) {
                $ranges[] = array_map('strval', [
                    $range->position, $range->month, $range->day, $range->hour, $range->rangedescr
                ]);
            }
            $result[] = [(string)$schedule->name, (string)$schedule->descr, $ranges];
        }
        return $result;
    }

    public function testStorageFieldsProvideVolatileValues()
    {
        $model = new Schedule();
        $range = $model->schedule->add()->timerange->add();
        $range->setNodes(['month' => '12,12,1', 'day' => '24,25,1', 'hour' => '8:00-17:15']);
        $this->assertSame(
            ['12/24,12/25,1/1', '8:00', '17:15'],
            [$range->dates->getValue(), $range->start_time->getValue(), $range->stop_time->getValue()]
        );
    }

    public function testVolatileFieldsOverrideStorageAndAreNotSerialized()
    {
        $model = new Schedule();
        $range = $model->schedule->add()->timerange->add();
        // Post derived fields first to verify precedence follows model order, not request order.
        $range->setNodes([
            'dates' => '02/29,12/25',
            'start_time' => '09:15',
            'stop_time' => '17:45',
            'month' => '1',
            'day' => '1',
            'hour' => '00:00-23:59'
        ]);
        $xml = new \SimpleXMLElement('<schedule/>');
        $range->addToXMLNode($xml);
        $this->assertSame(
            ['02,12', '29,25', '09:15-17:45'],
            array_map('strval', [$xml->timerange->month, $xml->timerange->day, $xml->timerange->hour])
        );
        $this->assertEmpty($xml->xpath('timerange/dates | timerange/start_time | timerange/stop_time'));
    }

    public function testOutputMatchesFixture()
    {
        $app = new AppConfig();
        $configDir = $app->application->configDir;
        try {
            $app->update('application.configDir', __DIR__ . '/Schedule');
            Config::getInstance()->forceReload();
            $this->assertSame(
                $this->payload(Config::getInstance()->object()->schedules->schedule),
                $this->payload((new Schedule())->toXML()->schedule)
            );
        } finally {
            $app->update('application.configDir', $configDir);
            Config::getInstance()->forceReload();
        }
    }
}
