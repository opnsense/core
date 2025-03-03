<?php

/*
 * Copyright (C) 2025 Deciso B.V.
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

namespace OPNsense\Firewall;

class FilterLegacyMapper
{
    /**
     * Mapping of model keys to legacy keys.
     *
     * @var array
     */
    protected $mapping = [
        'action'           => 'type',
        'replyto'          => 'reply-to',
        'description'      => 'descr',
        'source_net'       => 'from',
        'source_port'      => 'from_port',
        'destination_net'  => 'to',
        'destination_port' => 'to_port',
        'ref'              => '#ref',
    ];

    /**
     * Normalize a list of legacy rules.
    *
    * @param array  $rules    List of raw rules from backend.
    * @param array  $template The template containing model keys and default values.
    * @param string $ruleType The type of rule (e.g., 'internal', 'internal2').
    *
    * @return array Normalized rules.
    */
    public function normalizeRules(array $rules, array $template, string $ruleType): array
    {
        $normalizedRules = [];
        // internal2 rules are at the end of the ruleset
        $defaultSequence = ($ruleType === 'internal2') ? 1000000 : 0;

        foreach ($rules as $rule) {
            $newRule = $this->normalizeRule($rule, $template, $ruleType, $defaultSequence);
            if ($newRule['enabled'] === '0') {
                continue; // Skip disabled rules
            }
            $normalizedRules[] = $newRule;
        }

        return $normalizedRules;
    }

    /**
     * Normalize a single legacy rule based on the template.
     *
     * @param array  $rule            The raw rule data.
     * @param array  $template        The template containing model keys and default values.
     * @param string $ruleType        The rule type used for setting UUID.
     * @param int    $defaultSequence Default sequence number for ordering.
     *
     * @return array Normalized rule.
     */
    public function normalizeRule(array $rule, array $template, string $ruleType, int $defaultSequence): array
    {
        $newRule = [];

        foreach ($template as $key => $default) {
            // Use mapping if available
            $sourceKey = isset($this->mapping[$key]) ? $this->mapping[$key] : $key;
            // Use the value from the rule if present; otherwise use the default from the template.
            $value = array_key_exists($sourceKey, $rule) ? $rule[$sourceKey] : $default;

            // Special handling for 'enabled', some old rules use disabled
            if ($key === 'enabled') {
                $value = (isset($rule['disabled']) && $rule['disabled'] === true) ? '0' : '1';
            }

            // Capitalize 'action'
            if ($key === 'action') {
                $value = ucfirst(strtolower(trim($value)));
            }

            // Normalize 'ipprotocol'
            if ($key === 'ipprotocol') {
                switch ($value) {
                    case 'inet':
                        $value = gettext('IPv4');
                        break;
                    case 'inet6':
                        $value = gettext('IPv6');
                        break;
                    case 'inet46':
                        $value = gettext('IPv4+IPv6');
                        break;
                    default:
                        $value = gettext('IPv4'); // Default
                }
            }

            // If value is null force it to the default value (empty string).
            if ($value === null) {
                $value = $default;
            }

            $newRule[$key] = $value;
        }

        // Ensure 'uuid' is always set
        if (empty($newRule['uuid'])) {
            $newRule['uuid'] = $ruleType;
        }

        // Ensure 'sequence' is set
        if (empty($newRule['sequence']) || $newRule['sequence'] === "") {
            $newRule['sequence'] = (string)$defaultSequence;
        }

        return $newRule;
    }

}
