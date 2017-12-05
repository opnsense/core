<?php

/*
    Copyright (C) 2017 Deciso B.V.
    Copyright (C) 2004-2007 Scott Ullrich <sullrich@gmail.com>
    Copyright (C) 2005 Bill Marquette <bill.marquette@gmail.com>
    Copyright (C) 2006 Peter Allgeyer <allgeyer@web.de>
    Copyright (C) 2008-2010 Ermal Luçi
    Copyright (C) 2003-2004 Manuel Kasper <mk@neon1.net>
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

function filter_expand_alias_array($alias_name) {
    $expansion = filter_expand_alias($alias_name);
    return explode(" ", preg_replace('/\s+/', ' ', trim($expansion)));
}

function filter_expand_alias($alias_name)
{
    $tmp = filter_expand_aliases($alias_name);
    if (!empty($tmp[$alias_name]['addresses'])) {
        return implode(' ', $tmp[$alias_name]['addresses']);
    } else {
        return '';
    }
}

/**
 * recursively collect alias(es) contents
 * @param $aliasname string|null alias name or null to fetch all aliases
 * @param $maxdepth maximum recursion depth
 * @param $depth current depth (internal use)
 * @return array containing addresses (or ports) and hostnames
 */
function filter_expand_aliases($aliasname=null, $maxdepth=10, $depth=0)
{
    global $config;

    $response = array();
    if (isset($config['aliases']['alias'])) {
        foreach ($config['aliases']['alias'] as $aliased) {
            if ($aliasname === null || $aliasname == $aliased['name']) {
                // new output structure
                $response[$aliased['name']] = array('addresses' => array(), 'hostnames' => array());
                if ($aliasname !== null && in_array($aliased['type'], array('urltable', 'urltable_ports'))) {
                    // only get contents when nested,
                    // it's functional, but fetching remote lists has disadvantages...
                    $urltable_contents = @file_get_contents(alias_expand_urltable($aliased['name']));
                    $response[$aliased['name']]['addresses'] = preg_split('/[\s]+/', $urltable_contents);
                } elseif (in_array($aliased['type'], array('host', 'network', 'port', 'url'))
                      && isset($aliased['address'])) {
                    // split alias contents and handle per type
                    foreach (explode(" ", $aliased['address']) as $address) {
                        if (is_alias($address)) {
                            if ($depth < $maxdepth) {
                                $tmp = filter_expand_aliases($address, $maxdepth, $depth+1);
                                foreach ($tmp as $aliascontent2) {
                                    foreach ($aliascontent2 as $aliastype_key => $aliastype) {
                                        $response[$aliased['name']][$aliastype_key] = array_merge(
                                            $response[$aliased['name']][$aliastype_key], $aliastype
                                        );
                                    }
                                }
                            }
                        } elseif (!is_ipaddr($address) && !is_subnet($address)
                              && !is_port($address) && !is_portrange($address) && is_hostname($address)
                              && in_array($aliased['type'], array('host'))) {
                            $response[$aliased['name']]['hostnames'][] = $address;
                        } elseif (strpos($address, '-') !== false) {
                            $tmp = explode('-', $address);
                            $response[$aliased['name']]['addresses'] = array_merge(
                              $response[$aliased['name']]['addresses'],
                              ip_range_to_subnet_array($tmp[0], $tmp[1])
                            );
                        } else {
                            $response[$aliased['name']]['addresses'][] = $address;
                        }
                    }
                }
            }
        }
    }
    // de-duplicate results
    foreach ($response as $alias_name => $alias) {
        foreach ($alias as $type => $value) {
            $response[$alias_name][$type] = array_filter(array_unique($value), 'strlen');
        }
    }
    return $response;
}

function alias_expand_urltable($name) {
    global $config;
    $urltable_prefix = "/var/db/aliastables/";
    $urltable_filename = $urltable_prefix . $name . ".txt";

    if (is_array($config['aliases']['alias'])) {
        foreach ($config['aliases']['alias'] as $alias) {
            if (preg_match("/urltable/i", $alias['type']) && ($alias['name'] == $name)) {
                if (is_URL($alias["url"]) && file_exists($urltable_filename) && filesize($urltable_filename)) {
                    return $urltable_filename;
                } elseif (process_alias_urltable($name, $alias["type"], $alias["url"], 0, true)) {
                    return $urltable_filename;
                }
            } elseif ($alias['type'] == 'geoip' && $alias['name'] == $name) {
                if (!file_exists($urltable_filename) || filesize($urltable_filename) == 0) {
                    if (download_alias_geoip($alias)) {
                        return $urltable_filename;
                    }
                } else {
                    // alias file found and not 0 bytes
                    return $urltable_filename;
                }
            }
        }
    }
    return null;
}

/**
 * download geoip and collect alias contents into /var/db/aliastables
 * @param array $alias
 */
function download_alias_geoip($alias)
{
    if ($alias['type'] == 'geoip') {
        // create alias target directly if it doesn't exists (mfs)
        if (!file_exists("/var/db/aliastables/")) {
            mkdir("/var/db/aliastables");
        }
        // download the geoip database, first check if we haven't already done so the last day
        if (!is_file('/usr/local/share/GeoIP/alias/NL-IPv4') || (time() - filemtime('/usr/local/share/GeoIP/alias/NL-IPv4')) > (86400 - 90)) {
            log_error("{$argv[0]}: Download GeoIP database");
            exec('/usr/local/opnsense/scripts/filter/download_geoip.py');
        }
        // concat geoip countries and save to alias file
        $alias_content = "";
        foreach (explode(' ', $alias['address']) as $country_code) {
            if (strlen($country_code) == 2 && in_array($alias['proto'], array('IPv4', 'IPv6'))) {
                $filename = "/usr/local/share/GeoIP/alias/".$country_code."-".$alias['proto'];
                if (is_file($filename)) {
                    $alias_content .= file_get_contents($filename);
                }
            }
        }
        return file_put_contents('/var/db/aliastables/'.basename($alias['name']).'.txt', $alias_content);
    } else {
        return false;
    }
}

function process_alias_urltable($name, $alias_type, $url, $freq, $forceupdate = false)
{
    global $config;

    $urltable_prefix = "/var/db/aliastables/";
    $urltable_filename = $urltable_prefix . $name . ".txt";

    // Make the aliases directory if it doesn't exist
    if (!file_exists($urltable_prefix)) {
        mkdir($urltable_prefix);
    } elseif (!is_dir($urltable_prefix)) {
        unlink($urltable_prefix);
        mkdir($urltable_prefix);
    }
    if (empty($freq)) {
        $freq = 1;
    }
    $update_freq = ($freq * 86400) - 90;
    // If the file doesn't exist or is older than update_freq days, fetch a new copy.
    if (!file_exists($urltable_filename) || ((time() - filemtime($urltable_filename)) > $update_freq) || $forceupdate) {
        // open file handle to output file, in case the process takes a lot of time, make sure there's a file before
        // filter starts to load. Also helps for tracking progress.
        //
        // todo: rethink alias_expand_urltable in filter.inc , its probably not a very good idea to download and
        //       process files during boot.
        $output_file_handle = fopen($urltable_filename, 'w');
        // Try to fetch the URL supplied
        @unlink("{$urltable_filename}.tmp");
        $verify_ssl = isset($config['system']['checkaliasesurlcert']);
        if (download_file($url, "{$urltable_filename}.tmp", $verify_ssl)) {
            foreach (preg_split('/[\n\r]+/', file_get_contents("{$urltable_filename}.tmp"), -1, PREG_SPLIT_NO_EMPTY) as $line) {
                $line = trim($line);
                if (!strlen($line) || !strncmp($line, '#', 1) || !strncmp($line, '//', 2)) {
                    continue;
                }
                // cleanse line item
                $line = preg_split('/[\s,;|#]+/', $line)[0];
                if ($alias_type == "urltable_ports") {
                    if ((string)((int)$line) === $line && $line >= 1 && $line <= 65535) {
                        fwrite($output_file_handle, "{$line}\n");
                    }
                } else {
                    // validate or resolve line items, skip unparseable content
                    if (is_subnet($line) || is_ipaddr($line)) {
                        fwrite($output_file_handle, "{$line}\n");
                    } elseif (is_hostname($line)) {
                        foreach (array(DNS_AAAA, DNS_A) as $dns_type) {
                            // normally dns_get_record should be able to use DNS_A + DNS_AAAA
                            // but for some strange reason not on our platform...
                            $dns_records = @dns_get_record($line, $dns_type);
                            if ($dns_records) {
                                foreach ($dns_records as $dns_record) {
                                    if (!empty($dns_record['ipv6'])) {
                                        fwrite($output_file_handle, $dns_record['ipv6'] . "\n");
                                    } elseif (!empty($dns_record['ip'])) {
                                        fwrite($output_file_handle, $dns_record['ip'] . "\n");
                                    }
                                }
                            }
                        }
                    }
                    fflush($output_file_handle);
                }
            }
            fclose($output_file_handle);

            if ($alias_type == "urltable_ports") {
                $ports = explode("\n", file_get_contents($urltable_filename));
                $ports = group_ports($ports);
                file_put_contents($urltable_filename, implode("\n", $ports));
            }
            @unlink("{$urltable_filename}.tmp");
        } else {
          touch($urltable_filename);
        }
        return true;
    } else {
        // File exists, and it doesn't need updated.
        return -1;
    }
}
