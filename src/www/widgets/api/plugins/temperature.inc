<?php

/*
 * Copyright (C) 2016 Deciso B.V.
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

/**
 * widget temperature data
 */
function temperature_api()
{
    $result = array();
    exec("/sbin/sysctl -a | grep temperature", $sysctlOutput);
    foreach ($sysctlOutput as $sysctl) {
        $parts = explode(':', $sysctl);
        if (count($parts) >= 2) {
            $tempItem = array();
            $tempItem['device'] = $parts[0];
            $tempItem['device_seq'] = filter_var($tempItem['device'], FILTER_SANITIZE_NUMBER_INT);
            $tempItem['temperature'] = trim(str_replace('C', '', $parts[1]));
            $tempItem['type'] = strpos($tempItem['device'], 'hw.acpi') !== false ? "zone" : "core";
            $tempItem['type_translated'] = $tempItem['type'] == "zone" ? gettext("Zone") : gettext("Core");
            $result[] = $tempItem;
        }
    }
    usort($result, function ($item1, $item2) {
      return strcmp(strtolower($item1['device']), strtolower($item2['device']));
    });

    return $result;
}
