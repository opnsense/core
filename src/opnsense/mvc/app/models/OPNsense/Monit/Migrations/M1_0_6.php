<?php

/*
 * Copyright (C) 2018 Deciso B.V.
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

class M1_0_6 extends BaseModelMigration
{
    public function run($model)
    {
        $defaultTests = [];
        $defaultTests['ChangedStatus'] = [
            'action' => 'alert',
            'condition' => 'changed status',
            'name' => 'ChangedStatus',
            'type' => 'ProgramStatus',
        ];

        foreach ($defaultTests as &$newtest) {
            $found = false;
            foreach ($model->test->iterateItems() as $test) {
                if ($test->name == $newtest['name']) {
                    $found = $test;
                }
            }
            if (!$found) {
                $found = $model->test->Add();
                $found->name = $newtest['name'];
                $found->condition = $newtest['condition'];
                $found->action = $newtest['action'];
                $found->type = $newtest['type'];
            }
            $newtest['uuid'] = $found->getAttribute('uuid');
        }

        $defaultServices = [
            [
                'enabled' => '0',
                'name' => 'carp_status_change',
                'path' => '/usr/local/opnsense/scripts/OPNsense/Monit/carp_status',
                'tests' => $defaultTests['ChangedStatus']['uuid'],
                'type' => 'custom',
            ]
        ];

        foreach ($defaultServices as &$newservice) {
            $srv = $model->service->Add();
            $srv->enabled = $newservice['enabled'];
            $srv->name = $newservice['name'];
            $srv->type = $newservice['type'];
            $srv->path = $newservice['path'];
            $srv->tests = $newservice['tests'];
        }

        parent::run($model);
    }
}
