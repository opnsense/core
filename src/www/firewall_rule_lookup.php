<?php

/*
 * Copyright (C) 2019 Deciso B.V.
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

require_once("guiconfig.inc");
require_once("filter.inc");
require_once("system.inc");

use OPNsense\Firewall\Filter;

$a_filter = &config_read_array('filter', 'rule');

$fw = filter_core_get_initialized_plugin_system();
filter_core_bootstrap($fw);
plugins_firewall($fw);
filter_core_rules_user($fw);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!empty($_GET['rid'])) {
        $rid = $_GET['rid'];
        // search auto-generated rules
        foreach ($fw->iterateFilterRules() as $rule) {
            if (!empty($rule->getRef()) && $rid == $rule->getLabel()) {
                if (strpos($rule->getRef(), '?if=') !== false) {
                    $parts = parse_url($rule->getRef());
                    parse_str($parts['query'], $query);
                    if (!empty($parts['fragment'])) {
                        header(url_safe('Location: /%s?if=%s#%s', [$parts['path'], $query['if'], $parts['fragment']]));
                    } elseif (!empty($query['if']) && isset($query['id'])) {
                        // firewall index reference
                        header(url_safe('Location: /%s?if=%s&id=%s', [$parts['path'], $query['if'], $query['id']]));
                    }
                } else {
                    // search model firewall rule
                    $interface = '';
                    $node = (new Filter())->getNodeByReference('rules.rule.' . $rid);
                    if ($node !== null) {
                        $interfaceValue = $node->interface->getValue();
                        // multiple interfaces in a rule are skipped as empty string is the default for floating
                        if ($interfaceValue !== '' && strpos($interfaceValue, ',') === false) {
                            $interface = $interfaceValue;
                        }
                    }

                    $base = strtok($rule->getRef(), '#');
                    $hash = url_safe('interface=%s&edit=%s', [$interface, $rid]);
                    header("Location: /{$base}#{$hash}");
                }
                exit;
            }
        }
    }
}
?>
<script>
    // close when not found
    window.close();
</script>
