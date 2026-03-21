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

namespace OPNsense\Base\Menu;

use OPNsense\Core\Config;

/**
 * Class MenuFavorites
 * @package OPNsense\Base\Menu
 *
 * Shared helper for reading and writing per-user menu favorites.
 * Favorites are stored as a JSON array of URL strings in the
 * <menu_favorites> element of each <user> node in config.xml.
 */
class MenuFavorites
{
    /**
     * get favorites for a username
     * @param string $username username to look up
     * @return array list of favorite menu URLs (empty array if none)
     */
    public static function get($username)
    {
        if (empty($username)) {
            return [];
        }

        try {
            $cfg = Config::getInstance();
            foreach ($cfg->object()->system->user as $node) {
                if ($username === (string)$node->name && !empty($node->menu_favorites)) {
                    $favorites = json_decode((string)$node->menu_favorites, true);
                    return is_array($favorites) ? $favorites : [];
                }
            }
        } catch (\Exception $e) {
            return [];
        }

        return [];
    }

    /**
     * save favorites for a username
     * @param string $username username to save for
     * @param array $favorites list of menu URLs to save
     * @return bool true on success
     */
    public static function save($username, $favorites)
    {
        if (empty($username)) {
            return false;
        }

        try {
            $cfg = Config::getInstance();
            foreach ($cfg->object()->system->user as $node) {
                if ($username === (string)$node->name) {
                    $node->menu_favorites = json_encode(array_values($favorites));
                    $cfg->save();
                    return true;
                }
            }
            return false;
        } catch (\Exception $e) {
            return false;
        }
    }
}
