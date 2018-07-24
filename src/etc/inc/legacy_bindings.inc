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
require_once('script/load_phalcon.php');

/**
 * wrap config run
 */
function configd_run($cmd, $detach = false)
{
	$backend = new OPNsense\Core\Backend();
	return $backend->configdRun("{$cmd}", $detach);
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
    if (is_array($settings)) {
        foreach ($settings as $dataKey => &$dataValue) {
            if (is_array($dataValue)) {
                legacy_html_escape_form_data($dataValue);
            } else {
                $settings[$dataKey] = htmlspecialchars($dataValue);
            }
        }
    }
}

/**
 *  list aliases by type
 *  @param string $type type port or network
 */
function legacy_list_aliases($type)
{
    global $config;
    $result = array();

    foreach ((new \OPNsense\Firewall\Alias())->aliasIterator() as $alias) {
        if ($type == "port" && preg_match("/port/i", $alias['type'])) {
            $result[] = array("name" => $alias['name']);
        } elseif ($type != "port" && !preg_match("/port/i", $alias['type'])) {
            $result[] = array("name" => $alias['name']);
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
 *  @return new constructed list
 */
function legacy_move_config_list_items($source, $id, $items) {
    $new_config = array();

    if (!is_array($source)) {
        // input of wrong type, return empty array
        return array();
    } elseif ( !is_array($items) || !is_numericint($id)) {
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
        for ($i = $id+1; $i < count($source); $i++) {
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
 * @return description of alias, or null if no description was set or alias
 *   does not exist.
 */
function get_alias_description($name)
{
    global $config;

    $aliasMdl = new \OPNsense\Firewall\Alias();
    // MVC defined aliases
    foreach ((new \OPNsense\Firewall\Alias())->aliasIterator()  as $alias) {
        if ($alias['name'] == $name) {
            if (!empty($alias['descr'])) {
                return $alias['descr'];
            } elseif (!empty($alias['description'])) {
                return $alias['description'];
            } elseif (!empty($alias['content'])) {
                $tmp = array_slice(explode("\n", $alias['content']), 0, 10);
                asort($tmp);
                return implode("<br/>", $tmp);
            }
        }
    }
    return null;
}
