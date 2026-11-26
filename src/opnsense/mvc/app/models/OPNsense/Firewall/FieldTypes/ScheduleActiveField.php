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

namespace OPNsense\Firewall\FieldTypes;

use OPNsense\Base\FieldTypes\BooleanField;

class ScheduleActiveField extends BooleanField
{
    public function getValue(): string
    {
        $schedule = $this->getParentNode();
        $now = new \DateTimeImmutable();
        foreach ($schedule->timerange->iterateItems() as $range) {
            $days = $range->position->getValues();
            $dates = array_map(
                fn($date) => vsprintf('%d/%d', array_pad(explode('/', $date), 2, '0')),
                explode(',', $range->dates->getValue())
            );
            $matchesDay = $days ? in_array($now->format('N'), $days, true) :
                in_array($now->format('n/j'), $dates, true);
            $start = vsprintf('%02d:%02d', array_pad(explode(':', $range->start_time->getValue()), 2, '0'));
            $stop = vsprintf('%02d:%02d', array_pad(explode(':', $range->stop_time->getValue()), 2, '0'));
            if ($matchesDay && $now->format('H:i') >= $start && $now->format('H:i') < $stop) {
                return '1';
            }
        }
        return $schedule->timerange->count() === 0 ? '1' : '0';
    }
}
