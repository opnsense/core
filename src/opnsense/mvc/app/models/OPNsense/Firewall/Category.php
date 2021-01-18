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
        foreach ($this->categories->category->iterateItems() as $category) {
            if ((string)$category->name == $name) {
                return $category;
            }
        }
        return null;
    }

    public function iterateCategories()
    {
        foreach ($this->categories->category->iterateItems() as $category) {
            yield ['name' => (string)$category->name];
        }
    }

    private function ruleIterator()
    {
        $cfgObj = Config::getInstance()->object();
        $source = [
            ['filter', 'rule'],
            ['nat', 'rule'],
            ['nat', 'onetoone'],
            ['nat', 'outbound', 'rule'],
            ['nat', 'npt'],
        ];
        foreach ($source as $aliasref) {
            $cfgsection = $cfgObj;
            foreach ($aliasref as $cfgName) {
                if ($cfgsection != null) {
                    $cfgsection = $cfgsection->$cfgName;
                }
            }
            if ($cfgsection != null) {
                foreach ($cfgsection as $node) {
                    if (!empty($node->category)) {
                        yield $node;
                    }
                }
            }
        }
    }

    /**
     * refactor category usage (replace category in rules)
     */
    public function refactor($oldname, $newname)
    {
        $has_changed = false;
        foreach ($this->ruleIterator() as $node) {
            $cats = explode(",", (string)$node->category);
            if (in_array($oldname, $cats)) {
                  unset($cats[array_search((string)$oldname, $cats)]);
                  $cats[] = $newname;
                  $node->category = implode(",", $cats);
                  $has_changed = true;
            }
        }
        return $has_changed;
    }

    /**
     * refactor category usage (replace category in rules)
     */
    public function isUsed($name)
    {
        foreach ($this->ruleIterator() as $node) {
            if (in_array($name, explode(",", (string)$node->category))) {
                  return true;
            }
        }
        return false;
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
        $used_categories = [];
        foreach ($this->ruleIterator() as $node) {
            foreach (explode(",", (string)$node->category) as $cat) {
                if (!in_array($cat, $used_categories)) {
                    $used_categories[] = $cat;
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
