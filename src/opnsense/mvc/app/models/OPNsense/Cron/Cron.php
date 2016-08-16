<?php

/**
 *    Copyright (C) 2015 Deciso B.V.
 *
 *    All rights reserved.
 *
 *    Redistribution and use in source and binary forms, with or without
 *    modification, are permitted provided that the following conditions are met:
 *
 *    1. Redistributions of source code must retain the above copyright notice,
 *       this list of conditions and the following disclaimer.
 *
 *    2. Redistributions in binary form must reproduce the above copyright
 *       notice, this list of conditions and the following disclaimer in the
 *       documentation and/or other materials provided with the distribution.
 *
 *    THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
 *    INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 *    AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 *    AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 *    OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 *    SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 *    INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 *    CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 *    ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 *    POSSIBILITY OF SUCH DAMAGE.
 *
 */

namespace OPNsense\Cron;

use OPNsense\Base\BaseModel;

/**
 * Class Cron
 * @package OPNsense\Cron
 */
class Cron extends BaseModel
{
    /**
     * create a new daily job
     * @param string $origin
     * @param string $command
     * @param string $description
     * @param string $weekdays day(s) of the week to run
     * @param string $enabled default add disabled cron jobs, if triggered enabled be sure to call regenerate on cron.
     * @return string
     */
    public function newDailyJob($origin, $command, $description, $weekdays = "*", $enabled = "0")
    {
        $cron = $this->jobs->job->Add();
        $uuid = $cron->getAttributes()['uuid'];
        $cron->origin = $origin;
        $cron->command = $command;
        $cron->description = $description;
        $cron->weekdays = $weekdays;
        $cron->enabled = $enabled;
        return $uuid;
    }
}
