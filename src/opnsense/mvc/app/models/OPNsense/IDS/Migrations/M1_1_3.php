<?php

/*
 * Copyright (C) 2026 Konstantinos Spartalis <cspartalis@potatonetworks.com>
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

namespace OPNsense\IDS\Migrations;

use OPNsense\Base\BaseModelMigration;
use OPNsense\IDS\IDS;

class M1_1_3 extends BaseModelMigration
{
    public function run($model)
    {
        $uncat_node = null;
        $adult_node = null;

        foreach ($model->files->file->iterateItems() as $file) {
            if ((string)$file->filename === 'opnsense.uncategorized.rules') {
                $uncat_node = $file;
            } elseif ((string)$file->filename === 'opnsense.adult.rules') {
                $adult_node = $file;
            }
        }

        if ($uncat_node !== null) {
            $uncat_uuid = $uncat_node->getAttribute('uuid');

            if ($adult_node === null) {
                $adult_node = $model->files->file->Add();
                $adult_node->filename = 'opnsense.adult.rules';
            }

            if ((string)$uncat_node->enabled === '1') {
                $adult_node->enabled = '1';
            }

            $adult_uuid = $adult_node->getAttribute('uuid');

            foreach ($model->policies->policy->iterateItems() as $policy) {
                $rulesets = (string)$policy->rulesets;
                if ($rulesets === '') {
                    continue;
                }

                $parts = array_map(function($uuid) use ($uncat_uuid, $adult_uuid) {
                    return $uuid === $uncat_uuid ? $adult_uuid : $uuid;
                }, explode(',', $rulesets));

                $policy->rulesets = implode(',', array_unique($parts));
            }

            $model->files->file->del($uncat_uuid);
        }

        parent::run($model);
    }
