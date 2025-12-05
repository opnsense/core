<?php

/*
 * Copyright (C) 2025 Deciso B.V.
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
use OPNsense\Firewall\Category;

class DNatController extends FilterBaseController
{
    protected static $internalModelName = 'DNat';
    protected static $internalModelClass = 'OPNsense\\Firewall\\DNat';
    protected static $categorysource = 'rule';

    /**
     * @inheritdoc
     */
    protected function setBaseHook($node)
    {
        $node->updated->time = sprintf('%0.2f', microtime(true));
        $node->updated->username = $this->getUserName();
        $node->updated->description = sprintf('%s made changes', $_SERVER['SCRIPT_NAME']);
        if ($node->created->time->isEmpty()) {
            $node->created->time = $node->updated->time;
            $node->created->username = $node->updated->username;
            $node->created->description = $node->updated->description;
        }
    }

    public function searchRuleAction()
    {
        $category = (array)$this->request->get('category');
        $filter_funct = function ($record) use ($category) {
            /* categories are indexed by name in the record, but offered as uuid in the selector */
            $catids = !empty((string)$record->categories) ? explode(',', (string)$record->categories) : [];
            return empty($category) || array_intersect($catids, $category);
        };

        $results =  $this->searchBase("rule", null, "sequence", $filter_funct);

        /* carry results */
        foreach ($results['rows'] as &$record) {
            /* offer list of colors to be used by the frontend  */
            $record['category_colors'] = $this->getCategoryColors(explode(',', $record['categories']));
            /* format "networks" and ports */
            foreach (['source.network','source.port','destination.network','destination.port', 'target', 'local-port'] as $field) {
                if (!empty($record[$field])) {
                    $record["alias_meta_{$field}"] = $this->getNetworks($record[$field]);
                }
            }
        }

        return $results;
    }

    public function setRuleAction($uuid)
    {
        /* prevent created metadata being overwritten or offered */
        if (is_array($_POST['rule']) && isset($_POST['rule']['created'])) {
            unset($_POST['rule']['created']);
        }
        return $this->setBase("rule", "rule", $uuid);
    }

    public function addRuleAction()
    {
        /* prevent created metadata being overwritten or offered */
        if (is_array($_POST['rule']) && isset($_POST['rule']['created'])) {
            unset($_POST['rule']['created']);
        }
        return $this->addBase("rule", "rule");
    }

    public function getRuleAction($uuid = null)
    {
        return $this->getBase("rule", "rule", $uuid);
    }

    public function delRuleAction($uuid)
    {
        return $this->delBase("rule", $uuid);
    }

    /**
     * opposite toggle (disable instead of enable)
     */
    public function toggleRuleAction($uuid, $disabled = null)
    {
        $result = ['result' => 'failed'];
        if ($this->request->isPost() && $uuid != null) {
            Config::getInstance()->lock();
            $node = $this->getModel()->getNodeByReference('rule.' . $uuid);
            if ($node != null) {
                if (in_array($disabled, ['0', '1'])) {
                    $node->disabled = (string)$disabled;
                } else {
                    $node->disabled = (string)$node->disabled == '1' ? '0' : '1';
                }
                $result['result'] = $node->disabled->isEmpty() ? 'Enabled' : 'Disabled';
                $this->save(false, true);
            }
        }
        return $result;
    }

    public function moveRuleBeforeAction($selected_uuid, $target_uuid)
    {
        return $this->moveRuleBeforeBase($selected_uuid, $target_uuid, 'rule.', 'sequence', 'DNat');
    }

    public function toggleRuleLogAction($uuid, $log)
    {
        return $this->toggleRuleLogBase($uuid, $log, 'rule.', 'DNat');
    }
}
