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

namespace OPNsense\Firewall;

use OPNsense\Base\BaseModel;
use OPNsense\Base\Messages\Message;
use OPNsense\Core\Config;

class Schedule extends BaseModel
{
    private array $originalNames = [];

    protected function init()
    {
        foreach ($this->schedule->iterateItems() as $uuid => $schedule) {
            $this->originalNames[$uuid] = $schedule->name->getValue();
        }
    }

    /**
     * Protect schedule references in both rule stores.
     */
    public function performValidation($validateFullModel = false)
    {
        $messages = parent::performValidation($validateFullModel);
        // XXX: The legacy editor requires at least one time range; revisit this after the master/detail GUI works.
        // XXX: Both rule stores refer to schedules by name
        $config = Config::getInstance()->object();
        $referenced = [];
        foreach ($config->xpath('/opnsense/filter/rule/sched | /opnsense/OPNsense/Firewall/Filter/rules/rule/sched') as $name) {
            $referenced[(string)$name] = true;
        }
        foreach ($this->originalNames as $uuid => $name) {
            if (
                isset($referenced[$name]) &&
                (!isset($this->schedule->$uuid) || !$this->schedule->$uuid->name->isEqual($name))
            ) {
                $messages->appendMessage(new Message(
                    gettext('A schedule in use by a firewall rule cannot be renamed or deleted.'),
                    $this->schedule->__reference . '.' . $uuid . '.name'
                ));
            }
        }
        return $messages;
    }
}
