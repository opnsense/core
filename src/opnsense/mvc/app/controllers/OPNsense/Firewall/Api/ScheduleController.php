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
        return $this->searchBase('schedule', ['name', 'descr'], 'name');
    }

    private function findRange($uuid)
    {
        // Ranges are nested under schedules, but API routes identify them by their own UUID.
        foreach ($this->getModel()->schedule->iterateItems() as $schedule) {
            $range = $schedule->timerange->getChild($uuid);
            if ($range !== null) {
                return $range;
            }
        }
        return null;
    }

    public function getItemAction($uuid = null)
    {
        return $this->getBase('schedule', 'schedule', $uuid);
    }

    public function addItemAction()
    {
        return $this->addBase('schedule', 'schedule');
    }

    public function setItemAction($uuid)
    {
        return $this->setBase('schedule', 'schedule', $uuid);
    }

    public function delItemAction($uuid)
    {
        if (!$this->request->isPost()) {
            return ['result' => 'failed'];
        }
        Config::getInstance()->lock();
        if (!$this->getModel()->schedule->del($uuid)) {
            return ['result' => 'failed'];
        }
        return $this->save();
    }

    public function searchRangeAction()
    {
        $schedule = $this->getModel()->schedule->getChild($this->request->get('schedule', 'string', ''));
        return $schedule === null ? $this->searchRecordsetBase([]) :
            $this->searchBase($schedule->timerange->__reference);
    }

    public function getRangeAction($uuid = null)
    {
        $range = $uuid === null ?
            $this->getModel()->schedule->getChild($this->request->get('schedule', 'string', ''))?->timerange->add() :
            $this->findRange($uuid);
        if ($range === null) {
            return [];
        }
        $schedule = $range->getParentNode()->getParentNode();
        return [
            'timerange' => $range->getNodes(),
            // A single option displays the parent name while posting its UUID; ranges cannot be moved here.
            'schedule' => [
                $schedule->getAttribute('uuid') => ['value' => $schedule->name->getValue(), 'selected' => 1]
            ]
        ];
    }

    public function addRangeAction()
    {
        if (!$this->request->isPost()) {
            return ['result' => 'failed'];
        }
        Config::getInstance()->lock();
        $schedule = $this->getModel()->schedule->getChild($this->request->getPost('schedule', 'string', ''));
        if ($schedule === null) {
            return ['result' => 'failed'];
        }
        return $this->addBase('timerange', $schedule->timerange->__reference);
    }

    public function setRangeAction($uuid)
    {
        if (!$this->request->isPost()) {
            return ['result' => 'failed'];
        }
        Config::getInstance()->lock();
        $range = $this->findRange($uuid);
        if ($range === null) {
            return ['result' => 'failed'];
        }
        return $this->setBase('timerange', $range->getParentNode()->__reference, $uuid);
    }

    public function delRangeAction($uuid)
    {
        if (!$this->request->isPost()) {
            return ['result' => 'failed'];
        }
        Config::getInstance()->lock();
        $range = $this->findRange($uuid);
        if ($range === null) {
            return ['result' => 'failed'];
        }
        return $this->delBase($range->getParentNode()->__reference, $uuid);
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
