<?php

/*
 * Copyright (C) 2026 Deciso B.V.
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

namespace OPNsense\Interface;

use OPNsense\Core\Config;

/**
 * Returns information about IPv6 prefix state of all idassoc interfaces.
 * Services like DHCPv6 require a central authority with this information.
 */
class Idassoc extends Autoconf
{
    /**
     * Calculate the on-link /64 prefix selected by a hexadecimal identity association prefix ID.
     */
    private static function calculatePrefix($source_prefix, $prefix_id, $prefix_len = 64): string
    {
        [$address, $source_prefix_len] = explode('/', $source_prefix, 2);
        $bytes = array_values(unpack('C*', inet_pton($address)));

        $source_prefix_len = (int)$source_prefix_len;
        $prefix_id = hexdec((string)$prefix_id);
        $id_bits = 64 - $source_prefix_len;

        for ($i = 0; $i < $id_bits; $i++) {
            if (($prefix_id & (1 << ($id_bits - $i - 1))) === 0) {
                continue;
            }

            $bit = $source_prefix_len + $i;
            $bytes[intdiv($bit, 8)] |= 1 << (7 - ($bit % 8));
        }

        return inet_ntop(pack('C*', ...$bytes)) . '/' . $prefix_len;
    }

    /**
     * Return the largest aligned prefix length starting at this prefix ID
     * without crossing into the next configured prefix ID or the associated prefix end.
     */
    private static function calculateUsablePrefixLength($source_prefix_len, $prefix_id, $next_prefix_id = null): int
    {
        $source_prefix_len = (int)$source_prefix_len;
        $prefix_id = hexdec((string)$prefix_id);
        $limit = $next_prefix_id !== null ? hexdec((string)$next_prefix_id) : 1 << (64 - $source_prefix_len);

        $available = $limit - $prefix_id;
        $size = 1;
        $bits = 0;

        while ($size * 2 <= $available && $prefix_id % ($size * 2) === 0) {
            $size *= 2;
            $bits++;
        }

        return 64 - $bits;
    }

    /**
     * Collect configured IPv6 identity association prefixes.
     *
     * - prefix_id: hexadecimal prefix ID configured on the tracked interface.
     * - prefix_on_link: the /64 prefix used directly on this interface.
     * - prefix_allocated: the largest usable prefix block starting at prefix_id,
     *   bounded by the next configured prefix ID or the end of the associated prefix.
     * - prefix_associated: the delegated parent prefix received on the tracked source interface (usually WAN).
     *  [
     *       [lan] =>
     *           [
     *               [prefix_id] => 0
     *               [prefix_on_link] => 2001:db8:1234::/64
     *               [prefix_allocated] => 2001:db8:1234::/58
     *               [prefix_associated] => 2001:db8:1234::/56
     *           ]
     *       [opt1] =>
     *           [
     *               [prefix_id] => 68
     *               [prefix_on_link] => 2001:db8:1234:68::/64
     *               [prefix_allocated] => 2001:db8:1234:68::/61
     *               [prefix_associated] => 2001:db8:1234::/56
     *           ]
     *  ]
     */
    private static function prefixes(): array
    {
        $result = [];
        $groups = [];
        $cfg = Config::getInstance()->object();

        foreach ($cfg->interfaces->children() as $ifname => $ifcfg) {
            if ((string)($ifcfg->ipaddrv6 ?? '') !== 'idassoc6') {
                continue;
            }

            $prefix_id = (string)($ifcfg->{'track6-prefix-id'} ?? '');
            $trackif = (string)($ifcfg->{'track6-interface'} ?? '');

            if ($prefix_id === '' || $trackif === '' || empty($cfg->interfaces->{$trackif}->if)) {
                continue;
            }

            $prefix_associated = self::getPrefix((string)$cfg->interfaces->{$trackif}->if, 'inet6') ?? '';
            if ($prefix_associated === '') {
                continue;
            }

            $groups[$prefix_associated][$ifname] = $prefix_id;
        }

        foreach ($groups as $prefix_associated => $interfaces) {
            /* Sort by prefix ID because prefix_allocated depends on the next higher configured ID. */
            uasort($interfaces, fn($a, $b) => hexdec($a) <=> hexdec($b));
            $ordered = array_keys($interfaces);
            $source_prefix_len = (int)explode('/', $prefix_associated, 2)[1];

            foreach ($ordered as $idx => $ifname) {
                $prefix_id = $interfaces[$ifname];
                $next_prefix_id = isset($ordered[$idx + 1]) ? $interfaces[$ordered[$idx + 1]] : null;
                $prefix_usable_len = self::calculateUsablePrefixLength($source_prefix_len, $prefix_id, $next_prefix_id);

                $result[$ifname] = [
                    'prefix_id' => $prefix_id,
                    'prefix_on_link' => self::calculatePrefix($prefix_associated, $prefix_id),
                    'prefix_allocated' => self::calculatePrefix($prefix_associated, $prefix_id, $prefix_usable_len),
                    'prefix_associated' => $prefix_associated,
                ];
            }
        }

        return $result;
    }

    /**
     * Return configured IPv6 identity association prefix information.
     */
    public static function prefix($ifname = null): array
    {
        $prefixes = self::prefixes();

        if ($ifname !== null) {
            return $prefixes[$ifname] ?? [];
        }

        return $prefixes;
    }
}
