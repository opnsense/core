<?php

/*
    Copyright (C) 2004-2010 Scott Ullrich
    Copyright (C) 2003-2004 Manuel Kasper <mk@neon1.net>.
    All rights reserved.

    Redistribution and use in source and binary forms, with or without
    modification, are permitted provided that the following conditions are met:

    1. Redistributions of source code must retain the above copyright notice,
       this list of conditions and the following disclaimer.

    2. Redistributions in binary form must reproduce the above copyright
       notice, this list of conditions and the following disclaimer in the
       documentation and/or other materials provided with the distribution.

    THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
    INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
    AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
    AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
    OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
    SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
    INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
    CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
    ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
    POSSIBILITY OF SUCH DAMAGE.
*/

/* include globals from notices.inc /utility/XML parser files */
require_once('config.lib.inc');

$config = parse_config();

/* set timezone */
$timezone = $config['system']['timezone'];
if (!$timezone) {
    $timezone = 'Etc/UTC';
}

date_default_timezone_set($timezone);

/**
 * find list of registered interfaces
 * @param array $filters list of filters to apply
 * @return array interfaces
 */
function legacy_config_get_interfaces($filters = array())
{
    global $config;
    $interfaces = array();
    if (isset($config['interfaces'])) {
        foreach ($config['interfaces'] as $ifname => $iface) {
            // undo stupid listags() turning our item into a new array, preventing certain names to be used as interface.
            // see src/etc/inc/xmlparse.inc
            if (isset($iface[0])) {
                $iface = $iface[0];
            }
            // apply filters
            $iface_match = true;
            foreach ($filters as $filter_key => $filter_value) {
                $field_value = isset($iface[$filter_key]) ? $iface[$filter_key] : false;
                if ($field_value != $filter_value) {
                    $iface_match = false;
                    break;
                }
            }
            if ($iface_match) {
                $iface['descr'] = !empty($iface['descr']) ? $iface['descr'] : strtoupper($ifname);
                $interfaces[$ifname] = $iface;
            }
        }
    }
    return $interfaces;
}
