<?php

/*
 * Copyright (C) 2021 Deciso B.V.
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

use OPNsense\Base\FieldTypes\BaseField;
use OPNsense\Core\Config;
use OPNsense\Base\BaseModelMigration;
use OPNSense\IDS\IDS;

class M1_0_7 extends BaseModelMigration
{
    /**
     * Emerging threats suricata 5 ruleset movements
     * @param IDS $model
     */
    public function run($model)
    {
        $cfgObj = Config::getInstance()->object();
        if (!isset($cfgObj->OPNsense->IDS->files->file)) {
            return;
        }
        $csets = array();
        $nsets = array();
        $changed_sets = ['emerging-current_events.rules', 'emerging-trojan.rules',
                         'emerging-malware.rules',  'emerging-info.rules', 'emerging-policy.rules'];
        $new_sets = ['emerging-ja3.rules', 'emerging-hunting.rules', 'emerging-adware_pup.rules',
                     'emerging-phishing.rules', 'emerging-exploit_kit.rules', 'emerging-coinminer.rules',
                     'emerging-malware.rules'];
        foreach ($model->files->file->iterateItems() as $file) {
            if (in_array((string)$file->filename, $changed_sets)) {
                $csets[(string)$file->filename] = $file;
            }
            if (in_array((string)$file->filename, $new_sets)) {
                $nsets[(string)$file->filename] = $file;
            }
        }
        // add all new to config in deselected state
        foreach ($new_sets as $filename) {
            if (empty($nsets[$filename])) {
                $node = $model->files->file->Add();
                $node->filename = $filename;
                $nsets[$filename] = $node;
            }
        }
        // map rulesets
        if (!empty($csets['emerging-malware.rules']) && $csets['emerging-malware.rules']->enabled == "1") {
            $nsets['emerging-adware_pup.rules']->enabled = "1";
        }
        if (!empty($csets['emerging-current_events.rules']) && $csets['emerging-current_events.rules']->enabled == "1") {
            $nsets['emerging-phishing.rules']->enabled = "1";
            $nsets['emerging-exploit_kit.rules']->enabled = "1";
        }
        if (!empty($csets['emerging-trojan.rules']) && $csets['emerging-trojan.rules']->enabled == "1") {
            $nsets['emerging-coinminer.rules']->enabled = "1";
            $nsets['emerging-malware.rules']->enabled = "1";
        }
        if (!empty($csets['emerging-info.rules']) && $csets['emerging-info.rules']->enabled == "1") {
            $nsets['emerging-hunting.rules']->enabled = "1";
        }
        if (!empty($csets['emerging-policy.rules']) && $csets['emerging-policy.rules']->enabled == "1") {
            $nsets['emerging-hunting.rules']->enabled = "1";
        }
        if (!empty($csets['emerging-trojan.rules'])) {
            // deprecated ruleset
            $model->files->file->del($csets['emerging-trojan.rules']->getAttribute('uuid'));
        }
    }
}
