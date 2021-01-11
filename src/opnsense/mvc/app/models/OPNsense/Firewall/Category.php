<?php

/**
 *    Copyright (C) 2021 Deciso B.V.
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

namespace OPNsense\Firewall;

use OPNsense\Base\BaseModel;
use OPNsense\Core\Config;
use OPNsense\Firewall\Util;

/**
 * Class Category
 * @package OPNsense\Firewall
 */
class Category extends BaseModel
{
    public function getByName($name)
    {
        foreach ($this->categories->category->iterateItems() as $alias) {
            if ((string)$alias->name == $name) {
                return $alias;
            }
        }
        return null;
    }

    /**
     * collect unique categories from rules and updates the model with auto generated items.
     * XXX: if this operation turns out to be a bottleneck, we should move the maintance responsibiliy to the caller
     *      for the item in question (rule update)
     * @return bool true if changed
     */
    public function sync()
    {
        $has_changed = false;
        $cfgObj = Config::getInstance()->object();
        $source = [array('filter', 'rule')];
        $used_categories = [];
        foreach ($source as $aliasref) {
            $cfgsection = $cfgObj;
            foreach ($aliasref as $cfgName) {
                if ($cfgsection != null) {
                    $cfgsection = $cfgsection->$cfgName;
                }
            }
            if ($cfgsection != null) {
                foreach ($cfgsection as $node) {
                    if (!empty($node->category) && !in_array((string)$node->category, $used_categories)) {
                        $used_categories[] = (string)$node->category;
                    }
                }
            }
        }
        foreach ($this->categories->category->iterateItems() as $key => $category) {
            if (!empty((string)$category->auto) && !in_array((string)$category->name, $used_categories)) {
                $this->categories->category->del($key);
                $has_changed = true;
            } elseif (in_array((string)$category->name, $used_categories)) {
                unset($used_categories[array_search((string)$category->name, $used_categories)]);
            }
        }
        foreach ($used_categories as $name) {
            $node = $this->categories->category->add();
            $node->name = $name;
            $node->auto = "1";
            $has_changed = true;
        }
        return $has_changed;
    }
}
