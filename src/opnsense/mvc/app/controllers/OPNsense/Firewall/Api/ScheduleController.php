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

namespace OPNsense\Firewall\Api;

use OPNsense\Base\ApiMutableModelControllerBase;
use OPNsense\Core\Backend;
use OPNsense\Core\Config;

class ScheduleController extends ApiMutableModelControllerBase
{
    protected static $internalModelName = 'schedule';
    protected static $internalModelClass = 'OPNsense\Firewall\Schedule';

    public function searchItemAction()
    {
        $result = $this->searchBase('schedule', ['name', 'descr'], 'name');
        foreach ($result['rows'] as &$row) {
            $row['uuid'] = $row['name'];
        }
        return $result;
    }

    // Names and range positions also work for legacy entries without persisted UUIDs.
    private function findSchedule($name)
    {
        foreach ($this->getModel()->schedule->iterateItems() as $schedule) {
            if ($schedule->name->isEqual($name)) {
                return $schedule;
            }
        }
        return null;
    }

    private function findRange($id)
    {
        [$name, $index] = array_pad(explode('.', $id, 2), 2, '');
        $schedule = $this->findSchedule($name);
        if ($schedule === null || !ctype_digit($index)) {
            return null;
        }
        return array_values($schedule->timerange->getChildren())[(int)$index] ?? null;
    }

    public function getItemAction($name = null)
    {
        $schedule = $name === null ? $this->getModel()->schedule->add() : $this->findSchedule($name);
        if ($schedule === null) {
            return [];
        }
        return ['schedule' => $schedule->getNodes()];
    }

    public function addItemAction()
    {
        return $this->addBase('schedule', 'schedule');
    }

    public function setItemAction($name)
    {
        Config::getInstance()->lock();
        $schedule = $this->findSchedule($name);
        if ($schedule === null) {
            return ['result' => 'failed'];
        }
        return $this->setBase('schedule', 'schedule', $schedule->getAttribute('uuid'));
    }

    public function delItemAction($names)
    {
        return $this->deleteItems($names, [$this, 'findSchedule']);
    }

    public function searchRangeAction()
    {
        $schedule = $this->findSchedule($this->request->get('schedule', 'string', ''));
        $rows = [];
        if ($schedule !== null) {
            foreach (array_values($schedule->timerange->getChildren()) as $index => $range) {
                $rows[] = $range->getNodeContent() + [
                    'uuid' => $schedule->name->getValue() . '.' . $index
                ];
            }
        }
        return $this->searchRecordsetBase($rows);
    }

    public function getRangeAction($id = null)
    {
        $range = $id === null ?
            $this->findSchedule($this->request->get('schedule', 'string', ''))?->timerange->add() :
            $this->findRange($id);
        if ($range === null) {
            return [];
        }
        return [
            'timerange' => $range->getNodes(),
            'schedule' => $range->getParentNode()->getParentNode()->name->getValue()
        ];
    }

    public function addRangeAction()
    {
        Config::getInstance()->lock();
        $schedule = $this->findSchedule($this->request->getPost('schedule', 'string', ''));
        if ($schedule === null) {
            return [
                'result' => 'failed',
                'validations' => ['schedule' => gettext('Select a schedule before adding a time range.')]
            ];
        }
        return $this->addBase('timerange', $schedule->timerange->__reference);
    }

    public function setRangeAction($id)
    {
        Config::getInstance()->lock();
        $range = $this->findRange($id);
        if ($range === null) {
            return ['result' => 'failed'];
        }
        return $this->setBase('timerange', $range->getParentNode()->__reference, $range->getAttribute('uuid'));
    }

    public function delRangeAction($ids)
    {
        return $this->deleteItems($ids, [$this, 'findRange']);
    }

    private function deleteItems($ids, callable $lookup)
    {
        if (!$this->request->isPost()) {
            return ['result' => 'failed'];
        }
        Config::getInstance()->lock();
        // Resolve positions before deleting so bulk deletion cannot shift the remaining indexes.
        $items = [];
        foreach (explode(',', $ids) as $id) {
            $item = $lookup($id);
            if ($item === null) {
                return ['result' => 'failed'];
            }
            $items[] = $item;
        }
        foreach ($items as $item) {
            $item->getParentNode()->del($item->getAttribute('uuid'));
        }
        return $this->save();
    }

    public function reconfigureAction()
    {
        if ($this->request->isPost()) {
            (new Backend())->configdRun('filter reload skip_alias');
            return ['status' => 'ok'];
        }
        return ['status' => 'failed'];
    }
}
