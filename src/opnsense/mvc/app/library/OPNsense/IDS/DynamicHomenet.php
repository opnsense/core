<?php

/*
 * Copyright (C) 2026 Logan Galloway
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

namespace OPNsense\IDS;

use OPNsense\Interface\Idassoc;

/**
 * Maintains the file that defines DYNAMIC_HOME_NET for Suricata, the current IPv6 prefix of the
 * interfaces selected in the IDS settings. The prefix is runtime state, so it lives in this
 * generated file and not in the configuration. Besides the list, the file holds one comment line
 * per interface ("# lan 2001:db8:1234:ab00::/64"), which is how the last known prefix of an
 * interface survives a moment in which it has none.
 */
class DynamicHomenet
{
    public const FILE = '/usr/local/etc/suricata/dynamic_homenet.yaml';

    /**
     * The interface prefixes recorded in the content of the file.
     * @param string|null $content file content, null when the file does not exist
     * @return array prefix per interface name
     */
    public static function parse(?string $content): array
    {
        $known = [];
        if ($content !== null && preg_match_all('/^# (\w+) (\S+)$/m', $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $known[$match[1]] = $match[2];
            }
        }

        return $known;
    }

    /**
     * Prefix per selected interface. An interface with a valid prefix (prefix_valid, so not the
     * temporary one) gets its on-link prefix. One that Idassoc still lists without a valid prefix
     * is in a temporary gap and keeps its last known prefix, a never seen one contributes nothing.
     * Interfaces no longer selected or no longer listed by Idassoc (not an identity association
     * anymore) are dropped.
     * @param array $interfaces selected interface names
     * @param array $prefixes Idassoc::prefix() result for all interfaces
     * @param array $known last known prefix per interface, see parse()
     * @return array prefix per interface name, sorted by name
     */
    public static function merge(array $interfaces, array $prefixes, array $known): array
    {
        $result = [];
        foreach ($interfaces as $ifname) {
            if (!empty($prefixes[$ifname]['prefix_valid']) && !empty($prefixes[$ifname]['prefix_on_link'])) {
                $result[$ifname] = $prefixes[$ifname]['prefix_on_link'];
            } elseif (isset($prefixes[$ifname]) && isset($known[$ifname])) {
                $result[$ifname] = $known[$ifname];
            }
        }
        ksort($result, SORT_STRING);

        return $result;
    }

    /**
     * Content of the file for these interface prefixes.
     * @param array $result prefix per interface name, see merge()
     * @return string
     */
    public static function render(array $result): string
    {
        $content = "%YAML 1.1\n---\n";
        foreach ($result as $ifname => $prefix) {
            $content .= "# {$ifname} {$prefix}\n";
        }
        $prefixes = array_unique(array_values($result));
        sort($prefixes, SORT_STRING);

        return $content . 'DYNAMIC_HOME_NET: "[' . implode(',', $prefixes) . "]\"\n";
    }

    /**
     * One line telling which prefix each interface has in the file now.
     * @param array $result prefix per interface name, see merge()
     * @return string
     */
    public static function describe(array $result): string
    {
        $items = [];
        foreach ($result as $ifname => $prefix) {
            $items[] = "{$ifname} {$prefix}";
        }

        return 'dynamic home networks updated: ' . (empty($items) ? 'none' : implode(', ', $items));
    }

    /**
     * Bring the file in line with the current prefixes, nothing happens unless interfaces are selected.
     * @return bool true when the file was written
     */
    public static function sync(): bool
    {
        $interfaces = (new IDS())->general->homenet_interfaces->getValues();
        if (empty($interfaces)) {
            return false;
        }

        $current = is_file(self::FILE) ? file_get_contents(self::FILE) : null;
        $result = self::merge($interfaces, Idassoc::prefix(), self::parse($current));
        $content = self::render($result);
        if ($content === $current) {
            return false;
        }

        /* replace in one step, Suricata reads this file when it (re)loads */
        $tmpfile = self::FILE . '.tmp';
        file_put_contents($tmpfile, $content);
        chmod($tmpfile, 0640);

        if (!rename($tmpfile, self::FILE)) {
            return false;
        }

        /* informational only, syslog() does not throw and its result is not needed */
        syslog(LOG_NOTICE, self::describe($result));

        return true;
    }
}
