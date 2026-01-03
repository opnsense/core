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

namespace OPNsense\Core\Api;

use OPNsense\Base\ApiControllerBase;
use OPNsense\Base\Menu;
use OPNsense\Core\ACL;

/**
 * Class MenuController
 * @package OPNsense\Core
 */
class MenuController extends ApiControllerBase
{
    /**
     * traverse menu items and mark user visibility (isVisible true/false)
     * @param array $menuItems menuitems from menu->getItems()
     * @param ACL $acl acl object reference
     */
    private function applyACL(&$menuItems, &$acl)
    {
        foreach ($menuItems as &$menuItem) {
            $menuItem->isVisible = false;
            if (count($menuItem->Children) > 0) {
                $this->applyACL($menuItem->Children, $acl);
                foreach ($menuItem->Children as $subMenuItem) {
                    if ($subMenuItem->isVisible) {
                        $menuItem->isVisible = true;
                        break;
                    }
                }
            } else {
                if (!$acl->isPageAccessible($this->getUserName(), $menuItem->Url)) {
                    $menuItem->isVisible = false;
                } else {
                    $menuItem->isVisible = true;
                }
            }
        }
    }

    /**
     * search visible name of menu nodes
     * @param array $menuItems
     * @param string $query query string
     */
    private function search(&$menuItems, $query)
    {
        foreach ($menuItems as &$menuItem) {
            if (stripos($menuItem->VisibleName, $query) !== false) {
                // match on node
                continue;
            } elseif (count($menuItem->Children) > 0) {
                $this->search($menuItem->Children, $query);
                $menuItem->isVisible = false;
                foreach ($menuItem->Children as $subMenuItem) {
                    if ($subMenuItem->isVisible) {
                        $menuItem->isVisible = true;
                        break;
                    }
                }
            } elseif ($menuItem->isVisible && stripos($menuItem->VisibleName, $query) === false) {
                $menuItem->isVisible = false;
            }
        }
    }

    /**
     * request user context sensitive menu (items)
     * @param string $selected_uri selected uri
     * @return array menu items
     */
    private function getMenu($selected_uri)
    {
        // construct menu and acl and merge collected info
        $menu = new Menu\MenuSystem();
        $acl = new ACL();

        // fetch menu items and apply acl
        $menu_items = $menu->getItems($selected_uri);
        $this->applyACL($menu_items, $acl);
        return $menu_items;
    }


    /**
     * flatten menu structure, only returning visible entries
     * @param $menu_items array containing stdClass objects
     * @return array tree containing simple types
     */
    private function menuToArray($menu_items)
    {
        $result = array();
        foreach ($menu_items as $menu_item) {
            if ($menu_item->isVisible) {
                $new_item = (array) $menu_item;
                $new_item['Children'] = array();
                if (count($menu_item->Children) > 0) {
                    $new_item['Children'] = $this->menuToArray($menu_item->Children);
                }
                $result[] = $new_item;
            }
        }
        return $result;
    }

    /**
     * extract visitable leaves from collection of menu items
     * @param array $menu_items
     * @param array $items result
     */
    private function extractMenuLeaves($menu_items, &$items)
    {
        foreach ($menu_items as $menu_item) {
            if (!isset($menu_item->breadcrumb)) {
                $menu_item->breadcrumb = $menu_item->VisibleName;
                $menu_item->depth = 1;
            }
            if ($menu_item->isVisible) {
                if (count($menu_item->Children) > 0) {
                    foreach ($menu_item->Children as &$submenu) {
                        $submenu->breadcrumb = $menu_item->breadcrumb . ': ' . $submenu->VisibleName;
                        $submenu->depth = $menu_item->depth + 1;
                    }
                    $this->extractMenuLeaves($menu_item->Children, $items);
                }

                // only return visible items
                if ($menu_item->Visibility != 'hidden') {
                    unset($menu_item->Children);
                    $items[] = $menu_item;
                }
            }
        }
    }

    /**
     * return menu items for this user
     * @return array
     */
    public function treeAction()
    {
        $selected_uri = $this->request->get("uri", null, null);
        $menu_items = $this->getMenu($selected_uri);
        return $this->menuToArray($menu_items);
    }

    /**
     * search menu items
     * @return array
     */
    public function searchAction()
    {
        $menu_items = $this->getMenu(null);
        $query = $this->request->get("q", null, null);
        if ($query != null) {
            // only search when a query is provided, otherwise return all entries
            $this->search($menu_items, $query);
        }
        $items = array();
        $this->extractMenuLeaves($menu_items, $items);
        return $items;
    }
}
