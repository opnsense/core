<?php

/*
 * Copyright (C) 2026 Konstantinos Spartalis
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

namespace OPNsense\Monit\Migrations;

use OPNsense\Base\BaseModelMigration;

class M1_0_15 extends BaseModelMigration
{
    public function run($model)
    {
        $interval = (string)$model->general->interval;
        if ($interval !== '' && is_numeric($interval)) {
            if ((int)$interval < 1) {
                $model->general->interval = '1';
            } elseif ((int)$interval > 3600) {
                $model->general->interval = '3600';
            }
        }

        $mmonitTimeout = (string)$model->general->mmonitTimeout;
        if ($mmonitTimeout !== '' && is_numeric($mmonitTimeout) && (int)$mmonitTimeout < 1) {
            $model->general->mmonitTimeout = '1';
        }

        foreach ($model->test->iterateItems() as $test) {
            $condition = (string)$test->condition;
            if (preg_match('/^\s*failed\s+link(\b.*)$/i', $condition, $matches)) {
                $test->condition = 'link down' . $matches[1];
            }
            if (
                $model->getTestType((string)$test->condition) === 'NetworkInterface' &&
                (string)$test->type === 'Custom'
            ) {
                $test->type = 'NetworkInterface';
                /* validation would fail because we want to change the type of tests linked to services */
                $test->type->markUnchanged();
            }
        }

        parent::run($model);
    }
}
