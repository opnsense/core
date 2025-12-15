<?php

/*
 * Copyright (C) 2023-2025 Deciso B.V.
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

use OPNsense\Base\UserException;
use OPNsense\Core\Config;

class NptController extends FilterBaseController
{
    protected static $categorysource = "npt.rule";

    public function searchRuleAction()
    {
        $category = (array)$this->request->get('category');

        $filter_funct = function ($record) use ($category) {
            /* categories are indexed by name in the record, but offered as uuid in the selector */
            $catids = !$record->categories->isEmpty() ? $record->categories->getValues() : [];
            return empty($category) || array_intersect($catids, $category);
        };

        $results = $this->searchBase("npt.rule", null, "sequence", $filter_funct);

        /* carry results */
        foreach ($results['rows'] as &$record) {
            /* offer list of colors to be used by the frontend */
            $record['category_colors'] = $this->getCategoryColors(
                !empty($record['categories']) ? explode(',', $record['categories']) : []
            );
        }

        return $results;
    }

    public function setRuleAction($uuid)
    {
        return $this->setBase("rule", "npt.rule", $uuid);
    }

    public function addRuleAction()
    {
        return $this->addBase("rule", "npt.rule");
    }

    public function getRuleAction($uuid = null)
    {
        return $this->getBase("rule", "npt.rule", $uuid);
    }

    public function delRuleAction($uuid)
    {
        return $this->delBase("npt.rule", $uuid);
    }

    public function toggleRuleAction($uuid, $enabled = null)
    {
        return $this->toggleBase("npt.rule", $uuid, $enabled);
    }

    public function moveRuleBeforeAction($selected_uuid, $target_uuid)
    {
        return $this->moveRuleBeforeBase($selected_uuid, $target_uuid, 'npt.rule', 'sequence');
    }

    public function toggleRuleLogAction($uuid, $log)
    {
        return $this->toggleRuleLogBase($uuid, $log, 'npt.rule');
    }
}
