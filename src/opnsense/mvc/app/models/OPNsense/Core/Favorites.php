<?php

/*
 * Copyright (C) 2026 Greelan
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

namespace OPNsense\Core;

use OPNsense\Auth\User;

/**
 * Class Favorites
 * @package OPNsense\Core
 *
 * Shared helper for reading and writing per-user menu favorites.
 * Favorites are stored as a JSON array of URL strings in the
 * <menu_favorites> element of each <user> node in config.xml.
 */
class Favorites
{
    private $username = null;
    private $favorites = [];
    private $usermdl = null;

    public function __construct($username)
    {
        $this->username = $username;
        $this->usermdl = new User();

        if (!empty($username) && ($node = $this->usermdl->getUserByName($username)) !== null) {
            $decoded = json_decode($node->menu_favorites->getValue(), true);
            if (is_array($decoded)) {
                $this->favorites = $decoded;
            }
        }
    }

    /**
     * return the stored favorite URLs
     * @return array
     */
    public function getFavorites()
    {
        return $this->favorites;
    }

    /**
     * save current favorites to config.xml
     * @return bool true on success
     */
    public function save()
    {
        if (empty($this->username)) {
            return false;
        }

        if (($node = $this->usermdl->getUserByName($this->username)) === null) {
            return false;
        }

        $node->menu_favorites = json_encode(array_values($this->favorites));
        if ($this->usermdl->serializeToConfig(false, true)) {
            Config::getInstance()->save();
            return true;
        }

        return false;
    }

    /**
     * add a URL to favorites
     * @param string $url
     */
    public function addFavorite($url)
    {
        if (!in_array($url, $this->favorites)) {
            $this->favorites[] = $url;
        }
    }

    /**
     * remove a URL from favorites
     * @param string $url
     */
    public function removeFavorite($url)
    {
        $this->favorites = array_values(array_diff($this->favorites, [$url]));
    }

    /**
     * build a flat list of favorite entries by matching stored URLs
     * against menu items. Also prunes stale entries.
     * @param array $menuItems stdClass menu items from MenuSystem::getItems()
     * @param string $parentLabel accumulated breadcrumb from parent
     * @return array list of favorite entry objects
     */
    public function buildFavoritesEntries($menuItems, $parentLabel = '')
    {
        if (empty($this->favorites)) {
            return [];
        }

        $items = [];
        foreach ($menuItems as $item) {
            $label = $parentLabel !== ''
                ? $parentLabel . ': ' . strip_tags($item->VisibleName)
                : strip_tags($item->VisibleName);

            if (!empty($item->Url) && in_array($item->Url, $this->favorites)) {
                $items[] = (object)[
                    'Url' => $item->Url,
                    'Label' => $label,
                    'IsExternal' => $item->IsExternal ?? 'N',
                ];
            }

            if (!empty($item->Children)) {
                $items = array_merge($items, $this->buildFavoritesEntries($item->Children, $label));
            }
        }

        if ($parentLabel === '' && count($items) !== count($this->favorites)) {
            $this->favorites = array_column($items, 'Url');
            $this->save();
        }

        return $items;
    }
}
