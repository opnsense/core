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

use OPNsense\Base\FieldTypes\TextField;
use OPNsense\Base\Validators\CallbackValidator;

class ScheduleListField extends TextField
{
    public function getValue(): string
    {
        $range = $this->getParentNode();
        if ($this->getInternalXMLTagName() === 'dates') {
            return implode(',', array_map(
                fn($month, $day) => $month . '/' . $day,
                $range->month->isEmpty() ? [] : explode(',', $range->month->getValue()),
                $range->day->isEmpty() ? [] : explode(',', $range->day->getValue())
            ));
        }
        $times = explode('-', $range->hour->getValue());
        // XXX: Missing hours mean all day in the legacy runtime.
        if ($range->hour->isEmpty()) {
            $times = ['00:00', '23:59'];
        }
        return $times[$this->getInternalXMLTagName() === 'start_time' ? 0 : 1] ?? '';
    }

    public function setValue($value)
    {
        $range = $this->getParentNode();
        if ($this->getInternalXMLTagName() === 'dates') {
            $months = $days = [];
            foreach (array_filter(explode(',', $value), 'strlen') as $date) {
                [$months[], $days[]] = array_pad(explode('/', trim($date), 2), 2, '');
            }
            // XXX: Parallel lists preserve date order, duplicate months/days and zero padding.
            $range->month->setValue(implode(',', $months));
            $range->day->setValue(implode(',', $days));
        } else {
            $times = [$range->start_time->getValue(), $range->stop_time->getValue()];
            $times[$this->getInternalXMLTagName() === 'start_time' ? 0 : 1] = $value;
            $range->hour->setValue(implode('-', $times));
        }
    }

    public function getNodeData()
    {
        if ($this->getInternalXMLTagName() === 'dates') {
            return $this->getValue();
        }
        [$hour, $minute] = array_pad(explode(':', $this->getValue()), 2, '0');
        $selected = sprintf('%02d:%02d', (int)$hour, (int)$minute);
        $options = [];
        for ($hour = 0; $hour < 24; $hour++) {
            foreach ([0, 15, 30, 45, 59] as $minute) {
                $value = sprintf('%02d:%02d', $hour, $minute);
                $options[$value] = ['value' => $value, 'selected' => $value === $selected ? 1 : 0];
            }
        }
        return $options;
    }

    public function isFieldChanged()
    {
        $range = $this->getParentNode();
        return $this->getInternalXMLTagName() === 'dates' ?
            $range->month->isFieldChanged() || $range->day->isFieldChanged() || $range->position->isFieldChanged() :
            $range->hour->isFieldChanged();
    }

    public function getValidators()
    {
        $validators = parent::getValidators();
        $validators[] = new CallbackValidator(['callback' => function ($value) {
            $range = $this->getParentNode();
            if ($this->getInternalXMLTagName() === 'dates') {
                if (!$range->position->isEmpty()) {
                    return $value === '' ? [] : [gettext('Select either weekdays or calendar dates.')];
                }
                $message = gettext('Select valid calendar dates or weekdays.');
                if ($value === '') {
                    return [$message];
                }
                foreach (explode(',', $value) as $date) {
                    $parts = explode('/', $date);
                    // XXX: Dates repeat annually, including February 29 in leap years.
                    if (
                        count($parts) !== 2 || !ctype_digit($parts[0]) || !ctype_digit($parts[1]) ||
                        !checkdate((int)$parts[0], (int)$parts[1], 2000)
                    ) {
                        return [$message];
                    }
                }
                return [];
            }
            $parts = explode(':', $value);
            if (
                count($parts) !== 2 || !ctype_digit($parts[0]) || !ctype_digit($parts[1]) ||
                (int)$parts[0] > 23 || (int)$parts[1] > 59
            ) {
                return [gettext('Enter a valid time between 00:00 and 23:59.')];
            }
            $start = array_pad(explode(':', $range->start_time->getValue()), 2, '0');
            // XXX: The legacy editor rejects overnight ranges but accepts equal endpoints.
            return $this->getInternalXMLTagName() === 'stop_time' &&
                (int)$parts[0] * 60 + (int)$parts[1] < (int)$start[0] * 60 + (int)$start[1] ?
                [gettext('The start time must be no later than the stop time.')] : [];
        }]);
        return $validators;
    }
}
