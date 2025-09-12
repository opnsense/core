<?php

/*
 * Copyright (C) 2015 Deciso B.V.
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

require_once('script/load_phalcon.php');

/**
 * wrap config run
 * @param string $cmd
 * @param bool $detach
 * @return string
 * @throws Exception
 */
function configd_run($cmd, $detach = false)
{
    $backend = new OPNsense\Core\Backend();
    return $backend->configdRun($cmd, $detach);
}

/**
 * wrap config run with escaped parameters
 * @param string $cmd
 * @param array $params
 * @param bool $detach
 * @return string
 * @throws Exception
 */
function configdp_run($cmd, $params = array(), $detach = false)
{
    $backend = new OPNsense\Core\Backend();
    return $backend->configdpRun($cmd, $params, $detach);
}

/*************************************************************************************************
 * Legacy helper functions, only to be used in old (legacy) code.
 * Some patterns are very common in the old code, this section contains functions to help
 * cleanup the old code by performing less repetition.
 *
 * Never use these functions in new style OPNsense software, their only purpose is to help
 * migrating to something new.
 *
 ************************************************************************************************/

/**
 * simple function to perform htmlspecialchars recursively on all attributes
 * @param $settings array type form data
 */
function legacy_html_escape_form_data(&$settings)
{
    $rename_keys = [];
    if (is_array($settings)) {
        foreach ($settings as $dataKey => &$dataValue) {
            $cleansedKey = htmlspecialchars($dataKey, ENT_QUOTES | ENT_HTML401);
            if ($cleansedKey != $dataKey) {
                $rename_keys[$dataKey] = $cleansedKey;
            }
            if (is_array($dataValue)) {
                legacy_html_escape_form_data($dataValue);
            } elseif ($dataValue === null) {
                $settings[$dataKey] = '';
            } else {
                $settings[$dataKey] = htmlspecialchars($dataValue, ENT_QUOTES | ENT_HTML401);
            }
        }
        foreach ($rename_keys as $dataKey => $cleansedKey) {
            $settings[$cleansedKey] = $settings[$dataKey];
            unset($settings[$dataKey]);
        }
    }
}

/**
 *  list aliases by type
 *  @param string $type type port or network
 */
function legacy_list_aliases($type)
{
    $result = [];
    $cached = \OPNsense\Firewall\Alias::getCachedData();
    if (!isset($cached['aliases']) || empty($cached['aliases']['alias'])) {
        return $result;
    }
    foreach ($cached['aliases']['alias'] as $alias) {
        if ($alias['type'] == 'internal') {
            // XXX: skip internal aliases while still defined as "networks" in the rules pages
            continue;
        } elseif ($type == "port" && preg_match("/port/i", $alias['type'])) {
            $result[] = ['name' => $alias['name'], 'type' => $alias['type']];
        } elseif ($type != "port" && !preg_match("/port/i", $alias['type'])) {
            $result[] = ['name' => $alias['name'], 'type' => $alias['type']];
        }
    }

    return $result;
}

/**
 *  Function to move selected array items before another one.
 *  Mainly used in form processing.
 *  @param array $source config section to apply move on
 *  @param int $id item number to move selected to (before)
 *  @param array $items item numbers to move
 *  @return array new constructed list
 */
function legacy_move_config_list_items($source, $id, $items)
{
    $new_config = array();

    if (!is_array($source)) {
        // input of wrong type, return empty array
        return array();
    } elseif (!is_array($items) || !is_numericint($id)) {
        // selected items isn't an array or selected item isn't an int, return input
        return $source;
    } else {
        // input types are valid, move items around
        // copy all rules before selected target ($id) and not in items
        for ($i = 0; $i < min($id, count($source)); $i++) {
            if (!in_array($i, $items)) {
                $new_config[] = $source[$i];
            }
        }

        // next copy all selected rules (=before $id)
        for ($i = 0; $i < count($source); $i++) {
            if ($i != $id && in_array($i, $items)) {
                $new_config[] = $source[$i];
            }
        }

        // copy $id rule
        if ($id < count($source)) {
            $new_config[] = $source[$id];
        }

        /* copy all rules > $id and not selected */
        for ($i = $id + 1; $i < count($source); $i++) {
            if (!in_array($i, $items)) {
                $new_config[] = $source[$i];
            }
        }
        return $new_config;
    }
}

/**
 * Retrieve description text of firewall alias by specifying the alias name.
 * @param string $name alias name to retrieve the description from
 * @return string|null description of alias, or null if no description was set or alias
 *   does not exist.
 */
function get_alias_description($name)
{
    return OPNsense\Firewall\Util::aliasDescription($name);
}

/* XXX deprecated */
function return_gateways_status()
{
    /* get the process state of all monitor instances */
    $result = plugins_run('return_gateways_status');
    if (empty($result['dpinger'])) {
        return [];
    }

    /* enrich current process state with cached watcher status */
    $cache = plugins_run('return_gateways_watcher');
    foreach ($result['dpinger'] as $gwname => &$gwstatus) {
        if ($gwstatus['loss'] == '~' && isset($cache['dpinger'][$gwname])) {
            $gwstatus['status'] = $cache['dpinger'][$gwname];
        }
    }

    return $result['dpinger'];
}
