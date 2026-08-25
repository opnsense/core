<?php

/*
 * Copyright (C) 2018-2026 Deciso B.V.
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

namespace OPNsense\Firewall\FieldTypes;

use OPNsense\Base\FieldTypes\BaseField;
use OPNsense\Base\Validators\CallbackValidator;
use OPNsense\Firewall\Util;

/**
 * Class AliasNameField
 * @package OPNsense\Base\FieldTypes
 */
class AliasNameField extends BaseField
{
    /**
     * Internally reserved pf keywords.
     *
     * Should be kept in sync with:
     * https://github.com/opnsense/src/blob/6de4b6dd1c0f213aba55374be784fe22c35d6554/sbin/pfctl/parse.y#L6603
     *
     * @var array
     */
    private static $reservedWords = [
        'af-to',
        'all',
        'allow-opts',
        'allow-related',
        'altq',
        'anchor',
        'antispoof',
        'any',
        'bandwidth',
        'binat',
        'binat-anchor',
        'binat-to',
        'bitmask',
        'block',
        'block-policy',
        'bridge-to',
        'buckets',
        'cbq',
        'code',
        'codelq',
        'debug',
        'divert-reply',
        'divert-to',
        'dnpipe',
        'dnqueue',
        'drop',
        'dup-to',
        'endpoint-independent',
        'ether',
        'fail-policy',
        'fairq',
        'fastroute',
        'file',
        'fingerprints',
        'flags',
        'floating',
        'flush',
        'for',
        'fragment',
        'from',
        'global',
        'group',
        'hfsc',
        'hogs',
        'hostid',
        'icmp-type',
        'icmp6-type',
        'if-bound',
        'in',
        'include',
        'inet',
        'inet6',
        'interval',
        'keep',
        'keepcounters',
        'l3',
        'label',
        'limit',
        'linkshare',
        'load',
        'log',
        'loginterface',
        'map-e-portset',
        'match',
        'matches',
        'max',
        'max-mss',
        'max-pkt-rate',
        'max-pkt-size',
        'max-src-conn',
        'max-src-conn-rate',
        'max-src-nodes',
        'max-src-states',
        'min-ttl',
        'modulate',
        'nat',
        'nat-anchor',
        'nat-to',
        'no',
        'no-df',
        'no-route',
        'no-sync',
        'on',
        'optimization',
        'os',
        'out',
        'overload',
        'pass',
        'pflow',
        'port',
        'prefer-ipv6-nexthop',
        'prio',
        'priority',
        'priq',
        'probability',
        'proto',
        'qlimit',
        'queue',
        'quick',
        'random',
        'random-id',
        'rdr',
        'rdr-anchor',
        'rdr-to',
        'realtime',
        'reassemble',
        'received-on',
        'reply-to',
        'require-order',
        'return',
        'return-icmp',
        'return-icmp6',
        'return-rst',
        'ridentifier',
        'round-robin',
        'route',
        'route-to',
        'rtable',
        'rule',
        'ruleset-optimization',
        'scrub',
        'set',
        'set-tos',
        'skip',
        'sloppy',
        'source-hash',
        'source-track',
        'state',
        'state-defaults',
        'state-policy',
        'static-port',
        'sticky-address',
        'syncookies',
        'synproxy',
        'table',
        'tag',
        'tagged',
        'target',
        'tbrsize',
        'timeout',
        'to',
        'tos',
        'ttl',
        'upperlimit',
        'urpf-failed',
        'user',
    ];

    /**
     * @var bool marks if this is a data node or a container
     */
    protected $internalIsContainer = false;

    /**
     * retrieve field validators for this field type
     * @return array returns list of validators
     */
    public function getValidators()
    {
        $validators = parent::getValidators();
        if ($this->internalValue != null) {
            $validators[] = new CallbackValidator(
                [
                    "callback" => function ($value) {
                        $result = [];
                        /* avoids single "_" and prefixes of "__" here too */
                        if (
                            !preg_match(
                                '/^([a-zA-Z]|(([_a-zA-Z][a-zA-Z0-9]|[a-zA-Z][_a-zA-Z0-9])[_a-zA-Z0-9]{0,29}))$/',
                                $value
                            )
                        ) {
                            $result[] = gettext('The name must start with a letter or single underscore, be less than 32 characters and only consist of alphanumeric characters or underscores.');
                        }
                        if (in_array($value, self::$reservedWords)) {
                            $result[] = gettext('The name cannot be the internally reserved keyword "%s".');
                        }
                        if (Util::getservbyname($value) || getprotobyname($value)) {
                            $result[] = gettext('Reserved protocol or service names may not be used');
                        }
                        return $result;
                    }
                ]
            );
        }
        return $validators;
    }
}
