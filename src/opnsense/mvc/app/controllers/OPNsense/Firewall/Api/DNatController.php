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

class DNatController extends FilterBaseController
{
    protected static $internalModelName = 'DNat';
    protected static $internalModelClass = 'OPNsense\\Firewall\\DNat';
    protected static $categorysource = 'rule';

    public function searchRuleAction()
    {
        $category = (array)$this->request->get('category');
        $filter_funct = function ($record) use ($category) {
            $cats = !empty((string)$record->category) ? explode(',', (string)$record->category) : [];
            return empty($category) || array_intersect($cats, $category);
        };
        return $this->searchBase("rule", null, "sequence", $filter_funct);
    }

    public function setRuleAction($uuid)
    {
        return $this->setBase("rule", "rule", $uuid);
    }

    public function addRuleAction()
    {
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

    public function toggleRuleAction($uuid, $enabled = null)
    {
        return $this->toggleBase("rule", $uuid, $enabled);
    }

        /**
     * @param array $cats list of category ids
     * @return array colors
     */
    private function getCategoryColors($cats)
    {
        if (empty($this->catcolors)) {
            foreach ((new Category())->categories->category->iterateItems() as $key => $category) {
                $uuid = (string)$category->getAttributes()['uuid'];
                $color = trim((string)$category->color);
                $this->catcolors[$uuid] = !empty($color) ? "#{$color}" : '#000';
            }
        }
        $result = [];
        foreach ($cats as $cat) {
            if (isset($this->catcolors[$cat])) {
                $result[] = $this->catcolors[$cat];
            }
        }
        return $result;
    }
}

